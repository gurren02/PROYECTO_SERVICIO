<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar sesión segura y candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

// 2. RECIBIR PERIODO SELECCIONADO (por defecto el actual)
$mes_actual = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : (int)date('n');
$anio_actual = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : (int)date('Y');

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

// Mapeo de conceptos de anomalías CFE
$conceptos = [
    50 => [
        'nombre' => '50:CASA CERRADA',
        'pattern' => 'CASA CERRADA'
    ],
    51 => [
        'nombre' => '51:NO ENCONTRE DOMI',
        'pattern' => 'NO ENCONTRE DOM'
    ],
    52 => [
        'nombre' => '52:COMUNICACION INTERRUMPIDA',
        'pattern' => 'COMUNICACION IN'
    ],
    53 => [
        'nombre' => '53:UI SERV DIRECTO',
        'pattern' => 'UI SERV DIRECTO'
    ],
    56 => [
        'nombre' => '56:NO HAY MEDIDOR',
        'pattern' => 'NO HAY MEDIDOR'
    ],
    59 => [
        'nombre' => '59:DISPLAY APAGADO',
        'pattern' => 'DISPLAY APAGADO'
    ],
    60 => [
        'nombre' => '60:MEDID NO TRABAJA',
        'pattern' => 'MEDID NO TRABAJ'
    ],
    64 => [
        'nombre' => '64:P. INFRARROJO/MED. DANADO',
        'pattern' => 'P. INFRARROJO/M'
    ],
    65 => [
        'nombre' => '65:UI MED INVERTIDO',
        'pattern' => 'UI MED INVERTID'
    ],
    67 => [
        'nombre' => '67:CONTINGENCIA',
        'pattern' => 'CONTINGENCIA'
    ],
    70 => [
        'nombre' => '70:LECTURA NEGATIVA',
        'pattern' => 'LECTURA NEGATIV'
    ]
];

// Inicializar contadores
$valores_actual = array_fill_keys(array_keys($conceptos), 0);

