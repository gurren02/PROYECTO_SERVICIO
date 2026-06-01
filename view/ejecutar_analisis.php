<?php
// ==============================================================================
// 1. INICIO DE SESIÓN Y SEGURIDAD (¡SIEMPRE EN LA LÍNEA 1!)
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

// 3. CONFIGURACIÓN CRÍTICA
$columna_agencia = 'Agencia';

// 4. RECIBIR PERIODOS Y FILTROS DESDE LA URL
$p1_mes  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$p1_anio = isset($_GET['a']) ? (int)$_GET['a'] : (int)date('Y');

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

$filtro_zona  = isset($_GET['zona'])  ? trim($_GET['zona'])  : '';
$filtro_ciclo = [];
if (isset($_GET['ciclo'])) {
    if (is_array($_GET['ciclo'])) {
        $filtro_ciclo = $_GET['ciclo'];
    } else {
        $filtro_ciclo = array_filter(explode(',', (string)$_GET['ciclo']), 'strlen');
    }
}

$p2_mes  = $p1_mes;
$p2_anio = $p1_anio - 1;

$p3_mes  = $p1_mes - 2;
$p3_anio = $p1_anio;
if ($p3_mes <= 0) { $p3_mes += 12; $p3_anio -= 1; }

$sufijos = [
        'actual'      => $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT),
        'bimestre'    => $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT),
        'ano_anterior'=> $p2_anio . str_pad($p2_mes, 2, '0', STR_PAD_LEFT)
];

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

function obtenerNombreMes($num) {
    $meses = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
            7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    return $meses[$num] ?? '';
}

$prefijo_titulo = !empty($filtro_ciclo) ? formatearRangoCiclos($filtro_ciclo) : "TODOS";
if ($filtro_zona !== '') $prefijo_titulo .= " - ZONA " . $filtro_zona;

$titulos_completos = [
        'actual'       => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p1_mes) . " $p1_anio",
        'bimestre'     => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p3_mes) . " $p3_anio",
        'ano_anterior' => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p2_mes) . " $p2_anio"
];

$mapa_agencias = [
        'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
        'G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
];

$anomalias = ['cancelaciones','estimaciones','consumos_cero','servicios_sin_medicion',
        'correcciones_de_lecturas','anomalias_pendientes','sin_facturar','cargas_directas'];

$resultados = [];
$total_registros_analisis = 0;
$ultima_actualizacion_analisis = 'No disponible';

foreach ($sufijos as $periodo_key => $sufijo) {
    foreach ($mapa_agencias as $letra => $nombre_agencia) {
        foreach ($anomalias as $anomalia) {
            $resultados[$periodo_key][$nombre_agencia][$anomalia] = 0;
        }
    }
}

// 5. MOTOR DE CONSULTAS OPTIMIZADO
// OPTIMIZACIÓN: Una sola 'SHOW TABLES' al inicio en vez de SHOW TABLES LIKE por cada anomalía.
$stmt_all_tables = $pdo->query("SHOW TABLES");
$todas_las_tablas = array_flip($stmt_all_tables->fetchAll(PDO::FETCH_COLUMN));

$zonas_disponibles  = [];
$ciclos_actuales = [];
$ciclos_comparacion = [];
$tablas_involucradas = [];

