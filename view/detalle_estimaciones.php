<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

function formatearRangoCiclos($arr) {
    if (empty($arr)) return "";
    $arr_int = array_map('intval', $arr);
    sort($arr_int);
    
    $bimestrales = [];
    $mensuales = [];
    foreach ($arr_int as $c) {
        if ($c <= 61) {
            $bimestrales[] = $c;
        } else {
            $mensuales[] = $c;
        }
    }
    
    $partes = [];
    
    // Bimestrales (secuencias con paso 2)
    if (!empty($bimestrales)) {
        if (count($bimestrales) === 1) {
            $partes[] = (string)$bimestrales[0];
        } else {
            $es_secuencia = true;
            for ($i = 1; $i < count($bimestrales); $i++) {
                if ($bimestrales[$i] - $bimestrales[$i-1] !== 2) {
                    $es_secuencia = false;
                    break;
                }
            }
            if ($es_secuencia) {
                $partes[] = min($bimestrales) . " AL " . max($bimestrales);
            } else {
                $partes[] = implode(", ", $bimestrales);
            }
        }
    }
    
    // Mensuales (secuencias con paso 1)
    if (!empty($mensuales)) {
        if (count($mensuales) === 1) {
            $partes[] = (string)$mensuales[0];
        } else {
            $es_secuencia = true;
            for ($i = 1; $i < count($mensuales); $i++) {
                if ($mensuales[$i] - $mensuales[$i-1] !== 1) {
                    $es_secuencia = false;
                    break;
                }
            }
            if ($es_secuencia) {
                $partes[] = min($mensuales) . " AL " . max($mensuales);
            } else {
                $partes[] = implode(", ", $mensuales);
            }
        }
    }
    
    return implode(" Y ", $partes);
}

$p1_mes  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$p1_anio = isset($_GET['a']) ? (int)$_GET['a'] : (int)date('Y');

// Lógica de Persistencia de Filtros vía Sesión
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    $user_zona = isset($_SESSION['zona']) ? trim($_SESSION['zona']) : '';
    $_GET['zona'] = $user_zona;
    $_SESSION['filtro_zona'] = $user_zona;
}

// Detección de cambio de periodo para limpiar la persistencia de ciclos
$period_changed = false;
if (isset($_SESSION['last_m']) && $_SESSION['last_m'] !== $p1_mes) {
    $period_changed = true;
}
if (isset($_SESSION['last_a']) && $_SESSION['last_a'] !== $p1_anio) {
    $period_changed = true;
}
$_SESSION['last_m'] = $p1_mes;
$_SESSION['last_a'] = $p1_anio;

if ($period_changed || isset($_GET['clear_filters'])) {
    unset($_SESSION['filtro_ciclo']);
    unset($_GET['ciclo']);
}

// Lógica de Persistencia de Filtros vía Sesión
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    $user_zona = isset($_SESSION['zona']) ? trim($_SESSION['zona']) : '';
    $_GET['zona'] = $user_zona;
    $_SESSION['filtro_zona'] = $user_zona;
}

if (isset($_GET['clear_filters'])) {
    unset($_SESSION['filtro_zona']);
    unset($_GET['zona']);
}

if (isset($_GET['zona'])) {
    $_SESSION['filtro_zona'] = trim($_GET['zona']);
} elseif (!isset($_GET['clear_filters']) && isset($_SESSION['filtro_zona'])) {
    $_GET['zona'] = $_SESSION['filtro_zona'];
}

// Lógica de envío explícito de filtros
if (isset($_GET['filtrado_aplicado'])) {
    if (isset($_GET['ciclo'])) {
        $_SESSION['filtro_ciclo'] = $_GET['ciclo'];
    } else {
        unset($_SESSION['filtro_ciclo']);
    }
} else {
    if (isset($_GET['ciclo'])) {
        $_SESSION['filtro_ciclo'] = $_GET['ciclo'];
    } elseif (isset($_SESSION['filtro_ciclo'])) {
        $_GET['ciclo'] = $_SESSION['filtro_ciclo'];
    }
}

$filtro_zona  = isset($_GET['zona']) ? trim($_GET['zona']) : '';
$filtro_ciclo = [];
if (isset($_GET['ciclo'])) {
    if (is_array($_GET['ciclo'])) {
        $filtro_ciclo = $_GET['ciclo'];
    } else {
        $filtro_ciclo = array_filter(explode(',', (string)$_GET['ciclo']), 'strlen');
    }
}

$sufijo = $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
$nombre_tabla = "estimaciones" . $sufijo;

// --- Obtener datos para filtros ---
$zonas_disponibles  = [];
$ciclos_actuales = [];
$ciclos_comparacion = [];

