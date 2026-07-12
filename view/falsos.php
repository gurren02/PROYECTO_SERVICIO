<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar sesión segura y candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

// 2. RECIBIR PERIODO Y CICLO SELECCIONADO
$mes_actual = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : (int)date('n');
$anio_actual = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : (int)date('Y');
$ciclo_actual = isset($_REQUEST['ciclo']) && $_REQUEST['ciclo'] !== '' ? (int)$_REQUEST['ciclo'] : null;

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Calcular periodo actual
$mes_formateado = str_pad($mes_actual, 2, "0", STR_PAD_LEFT);
$tabla_actual = "falsos" . $anio_actual . $mes_formateado;

// Obtener todas las tablas existentes en la BD
$stmt_db = $pdo->query("SHOW TABLES");
$todas_las_tablas = $stmt_db->fetchAll(PDO::FETCH_COLUMN);
$todas_las_tablas_flipped = array_flip($todas_las_tablas);

$tabla_actual_existe = isset($todas_las_tablas_flipped[$tabla_actual]);

// Obtener los ciclos existentes en la tabla
$ciclos_existentes = [];
if ($tabla_actual_existe) {
    try {
        $stmt_ciclos = $pdo->query("SELECT DISTINCT Ciclo FROM `$tabla_actual` WHERE Ciclo IS NOT NULL ORDER BY Ciclo ASC");
        $ciclos_existentes = $stmt_ciclos->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // silently fail
    }
}

// Mapeo de conceptos de anomalías CFE
$conceptos = [
    50 => [
        'nombre' => '50: CASA CERRADA',
        'pattern' => 'CASA CERRADA'
    ],
    51 => [
        'nombre' => '51: NO ENCONTRE DOMI',
        'pattern' => 'NO ENCONTRE DOM'
    ],
    52 => [
        'nombre' => '52: COMUNICACION INTERRUMPIDA',
        'pattern' => 'COMUNICACION IN'
    ],
    53 => [
        'nombre' => '53: UI SERV DIRECTO',
        'pattern' => 'UI SERV DIRECTO'
    ],
    56 => [
        'nombre' => '56: NO HAY MEDIDOR',
        'pattern' => 'NO HAY MEDIDOR'
    ],
    59 => [
        'nombre' => '59: DISPLAY APAGADO',
        'pattern' => 'DISPLAY APAGADO'
    ],
    60 => [
        'nombre' => '60: MEDID NO TRABAJA',
        'pattern' => 'MEDID NO TRABAJ'
    ],
    64 => [
        'nombre' => '64: P. INFRARROJO/MED. DANADO',
        'pattern' => 'P. INFRARROJO/M'
    ],
    65 => [
        'nombre' => '65: UI MED INVERTIDO',
        'pattern' => 'UI MED INVERTID'
    ],
    67 => [
        'nombre' => '67: CONTINGENCIA',
        'pattern' => 'CONTINGENCIA'
    ],
    70 => [
        'nombre' => '70: LECTURA NEGATIVA',
        'pattern' => 'LECTURA NEGATIV'
    ]
];

// Inicializar contadores
$valores_actual = array_fill_keys(array_keys($conceptos), 0);

// Función para obtener y mapear conteos de RPUs únicos
function obtenerConteosMapeados($pdo, $nombre_tabla, $conceptos, $ciclo = null) {
    $conteos = array_fill_keys(array_keys($conceptos), 0);
    try {
        // Consultar anomalías agrupadas por descripción con conteo de Rpu únicos (filtrado por Estimado o similar)
        $query = "SELECT Anomalia, COUNT(DISTINCT Rpu) as total FROM `$nombre_tabla` WHERE (Tipo = 'Estimado' OR Tipo LIKE '%ESTIM%')";
        $params = [];
        if ($ciclo !== null) {
            $query .= " AND Ciclo = ?";
            $params[] = $ciclo;
        }
        $query .= " GROUP BY Anomalia";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $anom_val = trim($row['Anomalia']);
            $total_val = (int)$row['total'];

            foreach ($conceptos as $codigo => $info) {
                if (strpos($anom_val, $info['pattern']) !== false) {
                    $conteos[$codigo] += $total_val;
                }
            }
        }
    } catch (PDOException $e) {
        // En caso de que falle la consulta o falten columnas
    }
    return $conteos;
}

// Lista de agencias ordenada alfabéticamente por su letra asignada
$agencias_orden = ['CENTRO', 'NORTE', 'SUR', 'ORIENTE', 'PONIENTE', 'PROGRESO', 'HUNUCMA', 'UMAN', 'ACANCEH', 'CONKAL'];

// Mapa para obtener la letra correspondiente de cada agencia
$mapa_letras_agencias = [
    'CENTRO'   => 'A',
    'NORTE'    => 'B',
    'SUR'      => 'C',
    'ORIENTE'  => 'D',
    'PONIENTE' => 'E',
    'PROGRESO' => 'G',
    'HUNUCMA'  => 'H',
    'UMAN'     => 'J',
    'ACANCEH'  => 'K',
    'CONKAL'   => 'M'
];

// Función para obtener conteos por agencia y concepto
function obtenerConteosPorAgencia($pdo, $nombre_tabla, $conceptos, $ciclo = null) {
    // Estructura: $datos[$codigo_concepto][$agencia] = total_rpus_unicos
    $datos = [];
    foreach ($conceptos as $codigo => $info) {
        $datos[$codigo] = [];
    }
    try {
        $query = "SELECT Anomalia, Agencia, COUNT(DISTINCT Rpu) as total FROM `$nombre_tabla` WHERE (Tipo = 'Estimado' OR Tipo LIKE '%ESTIM%')";
        $params = [];
        if ($ciclo !== null) {
            $query .= " AND Ciclo = ?";
            $params[] = $ciclo;
        }
        $query .= " GROUP BY Anomalia, Agencia";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $anom_val  = trim($row['Anomalia']);
            $agencia   = trim($row['Agencia']);
            $total_val = (int)$row['total'];
            foreach ($conceptos as $codigo => $info) {
                if (strpos($anom_val, $info['pattern']) !== false) {
                    $datos[$codigo][$agencia] = ($datos[$codigo][$agencia] ?? 0) + $total_val;
                }
            }
        }
    } catch (PDOException $e) {
        // silently fail
    }
    return $datos;
}

// Cargar conteos (siempre inicializados)
$datos_por_agencia = [];
if ($tabla_actual_existe) {
    $valores_actual   = obtenerConteosMapeados($pdo, $tabla_actual, $conceptos, $ciclo_actual);
    $datos_por_agencia = obtenerConteosPorAgencia($pdo, $tabla_actual, $conceptos, $ciclo_actual);
}