foreach ($anomalias as $anomalia) {
    $nombre_tabla_act = $anomalia . $sufijos['actual'];
    if (isset($todas_las_tablas[$nombre_tabla_act])) {
        $tablas_involucradas[] = $nombre_tabla_act;
        try {
            // Obtener zonas únicas
            $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla_act` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
            
            // Obtener ciclos únicos del mes actual
            $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_act` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_actuales[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
    
    // Obtener ciclos del periodo de comparación (bimestre anterior)
    $nombre_tabla_comp = $anomalia . $sufijos['bimestre'];
    if (isset($todas_las_tablas[$nombre_tabla_comp])) {
        try {
            $stmt_cc = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_comp` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_cc->fetch(PDO::FETCH_ASSOC)) { $ciclos_comparacion[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
    
    // Obtener ciclos del periodo de comparación (año anterior)
    $nombre_tabla_comp_aa = $anomalia . $sufijos['ano_anterior'];
    if (isset($todas_las_tablas[$nombre_tabla_comp_aa])) {
        try {
            $stmt_cc_aa = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_comp_aa` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_cc_aa->fetch(PDO::FETCH_ASSOC)) { $ciclos_comparacion[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
}
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

// Obtener última actualización de las tablas involucradas
if (!empty($tablas_involucradas)) {
    $placeholders = implode(',', array_fill(0, count($tablas_involucradas), '?'));
    $stmt_u = $pdo->prepare("SELECT MAX(fecha_subida) FROM registro_archivos WHERE nombre_tabla IN ($placeholders)");
    $stmt_u->execute($tablas_involucradas);
    $res_u = $stmt_u->fetchColumn();
    if ($res_u) $ultima_actualizacion_analisis = date('d/m/Y H:i', strtotime($res_u));
}

foreach ($sufijos as $periodo_key => $sufijo) {
    foreach ($anomalias as $anomalia) {
        $nombre_tabla = $anomalia . $sufijo;
        // Verificación en memoria O(1) — sin llamada a BD
        if (isset($todas_las_tablas[$nombre_tabla])) {
            try {
                $where_sql = "WHERE 1=1";
                $parametros_sql = [];

                // === SOLUCIÓN: Conversión matemática para ignorar ceros a la izquierda ===
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
                // ========================================================================

                $query = "SELECT UPPER(TRIM(`$columna_agencia`)) as letra_bd, COUNT(*) as total 
                          FROM `$nombre_tabla` $where_sql
                          GROUP BY UPPER(TRIM(`$columna_agencia`))";
                $stmt_data = $pdo->prepare($query);
                $stmt_data->execute($parametros_sql);

                while ($fila = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
                    $letra_bd = $fila['letra_bd'];
                    if (array_key_exists($letra_bd, $mapa_agencias)) {
                        $nombre_real = $mapa_agencias[$letra_bd];
                        $resultados[$periodo_key][$nombre_real][$anomalia] = (int)$fila['total'];
                    } elseif (in_array($letra_bd, $mapa_agencias)) {
                        $resultados[$periodo_key][$letra_bd][$anomalia] = (int)$fila['total'];
                    }
                }
            } catch (PDOException $e) { /* Ignorar columnas faltantes */ }
        }
    }
}
// Calcular total de registros para el mes actual
foreach ($resultados['actual'] as $agencia_data) {
    foreach ($agencia_data as $valor_anomalia) {
        $total_registros_analisis += $valor_anomalia;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Nivel Agencia</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/sidebar_footer.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="ea-main">

    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="preparar_analisis.php?mes_objetivo=<?php echo $p1_mes; ?>&anio_objetivo=<?php echo $p1_anio; ?>" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span>
                Volver
            </a>
            <div>
                <h1 class="ea-page-title">Reporte de Resultados Nivel Agencia</h1>
                <p class="ea-page-subtitle">
                    Análisis comparativo — <?php echo obtenerNombreMes($p1_mes) . ' ' . $p1_anio; ?>
                </p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button onclick="exportarExcelAgencia()" class="ea-btn-excel" style="background-color: #fff; color: #2E7D32; border: 1.5px solid #2E7D32; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#E8F5E9';this.style.boxShadow='0 2px 8px rgba(46,125,50,0.15)'" onmouseout="this.style.backgroundColor='#fff';this.style.boxShadow='none'">
                <span class="material-symbols-rounded" style="font-size: 20px;">download</span> Excel
            </button>
            <button onclick="window.print()" class="ea-btn-print">
                <span class="material-symbols-rounded">print</span>
                Imprimir
            </button>
            <a href="ejecutar_analisis_zona.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d4efdf; color: #196f3d; border: 1px solid #a9dfbf; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#a9dfbf'" onmouseout="this.style.backgroundColor='#d4efdf'">
                <span class="material-symbols-rounded" style="font-size: 18px;">map</span>
                Ir a Nivel Zona
            </a>
            <?php 
                $params_est = "?m=$p1_mes&a=$p1_anio";
                if ($filtro_zona !== '') $params_est .= "&zona=" . urlencode($filtro_zona);
                if (!empty($filtro_ciclo)) $params_est .= "&ciclo=" . urlencode(implode(",", $filtro_ciclo));
            ?>
            <a href="detalle_estimaciones.php<?php echo $params_est; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #fcf3cf; color: #7d6608; border: 1px solid #f9e79f; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#f9e79f'" onmouseout="this.style.backgroundColor='#fcf3cf'">
                <span class="material-symbols-rounded" style="font-size: 18px;">table_chart</span>
                Ir a Estimaciones
            </a>
        </div>
    </div>

    <div class="ea-card ea-filters-card">
        <div class="ea-card__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">filter_alt</span> Filtros de consulta
                <?php if($filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                    <div class="ea-filter-tags" style="margin-left: 10px;">
                        <?php if($filtro_zona  !== ''): ?>
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
            <div class="ea-info-summary" style="display: flex; gap: 20px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--ea-border);">
                <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: var(--ea-primary); font-size: 20px;">history</span>
                    <div>
                        <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Última actualización</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);"><?php echo $ultima_actualizacion_analisis; ?></span>
                    </div>
                </div>
                <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: var(--ea-primary); font-size: 20px;">analytics</span>
                    <div>
                        <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Registros totales</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);" id="total-registros-placeholder">Cargando...</span>
                    </div>
                </div>
            </div>

            <form method="GET" action="" class="ea-form" style="flex-wrap: wrap; display: flex; gap: 20px;">
                <input type="hidden" name="m" value="<?php echo $p1_mes; ?>">
                <input type="hidden" name="a" value="<?php echo $p1_anio; ?>">
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
                        <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&clear_filters=1" class="ea-btn ea-btn--danger">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php
    $bloques = [
            ['key' => 'actual',       'label' => 'MES ACTUAL'],
            ['key' => 'bimestre',     'label' => 'BIMESTRE ANTERIOR'],
            ['key' => 'ano_anterior', 'label' => 'AÑO ANTERIOR (MÓVIL)'],
    ];

    foreach ($bloques as $bloque):
        $periodo = $bloque['key'];
        $totales_columnas  = array_fill_keys($anomalias, 0);
        $gran_total_defecto = 0;
        ?>

        <div class="ea-table-card" style="border-radius: 12px; border: 1px solid #000; overflow: hidden; background: #fff; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
            <div class="ea-card__header ea-card__header--<?php echo $bloque['key']; ?>">
                <span class="material-symbols-rounded">table_chart</span>
                <?php echo $titulos_completos[$periodo]; ?>
                <span class="ea-period-badge ea-period-badge--<?php echo $bloque['key']; ?>">
                <?php echo $bloque['label']; ?>
            </span>
            </div>
            <div class="ea-table-wrapper">
                <table class="ea-table">
                    <thead>
                    <tr class="ea-table__head-cols">
                        <th class="ea-th-agencia">AGENCIA</th>
                        <th>CANCELACIONES</th>
                        <th>ESTIMACIONES</th>
                        <th>CONSUMO CERO</th>
                        <th>SIN MED</th>
                        <th>CORREC. LEC</th>
                        <th>ANOM. PEND</th>
                        <th>SIN FACT</th>
                        <th>CARGAS DIR</th>
                        <th class="ea-th-defecto">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 2px;">
                                DEFECTO
                                <button type="button" class="ea-sort-heatmap-btn" title="Ordenar por total y ver mapa de calor">
                                    <span class="material-symbols-rounded" style="font-size: 16px; font-weight: bold;">arrow_downward</span>
                                </button>
                            </div>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $idx_row = 0;
                    foreach ($mapa_agencias as $letra => $agencia):
                        $suma_fila = 0;
                        foreach ($anomalias as $anomalia) {
                            $suma_fila += $resultados[$periodo][$agencia][$anomalia];
                        }
                        ?>
                        <tr data-orig-index="<?php echo $idx_row++; ?>" data-total="<?php echo $suma_fila; ?>">
                            <td class="ea-td-agencia"><?php echo $agencia; ?></td>
                            <?php foreach ($anomalias as $anomalia):
                                $valor = $resultados[$periodo][$agencia][$anomalia];
                                $totales_columnas[$anomalia] += $valor;
                                ?>
                                <td class="ea-td-num <?php echo $valor > 0 ? 'ea-td-num--val' : ''; ?>" data-val="<?php echo $valor; ?>">
                                    <?php if ($valor > 0): ?>
                                        <a href="javascript:void(0)" class="ea-detail-trigger" 
                                           data-tabla="<?php echo $anomalia . $sufijos[$periodo]; ?>" 
                                           data-agencia="<?php echo $letra; ?>" 
                                           data-zona="<?php echo htmlspecialchars($filtro_zona); ?>"
                                           data-ciclo="<?php echo htmlspecialchars(implode(',', $filtro_ciclo)); ?>"
                                           data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia; ?>">
                                            <?php echo number_format($valor); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="ea-zero">0</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="ea-td-defecto" data-val="<?php echo $suma_fila; ?>"><?php echo number_format($suma_fila); ?></td>
                        </tr>
                        <?php $gran_total_defecto += $suma_fila; ?>
                    <?php endforeach; ?>

                    <tr class="ea-tr-total">
                        <td class="ea-td-agencia">TOTAL</td>
                        <?php foreach ($anomalias as $anomalia): ?>
                            <td class="ea-td-num"><?php echo number_format($totales_columnas[$anomalia]); ?></td>
                        <?php endforeach; ?>
                        <td class="ea-td-defecto"><?php echo number_format($gran_total_defecto); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endforeach; ?>

</main>

<div id="ea-modal" class="ea-modal">
    <div class="ea-modal__content">
        <div class="ea-modal__header">
            <h3 id="ea-modal-title">Detalle de Anomalías</h3>
            <div style="display: flex; gap: 15px; align-items: center;">
                <button onclick="exportarPDF()" style="background: none; border: none; cursor: pointer; color: #d9534f; display: flex; align-items: center; gap: 5px; font-weight: 500;">
                    <span class="material-symbols-rounded">picture_as_pdf</span>
                    Exportar PDF
                </button>
                <button onclick="exportarCSV()" style="background: none; border: none; cursor: pointer; color: var(--ea-primary); display: flex; align-items: center; gap: 5px; font-weight: 500;">
                    <span class="material-symbols-rounded">download</span>
                    Exportar CSV
                </button>
                <span class="ea-modal__close material-symbols-rounded">close</span>
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

<style>
    /* ============================================================
       REGLAS DE DISEÑO NIVEL AGENCIA (EXCEL ESTILO)
       ============================================================ */
    .ea-table { border-collapse: collapse; border-style: hidden; table-layout: auto !important; width: auto !important; }
    .ea-table th, .ea-table td { 
        border: 1px solid #000 !important; 
        color: #000 !important; 
        overflow: hidden;
    }
    .ea-table__head-cols th {
        font-size: 12px !important;
        text-align: center !important;
        padding: 4px 10px !important;
        font-weight: bold !important;
        white-space: nowrap !important;
        width: auto !important;
    }
    .ea-td-num, .ea-td-defecto {
        font-size: 17px !important;
        text-align: center !important; 
        font-weight: normal !important; 
        padding: 1px 4px !important;
    }
    .ea-td-num--val { font-weight: normal !important; }
    .ea-td-num a { font-weight: normal !important; color: #000 !important; text-decoration: none !important; }
    
    .ea-th-agencia { background-color: #fff !important; }
    .ea-td-agencia { 
        background-color: #fff !important; 
        font-size: 13px !important; 
        text-align: center !important; 
        font-weight: bold !important; 
        padding: 1px 6px !important;
    }

    /* Columna Defecto */
    .ea-th-defecto, .ea-td-defecto {
        background-color: #FFFF00 !important;
        font-weight: bold !important;
    }

    .ea-card__header--actual { background-color: #2d5e17 !important; color: #fff !important; }
    .ea-card__header--bimestre { background-color: #104861 !important; color: #fff !important; }
    .ea-card__header--ano_anterior { background-color: #9a8700 !important; color: #fff !important; }

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
            margin: 0 !important;
            padding: 0 !important;
        }
        header, .ea-page-header, .ea-filters-card, .ea-btn-print, .ea-btn-excel, .ea-btn-switch-report, .ea-btn-back, .ea-modal, .ea-page-overlay, .ea-spinner-container {
            display: none !important;
        }
        .ea-main { padding: 0 !important; }
        .ea-table-card {
            box-shadow: none !important;
            border: 1px solid #000 !important;
            margin-bottom: 20px !important;
            page-break-inside: avoid;
            width: fit-content !important;
        }
        .ea-card__header {
            border-bottom: 1px solid #000 !important;
        }
    }




    /* Colores pastel para encabezados de anomalías, basados en periodo (Intercalados) */
    
    /* Tabla ACTUAL (Verde) */
    .ea-card__header--actual + .ea-table-wrapper .ea-table th:nth-child(even):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #C6E0B4 !important;
        color: #000 !important;
    }
    .ea-card__header--actual + .ea-table-wrapper .ea-table th:nth-child(odd):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #E2EFDA !important;
        color: #000 !important;
    }

    /* Tabla BIMESTRE (Azul) */
    .ea-card__header--bimestre + .ea-table-wrapper .ea-table th:nth-child(even):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #B4C6E7 !important;
        color: #000 !important;
    }
    .ea-card__header--bimestre + .ea-table-wrapper .ea-table th:nth-child(odd):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #DDEBF7 !important;
        color: #000 !important;
    }

    /* Tabla AÑO ANTERIOR (Amarillo) */
    .ea-card__header--ano_anterior + .ea-table-wrapper .ea-table th:nth-child(even):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #FFE699 !important;
        color: #000 !important;
    }
    .ea-card__header--ano_anterior + .ea-table-wrapper .ea-table th:nth-child(odd):not(.ea-th-agencia):not(.ea-th-defecto) {
        background-color: #FFF2CC !important;
        color: #000 !important;
    }
    
    .ea-tr-total td { background-color: #D9D9D9 !important; font-weight: bold !important; border-top: 1px solid #000 !important; color: #000 !important; }
    .ea-tr-total .ea-td-defecto { background-color: #D9D9D9 !important; font-weight: bold !important; }
    
    .ea-table tbody tr { background-color: #fff !important; border-bottom: 1px solid #000 !important; }
    .ea-table tbody tr:hover { background-color: #FFF9C4 !important; }

    .ea-card__header { border-bottom: 1px solid #000 !important; }
    .ea-table-wrapper { overflow-x: auto; border-radius: 0; }

    /* Wrapper se ajusta al contenido, no al 100% */
    .ea-table-card {
        width: fit-content;
        max-width: 100%;
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
    .ea-modal__header h3 { margin: 0; color: var(--ea-primary); font-size: 1.1rem; }
    .ea-modal__close { cursor: pointer; color: #aaa; transition: 0.2s; }
    .ea-modal__close:hover { color: #333; }
    .ea-modal__body { padding: 20px; }
    
    .ea-detail-trigger {
        color: var(--ea-primary);
        text-decoration: none;
        font-weight: 600;
    }
    .ea-detail-trigger:hover {
        text-decoration: underline;
    }

    /* Botón de Mapa de Calor y Ordenamiento */
    .ea-sort-heatmap-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: #b0bec5; /* Gris apagado por defecto */
        display: inline-flex;
        align-items: center;
        padding: 2px;
        transition: all 0.2s ease-in-out;
        border-radius: 4px;
        vertical-align: middle;
        margin-left: 4px;
    }
    .ea-sort-heatmap-btn:hover {
        background-color: rgba(0, 0, 0, 0.05);
        color: #e65100; /* Naranja en hover */
    }
    .ea-sort-heatmap-btn.active {
        color: #e65100 !important; /* Naranja activo */
        transform: scale(1.1);
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

            chkSelectAll.addEventListener('change', (e) => {
                chkCiclos.forEach(chk => chk.checked = e.target.checked);
                updateText();
                rangeStartIdx = null;
                lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
            });

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
                    chkSelectAll.checked = chkCiclos.length > 0 && chkCiclos.every(c => c.checked);
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
            if(chkCiclos.length > 0) {
                chkSelectAll.checked = chkCiclos.every(c => c.checked);
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

        // Actualizar total
        const total = <?php echo $total_registros_analisis; ?>;
        const placeholder = document.getElementById('total-registros-placeholder');
        if(placeholder) placeholder.textContent = total.toLocaleString();

        // Lógica del Modal
        const modal = document.getElementById('ea-modal');
        const modalBody = document.getElementById('ea-modal-body');
        const modalTitle = document.getElementById('ea-modal-title');
        const closeBtn = document.querySelector('.ea-modal__close');

        document.querySelectorAll('.ea-detail-trigger').forEach(trigger => {
            trigger.addEventListener('click', function() {
                const tabla = this.dataset.tabla;
                const agencia = this.dataset.agencia;
                const zona = this.dataset.zona;
                const ciclo = this.dataset.ciclo;
                const titulo = this.dataset.titulo;

                modalTitle.textContent = "Detalle: " + titulo;
                modalBody.innerHTML = '<div class="ea-spinner-container"><div class="ea-spinner"></div><span class="ea-spinner-text">Consultando registros…</span><span class="ea-spinner-subtext">Preparando tabla de datos</span></div>';
                modal.style.display = 'block';

                fetch(`get_detalle_anomalia.php?tabla=${tabla}&agencia=${agencia}&zona=${zona}&ciclo=${ciclo}`)
                    .then(response => response.text())
                    .then(html => {
                        modalBody.innerHTML = '<div class="ea-fade-in">' + html + '</div>';
                    })
                    .catch(err => {
                        modalBody.innerHTML = '<p style="color:red;">Error al cargar los datos.</p>';
                    });
            });
        });

        closeBtn.onclick = () => modal.style.display = 'none';
        window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; }

        // Mostrar overlay MD3 de carga al aplicar filtros
        document.querySelector('form[method="GET"]')?.addEventListener('submit', () => {
            const overlay = document.createElement('div');
            overlay.className = 'ea-page-overlay';
            overlay.innerHTML = `
                <div class="ea-page-overlay__card">
                    <div class="ea-spinner ea-spinner--lg"></div>
                    <span class="ea-spinner-text">Aplicando filtros…</span>
                    <span class="ea-spinner-subtext">Recalculando datos del periodo</span>
                </div>
            `;
            document.body.appendChild(overlay);
        });

        // Lógica de Mapa de Calor y Ordenamiento Opcional por periodo (Agencia)
        function getHeatmapColor(val, max) {
            if (val < 1) return '#FFFFFF';
            if (max <= 0) return '#FFFFFF';
            const ratio = val / max;
            if (ratio <= 0.10) return '#FFFFDF';
            if (ratio <= 0.20) return '#FFFFB8';
            if (ratio <= 0.30) return '#FFFF94';
            if (ratio <= 0.40) return '#FDF190';
            if (ratio <= 0.50) return '#FCEB93';
            if (ratio <= 0.60) return '#F9D08D';
            if (ratio <= 0.70) return '#EBCA8F';
            if (ratio <= 0.80) return '#F1AA87';
            if (ratio <= 0.90) return '#F08B82';
            return '#E57373';
        }

        document.querySelectorAll('.ea-sort-heatmap-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const tableCard = this.closest('.ea-table-card');
                const tbody = tableCard.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not(.ea-tr-total)'));
                const totalRow = tbody.querySelector('.ea-tr-total');
                
                const isActive = this.classList.toggle('active');
                
                if (isActive) {
                    // Calcular el valor absoluto máximo por cada columna (indices 1 a 9)
                    const colCount = 10;
                    const colMaxes = {};
                    for (let colIdx = 1; colIdx < colCount; colIdx++) {
                        let max = 0;
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells[colIdx]) {
                                const val = Math.abs(parseInt(cells[colIdx].getAttribute('data-val')) || 0);
                                if (val > max) {
                                    max = val;
                                }
                            }
                        });
                        colMaxes[colIdx] = max;
                    }

                    rows.sort((a, b) => {
                        const totalA = parseInt(a.getAttribute('data-total')) || 0;
                        const totalB = parseInt(b.getAttribute('data-total')) || 0;
                        return totalB - totalA;
                    });
                    
                    rows.forEach(row => {
                        row.querySelectorAll('td').forEach((td, colIdx) => {
                            if (td.hasAttribute('data-val')) {
                                const val = parseInt(td.getAttribute('data-val')) || 0;
                                const max = colMaxes[colIdx] || 0;
                                const color = getHeatmapColor(val, max);
                                td.style.backgroundColor = color;
                                td.style.setProperty('background-color', color, 'important');
                            }
                        });
                        tbody.appendChild(row);
                    });
                } else {
                    rows.sort((a, b) => {
                        const idxA = parseInt(a.getAttribute('data-orig-index')) || 0;
                        const idxB = parseInt(b.getAttribute('data-orig-index')) || 0;
                        return idxA - idxB;
                    });
                    
                    rows.forEach(row => {
                        row.querySelectorAll('td').forEach(td => {
                            if (td.hasAttribute('data-val')) {
                                td.style.backgroundColor = '';
                                td.style.removeProperty('background-color');
                            }
                        });
                        tbody.appendChild(row);
                    });
                }
                
                if (totalRow) {
                    tbody.appendChild(totalRow);
                }
            });
        });
    });

    function exportarCSV() {
        const titulo = document.getElementById('ea-modal-title').innerText.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const table = document.querySelector('#ea-modal-body table');
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [];
            const cols = rows[i].querySelectorAll('td, th');
            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s+)/gm, ' ');
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            csv.push(row.join(','));
        }

        const csvContent = "\uFEFF" + csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", titulo + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportarPDF() {
        const titulo = document.getElementById('ea-modal-title').innerText;
        const nombreArchivo = titulo.replace(/[^a-z0-9]/gi, '_').toLowerCase() + ".pdf";
        const table = document.querySelector('#ea-modal-body table');
        if (!table) return;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4'); // Horizontal (Landscape), puntos, tamaño A4

        doc.setFontSize(14);
        doc.text(titulo, 40, 40);

        doc.autoTable({
            html: table,
            startY: 50,
            theme: 'grid',
            styles: {
                fontSize: 3.5,
                cellPadding: 0.5,
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [52, 58, 64],
                textColor: 255,
                fontSize: 4,
                halign: 'center'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            margin: { top: 50, right: 10, bottom: 20, left: 10 }
        });

        doc.save(nombreArchivo);
    }
</script>

<!-- ExcelJS para exportación Excel con estilos -->
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
async function exportarExcelAgencia() {
    const tables = document.querySelectorAll('.ea-table-card');
    if (!tables.length) return;

    const wb = new ExcelJS.Workbook();
    const sheetNames = ['Actual', 'Bimestre Anterior', 'Año Anterior'];
    const B = {style:'thin', color:{argb:'FF000000'}};
    const borders = {top:B,bottom:B,left:B,right:B};
    const center = {horizontal:'center',vertical:'middle'};

    tables.forEach((card, idx) => {
        const table = card.querySelector('.ea-table');
        if (!table) return;
        const ws = wb.addWorksheet(sheetNames[idx] || `Hoja${idx+1}`);

        // Header row from card
        const headerDiv = card.querySelector('.ea-card__header');
        if (headerDiv) {
            // Clonar para limpiar iconos y badges del texto
            const clone = headerDiv.cloneNode(true);
            const icon = clone.querySelector('.material-symbols-rounded'); if(icon) icon.remove();
            const badge = clone.querySelector('.ea-period-badge'); if(badge) badge.remove();
            
            const headerText = clone.textContent.trim();
            const colCount = table.querySelectorAll('thead tr th').length;
            ws.mergeCells(1, 1, 1, colCount);
            const hCell = ws.getCell(1, 1);
            hCell.value = headerText;
            hCell.font = {bold:true, color:{argb:'FFFFFFFF'}, size:12};
            hCell.alignment = center;
            hCell.border = borders;
            // Determine header color (Actualizados a los nuevos tonos oscuros)
            if (headerDiv.classList.contains('ea-card__header--actual')) {
                hCell.fill = {type:'pattern',pattern:'solid',fgColor:{argb:'FF2D5E17'}};
            } else if (headerDiv.classList.contains('ea-card__header--bimestre')) {
                hCell.fill = {type:'pattern',pattern:'solid',fgColor:{argb:'FF104861'}};
            } else if (headerDiv.classList.contains('ea-card__header--ano_anterior')) {
                hCell.fill = {type:'pattern',pattern:'solid',fgColor:{argb:'FF9A8700'}};
            }
        }

        // Table headers
        const ths = table.querySelectorAll('thead tr th');
        const xlRow = ws.getRow(2);
        ths.forEach((th, c) => {
            const cell = xlRow.getCell(c + 1);
            cell.value = th.textContent.trim();
            cell.font = {bold:true, size:10};
            cell.alignment = center;
            cell.border = borders;
            const bg = window.getComputedStyle(th).backgroundColor;
            const argb = rgbToArgb(bg);
            if (argb) cell.fill = {type:'pattern',pattern:'solid',fgColor:{argb}};
        });

        // Body rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((tr, r) => {
            const xlR = ws.getRow(r + 3);
            const cells = tr.querySelectorAll('td');
            cells.forEach((td, c) => {
                const cell = xlR.getCell(c + 1);
                const text = td.textContent.trim().replace(/,/g, '');
                const num = parseInt(text);
                cell.value = !isNaN(num) ? num : text;
                cell.alignment = center;
                cell.border = borders;
                cell.font = {size:11};
                const bg = window.getComputedStyle(td).backgroundColor;
                const argb = rgbToArgb(bg);
                if (argb) cell.fill = {type:'pattern',pattern:'solid',fgColor:{argb}};
                if (td.classList.contains('ea-td-agencia')) cell.font = {bold:true, size:10};
                if (td.classList.contains('ea-td-defecto')) cell.font = {bold:true, size:11};
                if (tr.classList.contains('ea-tr-total')) {
                    cell.fill = {type:'pattern',pattern:'solid',fgColor:{argb:'FFD9D9D9'}};
                    cell.font = {bold:true, size:11};
                }
            });
        });

        // Auto width
        ws.columns.forEach(col => { col.width = 14; });
        if (ws.columns[0]) ws.columns[0].width = 12;
    });

    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'reporte_agencia_<?php echo $p1_anio . str_pad($p1_mes, 2, "0", STR_PAD_LEFT); ?>.xlsx';
    a.click();
    URL.revokeObjectURL(url);
}

function rgbToArgb(s) {
    if (!s || s === 'transparent' || s.includes('0, 0, 0, 0')) return null;
    const m = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
    if (!m) return null;
    return 'FF' + [m[1],m[2],m[3]].map(n => parseInt(n).toString(16).padStart(2,'0')).join('').toUpperCase();
}
</script>
</body>
</html>