// Bimestre Anterior para comparación de ciclos
$p3_mes  = $p1_mes - 2;
$p3_anio = $p1_anio;
if ($p3_mes <= 0) { $p3_mes += 12; $p3_anio -= 1; }
$sufijo_comp = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);
$nombre_tabla_comp = "estimaciones" . $sufijo_comp;

try {
    $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
    while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
} catch (PDOException $e) {}

try {
    $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
    while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_actuales[] = (int)$rc['c']; }
} catch (PDOException $e) {}

try {
    $stmt_cc = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_comp` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
    while($rc = $stmt_cc->fetch(PDO::FETCH_ASSOC)) { $ciclos_comparacion[] = (int)$rc['c']; }
} catch (PDOException $e) {}

$zonas_disponibles  = array_unique($zonas_disponibles);  sort($zonas_disponibles);
$ciclos_actuales = array_unique($ciclos_actuales);
$ciclos_comparacion = array_unique($ciclos_comparacion);
$ciclos_disponibles = array_unique(array_merge($ciclos_actuales, $ciclos_comparacion)); sort($ciclos_disponibles);

$is_even_month = ($p1_mes % 2 === 0);
$ciclos_bimestrales = [];
$ciclos_mensuales = [];
foreach ($ciclos_disponibles as $c) {
    if ($c >= 1 && $c <= 61) {
        if ($is_even_month && $c % 2 === 0) {
            $ciclos_bimestrales[] = $c;
        } elseif (!$is_even_month && $c % 2 !== 0) {
            $ciclos_bimestrales[] = $c;
        }
    } elseif ($c >= 62 && $c <= 80) {
        $ciclos_mensuales[] = $c;
    }
}

// Preselección de todos los ciclos que tengan valores en el mes actual (solo bimestrales por defecto)
// Solo se ejecuta en primera carga (cuando no hay filtros aplicados, ni en sesión, ni por submit del formulario)
$es_primera_carga = !isset($_GET['ciclo']) && !isset($_SESSION['filtro_ciclo']) && !isset($_GET['clear_filters']) && !isset($_GET['filtrado_aplicado']);
if ($period_changed) {
    $es_primera_carga = true;
}

if ($es_primera_carga && empty($filtro_ciclo)) {
    foreach ($ciclos_bimestrales as $c) {
        if (in_array($c, $ciclos_actuales)) {
            $filtro_ciclo[] = (string)$c;
        }
    }
}

$datos = [];
$totales_fila = [];
$totales_columnas = [];


try {
    $where_sql = "WHERE 1=1 AND Motivo_Estimacion != '' AND Motivo_Estimacion IS NOT NULL";
    $parametros_sql = [];

    if ($filtro_zona !== '')  {
        $where_sql .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
        $parametros_sql[] = (int)$filtro_zona;
    }
    if (!empty($filtro_ciclo)) {
        $placeholders = [];
        foreach ($filtro_ciclo as $c) {
            $parametros_sql[] = (int)$c;
            $placeholders[] = '?';
        }
        $ph = implode(',', $placeholders);
        $where_sql .= " AND CAST(TRIM(`Ciclo`) AS UNSIGNED) IN ($ph)";
    }

    $query = "SELECT UPPER(TRIM(`Agencia`)) as letra_bd, UPPER(TRIM(`Motivo_Estimacion`)) as motivo, COUNT(*) as total 
              FROM `$nombre_tabla` $where_sql 
              GROUP BY UPPER(TRIM(`Agencia`)), UPPER(TRIM(`Motivo_Estimacion`))";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute($parametros_sql);

    $total_registros_analisis = 0;
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $letra_bd = $fila['letra_bd'];
        $mot = $fila['motivo'];
        $total = (int)$fila['total'];
        
        $total_registros_analisis += $total;
        // Normalización de caracteres por posibles problemas de encoding
        if (strpos($mot, 'NO ENCONTR') !== false) $mot = 'NO ENCONTRÉ DOMICILIO';
        if (strpos($mot, 'COMUNICACI') !== false) $mot = 'COMUNICACIÓN INTERRUMPIDA';
        if (strpos($mot, 'INFRARROJO') !== false) $mot = 'P. INFRARROJO/MED. DAÑADO';
        
        $ag = $fila['letra_bd'];
        
        if ($mot !== '' && $ag !== '') {
            $val = (int)$fila['total'];
            if (!isset($datos[$mot][$ag])) $datos[$mot][$ag] = 0;
            $datos[$mot][$ag] += $val;
            
            if (!isset($totales_columnas[$ag])) $totales_columnas[$ag] = 0;
            $totales_columnas[$ag] += $val;
            
            if (!isset($totales_fila[$mot])) $totales_fila[$mot] = 0;
            $totales_fila[$mot] += $val;
        }
    }
} catch (PDOException $e) { /* Error o tabla no existe */ }

$ultima_actualizacion_analisis = 'No disponible';
try {
    $stmt_u = $pdo->prepare("SELECT MAX(fecha_subida) FROM registro_archivos WHERE nombre_tabla = ?");
    $stmt_u->execute([$nombre_tabla]);
    $res_u = $stmt_u->fetchColumn();
    if ($res_u) $ultima_actualizacion_analisis = date('d/m/Y H:i', strtotime($res_u));
} catch (PDOException $e) {}


// 1. Procesar datos para obtener motivos y agencias presentes
$agencias_presentes = [];
$motivos_presentes = [];

foreach ($datos as $mot => $ags) {
    if ($totales_fila[$mot] > 0) {
        $motivos_presentes[] = $mot;
    }
    foreach ($ags as $ag => $val) {
        if ($val > 0) {
            $agencias_presentes[$ag] = true;
        }
    }
}

// 2. Ordenar motivos por total descendente
arsort($totales_fila);
$motivos_final = [];
foreach ($totales_fila as $mot => $total) {
    if ($total > 0) {
        $motivos_final[] = $mot;
    }
}

// 3. Ordenar agencias por total descendente
arsort($totales_columnas);
$agencias_display = [];
foreach ($totales_columnas as $ag => $total) {
    if ($total > 0) {
        $agencias_display[] = $ag;
    }
}

$max_valor = 0;
foreach ($datos as $mot => $ags) {
    foreach ($ags as $ag => $val) {
        if ($val > $max_valor) $max_valor = $val;
    }
}
$max_fila_total = !empty($totales_fila) ? max($totales_fila) : 0;




function getHeatmapColor($val, $max) {
    if ($val < 1) return '#FFFFFF';
    if ($max <= 0) return '#FFFFFF';
    
    $ratio = $val / $max;
    if ($ratio <= 0.10) return '#FFFFDF';
    if ($ratio <= 0.20) return '#FFFFB8';
    if ($ratio <= 0.30) return '#FFFF94';
    if ($ratio <= 0.40) return '#FDF190';
    if ($ratio <= 0.50) return '#FCEB93';
    if ($ratio <= 0.60) return '#F9D08D';
    if ($ratio <= 0.70) return '#EBCA8F';
    if ($ratio <= 0.80) return '#F1AA87';
    if ($ratio <= 0.90) return '#F08B82';
    return '#E57373';
}

function getHeatmapTextColor($val, $max) {
    return '#000'; // Todos los números negros por solicitud del usuario
}

function obtenerNombreMes($num) {
    $meses = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
            7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    return $meses[$num] ?? '';
}

$titulo_mes = obtenerNombreMes($p1_mes) . " " . $p1_anio;

$origen = isset($_GET['origen']) ? $_GET['origen'] : 'agencia';
$destino_volver = ($origen === 'zona') ? 'ejecutar_analisis_zona.php' : 'ejecutar_analisis.php';

$url_volver = "{$destino_volver}?m={$p1_mes}&a={$p1_anio}";
if ($filtro_zona !== '') $url_volver .= "&zona=" . urlencode($filtro_zona);
if (!empty($filtro_ciclo)) $url_volver .= "&ciclo=" . urlencode(implode(",", $filtro_ciclo));

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Estimaciones</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/sidebar_footer.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        /* ── Estilos específicos del heatmap de estimaciones ── */
        .heatmap-table { border-collapse: collapse; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; width: fit-content; margin: 0; }
        .heatmap-table th, .heatmap-table td { border: 1px solid #000; padding: 5px 6px; font-size: 16px; }
        .heatmap-table th { background-color: #C1E5F3; color: #000; font-weight: 700; text-align: center; font-size: 14px; }
        .heatmap-table th.col-motivo { text-align: center; padding: 4px 6px; font-size: 13px; vertical-align: middle; }
        .col-motivo-wrapper { display: flex; align-items: center; justify-content: center; width: 100%; }
        .heatmap-table td { text-align: center; color: #000 !important; font-weight: 600; }
        .heatmap-table td.row-label { text-align: center; background-color: #fff; font-weight: 600; padding: 4px 6px; font-size: 13px; white-space: nowrap; }
        .heatmap-table td.row-total { font-weight: bold; color: #000; }
        .heatmap-table td.col-total { background-color: #C1E5F3; font-weight: bold; color: #000; padding: 5px 6px; width: max-content; }
        /* Clase para celdas de agencia con valor pequeño */
        .heatmap-table td.col-narrow { padding: 5px 4px !important; min-width: 28px; max-width: 40px; }
        .heatmap-table th.col-narrow-th { padding: 5px 4px !important; min-width: 28px; max-width: 40px; }
        .sort-btn { background: none; border: none; cursor: pointer; color: #fff; border-radius: 4px; display: inline-flex; align-items: center; padding: 2px; }
        .sort-btn:hover { background: rgba(255,255,255,0.2); }
        .ea-table-card { width: fit-content; max-width: 100%; }

        /* --- Estilos para los Filtros (Portados de reportes de zona) --- */
        .ea-filters-card { margin-bottom: 20px; }
        .ea-form { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }
        .ea-form__group { display: flex; flex-direction: column; gap: 4px; }
        .ea-form__label { font-size: 0.75rem; font-weight: 700; color: var(--ea-muted); text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
        .ea-form__label .material-symbols-rounded { font-size: 16px; }
        .ea-form__control { 
            padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; 
            font-size: 0.9rem; color: #495057; background-color: #fff; transition: border-color 0.15s ease-in-out; 
        }
        .ea-form__control:focus { border-color: var(--ea-primary); outline: 0; box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25); }

        .ea-tag {
            display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; 
            font-size: 0.75rem; font-weight: 600; background: #f1f3f5; color: #495057; border: 1px solid #dee2e6;
        }

        /* ── Estilos para Impresión ── */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            @page {
                size: landscape;
                margin: 10mm;
            }
            body, .ea-main {
                background-color: #fff !important;
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            header, .ea-page-header, .ea-btn-print {
                display: none !important;
            }
            .ea-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
                margin: 0 !important;
            }
            .ea-table-card {
                width: 100% !important;
                max-width: 100% !important;
            }
            .heatmap-table {
                width: 100% !important;
                font-size: 10pt !important;
            }
        }
    </style>
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="ea-main">

    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="<?php echo htmlspecialchars($url_volver); ?>" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span>
                Volver
            </a>
            <div>
                <h1 class="ea-page-title">Cuenta de Agencia — Desglose Estimaciones</h1>
                <p class="ea-page-subtitle"><?php echo $titulo_mes; ?></p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button onclick="window.print()" class="ea-btn-print">
                <span class="material-symbols-rounded">print</span>
                Imprimir
            </button>
            <a href="ejecutar_analisis_zona.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d4efdf; color: #196f3d; border: 1px solid #a9dfbf; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#a9dfbf'" onmouseout="this.style.backgroundColor='#d4efdf'">
                <span class="material-symbols-rounded" style="font-size: 18px;">map</span>
                Ir a Nivel Zona
            </a>
            <a href="ejecutar_analisis.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d6eaf8; color: #1b4f72; border: 1px solid #aed6f1; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#aed6f1'" onmouseout="this.style.backgroundColor='#d6eaf8'">
                <span class="material-symbols-rounded" style="font-size: 18px;">business</span>
                Ir a Nivel Agencia
            </a>
        </div>
    </div>

    <div class="ea-card ea-filters-card">
        <div class="ea-card__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">filter_alt</span> Filtros de consulta
                <?php if($filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                    <div class="ea-filter-tags" style="margin-left: 10px;">
                        <?php if($filtro_zona !== ''): ?>
                            <span class="ea-tag">Zona: <?php echo htmlspecialchars($filtro_zona); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($filtro_ciclo)): ?>
                            <span class="ea-tag">Ciclo: <?php echo htmlspecialchars(formatearRangoCiclos($filtro_ciclo)); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ea-card__body">
            <div class="ea-info-summary">
                <div class="ea-info-item">
                    <span class="material-symbols-rounded">history</span>
                    <div>
                        <span class="ea-info-label">Última actualización</span>
                        <span class="ea-info-value"><?php echo $ultima_actualizacion_analisis; ?></span>
                    </div>
                </div>
                <div class="ea-info-item">
                    <span class="material-symbols-rounded">analytics</span>
                    <div>
                        <span class="ea-info-label">Registros totales</span>
                        <span class="ea-info-value"><?php echo number_format($total_registros_analisis); ?></span>
                    </div>
                </div>
            </div>

            <form method="GET" action="" class="ea-form" style="flex-wrap: wrap; display: flex; gap: 20px;">
                <input type="hidden" name="m" value="<?php echo $p1_mes; ?>">
                <input type="hidden" name="a" value="<?php echo $p1_anio; ?>">
                <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen); ?>">
                <input type="hidden" name="filtrado_aplicado" value="1">

                <div class="ea-form__group">
                    <label class="ea-form__label" for="zona"><span class="material-symbols-rounded">location_on</span> Zona</label>
                    <select id="zona" name="zona" class="ea-form__control" style="width: 120px;" <?php echo (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') ? 'disabled' : ''; ?>>
                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin'): ?>
                            <option value="<?php echo htmlspecialchars($_SESSION['zona']); ?>" selected><?php echo htmlspecialchars($_SESSION['zona']); ?></option>
                        <?php else: ?>
                            <option value="">TODAS</option>
                            <?php foreach($zonas_disponibles as $z): ?>
                                <option value="<?php echo $z; ?>" <?php echo ($filtro_zona != '' && (int)$filtro_zona === $z) ? 'selected' : ''; ?>>
                                    <?php echo $z; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin'): ?>
                        <input type="hidden" name="zona" value="<?php echo htmlspecialchars($_SESSION['zona']); ?>">
                    <?php endif; ?>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label">
                        <span class="material-symbols-rounded">cycle</span>
                        Ciclos Bim. (1-61)
                    </label>
                    <div class="ea-dropdown-checkboxes" style="position: relative;">
                        <div class="ea-form__control ea-dropdown-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-width: 140px; background: #fff;">
                            <span class="ea-dropdown-text">TODOS</span>
                            <span class="material-symbols-rounded" style="font-size: 1.2rem; pointer-events: none;">arrow_drop_down</span>
                        </div>
                        <div class="ea-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10; margin-top: 5px;">
                            <div style="display: flex; gap: 5px; padding: 5px; margin-bottom: 5px;">
                                <button type="button" class="mode-rango active" style="flex:1; border: 1px solid var(--ea-primary); background: var(--ea-primary); color: #fff; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Rango</button>
                                <button type="button" class="mode-indiv" style="flex:1; border: 1px solid #ced4da; background: #f8f9fa; color: #333; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Individual</button>
                            </div>
                            <label style="display: block; margin-bottom: 2px; font-size: 0.9rem; cursor: pointer; padding: 5px; background: #f1f3f5;">
                                <input type="checkbox" class="chkSelectAllCiclo" /> <strong>(Seleccionar todo)</strong>
                            </label>
                            <hr style="margin: 4px 0; border-color: #eee;">
                            <?php foreach($ciclos_bimestrales as $c): 
                                $is_missing = !in_array($c, $ciclos_actuales);
                                $lbl_style = $is_missing ? 'color: #d32f2f; font-weight: bold;' : '';
                            ?>
                                <label style="display: block; font-size: 0.85rem; cursor: pointer; padding: 5px; <?php echo $lbl_style; ?>" class="lbl-ciclo">
                                    <input type="checkbox" class="chk-ciclo" name="ciclo[]" value="<?php echo $c; ?>" <?php echo in_array((string)$c, $filtro_ciclo) ? 'checked' : ''; ?> /> 
                                    <?php echo $c; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label">
                        <span class="material-symbols-rounded">update</span>
                        Ciclos Mens. (62-80)
                    </label>
                    <div class="ea-dropdown-checkboxes" style="position: relative;">
                        <div class="ea-form__control ea-dropdown-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-width: 140px; background: #fff;">
                            <span class="ea-dropdown-text">TODOS</span>
                            <span class="material-symbols-rounded" style="font-size: 1.2rem; pointer-events: none;">arrow_drop_down</span>
                        </div>
                        <div class="ea-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10; margin-top: 5px;">
                            <div style="display: flex; gap: 5px; padding: 5px; margin-bottom: 5px;">
                                <button type="button" class="mode-rango active" style="flex:1; border: 1px solid var(--ea-primary); background: var(--ea-primary); color: #fff; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Rango</button>
                                <button type="button" class="mode-indiv" style="flex:1; border: 1px solid #ced4da; background: #f8f9fa; color: #333; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Individual</button>
                            </div>
                            <label style="display: block; margin-bottom: 2px; font-size: 0.9rem; cursor: pointer; padding: 5px; background: #f1f3f5;">
                                <input type="checkbox" class="chkSelectAllCiclo" /> <strong>(Seleccionar todo)</strong>
                            </label>
                            <hr style="margin: 4px 0; border-color: #eee;">
                            <?php foreach($ciclos_mensuales as $c): 
                                $is_missing = !in_array($c, $ciclos_actuales);
                                $lbl_style = $is_missing ? 'color: #d32f2f; font-weight: bold;' : '';
                            ?>
                                <label style="display: block; font-size: 0.85rem; cursor: pointer; padding: 5px; <?php echo $lbl_style; ?>" class="lbl-ciclo">
                                    <input type="checkbox" class="chk-ciclo" name="ciclo[]" value="<?php echo $c; ?>" <?php echo in_array((string)$c, $filtro_ciclo) ? 'checked' : ''; ?> /> 
                                    <?php echo $c; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit" class="ea-btn ea-btn--primary">
                        <span class="material-symbols-rounded">search</span> Aplicar
                    </button>
                    <?php if($filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                        <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&origen=<?php echo $origen; ?>&clear_filters=1" class="ea-btn ea-btn--danger">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="ea-card ea-table-card">
        <div class="ea-card__header" style="background: #C1E5F3; color: #000;">
            <span class="material-symbols-rounded">table_chart</span>
            ESTIMACIONES POR AGENCIA Y TIPO
        </div>
        <div class="ea-table-wrapper">
            <table class="heatmap-table" id="tabla-estimaciones">
                <thead>
                    <tr>
                        <th class="col-motivo">
                            <div class="col-motivo-wrapper">
                                ESTIMACION
                                <button class="sort-btn" onclick="toggleSort()" title="Cambiar orden por total">
                                    <span class="material-symbols-rounded" id="sort-icon" style="font-size: 18px; color: #000;">arrow_downward</span>
                                </button>
                            </div>
                        </th>
                        <?php foreach ($agencias_display as $ag): ?>
                            <th><?php echo $ag; ?></th>
                        <?php endforeach; ?>
                        <th>Total general</th>
                    </tr>
                </thead>
                <tbody id="tbody-datos">
                    <?php 
                    $max_columnas = [];
                    foreach ($agencias_display as $ag) {
                        $vals = [];
                        foreach ($motivos_final as $motivo) {
                            $val = $datos[$motivo][$ag] ?? 0;
                            if ($val > 0) {
                                $vals[] = $val;
                            }
                        }
                        $max_columnas[$ag] = empty($vals) ? 0 : max($vals);
                    }

                    $vals_total = [];
                    foreach ($motivos_final as $motivo) {
                        $val = $totales_fila[$motivo] ?? 0;
                        if ($val > 0) {
                            $vals_total[] = $val;
                        }
                    }
                    $max_total_col = empty($vals_total) ? 0 : max($vals_total);

                    $gran_total = 0;
                    foreach ($motivos_final as $motivo): 
                        $suma_fila = $totales_fila[$motivo];
                    ?>
                    <tr data-total="<?php echo $suma_fila; ?>">

                        <td class="row-label"><?php echo $motivo; ?></td>
                        <?php foreach ($agencias_display as $ag): 
                            $val = $datos[$motivo][$ag] ?? 0;
                            $bgColor = getHeatmapColor($val, $max_columnas[$ag]);
                        ?>
                            <td style="background-color: <?php echo $bgColor; ?>; color: <?php echo getHeatmapTextColor($val, $max_columnas[$ag]); ?> !important;">
                                <?php echo (isset($val) && $val > 0) ? $val : ''; ?>
                            </td>

                        <?php endforeach; ?>
                        <?php $bgRow = getHeatmapColor($suma_fila, $max_total_col); ?>
                        <td class="row-total" style="background-color: <?php echo $bgRow; ?>; color: <?php echo getHeatmapTextColor($suma_fila, $max_total_col); ?> !important;">
                            <?php echo $suma_fila > 0 ? $suma_fila : ''; ?>
                        </td>
                    </tr>
                    <?php 
                        $gran_total += $suma_fila;
                    endforeach; 
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="row-label" style="font-weight:bold; background-color: #C1E5F3; color: #000;">Total general</td>
                        <?php foreach ($agencias_display as $ag): ?>
                            <td class="col-total"><?php echo $totales_columnas[$ag] > 0 ? $totales_columnas[$ag] : ''; ?></td>
                        <?php endforeach; ?>
                        <td style="background-color: #E26B0A; color: #fff !important; font-weight: bold;"><?php echo $gran_total; ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    let currentDir = 'desc'; // Por defecto es descendente desde PHP

    function toggleSort() {
        const tbody = document.getElementById('tbody-datos');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        currentDir = (currentDir === 'desc') ? 'asc' : 'desc';
        document.getElementById('sort-icon').innerText = (currentDir === 'desc') ? 'arrow_downward' : 'arrow_upward';
        
        rows.sort((a, b) => {
            const valA = parseInt(a.getAttribute('data-total')) || 0;
            const valB = parseInt(b.getAttribute('data-total')) || 0;
            if (currentDir === 'desc') {
                return valB - valA;
            } else {
                return valA - valB;
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }

    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? [
            parseInt(result[1], 16),
            parseInt(result[2], 16),
            parseInt(result[3], 16)
        ] : null;
    }

    function exportarPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4'); 
        
        const titulo = "<?php echo 'Estimaciones - ' . $titulo_mes; ?>";

        doc.setFontSize(14);
        doc.text(titulo, 40, 40);

        doc.autoTable({
            html: '#tabla-estimaciones',
            startY: 50,
            theme: 'grid',
            styles: {
                fontSize: 6,
                cellPadding: 1.5,
                textColor: [0, 0, 0]
            },
            headStyles: {
                fillColor: [209, 228, 243],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            footStyles: {
                fillColor: [209, 228, 243],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            didParseCell: function(data) {
                if (data.column.dataKey !== 0) {
                    let td = data.cell.raw;
                    if (td && td.style && td.style.backgroundColor) {
                        let bgColor = td.style.backgroundColor;
                        if (bgColor !== 'transparent' && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== '') {
                            if (bgColor.indexOf('rgb') > -1) {
                                var res = bgColor.match(/\d+/g);
                                if (res && res.length >= 3) {
                                    data.cell.styles.fillColor = [parseInt(res[0]), parseInt(res[1]), parseInt(res[2])];
                                    if(data.row.index === data.table.body.length && data.column.dataKey === data.table.columns.length - 1) {
                                        data.cell.styles.textColor = [255, 255, 255];
                                    }
                                }
                            } else if (bgColor.startsWith('#')) {
                                let c = hexToRgb(bgColor);
                                if (c) {
                                    data.cell.styles.fillColor = c;
                                    if(data.row.index === data.table.body.length && data.column.dataKey === data.table.columns.length - 1) {
                                        data.cell.styles.textColor = [255, 255, 255];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });

        // Vista previa antes de guardar
        const blob = doc.output('blob');
        const url = URL.createObjectURL(blob);
        const preview = window.open(url, '_blank');
        if (!preview) {
            doc.save("estimaciones_<?php echo $sufijo; ?>.pdf");
        }
    }

    // ─── COMPRESIÓN DE COLUMNAS POR DÍGITOS ─────────────────────────────────
    (function comprimirColumnasHeatmap() {
        const table = document.getElementById('tabla-estimaciones');
        if (!table) return;

        const agHeaders = table.querySelectorAll('thead tr th');
        const colCount = agHeaders.length;

        // Tabla de parámetros por nivel de dígitos
        const config = {
            1: { minWidth: '24px', width: '1.8%',  padding: '1px 2px', fontSize: '15px', thPad: '3px 1px' },
            2: { minWidth: '30px', width: '2.2%',  padding: '1px 3px', fontSize: '16px', thPad: '3px 2px' },
            3: { minWidth: '38px', width: '2.8%',  padding: '1px 3px', fontSize: '16px', thPad: '3px 2px' },
        };

        for (let colIdx = 1; colIdx < colCount - 1; colIdx++) {
            let maxDigits = 0;
            // Recorrer tbody + tfoot
            table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
                const cells = tr.querySelectorAll('td');
                if (cells[colIdx]) {
                    const val = parseInt(cells[colIdx].textContent.trim()) || 0;
                    const d = String(val).length;
                    if (d > maxDigits) maxDigits = d;
                }
            });

            const cfg = config[maxDigits];
            if (cfg) {
                // Encabezado
                if (agHeaders[colIdx]) {
                    agHeaders[colIdx].style.padding = cfg.thPad;
                    agHeaders[colIdx].style.minWidth = cfg.minWidth;
                    agHeaders[colIdx].style.width = cfg.width;
                }
                // Celdas de datos y total
                table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
                    const cells = tr.querySelectorAll('td');
                    if (cells[colIdx]) {
                        cells[colIdx].style.padding = cfg.padding;
                        cells[colIdx].style.minWidth = cfg.minWidth;
                        cells[colIdx].style.width = cfg.width;
                        cells[colIdx].style.fontSize = cfg.fontSize;
                    }
                });
            }
        }
    })();

    // ─── LÓGICA DE DROPDOWNS DE FILTROS ─────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Lógica de los Dropdowns de Ciclos (Múltiples)
        document.querySelectorAll('.ea-dropdown-checkboxes').forEach(dropdown => {
            const toggle = dropdown.querySelector('.ea-dropdown-toggle');
            const menu = dropdown.querySelector('.ea-dropdown-menu');
            const text = dropdown.querySelector('.ea-dropdown-text');
            const chkSelectAll = dropdown.querySelector('.chkSelectAllCiclo');
            const chkCiclos = Array.from(dropdown.querySelectorAll('.chk-ciclo'));
            const lblCiclos = Array.from(dropdown.querySelectorAll('.lbl-ciclo'));
            const btnIndiv = dropdown.querySelector('.mode-indiv');
            const btnRango = dropdown.querySelector('.mode-rango');

            let mode = 'rango';
            let rangeStartIdx = null;

            function updateText() {
                const checked = chkCiclos.filter(c => c.checked);
                if (checked.length === 0 || checked.length === chkCiclos.length) {
                    text.textContent = 'TODOS';
                } else if (checked.length === 1) {
                    text.textContent = checked[0].value;
                } else {
                    // Obtener valores numéricos y ordenar
                    const vals = checked.map(c => parseInt(c.value)).sort((a, b) => a - b);
                    // Verificar si forman una secuencia con paso constante
                    const step = vals[1] - vals[0];
                    let esSecuencia = step > 0;
                    for (let i = 2; i < vals.length; i++) {
                        if (vals[i] - vals[i - 1] !== step) {
                            esSecuencia = false;
                            break;
                        }
                    }
                    if (esSecuencia) {
                        text.textContent = vals[0] + ' AL ' + vals[vals.length - 1];
                    } else if (vals.length <= 4) {
                        text.textContent = vals.join(', ');
                    } else {
                        text.textContent = checked.length + ' sel.';
                    }
                }
            }

            if (btnIndiv && btnRango) {
                btnIndiv.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mode = 'individual';
                    rangeStartIdx = null;
                    btnIndiv.classList.add('active');
                    btnRango.classList.remove('active');
                    btnIndiv.style.backgroundColor = 'var(--ea-primary)';
                    btnIndiv.style.color = '#fff';
                    btnIndiv.style.border = '1px solid var(--ea-primary)';
                    btnRango.style.backgroundColor = '#f8f9fa';
                    btnRango.style.color = '#333';
                    btnRango.style.border = '1px solid #ced4da';
                    lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                });

                btnRango.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mode = 'rango';
                    rangeStartIdx = null;
                    btnRango.classList.add('active');
                    btnIndiv.classList.remove('active');
                    btnRango.style.backgroundColor = 'var(--ea-primary)';
                    btnRango.style.color = '#fff';
                    btnRango.style.border = '1px solid var(--ea-primary)';
                    btnIndiv.style.backgroundColor = '#f8f9fa';
                    btnIndiv.style.color = '#333';
                    btnIndiv.style.border = '1px solid #ced4da';

                    // Sincronización absoluta: autoseleccionar el rango si hay elementos previamente marcados
                    const checkedIndices = chkCiclos.map((c, i) => c.checked ? i : -1).filter(idx => idx !== -1);
                    if (checkedIndices.length > 0) {
                        const minIdx = Math.min(...checkedIndices);
                        const maxIdx = Math.max(...checkedIndices);
                        for (let i = minIdx; i <= maxIdx; i++) {
                            chkCiclos[i].checked = true;
                        }
                        chkSelectAll.checked = chkCiclos.every(c => c.checked);
                        updateText();
                    }
                });
            }

            if (chkSelectAll) {
                chkSelectAll.addEventListener('change', (e) => {
                    chkCiclos.forEach(chk => chk.checked = e.target.checked);
                    updateText();
                    rangeStartIdx = null;
                    lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                });
            }

            chkCiclos.forEach((chk, idx) => {
                chk.addEventListener('change', (e) => {
                    if (mode === 'rango') {
                        if (rangeStartIdx === null) {
                            // Primer click del rango: se toma como límite superior y se selecciona desde el inicio
                            const valToSet = chkCiclos[idx].checked;
                            for (let i = 0; i <= idx; i++) {
                                chkCiclos[i].checked = valToSet;
                            }
                            rangeStartIdx = idx;
                            lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                            lblCiclos[idx].style.backgroundColor = '#e2e3e5'; // Resaltar anchor
                        } else {
                            // Segundo click del rango
                            const start = Math.min(rangeStartIdx, idx);
                            const end = Math.max(rangeStartIdx, idx);
                            const valToSet = chkCiclos[rangeStartIdx].checked;
                            for (let i = start; i <= end; i++) {
                                chkCiclos[i].checked = valToSet;
                            }
                            rangeStartIdx = null;
                            lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                        }
                    }
                    if (chkSelectAll) {
                        chkSelectAll.checked = chkCiclos.length > 0 && chkCiclos.every(c => c.checked);
                    }
                    updateText();
                });
            });

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            });

            menu.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Inicializar texto y checkbox 'Select All'
            if (chkCiclos.length > 0) {
                if (chkSelectAll) {
                    chkSelectAll.checked = chkCiclos.every(c => c.checked);
                }
                updateText();
            }
        });

        // Cerrar dropdowns si se clickea afuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.ea-dropdown-checkboxes')) {
                document.querySelectorAll('.ea-dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
    });
</script>
</body>
</html>