// Obtener fecha de última subida para el periodo actual
$ultima_actualizacion = 'No disponible';
if ($tabla_actual_existe) {
    $stmt_u = $pdo->prepare("SELECT nombre_archivo_original, fecha_subida, mes_asociado, anio_asociado FROM registro_archivos WHERE nombre_tabla = ? ORDER BY fecha_subida DESC LIMIT 1");
    $stmt_u->execute([$tabla_actual]);
    $res_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
    if ($res_u) {
        $fecha_f = date('d/m/Y H:i', strtotime($res_u['fecha_subida']));
        $nombre_sin_ext = pathinfo($res_u['nombre_archivo_original'], PATHINFO_FILENAME);
        $mes_nom = $nombres_meses[$res_u['mes_asociado']];
        $anio_asoc = $res_u['anio_asociado'];
        
        $ultima_actualizacion = "<strong>{$mes_nom} {$anio_asoc}</strong><br>"
            . "<span style='font-size: 0.78rem; color:#475569; display:block; margin-top:2px; font-weight:500;'>{$nombre_sin_ext}</span>"
            . "<span style='font-size: 0.68rem; color:#64748b; display:block;'>Subido: {$fecha_f}</span>";
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="../assets/multimedia/logo_cf.webp">
    <title>CFE - Módulo Falsos</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/subir.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        /* Toast Notification Premium */
        .custom-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #ffffff;
            border-left: 5px solid #074776;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border-radius: 6px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            font-family: "Segoe UI", system-ui, sans-serif;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-width: 380px;
        }
        .custom-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .custom-toast--success {
            border-left-color: #10b981;
        }
        .custom-toast--error {
            border-left-color: #ef4444;
        }
        .custom-toast--info {
            border-left-color: #3b82f6;
        }
        .custom-toast-icon {
            font-size: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .custom-toast-icon--success {
            color: #10b981;
        }
        .custom-toast-icon--error {
            color: #ef4444;
        }
        .custom-toast-icon--info {
            color: #3b82f6;
        }
        .custom-toast-content {
            flex-grow: 1;
        }
        .custom-toast-title {
            font-weight: 700;
            color: #1f2937;
            margin: 0;
            font-size: 0.9rem;
        }
        .custom-toast-desc {
            color: #4b5563;
            margin: 2px 0 0 0;
            font-size: 0.8rem;
            line-height: 1.3;
            white-space: pre-line;
        }
        .custom-toast-close {
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
            font-size: 16px;
            user-select: none;
        }
        .custom-toast-close:hover {
            color: #4b5563;
        }

        .animate-spin {
            animation: spin 1s linear infinite !important;
            display: inline-block !important;
        }

        /* Estilos específicos para la sección Falsos */
        .falsos-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        @media(min-width: 1024px) {
            .falsos-grid {
                grid-template-columns: 1.2fr 0.8fr;
            }
        }
        /* Estilo de la tabla de Anomalías idéntico al de la imagen */
        .anomalias-table-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .anomalias-title-bar {
            background-color: #008f39; /* Verde CFE */
            color: #ffffff;
            padding: 12px 20px;
            font-size: 1.25rem;
            font-weight: 700;
            text-align: right; /* Alineado a la derecha como en la imagen */
            letter-spacing: 0.5px;
        }
        .anomalias-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .anomalias-table th {
            background-color: #074776; /* Azul Oscuro de la imagen */
            color: #ffffff;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 8px 12px;
            border: 1px solid #ffffff;
            text-align: center;
        }
        .anomalias-table td {
            padding: 6px 12px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.9rem;
        }
        .anomalias-table tr:nth-child(even) td {
            background-color: #f7fee7; /* Fondo verde muy claro intercalado */
        }
        .anomalias-table tr:nth-child(odd) td {
            background-color: #fefce8; /* Fondo amarillo muy claro intercalado */
        }
        .anomalias-table td.col-num {
            text-align: center;
            font-weight: 600;
            width: 50px;
            background-color: #f8fafc !important;
        }
        .anomalias-table td.col-concepto {
            text-align: left;
            font-weight: 500;
            color: #0f172a;
        }
        .anomalias-table td.col-valor {
            text-align: center;
            width: 100px;
            font-weight: 600;
        }
        .anomalias-table td.col-valor a {
            color: #074776;
            text-decoration: none;
            display: block;
            width: 100%;
            height: 100%;
        }
        .anomalias-table td.col-valor a:hover {
            text-decoration: underline;
            color: #008f39;
        }
        .anomalias-table tr.total-row td {
            background-color: #074776 !important;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 1rem;
            padding: 10px 12px;
        }
        .anomalias-table tr.total-row td.col-label {
            text-align: right;
            padding-right: 20px;
        }
        .falsos-dropzone {
            border: 2px dashed #0F4A38;
            background-color: #f9fbf9;
            transition: all 0.25s ease;
        }
        .falsos-dropzone:hover, .falsos-dropzone.dragover {
            background-color: #f0f7f0;
            border-color: #0F4A38;
            transform: scale(1.005);
        }
        .falsos-dropzone__icon {
            color: #0F4A38;
        }

        /* ── Tabla Desglose por Agencias ── */
        .agencias-table-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-top: 24px;
        }
        .agencias-table-card .anomalias-title-bar {
            text-align: left;
        }
        .agencias-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 0.85rem;
        }
        .agencias-table th {
            background-color: #074776;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 6px 7px;
            border: 1px solid #ffffff;
            text-align: center;
            white-space: nowrap;
        }
        .agencias-table th.col-concepto-h {
            text-align: left;
            min-width: 150px;
        }
        .agencias-table th.th-agencia {
            cursor: pointer;
            transition: background 0.18s;
            min-width: 60px;
        }
        .agencias-table th.th-agencia:hover {
            background-color: #0a5fa0;
        }
        .agencias-table th.th-agencia .ag-btn-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
        }
        .agencias-table th.th-agencia .ag-icon {
            font-size: 12px;
            opacity: 0.75;
        }
        /* Celdas de datos — mismos colores que anomalias-table */
        .agencias-table td {
            padding: 5px 7px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.85rem;
            text-align: center;
            font-weight: 600;
        }
        .agencias-table tbody tr:nth-child(even) td {
            background-color: #f7fee7;
        }
        .agencias-table tbody tr:nth-child(odd) td {
            background-color: #fefce8;
        }
        .agencias-table td.col-concepto-d {
            text-align: left;
            font-weight: 500;
            color: #0f172a;
            background-color: #f8fafc !important;
            white-space: nowrap;
        }
        .agencias-table td.col-num-d {
            text-align: center;
            font-weight: 600;
            width: 32px;
            max-width: 38px;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-width: 1px;
            background-color: #f8fafc !important;
        }
        .agencias-table th.col-num-h {
            width: 32px;
            max-width: 38px;
            padding: 6px 8px;
            font-size: 0.72rem;
            border-width: 1px;
        }
        /* Fila total — igual a la tabla de anomalías */
        .agencias-table tfoot tr td {
            background-color: #074776 !important;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 0.9rem;
            padding: 8px 7px;
            border: 1px solid #ffffff;
        }
        .agencias-table tfoot tr td.col-concepto-d {
            text-align: right;
            padding-right: 14px;
            background-color: #074776 !important;
            color: #fff;
        }
        .agencias-table td.col-total-d {
            background-color: #1e3a5f !important;
            color: #fff !important;
            font-weight: bold;
        }
        /* Modal de agencia */
        #ea-agencia-modal .anomalias-table-card {
            width: 100% !important;
            box-shadow: none;
            border: none;
        }

        .clickable-metric {
            cursor: pointer;
            transition: color 0.15s ease, background-color 0.15s ease;
        }
        .clickable-metric:hover {
            color: #008f39 !important;
            background-color: rgba(0, 143, 57, 0.08) !important;
        }
        .col-total-d.clickable-metric:hover,
        tfoot td.clickable-metric:hover,
        .total-row td span.clickable-metric:hover {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: #fff !important;
        }

        /* Estilos del Modal */
        .ea-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
        }
        .ea-modal__content {
            background-color: #fff;
            margin: 5% auto;
            width: 90%;
            max-width: 1100px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: animatetop 0.3s;
        }
        @keyframes animatetop {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
        }
        .ea-modal__header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ea-modal__header h3 { margin: 0; color: #0F4A38; font-size: 1.1rem; }
        .ea-modal__close { cursor: pointer; color: #aaa; transition: 0.2s; }
        .ea-modal__close:hover { color: #333; }
        .ea-modal__body { padding: 20px; }
    </style>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="ea-main">
    <div class="ea-page-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div class="ea-page-header__left">
            <div>
                <h1 class="ea-page-title">Módulo Falsos</h1>
                <p class="ea-page-subtitle">Carga de archivos TXT y control de anomalías</p>
            </div>
        </div>
    </div>

    <!-- Filtros y Período -->
    <div class="ea-card ea-filters-card" style="margin-bottom: 20px;">
        <div class="ea-card__body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <form method="GET" action="" class="ea-form" style="display: flex; gap: 15px; margin: 0; align-items: flex-end;">
                <div class="ea-form__group" style="margin: 0;">
                    <label class="ea-form__label" for="mes">Mes</label>
                    <select id="mes" name="mes" class="ea-form__control" style="width: 130px;">
                        <?php foreach ($nombres_meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($num === $mes_actual) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ea-form__group" style="margin: 0;">
                    <label class="ea-form__label" for="anio">Año</label>
                    <input type="number" id="anio" name="anio" class="ea-form__control" style="width: 100px;" value="<?php echo $anio_actual; ?>" min="2000" max="2100">
                </div>
                <div class="ea-form__group" style="margin: 0;">
                    <label class="ea-form__label" for="ciclo">Ciclo</label>
                    <select id="ciclo" name="ciclo" class="ea-form__control" style="width: 110px;">
                        <option value="">Todos</option>
                        <?php foreach ($ciclos_existentes as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo ($ciclo_actual !== null && $c === $ciclo_actual) ? 'selected' : ''; ?>>
                                <?php echo $c; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="ea-btn ea-btn--primary" style="margin: 0;">
                    <span class="material-symbols-rounded">search</span> Consultar
                </button>
            </form>

            <div style="display: flex; gap: 20px;">
                <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: #008f39; font-size: 24px;">history</span>
                    <div>
                        <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Última Carga (Actual)</span>
                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text); margin-top: 4px; line-height: 1.35;"><?php echo $ultima_actualizacion; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <?php
    // ── Tabla de Desglose por Agencias ──────────────────────────────────────
    // Usar la lista estática de agencias (siempre visible aunque no haya datos cargados)
    $datos_ag = $datos_por_agencia; // arreglo plano: [codigo][agencia] = count

    // Totales por columna y fila
    $totales_ag_col  = array_fill_keys($agencias_orden, 0);
    $totales_ag_fila = [];
    foreach ($conceptos as $codigo => $info) {
        $suma_fila = 0;
        foreach ($agencias_orden as $ag) {
            $v = $datos_ag[$codigo][$ag] ?? 0;
            $totales_ag_col[$ag] += $v;
            $suma_fila += $v;
        }
        $totales_ag_fila[$codigo] = $suma_fila;
    }
    $gran_total_ag = array_sum($totales_ag_col);

    // Serializar para JavaScript
    $js_conceptos = [];
    foreach ($conceptos as $codigo => $info) {
        $js_conceptos[] = ['codigo' => $codigo, 'nombre' => $info['nombre'], 'pattern' => $info['pattern']];
    }
    ?>

    <div class="agencias-table-card" style="width: fit-content; max-width: 100%;">
        <div class="anomalias-title-bar" style="display:flex; align-items:center; gap:10px;">
            <span class="material-symbols-rounded" style="font-size:1.3rem;">table_chart</span>
            Desglose de Anomalías por Agencia
            <span style="font-size:0.8rem; font-weight:400; opacity:0.85; margin-left:6px;">(haz clic en una agencia para ver el detalle)</span>
        </div>
        <div class="ea-table-wrapper">
            <table class="agencias-table">
                <thead>
                    <tr>
                        <th class="col-num-h">No.</th>
                        <th class="col-concepto-h">Concepto</th>
                        <?php foreach ($agencias_orden as $ag): 
                            $letra = $mapa_letras_agencias[$ag] ?? '';
                        ?>
                            <th class="th-agencia" onclick="abrirModalAgencia('<?php echo htmlspecialchars($ag, ENT_QUOTES); ?>')" title="Ver detalle: <?php echo htmlspecialchars($ag, ENT_QUOTES); ?>">
                                <div class="ag-btn-inner">
                                    <span><?php echo htmlspecialchars($ag); ?></span>
                                    <span style="font-size: 0.8rem; opacity: 0.85; font-weight: 500;"><?php echo htmlspecialchars($letra); ?></span>
                                </div>
                            </th>
                        <?php endforeach; ?>
                        <th style="background-color:#1e3a5f;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx_ag = 1; foreach ($conceptos as $codigo => $info):
                        $suma_fila = $totales_ag_fila[$codigo] ?? 0;
                    ?>
                    <tr>
                        <td class="col-num-d"><?php echo $idx_ag++; ?></td>
                        <td class="col-concepto-d"><?php echo htmlspecialchars($info['nombre']); ?></td>
                        <?php foreach ($agencias_orden as $ag):
                            $val = $datos_ag[$codigo][$ag] ?? 0;
                        ?>
                            <?php if ($val > 0): ?>
                                <td class="clickable-metric" onclick="abrirDetalleFalsos(falsosTablaNombre, '<?php echo addslashes($info['pattern']); ?>', '<?php echo addslashes($info['nombre'] . ' — ' . $ag); ?>', '<?php echo addslashes($ag); ?>')">
                                    <?php echo number_format($val); ?>
                                </td>
                            <?php else: ?>
                                <td><span style="color:#94a3b8;">0</span></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if ($suma_fila > 0): ?>
                            <td class="col-total-d clickable-metric" onclick="abrirDetalleFalsos(falsosTablaNombre, '<?php echo addslashes($info['pattern']); ?>', '<?php echo addslashes($info['nombre'] . ' — Todas las Agencias'); ?>', '')">
                                <?php echo number_format($suma_fila); ?>
                            </td>
                        <?php else: ?>
                            <td class="col-total-d"><span style="color:#94a3b8;">0</span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="col-concepto-d">TOTAL</td>
                        <?php foreach ($agencias_orden as $ag): ?>
                            <?php if ($totales_ag_col[$ag] > 0): ?>
                                <td class="clickable-metric" onclick="abrirDetalleFalsos(falsosTablaNombre, '', 'Todas las Anomalías — <?php echo addslashes($ag); ?>', '<?php echo addslashes($ag); ?>')">
                                    <?php echo number_format($totales_ag_col[$ag]); ?>
                                </td>
                            <?php else: ?>
                                <td>0</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if ($gran_total_ag > 0): ?>
                            <td class="clickable-metric" onclick="abrirDetalleFalsos(falsosTablaNombre, '', 'Todas las Anomalías — Todas las Agencias', '')">
                                <?php echo number_format($gran_total_ag); ?>
                            </td>
                        <?php else: ?>
                            <td>0</td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Datos PHP → JS para el modal de agencia -->
    <script>
        const falsosTablaNombre = '<?php echo $tabla_actual; ?>';
        const falsosConceptos = <?php echo json_encode(array_values($js_conceptos), JSON_UNESCAPED_UNICODE); ?>;
        const falsosDatosAg   = <?php echo json_encode($datos_ag, JSON_UNESCAPED_UNICODE); ?>;
        const falsosAgencias  = <?php echo json_encode($agencias_orden, JSON_UNESCAPED_UNICODE); ?>;
        const falsosMesNombre = '<?php echo $nombres_meses[$mes_actual] . ' ' . $anio_actual; ?>';
        const falsosCiclo     = <?php echo json_encode($ciclo_actual); ?>;
        const falsosEsAdmin   = <?php echo (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ? 'true' : 'false'; ?>;
    </script>

</main>

<!-- Modal de Carga de Archivo (Falsos) -->


<!-- Modal de Detalle (Genérico) -->
<div id="ea-modal" class="ea-modal">
    <div class="ea-modal__content" style="width: 100%; max-width: 100%; height: 100vh; margin: 0; border-radius: 0; display: flex; flex-direction: column; position: relative;">
        
        <!-- Overlay de Drag and Drop para Importar Excel -->
        <div id="drag-drop-import-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(7, 71, 118, 0.95); z-index: 2000; align-items: center; justify-content: center; flex-direction: column; color: white; font-family: 'Segoe UI', system-ui, sans-serif; pointer-events: none; transition: all 0.2s;">
            <span class="material-symbols-rounded" style="font-size: 80px; margin-bottom: 20px;">upload_file</span>
            <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700;">Suelta el archivo Excel (CSV) aquí</h2>
            <p style="margin: 10px 0 0 0; font-size: 1.1rem; opacity: 0.85;">Se actualizarán automáticamente los comentarios de la tabla</p>
        </div>

        <div class="ea-modal__header" style="background-color: #074776; border-bottom: none; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; flex-shrink: 0;">
            <h3 id="ea-modal-title" style="margin: 0; color: #fff; font-size: 1.05rem; font-weight: 700; display:flex; align-items:center; gap:8px;">Detalle de Anomalías</h3>
            <div style="display: flex; align-items: center; position: relative;">
                <button id="btn-columnas-detalle" onclick="toggleSelectorColumnas()" class="ea-btn" style="background-color: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; font-weight: 600; font-size: 0.82rem; padding: 6px 12px; cursor: pointer; display: none; align-items: center; gap: 6px; transition: background 0.2s; margin-right: 15px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.25)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.15)'">
                    <span class="material-symbols-rounded" style="font-size: 16px; color: white;">view_column</span> Columnas
                </button>
                <button id="btn-importar-detalle" onclick="triggerImportarCSV()" class="ea-btn" style="background-color: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; font-weight: 600; font-size: 0.82rem; padding: 6px 12px; cursor: pointer; display: none; align-items: center; gap: 6px; transition: background 0.2s; margin-right: 15px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.25)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.15)'">
                    <span class="material-symbols-rounded" style="font-size: 16px; color: white;">upload</span> Importar Excel
                </button>
                <button id="btn-exportar-detalle" onclick="exportarDetalleCSV()" class="ea-btn" style="background-color: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; font-weight: 600; font-size: 0.82rem; padding: 6px 12px; cursor: pointer; display: none; align-items: center; gap: 6px; transition: background 0.2s; margin-right: 15px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.25)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.15)'">
                    <span class="material-symbols-rounded" style="font-size: 16px; color: white;">download</span> Descargar Excel (CSV)
                </button>
                <input type="file" id="input-importar-csv" accept=".csv" style="display: none;" onchange="handleImportarCSVSeleccionado(this)">
                <span class="ea-modal__close material-symbols-rounded" onclick="cerrarDetalleFalsos()" style="color:#fff; cursor:pointer;">close</span>
                
                <!-- Dropdown de Selector de Columnas -->
                <div id="col-selector-dropdown" style="display: none; position: absolute; top: 42px; right: 280px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1050; padding: 12px; width: 220px; text-align: left; font-family: 'Segoe UI', system-ui, sans-serif;">
                    <h4 style="margin: 0 0 8px 0; font-size: 0.88rem; color: #1e293b; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center;">
                         <span style="color:#0f172a;">Mostrar Columnas</span>
                        <span style="font-size: 0.72rem; color: #0284c7; cursor: pointer; font-weight: 500;" onclick="resetColumnas()">Restaurar</span>
                    </h4>
                    <div id="col-checkboxes-container" style="display: flex; flex-direction: column; gap: 6px; max-height: 250px; overflow-y: auto;">
                        <!-- Se poblará dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
        <div class="ea-modal__body" id="ea-modal-body" style="padding: 0; flex: 1; min-height: 0; display: flex; flex-direction: column;">
            <div class="ea-spinner-container">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text">Cargando detalles…</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalle por Agencia -->
<div id="ea-agencia-modal" class="ea-modal" onclick="if(event.target===this)cerrarModalAgencia()">
    <div class="ea-modal__content" style="max-width: 480px;">
        <div class="ea-modal__header" style="background-color: #074776; border-bottom: none; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px;">
            <h3 id="ea-agencia-modal-title" style="margin: 0; color: #fff; font-size: 1.05rem; font-weight: 700; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-rounded" style="font-size:1.2rem;">business</span>
                Agencia
            </h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarModalAgencia()" style="color:#fff; cursor:pointer;">close</span>
        </div>
        <div class="ea-modal__body" id="ea-agencia-modal-body" style="padding: 0;"></div>
    </div>
</div>



<!-- Sub-modal para Historial de Comentarios -->
<div id="falsos-comment-history-modal" class="ea-modal" style="display:none; z-index:1100;" onclick="if(event.target===this)cerrarSubModal('falsos-comment-history-modal')">
    <div class="ea-modal__content" style="width: 90%; max-width: 1300px; margin: 2% auto;">
        <div class="ea-modal__header" style="background-color: #074776; padding: 12px 20px; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #fff; font-size: 1.25rem; font-weight: 700; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-rounded" style="color: white; font-size: 24px;">history</span>
                Historial de Comentarios
            </h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarSubModal('falsos-comment-history-modal')" style="color:#fff; cursor:pointer;">close</span>
        </div>
        <div class="ea-modal__body" id="falsos-comment-history-body" style="padding: 20px;">
            <!-- Cargar historial vía AJAX -->
        </div>
    </div>
</div>

<!-- Sub-modal para Agregar Comentario -->
<div id="falsos-add-comment-modal" class="ea-modal" style="display:none; z-index:1100;" onclick="if(event.target===this)cerrarSubModal('falsos-add-comment-modal')">
    <div class="ea-modal__content" style="max-width: 800px; margin: 5% auto;">
        <div class="ea-modal__header" style="background-color: #008f39; padding: 12px 20px; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #fff; font-size: 1.25rem; font-weight: 700; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-rounded" style="color: white; font-size: 24px;">add_comment</span>
                Nuevo Comentario
            </h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarSubModal('falsos-add-comment-modal')" style="color:#fff; cursor:pointer;">close</span>
        </div>
        <div class="ea-modal__body" style="padding: 20px;">
            <!-- Loading Spinner (inicialmente oculto) -->
            <div id="add-comment-loading" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:30px 0;">
                <div class="ea-spinner"></div>
                <span style="margin-top:10px; color:#64748b; font-size:1.05rem; font-weight:600;">Registrando comentario...</span>
            </div>
            
            <!-- Contenido del Formulario -->
            <div id="add-comment-form-content" style="display:flex; flex-direction:column; gap:12px;">
                <label style="font-weight:600; font-size:1.05rem; color:#1e293b;">Escribe tu comentario:</label>
                <textarea id="nuevo-comentario-textarea" style="width:100%; height:200px; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px; font-size:1.05rem; outline:none; resize:none; font-family:inherit;" placeholder="Agregar comentarios sobre la anomalía..."></textarea>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                    <button type="button" class="ea-btn" onclick="cerrarSubModal('falsos-add-comment-modal')" style="background-color:#64748b; color:white; border:none; border-radius:6px; font-weight:600; font-size:1.0rem; padding:8px 16px; cursor:pointer;">Cancelar</button>
                    <button type="button" class="ea-btn" onclick="submitNuevoComentario()" style="background-color:#008f39; color:white; border:none; border-radius:6px; font-weight:600; font-size:1.0rem; padding:8px 16px; cursor:pointer;">Enviar Comentario</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar Observación Directa de Administrador (Tema Amarillo Premium) -->
<div id="falsos-admin-observation-modal" class="ea-modal" style="display:none; z-index:1100;" onclick="if(event.target===this)cerrarSubModal('falsos-admin-observation-modal')">
    <div class="ea-modal__content" style="max-width: 600px; margin: 8% auto; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); border: 1px solid #fef08a;">
        
        <!-- Header con degradado amarillo/ambar premium -->
        <div class="ea-modal__header" style="background: linear-gradient(135deg, #d97706, #ca8a04); padding: 16px 24px; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #fff; font-size: 1.2rem; font-weight: 700; display:flex; align-items:center; gap:8px; text-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                <span class="material-symbols-rounded" style="color: white; font-size: 24px;">gavel</span>
                Observación de Administrador
            </h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarSubModal('falsos-admin-observation-modal')" style="color:#fff; cursor:pointer; opacity: 0.85; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.85">close</span>
        </div>
        
        <div class="ea-modal__body" style="padding: 24px; background: #fff; display: flex; flex-direction: column; gap: 16px;">
            <!-- Loading Spinner (inicialmente oculto) -->
            <div id="admin-obs-loading" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:20px 0;">
                <div class="ea-spinner" style="border-top-color: #ca8a04;"></div>
                <span style="margin-top:10px; color:#ca8a04; font-size:0.95rem; font-weight:600;">Guardando observación...</span>
            </div>
            
            <!-- Contenido del Formulario -->
            <div id="admin-obs-form-content" style="display:flex; flex-direction:column; gap:16px;">
                <!-- Caja de Comentario Actual -->
                <div style="background-color: #fefce8; border-left: 4px solid #ca8a04; padding: 14px 16px; border-radius: 4px;">
                    <span style="display: block; font-size: 0.72rem; color: #854d0e; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.5px;">Comentario de Referencia</span>
                    <p id="admin-obs-referencia-comment" style="margin: 0; font-size: 0.95rem; color: #451a03; line-height: 1.5; white-space: pre-wrap; word-break: break-all; font-weight: 500;"></p>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label for="admin-obs-textarea" style="font-weight:600; font-size:0.95rem; color:#1e293b;">Escribe tu observación:</label>
                    <textarea id="admin-obs-textarea" style="width:100%; height:120px; border:1px solid #fef08a; border-radius:6px; padding:10px; font-size:0.95rem; outline:none; resize:none; font-family:inherit; transition: all 0.2s; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); background-color: #ffffbf50;" placeholder="Ingresar observaciones sobre este comentario..." onfocus="this.style.borderColor='#ca8a04'; this.style.boxShadow='0 0 0 3px rgba(202, 138, 4, 0.15)'" onblur="this.style.borderColor='#fef08a'; this.style.boxShadow='none'" onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submitAdminObservacion(); }"></textarea>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:6px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                    <button type="button" class="ea-btn" onclick="cerrarSubModal('falsos-admin-observation-modal')" style="background-color:#e2e8f0; color:#475569; border:none; border-radius:6px; font-weight:600; font-size:0.9rem; padding:8px 16px; cursor:pointer; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#cbd5e1'" onmouseout="this.style.backgroundColor='#e2e8f0'">Cancelar</button>
                    <button type="button" class="ea-btn" onclick="submitAdminObservacion()" style="background-color:#ca8a04; color:white; border:none; border-radius:6px; font-weight:600; font-size:0.9rem; padding:8px 16px; cursor:pointer; transition: background 0.2s; box-shadow: 0 2px 4px rgba(202,138,4,0.2);" onmouseover="this.style.backgroundColor='#a16207'" onmouseout="this.style.backgroundColor='#ca8a04'">Guardar Observación</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>

    function abrirModalAgencia(agencia) {
        const modal = document.getElementById('ea-agencia-modal');
        const title = document.getElementById('ea-agencia-modal-title');
        const body  = document.getElementById('ea-agencia-modal-body');

        let cicloTexto = (falsosCiclo !== null && falsosCiclo !== '') ? falsosCiclo : 'Todos';
        title.innerHTML = `<span class="material-symbols-rounded" style="font-size:1.2rem;">business</span>
            Atenci&oacute;n de Anomal&iacute;as &mdash; <em style="font-style:normal; opacity:0.9;">${agencia}</em> (Ciclo: ${cicloTexto} | ${falsosMesNombre})`;

        // Construir tabla estilo anomalias-table
        let idx = 1;
        let sumaTotal = 0;
        let filas = '';
        for (const concepto of (typeof falsosConceptos !== 'undefined' ? falsosConceptos : [])) {
            const codigo = concepto.codigo;
            const val = (falsosDatosAg[codigo] && falsosDatosAg[codigo][agencia])
                        ? falsosDatosAg[codigo][agencia] : 0;
            sumaTotal += val;
            const valDisplay = val > 0
                ? `<span class="clickable-metric" style="font-weight:700; color:#074776;" onclick="cerrarModalAgencia(); abrirDetalleFalsos(falsosTablaNombre, '${concepto.pattern}', '${concepto.nombre.replace(/'/g, "\\'")} — ${agencia}', '${agencia}')">${val.toLocaleString('es-MX')}</span>`
                : `<span style="color:#94a3b8;">0</span>`;
            filas += `<tr>
                <td class="col-num">${idx++}</td>
                <td class="col-concepto">${concepto.nombre}</td>
                <td class="col-valor">${valDisplay}</td>
            </tr>`;
        }

        const totalDisplay = sumaTotal > 0
            ? `<span class="clickable-metric" style="font-weight:700;" onclick="cerrarModalAgencia(); abrirDetalleFalsos(falsosTablaNombre, '', 'Todas las Anomalías — ${agencia}', '${agencia}')">${sumaTotal.toLocaleString('es-MX')}</span>`
            : `0`;

        body.innerHTML = `
            <div class="anomalias-table-card" style="margin:0; border-radius:0; border:none; box-shadow:none;">
                <div class="anomalias-title-bar" style="font-size:1rem; padding:10px 16px;">
                    ${agencia} &mdash; ${falsosMesNombre || ''} &mdash; Ciclo: ${cicloTexto}
                </div>
                <div class="ea-table-wrapper">
                    <table class="anomalias-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">No.</th>
                                <th>Concepto</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td>${(typeof falsosConceptos !== 'undefined' ? falsosConceptos.length : '')}</td>
                                <td class="col-label">TOTAL</td>
                                <td>${totalDisplay}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>`;

        modal.style.display = 'block';
    }

    function cerrarModalAgencia() {
        document.getElementById('ea-agencia-modal').style.display = 'none';
    }

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const addCommentModal = document.getElementById('falsos-add-comment-modal');
            const historyModal = document.getElementById('falsos-comment-history-modal');
            const adminObsModal = document.getElementById('falsos-admin-observation-modal');
            const agencyModal = document.getElementById('ea-agencia-modal');
            
            let subModalClosed = false;
            
            if (addCommentModal && addCommentModal.style.display === 'block') {
                cerrarSubModal('falsos-add-comment-modal');
                subModalClosed = true;
            }
            if (historyModal && historyModal.style.display === 'block') {
                cerrarSubModal('falsos-comment-history-modal');
                subModalClosed = true;
            }
            if (adminObsModal && adminObsModal.style.display === 'block') {
                cerrarSubModal('falsos-admin-observation-modal');
                subModalClosed = true;
            }
            if (agencyModal && agencyModal.style.display === 'block') {
                cerrarModalAgencia();
                subModalClosed = true;
            }
            
            if (subModalClosed) {
                e.preventDefault();
                e.stopPropagation();
                // Devolver el foco a la celda del comentario del registro correspondiente si existe
                if (currentCommentTarget && currentCommentTarget.id_registro) {
                    const cell = document.getElementById(`comment-cell-${currentCommentTarget.id_registro}`);
                    if (cell) {
                        const td = cell.closest('td');
                        if (td) {
                            setTimeout(() => td.focus(), 50);
                        }
                    }
                }
                return;
            }
            
            cerrarDetalleFalsos();
        }
    });

    // Abrir Modal de Detalle
    function abrirDetalleFalsos(tabla, pattern, concepto, agencia = '') {
        // Guardar parámetros para recargas y almacenamiento global
        lastDetailParams = { tabla, pattern, concepto, agencia };
        currentDetailedTable = tabla;

        const modal = document.getElementById('ea-modal');
        const modalTitle = document.getElementById('ea-modal-title');
        const modalBody = document.getElementById('ea-modal-body');
        const exportBtn = document.getElementById('btn-exportar-detalle');
        const colBtn = document.getElementById('btn-columnas-detalle');
        const importBtn = document.getElementById('btn-importar-detalle');
        const dropdown = document.getElementById('col-selector-dropdown');

        if (exportBtn) {
            exportBtn.style.display = 'none';
        }
        if (colBtn) {
            colBtn.style.display = 'none';
        }
        if (importBtn) {
            importBtn.style.display = 'none';
        }
        if (dropdown) {
            dropdown.style.display = 'none';
        }

        let cicloTexto = (falsosCiclo !== null && falsosCiclo !== '') ? falsosCiclo : 'Todos';
        modalTitle.innerText = "Detalle: " + concepto + " (Ciclo: " + cicloTexto + " | " + falsosMesNombre + ")";
        modalBody.innerHTML = `
            <div class="ea-spinner-container" style="display:flex; flex-direction:column; align-items:center; padding:50px 0;">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text" style="margin-top:10px; color:#64748b;">Cargando registros detallados...</span>
            </div>
        `;
        modal.style.display = 'block';

        let url = `get_detalle_falsos.php?tabla=${tabla}&pattern=${encodeURIComponent(pattern)}&concepto=${encodeURIComponent(concepto)}&agencia=${encodeURIComponent(agencia)}`;
        if (typeof falsosCiclo !== 'undefined' && falsosCiclo !== null) {
            url += `&ciclo=${falsosCiclo}`;
        }
        fetch(url)
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
                if (exportBtn && html.includes('ea-table--detailed')) {
                    exportBtn.style.display = 'inline-flex';
                }
                if (importBtn && html.includes('ea-table--detailed')) {
                    importBtn.style.display = 'inline-flex';
                }
                if (colBtn && html.includes('ea-table--detailed')) {
                    colBtn.style.display = 'inline-flex';
                    generarCheckboxesColumnas();
                }
                if (html.includes('ea-table--detailed')) {
                    initGridNavigation();
                }
            })
            .catch(err => {
                modalBody.innerHTML = `<div style="padding:20px; color:#dc3545;">Error al cargar detalles: ${err.message}</div>`;
            });
    }

    function getHiddenColumns() {
        try {
            const hidden = localStorage.getItem('falsos_hidden_columns');
            return hidden ? JSON.parse(hidden) : [];
        } catch(e) {
            return [];
        }
    }

    function saveHiddenColumn(colName, isHidden) {
        try {
            let hidden = getHiddenColumns();
            if (isHidden) {
                if (!hidden.includes(colName)) {
                    hidden.push(colName);
                }
            } else {
                hidden = hidden.filter(name => name !== colName);
            }
            localStorage.setItem('falsos_hidden_columns', JSON.stringify(hidden));
        } catch(e) {}
    }

    function generarCheckboxesColumnas() {
        const container = document.getElementById('col-checkboxes-container');
        if (!container) return;
        container.innerHTML = '';
        
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        
        const hiddenCols = getHiddenColumns();
        const headers = table.querySelectorAll('thead tr th');
        headers.forEach((th, index) => {
            const colName = th.textContent.trim();
            const isHidden = hiddenCols.includes(colName);

            // Aplicar la visibilidad inicial según lo guardado en localStorage
            toggleColumn(index, !isHidden);

            const label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.gap = '8px';
            label.style.cursor = 'pointer';
            label.style.fontSize = '0.82rem';
            label.style.color = '#334155';
            label.style.userSelect = 'none';
            label.style.padding = '4px 2px';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !isHidden;
            checkbox.dataset.index = index;
            checkbox.style.cursor = 'pointer';
            
            checkbox.addEventListener('change', (e) => {
                toggleColumn(index, e.target.checked);
                saveHiddenColumn(colName, !e.target.checked);
            });
            
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(colName));
            container.appendChild(label);
        });
    }

    function toggleColumn(colIndex, show) {
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        
        const headerCell = table.querySelectorAll('thead tr th')[colIndex];
        if (headerCell) {
            headerCell.style.display = show ? '' : 'none';
        }
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cell = row.cells[colIndex];
            if (cell) {
                cell.style.display = show ? '' : 'none';
            }
        });
    }

    function toggleSelectorColumnas() {
        const dropdown = document.getElementById('col-selector-dropdown');
        if (!dropdown) return;
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }

    function resetColumnas() {
        const container = document.getElementById('col-checkboxes-container');
        if (!container) return;
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            if (!cb.checked) {
                cb.checked = true;
                cb.dispatchEvent(new Event('change'));
            }
        });
    }

    function initGridNavigation() {
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        
        // Funciones auxiliares para copiado y feedback visual
        function flashCellSuccess(targetCell) {
            const originalBg = targetCell.style.backgroundColor;
            const originalTransition = targetCell.style.transition;
            targetCell.style.transition = 'background-color 0.15s ease';
            targetCell.style.backgroundColor = '#dcfce7'; // Verde pastel de éxito
            setTimeout(() => {
                targetCell.style.backgroundColor = originalBg;
                setTimeout(() => {
                    targetCell.style.transition = originalTransition;
                }, 150);
            }, 250);
        }
        
        function fallbackCopy(targetCell, text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                flashCellSuccess(targetCell);
            } catch (err) {
                console.error('Error al copiar: ', err);
            }
            document.body.removeChild(textarea);
        }
        
        const tbodyRows = table.querySelectorAll('tbody tr');
        tbodyRows.forEach((row, rowIndex) => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, colIndex) => {
                // Hacer cada celda enfocable
                cell.setAttribute('tabindex', '0');
                
                // Si es la celda de comentarios, agregar click listener
                const commentContainer = cell.querySelector('.comment-cell-container');
                if (commentContainer) {
                    cell.addEventListener('click', (e) => {
                        // Evitar doble ejecución si hacen clic en los botones de acción
                        if (e.target.closest('.btn-comment-action')) {
                            return;
                        }
                        manejarAccionComentarioCell(cell);
                    });
                }
                
                cell.addEventListener('keydown', (e) => {
                    let nextCell = null;
                    
                    // Copiar con Ctrl+C o Cmd+C si la celda tiene el foco
                    if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) {
                        e.preventDefault();
                        let textToCopy = '';
                        const commentContainer = cell.querySelector('.comment-cell-container');
                        if (commentContainer) {
                            textToCopy = commentContainer.dataset.original || '';
                        } else {
                            textToCopy = cell.innerText.trim();
                        }
                        
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(textToCopy).then(() => {
                                flashCellSuccess(cell);
                            }).catch(() => {
                                fallbackCopy(cell, textToCopy);
                            });
                        } else {
                            fallbackCopy(cell, textToCopy);
                        }
                        return;
                    }
                    
                    if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        // Buscar la siguiente celda visible
                        let idx = colIndex + 1;
                        while (idx < cells.length) {
                            if (cells[idx].style.display !== 'none') {
                                nextCell = cells[idx];
                                break;
                            }
                            idx++;
                        }
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        // Buscar la anterior celda visible
                        let idx = colIndex - 1;
                        while (idx >= 0) {
                            if (cells[idx].style.display !== 'none') {
                                nextCell = cells[idx];
                                break;
                            }
                            idx--;
                        }
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        // Buscar la celda en la misma columna de la fila siguiente
                        let nextRowIndex = rowIndex + 1;
                        const allRows = table.querySelectorAll('tbody tr');
                        if (nextRowIndex < allRows.length) {
                            const targetRow = allRows[nextRowIndex];
                            nextCell = targetRow.cells[colIndex];
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        // Buscar la celda en la misma columna de la fila anterior
                        let prevRowIndex = rowIndex - 1;
                        const allRows = table.querySelectorAll('tbody tr');
                        if (prevRowIndex >= 0) {
                            const targetRow = allRows[prevRowIndex];
                            nextCell = targetRow.cells[colIndex];
                        }
                    } else if (e.key === 'Enter') {
                        const commentContainer = cell.querySelector('.comment-cell-container');
                        if (commentContainer) {
                            e.preventDefault();
                            manejarAccionComentarioCell(cell);
                        }
                    }
                    
                    if (nextCell) {
                        nextCell.focus();
                    }
                });
            });
        });

        // Auto-enfocar la primera celda visible de la primera fila al cargar para permitir navegación inmediata
        if (tbodyRows.length > 0) {
            const firstRowCells = tbodyRows[0].querySelectorAll('td');
            for (let i = 0; i < firstRowCells.length; i++) {
                if (firstRowCells[i].style.display !== 'none') {
                    // Esperar un breve instante para que el modal se muestre y la celda pueda recibir el foco
                    setTimeout(() => {
                        firstRowCells[i].focus();
                    }, 50);
                    break;
                }
            }
        }
    }

    function toggleSortComments() {
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const arrow = document.getElementById('sort-arrow');
        if (!arrow) return;
        
        let currentState = arrow.dataset.state || 'none'; // 'none', 'asc'
        let nextState = 'asc';
        if (currentState === 'asc') {
            nextState = 'none';
        }
        
        arrow.dataset.state = nextState;
        
        if (nextState === 'asc') {
            arrow.textContent = 'arrow_upward';
            rows.sort((a, b) => {
                const dateA = a.dataset.commentDate || '';
                const dateB = b.dataset.commentDate || '';
                
                // Si ninguno tiene fecha de comentario, mantener el orden original relativo
                if (!dateA && !dateB) {
                    return parseInt(a.dataset.originalIndex) - parseInt(b.dataset.originalIndex);
                }
                // Si solo A tiene comentario, va primero
                if (dateA && !dateB) return -1;
                // Si solo B tiene comentario, va primero
                if (!dateA && dateB) return 1;
                
                // Ambos tienen comentarios, ordenar por fecha ascendente
                return new Date(dateA) - new Date(dateB);
            });
        } else {
            arrow.textContent = 'unfold_more';
            rows.sort((a, b) => {
                const idxA = parseInt(a.dataset.originalIndex) || 0;
                const idxB = parseInt(b.dataset.originalIndex) || 0;
                return idxA - idxB;
            });
        }
        
        // Reordenar filas en el DOM
        rows.forEach(row => tbody.appendChild(row));
    }

    function exportarDetalleCSV() {
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        
        let csv = [];
        
        // 1. Obtener cabeceras y verificar si la columna de Comentarios está visible
        const headerCols = table.querySelectorAll('thead tr th');
        let headers = [];
        let isCommentVisible = false;
        
        headerCols.forEach((th, idx) => {
            if (th.style.display !== 'none') {
                if (idx === headerCols.length - 1) {
                    isCommentVisible = true;
                }
                headers.push(th.textContent.trim());
            }
        });
        
        // Prepend ID a las cabeceras
        headers.unshift("ID");
        
        // Si Comentarios no está visible, añadirlo
        if (!isCommentVisible) {
            headers.push("Comentarios");
        }
        
        // Siempre añadir Observaciones al final
        headers.push("Observaciones");
        
        // Formatear cabecera para CSV
        const headerRowFormatted = headers.map(text => {
            text = text.replace(/"/g, '""');
            if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                return `"${text}"`;
            }
            return text;
        }).join(',');
        csv.push(headerRowFormatted);
        
        // 2. Obtener datos de cada fila
        const tbodyRows = table.querySelectorAll('tbody tr');
        tbodyRows.forEach(row => {
            const id = row.dataset.id || '';
            const lastObs = row.dataset.lastObs || '';
            const cells = row.querySelectorAll('td');
            
            let rowData = [];
            
            // Obtener celdas visibles
            cells.forEach((cell, idx) => {
                if (cell.style.display !== 'none') {
                    let text = '';
                    if (idx === cells.length - 1) {
                        // Columna Comentarios
                        const commentContainer = cell.querySelector('.comment-cell-container');
                        text = commentContainer ? (commentContainer.dataset.original || '') : '';
                    } else {
                        text = cell.textContent.trim();
                    }
                    rowData.push(text);
                }
            });
            
            // Prepend ID
            rowData.unshift(id);
            
            // Append Comentarios si no estaba visible
            if (!isCommentVisible) {
                const commentCell = cells[cells.length - 1];
                const commentContainer = commentCell.querySelector('.comment-cell-container');
                const commentText = commentContainer ? (commentContainer.dataset.original || '') : '';
                rowData.push(commentText);
            }
            
            // Siempre append Observaciones
            rowData.push(lastObs);
            
            // Formatear fila para CSV
            const rowFormatted = rowData.map(text => {
                text = text.replace(/"/g, '""');
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    return `"${text}"`;
                }
                return text;
            }).join(',');
            csv.push(rowFormatted);
        });
        
        const csvContent = "\uFEFF" + csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        const modalTitle = document.getElementById('ea-modal-title').innerText;
        const filename = modalTitle.toLowerCase().replace(/[^a-z0-9_]/g, '_') + '.csv';
        
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function mostrarNotificacion(titulo, descripcion, tipo = 'success') {
        let container = document.getElementById('custom-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'custom-toast-container';
            container.style.position = 'fixed';
            container.style.bottom = '24px';
            container.style.right = '24px';
            container.style.zIndex = '9999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `custom-toast custom-toast--${tipo}`;
        
        let iconName = 'info';
        if (tipo === 'success') iconName = 'check_circle';
        if (tipo === 'error') iconName = 'error';
        
        toast.innerHTML = `
            <span class="material-symbols-rounded custom-toast-icon custom-toast-icon--${tipo}" style="font-size: 22px;">${iconName}</span>
            <div class="custom-toast-content">
                <h4 class="custom-toast-title">${titulo}</h4>
                <p class="custom-toast-desc">${descripcion}</p>
            </div>
            <span class="material-symbols-rounded custom-toast-close" onclick="this.parentElement.remove()" style="font-size: 18px;">close</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
    }

    function triggerImportarCSV() {
        const fileInput = document.getElementById('input-importar-csv');
        if (fileInput) {
            fileInput.value = '';
            fileInput.click();
        }
    }

    function handleImportarCSVSeleccionado(input) {
        if (input.files && input.files[0]) {
            procesarImportarCSV(input.files[0]);
        }
    }

    function procesarImportarCSV(file) {
        if (!file) return;
        if (!currentDetailedTable) {
            mostrarNotificacion('Error', 'No se ha identificado la tabla detallada activa.', 'error');
            return;
        }
        
        const modalBody = document.getElementById('ea-modal-body');
        const originalContent = modalBody.innerHTML;
        
        modalBody.innerHTML = `
            <div class="ea-spinner-container" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height: 100%; min-height: 200px;">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text" style="margin-top:15px; color:#074776; font-weight: 600; font-size: 1.1rem;">Importando y actualizando registros...</span>
                <span style="margin-top:5px; color:#64748b; font-size:0.9rem;">Por favor, no cierre este modal.</span>
            </div>
        `;
        
        const formData = new FormData();
        formData.append('tabla', currentDetailedTable);
        formData.append('file', file);
        
        fetch('importar_comentarios_falsos.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                mostrarNotificacion('Importación completada', `${data.message}\nRegistros actualizados: ${data.updated}\nRegistros ignorados/sin cambios: ${data.skipped}`, 'success');
                if (lastDetailParams && lastDetailParams.tabla) {
                    abrirDetalleFalsos(
                        lastDetailParams.tabla, 
                        lastDetailParams.pattern, 
                        lastDetailParams.concepto, 
                        lastDetailParams.agencia
                    );
                }
            } else {
                mostrarNotificacion('Error de Importación', data.message || 'Error desconocido.', 'error');
                modalBody.innerHTML = originalContent;
                if (originalContent.includes('ea-table--detailed')) {
                    initGridNavigation();
                }
            }
        })
        .catch(err => {
            mostrarNotificacion('Error de Conexión', 'No se pudo conectar con el servidor: ' + err, 'error');
            modalBody.innerHTML = originalContent;
            if (originalContent.includes('ea-table--detailed')) {
                initGridNavigation();
            }
        });
    }

    function cerrarDetalleFalsos() {
        document.getElementById('ea-modal').style.display = 'none';
        const dropdown = document.getElementById('col-selector-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    // Variables globales para comentario objetivo
    let currentCommentTarget = {
        tabla: '',
        id_registro: 0
    };
    
    // Variables globales para la tabla detallada activa e importación
    let currentDetailedTable = '';
    let lastDetailParams = {
        tabla: '',
        pattern: '',
        concepto: '',
        agencia: ''
    };

    function abrirHistorialComentarios(tabla, id_registro, autoOpenObs = false) {
        currentCommentTarget.tabla = tabla;
        currentCommentTarget.id_registro = id_registro;
        
        const body = document.getElementById('falsos-comment-history-body');
        body.innerHTML = `
            <div class="ea-spinner-container" style="display:flex; flex-direction:column; align-items:center; padding:20px;">
                <div class="ea-spinner"></div>
                <span style="margin-top:10px; color:#64748b; font-size:0.9rem;">Cargando historial...</span>
            </div>`;
        
        document.getElementById('falsos-comment-history-modal').style.display = 'block';
        
        fetch(`get_historial_comentarios_falsos.php?tabla=${tabla}&id_registro=${id_registro}`)
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
                if (autoOpenObs) {
                    // Buscar el primer botón de "Agregar Observación" en la lista de historial
                    const firstToggleBtn = body.querySelector('div[id^="obs-toggle-btn-"] button');
                    if (firstToggleBtn) {
                        firstToggleBtn.click();
                    }
                }
            })
            .catch(err => {
                body.innerHTML = `<div style="color:#dc3545; text-align:center;">Error al cargar historial: ${err}</div>`;
            });
    }

    function manejarAccionComentarioCell(cell) {
        const container = cell.querySelector('.comment-cell-container');
        if (!container) return;
        
        const tr = cell.closest('tr');
        const id_registro = parseInt(tr.dataset.id);
        const comment = container.dataset.original || '';
        const latestCommentId = parseInt(container.dataset.latestCommentId) || 0;
        
        if (typeof falsosEsAdmin !== 'undefined' && falsosEsAdmin) {
            if (comment.trim() !== '' && latestCommentId > 0) {
                // Si tiene comentarios, abre la nueva interfaz amarilla para ingresar una observación directamente
                abrirAgregarObservacionDirecta(latestCommentId, comment, id_registro);
            } else {
                // Si no tiene comentarios, abre la interfaz para ingresar un comentario (modo normal)
                abrirAgregarComentario(falsosTablaNombre, id_registro);
            }
        } else {
            // Si no es admin, abrir agregar comentario
            abrirAgregarComentario(falsosTablaNombre, id_registro);
        }
    }

    function abrirAgregarComentario(tabla, id_registro) {
        currentCommentTarget.tabla = tabla;
        currentCommentTarget.id_registro = id_registro;
        
        document.getElementById('nuevo-comentario-textarea').value = '';
        document.getElementById('falsos-add-comment-modal').style.display = 'block';
        setTimeout(() => {
            document.getElementById('nuevo-comentario-textarea').focus();
        }, 150);
    }

    function abrirAgregarObservacionDirecta(id_comentario, commentText, id_registro) {
        currentCommentTarget.id_comentario = id_comentario;
        currentCommentTarget.id_registro = id_registro;
        
        document.getElementById('admin-obs-referencia-comment').textContent = commentText;
        document.getElementById('admin-obs-textarea').value = '';
        
        document.getElementById('falsos-admin-observation-modal').style.display = 'block';
        setTimeout(() => {
            document.getElementById('admin-obs-textarea').focus();
        }, 150);
    }

    function submitAdminObservacion() {
        const observacion = document.getElementById('admin-obs-textarea').value.trim();
        if (observacion === '') {
            mostrarNotificacion('Observación vacía', 'Por favor escribe una observación.', 'info');
            return;
        }
        
        const id_comentario = currentCommentTarget.id_comentario;
        const id_registro = currentCommentTarget.id_registro;
        
        // Cerrar el modal de inmediato
        cerrarSubModal('falsos-admin-observation-modal');
        
        // Activar animación en la celda
        setCellCommentLoading(id_registro, true);
        
        const formData = new FormData();
        formData.append('id_comentario', id_comentario);
        formData.append('observacion', observacion);
        
        fetch('guardar_observacion_falsos.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            setCellCommentLoading(id_registro, false);
            
            if (data.status === 'ok') {
                mostrarNotificacion('Observación guardada', 'La observación se ha guardado exitosamente.', 'success');
                
                // Actualizar color del icono de historial ya que contiene una observación
                const historyBtn = document.getElementById(`btn-history-comment-${id_registro}`);
                if (historyBtn) {
                    historyBtn.style.color = '#2563eb';
                }
                // Actualizar color de estado del comentario a amarillo
                const addBtn = document.getElementById(`btn-add-comment-${id_registro}`);
                if (addBtn) {
                    addBtn.style.color = '#ca8a04';
                }
                
                // Sincronizar localmente la última observación en el DOM del row
                const row = document.querySelector(`tr[data-id="${id_registro}"]`);
                if (row) {
                    row.dataset.lastObs = observacion;
                }
            } else {
                mostrarNotificacion('Error', data.error || 'No se pudo guardar la observación.', 'error');
            }
        })
        .catch(err => {
            setCellCommentLoading(id_registro, false);
            mostrarNotificacion('Error de Conexión', 'Error de conexión: ' + err, 'error');
        });
    }

    function cerrarSubModal(id) {
        document.getElementById(id).style.display = 'none';
        
        // Devolver el foco a la celda del comentario del registro correspondiente si existe
        if ((id === 'falsos-add-comment-modal' || id === 'falsos-comment-history-modal' || id === 'falsos-admin-observation-modal') && 
            currentCommentTarget && currentCommentTarget.id_registro) {
            
            const cell = document.getElementById(`comment-cell-${currentCommentTarget.id_registro}`);
            if (cell) {
                const td = cell.closest('td');
                if (td) {
                    // Esperar un instante para evitar pérdida de foco si el navegador está cerrando elementos
                    setTimeout(() => {
                        td.focus();
                    }, 50);
                }
            }
        }
    }

    function setCellCommentLoading(id, isLoading) {
        const cell = document.getElementById(`comment-cell-${id}`);
        if (!cell) return;
        
        const textSpan = document.getElementById(`comment-text-${id}`);
        const actions = cell.querySelector('.comment-actions-wrapper');
        let spinner = cell.querySelector('.cell-loading-spinner');
        
        if (isLoading) {
            if (textSpan) textSpan.style.display = 'none';
            if (actions) actions.style.display = 'none';
            if (!spinner) {
                spinner = document.createElement('div');
                spinner.className = 'cell-loading-spinner';
                spinner.innerHTML = `
                    <span class="comment-cell-spinner" style="vertical-align: middle;"></span>
                    <span style="font-size: 0.75rem; color: #64748b; font-style: italic; vertical-align: middle; margin-left: 4px;">Guardando...</span>
                `;
                cell.insertBefore(spinner, cell.firstChild);
            }
        } else {
            if (textSpan) textSpan.style.display = 'inline-block';
            if (actions) actions.style.display = 'inline-flex';
            if (spinner) spinner.remove();
        }
    }

    function setHistoryLoading(id, isLoading) {
        const btn = document.getElementById(`btn-history-comment-${id}`);
        if (!btn) return;
        const icon = btn.querySelector('.material-symbols-rounded');
        if (!icon) return;
        
        if (isLoading) {
            btn.disabled = true;
            icon.dataset.originalText = icon.innerText;
            icon.innerText = 'sync';
            icon.classList.add('animate-spin');
            btn.style.color = '#2563eb';
        } else {
            btn.disabled = false;
            icon.innerText = icon.dataset.originalText || 'history';
            icon.classList.remove('animate-spin');
        }
    }

    function submitNuevoComentario() {
        const comentario = document.getElementById('nuevo-comentario-textarea').value.trim();
        if (comentario === '') {
            mostrarNotificacion('Comentario vacío', 'Por favor escribe un comentario.', 'info');
            return;
        }
        
        const id_registro = currentCommentTarget.id_registro;
        const tabla = currentCommentTarget.tabla;
        
        // Cerrar el modal inmediatamente
        cerrarSubModal('falsos-add-comment-modal');
        
        // Activar animación en la celda
        setCellCommentLoading(id_registro, true);

        const formData = new FormData();
        formData.append('tabla', tabla);
        formData.append('id_registro', id_registro);
        formData.append('comentario', comentario);
        
        fetch('guardar_comentario_falsos.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            setCellCommentLoading(id_registro, false);
            if (data.status === 'ok') {
                const textSpan = document.getElementById(`comment-text-${id_registro}`);
                const cellContainer = document.getElementById(`comment-cell-${id_registro}`);
                if (textSpan) {
                    textSpan.textContent = data.ultimo_comentario;
                    if (data.ultimo_comentario === '') {
                        textSpan.classList.add('empty');
                        textSpan.textContent = '(Sin comentario)';
                    } else {
                        textSpan.classList.remove('empty');
                    }
                    textSpan.title = data.ultimo_comentario;
                }
                if (cellContainer) {
                    cellContainer.dataset.original = data.ultimo_comentario;
                    cellContainer.dataset.latestCommentId = data.id_comentario || 0;
                }
                
                // Actualizar la fecha del comentario en el tr del DOM
                const row = document.querySelector(`tr[data-id="${id_registro}"]`);
                if (row) {
                    row.dataset.commentDate = data.fecha_registro || '';
                }
                
                // Actualizar colores de los iconos dinámicamente
                const addBtn = document.getElementById(`btn-add-comment-${id_registro}`);
                const historyBtn = document.getElementById(`btn-history-comment-${id_registro}`);
                if (addBtn) {
                    addBtn.style.color = (data.ultimo_comentario !== '') ? '#008f39' : '#94a3b8';
                }
            } else {
                mostrarNotificacion('Error', data.error || 'No se pudo guardar el comentario.', 'error');
            }
        })
        .catch(err => {
            setCellCommentLoading(id_registro, false);
            mostrarNotificacion('Error de Conexión', 'Error de conexión: ' + err, 'error');
        });
    }

    function guardarObservacion(id_comentario) {
        const textObs = document.getElementById(`obs-text-${id_comentario}`);
        if (!textObs) return;
        const observacion = textObs.value.trim();
        
        if (observacion === '') {
            mostrarNotificacion('Observación vacía', 'Por favor escribe una observación.', 'info');
            return;
        }
        
        const id_registro = currentCommentTarget.id_registro;
        
        // Cerrar el modal de historial de comentarios de inmediato
        cerrarSubModal('falsos-comment-history-modal');
        
        // Activar la animación de carga en el icono de historial
        setHistoryLoading(id_registro, true);

        const formData = new FormData();
        formData.append('id_comentario', id_comentario);
        formData.append('observacion', observacion);
        
        fetch('guardar_observacion_falsos.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            setHistoryLoading(id_registro, false);
            if (data.status === 'ok') {
                // Actualizar color del icono de historial ya que contiene una observación
                const historyBtn = document.getElementById(`btn-history-comment-${id_registro}`);
                if (historyBtn) {
                    historyBtn.style.color = '#2563eb';
                }
                // Si el comentario ya cuenta con una observación, el icono de estado de comentario pasará a amarillo
                const addBtn = document.getElementById(`btn-add-comment-${id_registro}`);
                if (addBtn) {
                    addBtn.style.color = '#ca8a04';
                }
                // Sincronizar localmente la última observación en el DOM del row
                const row = document.querySelector(`tr[data-id="${id_registro}"]`);
                if (row) {
                    row.dataset.lastObs = observacion;
                }
            } else {
                mostrarNotificacion('Error', data.error || 'No se pudo guardar la observación.', 'error');
            }
        })
        .catch(err => {
            setHistoryLoading(id_registro, false);
            mostrarNotificacion('Error de Conexión', 'Error de conexión: ' + err, 'error');
        });
    }

    function toggleFormObservacion(id_comentario, show) {
        const form = document.getElementById('obs-form-container-' + id_comentario);
        const toggleBtn = document.getElementById('obs-toggle-btn-' + id_comentario);
        if (form && toggleBtn) {
            if (show) {
                form.style.display = 'flex';
                toggleBtn.style.display = 'none';
                setTimeout(() => {
                    const txt = document.getElementById('obs-text-' + id_comentario);
                    if (txt) txt.focus();
                }, 100);
            } else {
                form.style.display = 'none';
                toggleBtn.style.display = 'flex';
                const txt = document.getElementById('obs-text-' + id_comentario);
                if (txt) txt.value = '';
            }
        }
    }

    // Cerrar modal al hacer click fuera
    window.onclick = function(event) {
        const modal = document.getElementById('ea-modal');
        if (event.target == modal) {
            cerrarDetalleFalsos();
        }
        
        // Cerrar dropdown de columnas si se hace clic fuera del botón y del dropdown
        const dropdown = document.getElementById('col-selector-dropdown');
        const btnCol = document.getElementById('btn-columnas-detalle');
        if (dropdown && btnCol && !dropdown.contains(event.target) && !btnCol.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const txtArea = document.getElementById('nuevo-comentario-textarea');
        if (txtArea) {
            txtArea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submitNuevoComentario();
                }
            });
        }

        // Configuración de Drag and Drop para importar comentarios desde Excel (CSV)
        const modal = document.getElementById('ea-modal');
        const overlay = document.getElementById('drag-drop-import-overlay');
        
        if (modal && overlay) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                modal.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                modal.addEventListener(eventName, () => {
                    overlay.style.display = 'flex';
                }, false);
            });
            
            modal.addEventListener('dragleave', (e) => {
                const rect = modal.getBoundingClientRect();
                if (e.clientX < rect.left || e.clientX >= rect.right || e.clientY < rect.top || e.clientY >= rect.bottom) {
                    overlay.style.display = 'none';
                }
            }, false);
            
            modal.addEventListener('drop', (e) => {
                overlay.style.display = 'none';
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    const file = files[0];
                    if (file.name.toLowerCase().endsWith('.csv')) {
                        procesarImportarCSV(file);
                    } else {
                        mostrarNotificacion('Formato no válido', 'Por favor, arrastre únicamente archivos en formato CSV (.csv).', 'error');
                    }
                }
            }, false);
        }
    });
</script>
</body>
</html>