// Función para obtener y mapear conteos de RPUs únicos
function obtenerConteosMapeados($pdo, $nombre_tabla, $conceptos) {
    $conteos = array_fill_keys(array_keys($conceptos), 0);
    try {
        // Consultar anomalías agrupadas por descripción con conteo de Rpu únicos (filtrado por Estimado o similar)
        $query = "SELECT Anomalia, COUNT(DISTINCT Rpu) as total FROM `$nombre_tabla` WHERE Tipo = 'Estimado' OR Tipo LIKE '%ESTIM%' GROUP BY Anomalia";
        $stmt = $pdo->query($query);
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

// Cargar conteos si las tablas existen
if ($tabla_actual_existe) {
    $valores_actual = obtenerConteosMapeados($pdo, $tabla_actual, $conceptos);
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
    <title>CFE - Módulo Falsos</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/subir.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
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
            border: 2px dashed #008f39;
            background-color: #f9fbf9;
            transition: all 0.25s ease;
        }
        .falsos-dropzone:hover, .falsos-dropzone.dragover {
            background-color: #f0f7f0;
            border-color: #16a34a;
            transform: scale(1.005);
        }
        .falsos-dropzone__icon {
            color: #008f39;
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
        .ea-modal__header h3 { margin: 0; color: #008f39; font-size: 1.1rem; }
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
        <div>
            <button onclick="abrirModalCarga()" class="subir-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background-color: #008f39; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#0e7a33'" onmouseout="this.style.backgroundColor='#008f39'">
                <span class="material-symbols-rounded" style="font-size: 18px; color: white;">upload_file</span>
                Cargar Archivo TXT
            </button>
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

    <div class="anomalias-table-card" style="margin-top: 10px; width: 70%;">
        <div class="anomalias-title-bar">Atención de Anomalías</div>
        <div class="ea-table-wrapper">
            <table class="anomalias-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">No.</th>
                        <th>Concepto</th>
                        <th>Actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    $suma_actual = 0;
                    foreach ($conceptos as $codigo => $info):
                        $val_act = $valores_actual[$codigo];
                        $suma_actual += $val_act;
                        ?>
                        <tr>
                            <td class="col-num"><?php echo $idx++; ?></td>
                            <td class="col-concepto"><?php echo htmlspecialchars($info['nombre']); ?></td>
                            
                            <!-- Celda Actual -->
                            <td class="col-valor">
                                <?php if ($val_act > 0 && $tabla_actual_existe): ?>
                                    <a href="javascript:void(0)" onclick="abrirDetalleFalsos('<?php echo $tabla_actual; ?>', '<?php echo $info['pattern']; ?>', '<?php echo htmlspecialchars($info['nombre'] . ' - ' . $nombres_meses[$mes_actual] . ' ' . $anio_actual); ?>')">
                                        <?php echo number_format($val_act); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Fila de Totales -->
                    <tr class="total-row">
                        <td><?php echo count($conceptos); ?></td>
                        <td class="col-label">TOTAL</td>
                        <td><?php echo number_format($suma_actual); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal de Carga de Archivo (Falsos) -->
<div id="ea-upload-modal" class="ea-modal">
    <div class="ea-modal__content" style="max-width: 500px;">
        <div class="ea-modal__header" style="background-color: #008f39; border-bottom: none; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
            <h3 id="ea-upload-modal-title" style="margin: 0; color: #ffffff; font-size: 1.15rem; font-weight: 700;">Cargar Archivo TXT</h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarModalCarga()" style="color: #ffffff; cursor: pointer;">close</span>
        </div>
        <div class="ea-modal__body" style="padding: 20px;">
            <p style="font-size: 0.85rem; color: #64748b; margin-top: 0; margin-bottom: 20px;">
                Sube el archivo TXT para actualizar la información correspondiente al periodo detectado en el nombre del archivo.
            </p>
            
            <div id="falsos-alert-container"></div>
            
            <form id="falsos-upload-form" class="subir-form">
                
                <div class="subir-form__group">
                    <label class="subir-dropzone falsos-dropzone" for="archivo_txt" id="falsos-dropzone-label">
                        <svg class="subir-dropzone__icon falsos-dropzone__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 48px; height: 48px; margin-bottom: 10px; color: #008f39;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        <span class="subir-dropzone__text" id="falsos-dropzone-text" style="font-size: 0.95rem;">Seleccionar archivo <strong>.txt</strong></span>
                        <span class="subir-dropzone__hint" style="font-size: 0.8rem;">o arrastra y suelta aquí</span>
                        <input type="file" name="archivo" id="archivo_txt" accept=".txt" required style="display: none;">
                    </label>
                </div>

                <!-- Preview y Confirmación de Parámetros -->
                <div id="falsos-file-preview" style="margin-top: 15px; padding: 15px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.85rem; color: #0f172a; font-weight: 700;">Datos a Procesar (Confirmar):</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <div style="flex: 1.2; min-width: 120px; display: flex; flex-direction: column; gap: 4px;">
                            <label style="color:#475569; font-size:0.72rem; font-weight:700; text-transform:uppercase;">Mes</label>
                            <select name="mes" id="upload-mes" class="ea-form__control" style="width: 100%; height: 36px; padding: 0 8px; font-size: 0.85rem;">
                                <?php foreach ($nombres_meses as $num => $nombre): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($num === $mes_actual) ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 0.8; min-width: 80px; display: flex; flex-direction: column; gap: 4px;">
                            <label style="color:#475569; font-size:0.72rem; font-weight:700; text-transform:uppercase;">Año</label>
                            <input type="number" name="anio" id="upload-anio" class="ea-form__control" style="width: 100%; height: 36px; padding: 0 8px; font-size: 0.85rem;" value="<?php echo $anio_actual; ?>" min="2000" max="2100">
                        </div>
                        <div style="flex: 0.8; min-width: 80px; display: flex; flex-direction: column; gap: 4px;">
                            <label style="color:#475569; font-size:0.72rem; font-weight:700; text-transform:uppercase;">Ciclo</label>
                            <input type="text" id="upload-ciclo" class="ea-form__control" style="width: 100%; height: 36px; padding: 0 8px; font-size: 0.85rem; background-color: #f1f5f9; cursor: not-allowed;" value="Pendiente" readonly>
                        </div>
                    </div>
                    <div id="preview-filename-display" style="margin-top: 10px; font-size: 0.72rem; color: #475569; font-weight: 500; font-family: monospace; display: none; background: #f1f5f9; padding: 6px 10px; border-radius: 4px;"></div>
                </div>

                <button type="submit" class="subir-btn" style="background-color: #008f39; font-weight: 600; width: 100%; margin-top: 20px; border-radius: 8px; padding: 12px; color: white; border: none; cursor: pointer;">
                    Comenzar Carga
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Detalle (Genérico) -->
<div id="ea-modal" class="ea-modal">
    <div class="ea-modal__content" style="max-width: 1100px;">
        <div class="ea-modal__header">
            <h3 id="ea-modal-title">Detalle de Anomalías</h3>
            <div style="display: flex; gap: 15px; align-items: center;">
                <span class="ea-modal__close material-symbols-rounded" onclick="cerrarDetalleFalsos()">close</span>
            </div>
        </div>
        <div class="ea-modal__body" id="ea-modal-body">
            <div class="ea-spinner-container">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text">Cargando detalles…</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Subida Progresiva -->
<div id="ea-upload-overlay" class="ea-upload-overlay" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div class="ea-upload-overlay__card" style="background: #fff; padding: 30px; border-radius: 16px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 400px; width: 90%;">
        <div class="ea-upload-icon" style="margin-bottom: 15px;">
            <span class="material-symbols-rounded" style="font-size: 48px; color: #008f39; animation: bounce 1s infinite alternate;">cloud_upload</span>
        </div>
        <span class="ea-spinner-text" id="progreso-texto-modal" style="font-weight: 700; font-size: 1.1rem; display: block; margin-bottom: 5px;">Iniciando subida...</span>
        <span class="ea-spinner-subtext" id="progreso-subtexto-modal" style="font-size: 0.88rem; color: #64748b; display: block; margin-bottom: 20px;">Preparando archivo...</span>
        <div class="ea-upload-steps" style="display: flex; justify-content: center; gap: 8px;">
            <div class="ea-upload-step" id="m-step-1" style="width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1;"></div>
            <div class="ea-upload-step" id="m-step-2" style="width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1;"></div>
            <div class="ea-upload-step" id="m-step-3" style="width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1;"></div>
        </div>
    </div>
</div>

<script>
    // Configuración del Drag & Drop y Selección
    const fileInput = document.getElementById('archivo_txt');
    const dropzone = document.getElementById('falsos-dropzone-label');
    const dropzoneText = document.getElementById('falsos-dropzone-text');

    const defaultMes = <?php echo $mes_actual; ?>;
    const defaultAnio = <?php echo $anio_actual; ?>;

    function parseFalsosFilename(filename) {
        let cleanName = filename.replace(/\.txt$/i, '');
        
        // 1. Ciclo (cXX)
        let ciclo = null;
        let cycleMatch = cleanName.match(/c(\d+)/i);
        if (cycleMatch) {
            ciclo = parseInt(cycleMatch[1]);
        }
        
        // 2. Año (20XX)
        let anio = null;
        let yearMatch = cleanName.match(/(20\d{2})/);
        if (yearMatch) {
            anio = parseInt(yearMatch[1]);
        }
        
        // 3. Mes (nombre del mes en español)
        let mes = null;
        const mesesMap = {
            'enero': 1, 'febrero': 2, 'marzo': 3, 'abril': 4, 'mayo': 5, 'junio': 6,
            'julio': 7, 'agosto': 8, 'septiembre': 9, 'octubre': 10, 'noviembre': 11, 'diciembre': 12
        };
        let nameLower = cleanName.toLowerCase();
        for (let key in mesesMap) {
            if (nameLower.includes(key)) {
                mes = mesesMap[key];
                break;
            }
        }
        
        return { ciclo, anio, mes, cleanName };
    }

    function handleFileSelected(file) {
        if (!file) return;
        dropzoneText.innerHTML = `Archivo seleccionado: <strong>${file.name}</strong>`;
        
        const info = parseFalsosFilename(file.name);
        if (info.mes) {
            document.getElementById('upload-mes').value = info.mes;
        }
        if (info.anio) {
            document.getElementById('upload-anio').value = info.anio;
        }
        if (info.ciclo) {
            document.getElementById('upload-ciclo').value = "Ciclo " + info.ciclo;
        } else {
            document.getElementById('upload-ciclo').value = "No detectado";
        }
        
        const filenameDisplay = document.getElementById('preview-filename-display');
        filenameDisplay.innerHTML = `<strong>Archivo:</strong> ${info.cleanName}`;
        filenameDisplay.style.display = 'block';
    }

    fileInput.addEventListener('change', function() {
        if (this.files[0]) {
            handleFileSelected(this.files[0]);
        } else {
            resetUploadPreview();
        }
    });

    // Drag over/leave effects
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        }, false);
    });

    // Drop file
    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files[0] && files[0].name.toLowerCase().endsWith('.txt')) {
            fileInput.files = files;
            handleFileSelected(files[0]);
        } else {
            alert("Solo se aceptan archivos de texto (.txt).");
        }
    });

    function resetUploadPreview() {
        fileInput.value = "";
        dropzoneText.innerHTML = "Seleccionar archivo <strong>.txt</strong>";
        document.getElementById('upload-mes').value = defaultMes;
        document.getElementById('upload-anio').value = defaultAnio;
        document.getElementById('upload-ciclo').value = "Pendiente";
        const filenameDisplay = document.getElementById('preview-filename-display');
        filenameDisplay.innerHTML = "";
        filenameDisplay.style.display = 'none';
    }

    // Envío progresivo por chunks
    const uploadForm = document.getElementById('falsos-upload-form');
    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (fileInput.files.length === 0) {
            alert('Por favor selecciona un archivo TXT.');
            return;
        }

        const overlay = document.getElementById('ea-upload-overlay');
        const txtElement = document.getElementById('progreso-texto-modal');
        const subElement = document.getElementById('progreso-subtexto-modal');
        const step1 = document.getElementById('m-step-1');
        const step2 = document.getElementById('m-step-2');
        const step3 = document.getElementById('m-step-3');

        // Reset steps
        step1.style.background = '#cbd5e1';
        step2.style.background = '#cbd5e1';
        step3.style.background = '#cbd5e1';

        overlay.style.display = 'flex';
        txtElement.innerText = "Paso 1: Inicializando";
        subElement.innerText = "Subiendo archivo TXT y creando la estructura de la tabla...";
        step1.style.background = '#008f39';

        try {
            // FASE 1: INICIALIZACIÓN
            let formData = new FormData(this);
            formData.append('action', 'init');
            formData.append('archivo', fileInput.files[0]);

            let initRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: formData });
            let initData = await initRes.json();
            if (initData.error) throw new Error(initData.error);

            const totalLineas = initData.total_lines;
            const tmpFile = initData.tmp_file;
            const originalName = initData.original_name;

            step1.style.background = '#16a34a';
            step2.style.background = '#008f39';

            // FASE 2: CHUNKS
            txtElement.innerText = "Paso 2: Procesando líneas";
            let lineasProcesadas = 0;
            const limitMaximo = 5000;

            while (lineasProcesadas < totalLineas) {
                let chunkFormData = new FormData();
                chunkFormData.append('action', 'process_chunk');
                chunkFormData.append('tmp_file', tmpFile);
                chunkFormData.append('start', lineasProcesadas);
                chunkFormData.append('limit', limitMaximo);
                chunkFormData.append('original_name', originalName);
                chunkFormData.append('anio', formData.get('anio'));
                chunkFormData.append('mes', formData.get('mes'));

                let chunkRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: chunkFormData });
                let chunkData = await chunkRes.json();
                if (chunkData.error) throw new Error(chunkData.error);

                lineasProcesadas += chunkData.processed;
                let porcentaje = Math.min(100, Math.round((lineasProcesadas / totalLineas) * 100));
                subElement.innerHTML = `<strong style="font-size:1.1rem; color:#008f39;">${porcentaje}%</strong> procesado (${lineasProcesadas} de ${totalLineas} registros)`;
                
                if (chunkData.processed < limitMaximo) break;
            }

            step2.style.background = '#16a34a';
            step3.style.background = '#008f39';

            // FASE 3: FINALIZACIÓN
            txtElement.innerText = "Paso 3: Guardando registro";
            subElement.innerText = "Finalizando la importación y refrescando la pantalla...";

            let finishFormData = new FormData();
            finishFormData.append('action', 'finish');
            finishFormData.append('tmp_file', tmpFile);
            finishFormData.append('original_name', originalName);
            finishFormData.append('anio', formData.get('anio'));
            finishFormData.append('mes', formData.get('mes'));

            let finishRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: finishFormData });
            let finishData = await finishRes.json();
            if (finishData.error) throw new Error(finishData.error);

            step3.style.background = '#16a34a';
            
            // Éxito
            document.getElementById('falsos-alert-container').innerHTML = `
                <div style='padding:15px; background-color:#d1e7dd; color:#0f5132; margin-bottom:20px; border-radius:8px; border:1px solid #badbcc; font-size:0.9rem;'>
                    <strong>¡Éxito!</strong> Se cargó el archivo correctamente. ${lineasProcesadas} registros guardados.
                </div>`;

            setTimeout(() => {
                overlay.style.display = 'none';
                window.location.reload();
            }, 1200);

        } catch (err) {
            overlay.style.display = 'none';
            document.getElementById('falsos-alert-container').innerHTML = `
                <div style='padding:15px; background-color:#f8d7da; color:#842029; margin-bottom:20px; border-radius:8px; border:1px solid #f5c2c7; font-size:0.9rem;'>
                    <strong>Error de importación:</strong> ${err.message}
                </div>`;
        }
    });

    // Abrir Modal de Detalle
    function abrirDetalleFalsos(tabla, pattern, concepto) {
        const modal = document.getElementById('ea-modal');
        const modalTitle = document.getElementById('ea-modal-title');
        const modalBody = document.getElementById('ea-modal-body');

        modalTitle.innerText = "Detalle: " + concepto;
        modalBody.innerHTML = `
            <div class="ea-spinner-container" style="display:flex; flex-direction:column; align-items:center; padding:50px 0;">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text" style="margin-top:10px; color:#64748b;">Cargando registros detallados...</span>
            </div>
        `;
        modal.style.display = 'block';

        fetch(`get_detalle_falsos.php?tabla=${tabla}&pattern=${encodeURIComponent(pattern)}&concepto=${encodeURIComponent(concepto)}`)
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(err => {
                modalBody.innerHTML = `<div style="padding:20px; color:#dc3545;">Error al cargar detalles: ${err.message}</div>`;
            });
    }

    function exportarDetalleCSV() {
        const table = document.querySelector('.ea-table--detailed');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const cols = rows[i].querySelectorAll('th, td');
            let rowData = [];
            for (let j = 0; j < cols.length; j++) {
                let text = cols[j].textContent.trim().replace(/"/g, '""');
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = `"${text}"`;
                }
                rowData.push(text);
            }
            csv.push(rowData.join(','));
        }
        
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

    function abrirModalCarga() {
        document.getElementById('ea-upload-modal').style.display = 'block';
    }

    function cerrarModalCarga() {
        document.getElementById('ea-upload-modal').style.display = 'none';
        resetUploadPreview();
        document.getElementById('falsos-alert-container').innerHTML = "";
    }

    function cerrarDetalleFalsos() {
        document.getElementById('ea-modal').style.display = 'none';
    }

    // Cerrar modal al hacer click fuera
    window.onclick = function(event) {
        const modal = document.getElementById('ea-modal');
        const uploadModal = document.getElementById('ea-upload-modal');
        if (event.target == modal) {
            cerrarDetalleFalsos();
        }
        if (event.target == uploadModal) {
            cerrarModalCarga();
        }
    }
</script>
</body>
</html>
