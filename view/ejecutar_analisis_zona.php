<?php
// ==============================================================================
// 1. INICIO DE SESIÓN Y SEGURIDAD
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// Aumentar límites para XAMPP (evitar timeouts con tablas grandes)
set_time_limit(120);
ini_set('memory_limit', '256M');

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

$columna_agencia = 'Agencia';

// 3. RECIBIR PERIODOS Y FILTROS
$p1_mes  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$p1_anio = isset($_GET['a']) ? (int)$_GET['a'] : (int)date('Y');

$filtro_zona    = isset($_GET['zona'])  ? trim($_GET['zona'])  : '';
$ciclo_inicio   = isset($_GET['ciclo_inicio']) ? trim($_GET['ciclo_inicio']) : '';
$ciclo_fin      = isset($_GET['ciclo_fin']) ? trim($_GET['ciclo_fin']) : '';

// FILTROS PARA EL SEGUNDO RANGO
$ciclo_inicio_2 = isset($_GET['ciclo_inicio_2']) ? trim($_GET['ciclo_inicio_2']) : '';
$ciclo_fin_2    = isset($_GET['ciclo_fin_2']) ? trim($_GET['ciclo_fin_2']) : '';

// Bimestre Anterior
$p3_mes  = $p1_mes - 2;
$p3_anio = $p1_anio;
if ($p3_mes <= 0) { $p3_mes += 12; $p3_anio -= 1; }

$tipo_comp = isset($_GET['comp']) && $_GET['comp'] === 'anual' ? 'anual' : 'bimestre';

$sufijos = [
    'actual' => $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT)
];

function obtenerNombreMes($num) {
    $meses = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
              7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    return $meses[$num] ?? '';
}

$meses_abrev = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',
                7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC'];

if ($tipo_comp === 'anual') {
    $p2_mes = $p1_mes;
    $p2_anio = $p1_anio - 1;
    $sufijos['bimestre'] = $p2_anio . str_pad($p2_mes, 2, '0', STR_PAD_LEFT);
    $th_actual   = (string)$p1_anio;
    $th_bimestre = (string)$p2_anio;
    $lbl_comp = obtenerNombreMes($p2_mes).' '.$p2_anio;
} else {
    $sufijos['bimestre'] = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);
    $th_actual   = $meses_abrev[$p1_mes];
    $th_bimestre = $meses_abrev[$p3_mes];
    $lbl_comp = obtenerNombreMes($p3_mes).' '.$p3_anio;
}

// Título dinámico
$rango1 = "";
if ($ciclo_inicio !== '' && $ciclo_fin !== '') { $rango1 = "$ciclo_inicio AL $ciclo_fin"; }
elseif ($ciclo_inicio !== '') { $rango1 = "$ciclo_inicio"; }

$rango2 = "";
if ($ciclo_inicio_2 !== '' && $ciclo_fin_2 !== '') { $rango2 = "$ciclo_inicio_2 AL $ciclo_fin_2"; }
elseif ($ciclo_inicio_2 !== '') { $rango2 = "$ciclo_inicio_2"; }

$prefijo_titulo = "";
if ($rango1 !== '' && $rango2 !== '') {
    $prefijo_titulo = "CICLOS $rango1 Y $rango2";
} elseif ($rango1 !== '') {
    $prefijo_titulo = "CICLOS $rango1";
} elseif ($rango2 !== '') {
    $prefijo_titulo = "CICLOS $rango2";
} else {
    $prefijo_titulo = "TODOS LOS CICLOS";
}

if ($filtro_zona !== '') {
    $prefijo_titulo .= " - ZONA " . $filtro_zona;
}

$titulo_reporte = $prefijo_titulo . " RESULTADO NIVEL ZONA " . obtenerNombreMes($p1_mes) . " $p1_anio";

$mapa_agencias = [
    'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
    'G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
];

$anomalias = ['cancelaciones','estimaciones','consumos_cero','servicios_sin_medicion',
              'correcciones_de_lecturas','anomalias_pendientes','sin_facturar','cargas_directas'];

$resultados = [];
$combinaciones_existentes = [];
$total_registros_analisis = 0;
$ultima_actualizacion_analisis = 'No disponible';

// ==============================================================================
// 4. MOTOR DE CONSULTAS OPTIMIZADO
// ==============================================================================

// OPTIMIZACIÓN #1: Una sola query para conocer qué tablas existen en la BD,
// en lugar de SHOW TABLES LIKE por cada anomalía dentro de un loop.
$stmt_all_tables = $pdo->query("SHOW TABLES");
$todas_las_tablas = array_flip($stmt_all_tables->fetchAll(PDO::FETCH_COLUMN));

$zonas_disponibles  = [];
$ciclos_disponibles = [];
$tablas_involucradas = [];

// Filtros disponibles (solo desde tablas del período actual)
foreach ($anomalias as $anomalia) {
    $nombre_tabla = $anomalia . $sufijos['actual'];
    if (isset($todas_las_tablas[$nombre_tabla])) {
        $tablas_involucradas[] = $nombre_tabla;
        try {
            $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
            $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_disponibles[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
}
$zonas_disponibles  = array_unique($zonas_disponibles);  sort($zonas_disponibles);
$ciclos_disponibles = array_unique($ciclos_disponibles); sort($ciclos_disponibles);

// Última actualización
if (!empty($tablas_involucradas)) {
    $placeholders = implode(',', array_fill(0, count($tablas_involucradas), '?'));
    $stmt_u = $pdo->prepare("SELECT MAX(fecha_subida) FROM registro_archivos WHERE nombre_tabla IN ($placeholders)");
    $stmt_u->execute($tablas_involucradas);
    $res_u = $stmt_u->fetchColumn();
    if ($res_u) $ultima_actualizacion_analisis = date('d/m/Y H:i', strtotime($res_u));
}

// OPTIMIZACIÓN #2: Función helper para construir la cláusula WHERE de ciclos.
// Evita duplicar la misma lógica varias veces.
function buildCicloWhere($ciclo_inicio, $ciclo_fin, $ciclo_inicio_2, $ciclo_fin_2, &$params, $alias = '') {
    $col = $alias ? "`$alias`.`Ciclo`" : '`Ciclo`';
    $condiciones = [];
    if ($ciclo_inicio !== '' && $ciclo_fin !== '') {
        $condiciones[] = "CAST(TRIM($col) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_inicio; $params[] = (int)$ciclo_fin;
    } elseif ($ciclo_inicio !== '') {
        $condiciones[] = "CAST(TRIM($col) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_inicio;
    }
    if ($ciclo_inicio_2 !== '' && $ciclo_fin_2 !== '') {
        $condiciones[] = "CAST(TRIM($col) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_inicio_2; $params[] = (int)$ciclo_fin_2;
    } elseif ($ciclo_inicio_2 !== '') {
        $condiciones[] = "CAST(TRIM($col) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_inicio_2;
    }
    if (!empty($condiciones)) {
        return " AND (" . implode(" OR ", $condiciones) . ")";
    }
    return "";
}

// Consultas principales (actual + bimestre)
foreach ($sufijos as $periodo_key => $sufijo) {
    foreach ($anomalias as $anomalia) {
        $nombre_tabla = $anomalia . $sufijo;
        // Verificación en memoria O(1) — sin llamada a BD
        if (!isset($todas_las_tablas[$nombre_tabla])) continue;
        try {
            $where_sql = "WHERE 1=1";
            $parametros_sql = [];
            if ($filtro_zona !== '')  {
                $where_sql .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
                $parametros_sql[] = (int)$filtro_zona;
            }
            $where_sql .= buildCicloWhere($ciclo_inicio, $ciclo_fin, $ciclo_inicio_2, $ciclo_fin_2, $parametros_sql);

            $query = "SELECT TRIM(`Zona`) as zona_bd, UPPER(TRIM(`$columna_agencia`)) as letra_bd, COUNT(*) as total
                      FROM `$nombre_tabla` $where_sql
                      GROUP BY TRIM(`Zona`), UPPER(TRIM(`$columna_agencia`))";

            $stmt_data = $pdo->prepare($query);
            $stmt_data->execute($parametros_sql);

            while ($fila = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
                $zona_bd  = $fila['zona_bd'];
                $letra_bd = $fila['letra_bd'];
                if ($zona_bd !== '' && $zona_bd !== null) {
                    if (array_key_exists($letra_bd, $mapa_agencias)) {
                        $nombre_real = $mapa_agencias[$letra_bd];
                    } elseif (in_array($letra_bd, $mapa_agencias)) {
                        $nombre_real = $letra_bd;
                    } else {
                        $nombre_real = 'OTRA';
                    }
                    $combinaciones_existentes[$zona_bd][$nombre_real] = true;
                    if (!isset($resultados[$periodo_key][$zona_bd][$nombre_real][$anomalia])) {
                        $resultados[$periodo_key][$zona_bd][$nombre_real][$anomalia] = 0;
                    }
                    $resultados[$periodo_key][$zona_bd][$nombre_real][$anomalia] += (int)$fila['total'];
                }
            }
        } catch (PDOException $e) {}
    }
}

// ==============================================================================
// REINCIDENTES → se calculan vía AJAX al cargar la página (no bloquea el render)
// ==============================================================================

// 5. ORDENAMIENTO
$lista_zonas = array_keys($combinaciones_existentes);
sort($lista_zonas, SORT_NUMERIC);

foreach (array_keys($sufijos) as $periodo_key) {
    foreach ($lista_zonas as $z) {
        foreach (array_keys($combinaciones_existentes[$z]) as $a) {
            foreach ($anomalias as $anomalia) {
                if (!isset($resultados[$periodo_key][$z][$a][$anomalia])) {
                    $resultados[$periodo_key][$z][$a][$anomalia] = 0;
                }
            }
        }
    }
}
// Total de registros para el período actual
if (isset($resultados['actual'])) {
    foreach ($resultados['actual'] as $zona_data) {
        foreach ($zona_data as $agencia_data) {
            foreach ($agencia_data as $valor_anomalia) {
                $total_registros_analisis += $valor_anomalia;
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Nivel Zona</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/sidebar_footer.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .ea-form__group--rango { display: flex; gap: 10px; align-items: center; }
        .ea-form__group--rango input { width: 70px; }

        .ea-table-wrapper { overflow-x: auto; }
        .ea-table { min-width: 1800px; table-layout: auto !important; }

        /* Estilos específicos para ZONA y AGENCIA en este reporte */
        .ea-td-agencia, .ea-th-agencia { 
            white-space: nowrap !important; 
            overflow: visible !important; 
            text-overflow: clip !important;
            padding: 10px 15px !important;
            width: auto !important;
            min-width: 100px;
        }
        
        /* Columna AGENCIA específica para darle un poco más de aire */
        th.ea-th-agencia:nth-child(2), 
        td.ea-td-agencia:nth-child(2) {
            min-width: 150px !important;
        }

        .b-left { border-left: 2px solid #dee2e6 !important; }
        .th-sub { font-size: 0.75rem !important; font-weight: 600; color: #555; background: #f8f9fa; }
        .td-dif { font-weight: bold; background: #fdfdfd; }
        .dif-pos { color: #dc3545; }
        .dif-neg { color: #198754; }
        .dif-zero { color: #adb5bd; }
        .ea-table__head-main { background-color: #f8f9fa; font-weight: bold; border-bottom: 2px solid #dee2e6; }
    </style>
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="ea-main">
    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="preparar_analisis.php" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span> Volver
            </a>
            <div>
                <h1 class="ea-page-title">Reporte Unificado: Nivel Zona</h1>
                <p class="ea-page-subtitle">Comparativa: <?php echo obtenerNombreMes($p1_mes).' '.$p1_anio; ?> vs <?php echo $lbl_comp; ?></p>
            </div>
        </div>
        <button onclick="window.print()" class="ea-btn-print">
            <span class="material-symbols-rounded">print</span> Imprimir / PDF
        </button>
    </div>

    <div class="ea-card ea-filters-card">
        <div class="ea-card__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">filter_alt</span> Filtros de consulta
                <?php if($filtro_zona !== '' || $ciclo_inicio !== '' || $ciclo_inicio_2 !== ''): ?>
                    <div class="ea-filter-tags" style="margin-left: 10px;">
                        <?php if($filtro_zona !== ''): ?>
                            <span class="ea-tag">Zona: <?php echo htmlspecialchars($filtro_zona); ?></span>
                        <?php endif; ?>
                        <?php if($rango1 !== '' || $rango2 !== ''): ?>
                            <span class="ea-tag">
                                Ciclos: <?php echo htmlspecialchars($rango1 . ($rango1 && $rango2 ? ' y ' : '') . $rango2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 5px; align-items: center;">
                <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&comp=bimestre<?php echo ($filtro_zona?'&zona='.$filtro_zona:'').($ciclo_inicio?'&ciclo_inicio='.$ciclo_inicio:'').($ciclo_fin?'&ciclo_fin='.$ciclo_fin:'').($ciclo_inicio_2?'&ciclo_inicio_2='.$ciclo_inicio_2:'').($ciclo_fin_2?'&ciclo_fin_2='.$ciclo_fin_2:''); ?>" 
                   style="padding: 6px 15px; font-size: 0.85rem; font-weight: 600; border-radius: 20px; text-decoration: none; transition: 0.2s; 
                   <?php echo $tipo_comp === 'bimestre' ? 'background-color:#0d6efd; color:white; border: 1px solid #0d6efd;' : 'background-color:#fff; color:#495057; border: 1px solid #ced4da;'; ?>">
                    Bimestral
                </a>
                <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&comp=anual<?php echo ($filtro_zona?'&zona='.$filtro_zona:'').($ciclo_inicio?'&ciclo_inicio='.$ciclo_inicio:'').($ciclo_fin?'&ciclo_fin='.$ciclo_fin:'').($ciclo_inicio_2?'&ciclo_inicio_2='.$ciclo_inicio_2:'').($ciclo_fin_2?'&ciclo_fin_2='.$ciclo_fin_2:''); ?>" 
                   style="padding: 6px 15px; font-size: 0.85rem; font-weight: 600; border-radius: 20px; text-decoration: none; transition: 0.2s; 
                   <?php echo $tipo_comp === 'anual' ? 'background-color:#0d6efd; color:white; border: 1px solid #0d6efd;' : 'background-color:#fff; color:#495057; border: 1px solid #ced4da;'; ?>">
                    Anual
                </a>
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
                <input type="hidden" name="comp" value="<?php echo htmlspecialchars($tipo_comp); ?>">

                <div class="ea-form__group">
                    <label class="ea-form__label" for="zona"><span class="material-symbols-rounded">location_on</span> Zona</label>
                    <select id="zona" name="zona" class="ea-form__control" style="width: 120px;">
                        <option value="">TODAS</option>
                        <?php foreach($zonas_disponibles as $z): ?>
                            <option value="<?php echo $z; ?>" <?php echo ($filtro_zona != '' && (int)$filtro_zona === $z) ? 'selected' : ''; ?>>
                                <?php echo $z; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label"><span class="material-symbols-rounded">cycle</span> Rango 1 (Impar)</label>
                    <div class="ea-form__group--rango">
                        <select id="ciclo_inicio" name="ciclo_inicio" class="ea-form__control">
                            <option value=""></option>
                            <?php foreach($ciclos_disponibles as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($ciclo_inicio != '' && (int)$ciclo_inicio === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span>-</span>
                        <select id="ciclo_fin" name="ciclo_fin" class="ea-form__control">
                            <option value=""></option>
                            <?php foreach($ciclos_disponibles as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($ciclo_fin != '' && (int)$ciclo_fin === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label"><span class="material-symbols-rounded">add_circle</span> Rango 2 (Par)</label>
                    <div class="ea-form__group--rango">
                        <select id="ciclo_inicio_2" name="ciclo_inicio_2" class="ea-form__control">
                            <option value=""></option>
                            <?php foreach($ciclos_disponibles as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($ciclo_inicio_2 != '' && (int)$ciclo_inicio_2 === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span>-</span>
                        <select id="ciclo_fin_2" name="ciclo_fin_2" class="ea-form__control">
                            <option value=""></option>
                            <?php foreach($ciclos_disponibles as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($ciclo_fin_2 != '' && (int)$ciclo_fin_2 === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit" class="ea-btn ea-btn--primary">
                        <span class="material-symbols-rounded">search</span> Aplicar
                    </button>
                    <?php if($filtro_zona !== '' || $ciclo_inicio !== '' || $ciclo_inicio_2 !== ''): ?>
                        <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn ea-btn--danger">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="ea-card ea-table-card">
        <div class="ea-table-wrapper">
            <table class="ea-table">
                <thead>
                <tr>
                    <th colspan="37" class="ea-table__head-main" style="padding: 15px; text-align: center; font-size: 1rem; color: #333;">
                        <?php echo $titulo_reporte; ?>
                    </th>
                </tr>

                <tr class="ea-table__head-cols">
                    <th rowspan="2" class="ea-th-agencia" style="vertical-align: middle; background-color: #e9ecef; border-right: 2px solid #dee2e6;">ZONA</th>
                    <th rowspan="2" class="ea-th-agencia" style="vertical-align: middle; background-color: #e9ecef; border-right: 2px solid #dee2e6;">AGENCIA</th>

                    <?php foreach ($anomalias as $anomalia): ?>
                        <th colspan="4" class="b-left" style="text-align: center; background-color: #e9ecef; color: #333;">
                            <?php echo strtoupper(str_replace('_', ' ', $anomalia)); ?>
                        </th>
                    <?php endforeach; ?>

                    <th colspan="3" class="b-left" style="text-align: center; background-color: #343a40; color: white;">
                        DEFECTO (TOTAL)
                    </th>
                </tr>

                <tr class="ea-table__head-cols">
                    <?php foreach ($anomalias as $anomalia): ?>
                        <th class="th-sub b-left"><?php echo $th_actual; ?></th>
                        <th class="th-sub"><?php echo $th_bimestre; ?></th>
                        <th class="th-sub">DIF</th>
                        <th class="th-sub" style="color: #dc3545; background-color: #f8d7da;">REINC.</th>
                    <?php endforeach; ?>

                    <th class="th-sub b-left" style="background-color: #495057; color: white;"><?php echo $th_actual; ?></th>
                    <th class="th-sub" style="background-color: #495057; color: white;"><?php echo $th_bimestre; ?></th>
                    <th class="th-sub" style="background-color: #495057; color: white;">DIF</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($lista_zonas)): ?>
                    <tr><td colspan="37" style="text-align:center; padding: 30px;">No se encontraron datos de Zonas y Agencias para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php
                    $totales_columnas_actual = array_fill_keys($anomalias, 0);
                    $totales_columnas_bimestre = array_fill_keys($anomalias, 0);
                    $totales_columnas_reinc = array_fill_keys($anomalias, 0);
                    $gran_defecto_actual = 0;
                    $gran_defecto_bimestre = 0;

                    foreach ($lista_zonas as $zona):
                        $agencias_de_esta_zona = array_keys($combinaciones_existentes[$zona]);
                        sort($agencias_de_esta_zona);

                        $rowspan_zona = count($agencias_de_esta_zona);
                        $es_primera_agencia = true;

                        foreach ($agencias_de_esta_zona as $agencia):
                            $defecto_fila_actual = 0;
                            $defecto_fila_bimestre = 0;
                            ?>
                            <tr>
                                <?php if($es_primera_agencia): ?>
                                    <td rowspan="<?php echo $rowspan_zona; ?>" class="ea-td-agencia" style="border-right: 2px solid #dee2e6; vertical-align: middle; text-align: center; font-size: 1.1rem; background-color: #fdfdfd;">
                                        <?php echo $zona; ?>
                                    </td>
                                    <?php $es_primera_agencia = false; endif; ?>

                                <td class="ea-td-agencia" style="border-right: 2px solid #dee2e6;"><?php echo $agencia; ?></td>

                                <?php foreach ($anomalias as $anomalia):
                                    $val_actual = $resultados['actual'][$zona][$agencia][$anomalia];
                                    $val_bimestre = $resultados['bimestre'][$zona][$agencia][$anomalia];
                                    $diferencia = $val_actual - $val_bimestre;

                                    $defecto_fila_actual += $val_actual;
                                    $defecto_fila_bimestre += $val_bimestre;
                                    $totales_columnas_actual[$anomalia] += $val_actual;
                                    $totales_columnas_bimestre[$anomalia] += $val_bimestre;

                                    $clase_dif = $diferencia > 0 ? 'dif-pos' : ($diferencia < 0 ? 'dif-neg' : 'dif-zero');
                                    $signo = $diferencia > 0 ? '+' : '';
                                    
                                    // Reincidentes: se actualizarán via AJAX, solo generamos la celda con datos-id
                                    ?>

                                    <td class="b-left ea-td-num <?php echo $val_actual > 0 ? 'ea-td-num--val' : ''; ?>">
                                        <?php if ($val_actual > 0): ?>
                                            <a href="javascript:void(0)" class="ea-detail-trigger" 
                                               data-tabla="<?php echo $anomalia . $sufijos['actual']; ?>" 
                                               data-agencia="<?php echo $agencia; ?>" 
                                               data-zona="<?php echo $zona; ?>"
                                               data-ciclo_inicio="<?php echo htmlspecialchars($ciclo_inicio); ?>"
                                               data-ciclo_fin="<?php echo htmlspecialchars($ciclo_fin); ?>"
                                               data-ciclo_inicio_2="<?php echo htmlspecialchars($ciclo_inicio_2); ?>"
                                               data-ciclo_fin_2="<?php echo htmlspecialchars($ciclo_fin_2); ?>"
                                               data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ')'; ?>">
                                                <?php echo number_format($val_actual); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="ea-zero">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ea-td-num <?php echo $val_bimestre > 0 ? 'ea-td-num--val' : ''; ?>">
                                        <?php if ($val_bimestre > 0): ?>
                                            <a href="javascript:void(0)" class="ea-detail-trigger" 
                                               data-tabla="<?php echo $anomalia . $sufijos['bimestre']; ?>" 
                                               data-agencia="<?php echo $agencia; ?>" 
                                               data-zona="<?php echo $zona; ?>"
                                               data-ciclo_inicio="<?php echo htmlspecialchars($ciclo_inicio); ?>"
                                               data-ciclo_fin="<?php echo htmlspecialchars($ciclo_fin); ?>"
                                               data-ciclo_inicio_2="<?php echo htmlspecialchars($ciclo_inicio_2); ?>"
                                               data-ciclo_fin_2="<?php echo htmlspecialchars($ciclo_fin_2); ?>"
                                               data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ') ' . $th_bimestre; ?>">
                                                <?php echo number_format($val_bimestre); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="ea-zero">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ea-td-num td-dif <?php echo $clase_dif; ?>">
                                        <?php echo $diferencia !== 0 ? $signo . number_format($diferencia) : '-'; ?>
                                    </td>
                                    <td class="ea-td-num reinc-cell" 
                                        data-zona="<?php echo htmlspecialchars($zona); ?>"
                                        data-agencia="<?php echo htmlspecialchars($agencia); ?>"
                                        data-anomalia="<?php echo htmlspecialchars($anomalia); ?>"
                                        data-tabla="<?php echo $anomalia . $sufijos['actual']; ?>"
                                        data-tablacomp="<?php echo $anomalia . $sufijos['bimestre']; ?>"
                                        data-ciclo_inicio="<?php echo htmlspecialchars($ciclo_inicio); ?>"
                                        data-ciclo_fin="<?php echo htmlspecialchars($ciclo_fin); ?>"
                                        data-ciclo_inicio_2="<?php echo htmlspecialchars($ciclo_inicio_2); ?>"
                                        data-ciclo_fin_2="<?php echo htmlspecialchars($ciclo_fin_2); ?>"
                                        data-titulo="REINCIDENTES - <?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ')'; ?>"
                                        style="background-color: #fdf5f6;">
                                        <span class="reinc-valor" style="color:#ccc; font-size:0.85rem;">⋯</span>
                                    </td>

                                <?php endforeach; ?>

                                <?php
                                $dif_defecto = $defecto_fila_actual - $defecto_fila_bimestre;
                                $clase_dif_defecto = $dif_defecto > 0 ? 'dif-pos' : ($dif_defecto < 0 ? 'dif-neg' : 'dif-zero');
                                $signo_defecto = $dif_defecto > 0 ? '+' : '';
                                ?>
                                <td class="b-left ea-td-defecto" style="background-color: #f8f9fa;"><?php echo number_format($defecto_fila_actual); ?></td>
                                <td class="ea-td-defecto" style="background-color: #f8f9fa;"><?php echo number_format($defecto_fila_bimestre); ?></td>
                                <td class="ea-td-defecto <?php echo $clase_dif_defecto; ?>" style="background-color: #f8f9fa;">
                                    <?php echo $dif_defecto !== 0 ? $signo_defecto . number_format($dif_defecto) : '-'; ?>
                                </td>
                            </tr>
                            <?php
                            $gran_defecto_actual += $defecto_fila_actual;
                            $gran_defecto_bimestre += $defecto_fila_bimestre;
                        endforeach;
                    endforeach;
                    ?>

                    <tr class="ea-tr-total">
                        <td colspan="2" class="ea-td-agencia" style="border-right: 2px solid #dee2e6; text-align: center;">TOTAL GLOBAL</td>

                        <?php foreach ($anomalias as $anomalia):
                            $tot_act = $totales_columnas_actual[$anomalia];
                            $tot_bim = $totales_columnas_bimestre[$anomalia];
                            $tot_dif = $tot_act - $tot_bim;
                            $clase_tot_dif = $tot_dif > 0 ? 'dif-pos' : ($tot_dif < 0 ? 'dif-neg' : 'dif-zero');
                            $signo_tot = $tot_dif > 0 ? '+' : '';
                            ?>
                            <td class="b-left ea-td-num"><?php echo number_format($tot_act); ?></td>
                            <td class="ea-td-num"><?php echo number_format($tot_bim); ?></td>
                            <td class="ea-td-num td-dif <?php echo $clase_tot_dif; ?>">
                                <?php echo $tot_dif !== 0 ? $signo_tot . number_format($tot_dif) : '-'; ?>
                            </td>
                            <td class="ea-td-num reinc-total" data-anomalia="<?php echo htmlspecialchars($anomalia); ?>" style="background-color: #f8d7da; color: #842029; font-weight: bold;">
                                <span class="reinc-total-valor" style="color:#ccc;">⋯</span>
                            </td>
                        <?php endforeach; ?>

                        <?php
                        $gran_dif_defecto = $gran_defecto_actual - $gran_defecto_bimestre;
                        $clase_gran_dif = $gran_dif_defecto > 0 ? 'dif-pos' : ($gran_dif_defecto < 0 ? 'dif-neg' : 'dif-zero');
                        $signo_gran = $gran_dif_defecto > 0 ? '+' : '';
                        ?>
                        <td class="b-left ea-td-defecto" style="background-color: #e9ecef;"><?php echo number_format($gran_defecto_actual); ?></td>
                        <td class="ea-td-defecto" style="background-color: #e9ecef;"><?php echo number_format($gran_defecto_bimestre); ?></td>
                        <td class="ea-td-defecto <?php echo $clase_gran_dif; ?>" style="background-color: #e9ecef;">
                            <?php echo $gran_dif_defecto !== 0 ? $signo_gran . number_format($gran_dif_defecto) : '-'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal para detalles -->
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
            <div style="text-align: center; padding: 40px;">
                <p>Cargando detalles...</p>
            </div>
        </div>
    </div>
</div>

<style>
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
        max-width: 1200px;
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
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
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
                const tablacomp = this.dataset.tablacomp || '';
                const modo = this.dataset.modo || 'normal';
                const agencia = this.dataset.agencia;
                const zona = this.dataset.zona;
                const c_i  = this.dataset.ciclo_inicio;
                const c_f  = this.dataset.ciclo_fin;
                const c_i2 = this.dataset.ciclo_inicio_2;
                const c_f2 = this.dataset.ciclo_fin_2;
                const titulo = this.dataset.titulo;

                modalTitle.textContent = "Detalle: " + titulo;
                modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Consultando registros...</p></div>';
                modal.style.display = 'block';

                fetch(`get_detalle_anomalia.php?tabla=${tabla}&tablacomp=${tablacomp}&modo=${modo}&agencia=${agencia}&zona=${zona}&ciclo_inicio=${c_i}&ciclo_fin=${c_f}&ciclo_inicio_2=${c_i2}&ciclo_fin_2=${c_f2}`)
                    .then(response => response.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                    })
                    .catch(err => {
                        modalBody.innerHTML = '<p style="color:red;">Error al cargar los datos.</p>';
                    });
            });
        });

        closeBtn.onclick = () => modal.style.display = 'none';
        window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; }
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
        const doc = new jsPDF('l', 'pt', 'a4');

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

    // ─── CARGA ASÍNCRONA DE REINCIDENTES ─────────────────────────────────────
    // Usa AbortController para limitar el tiempo de espera y cancelar
    // cualquier request anterior antes de iniciar uno nuevo.
    // ─────────────────────────────────────────────────────────────────────────
    let reincController = null; // guarda el controlador activo para poder cancelarlo

    function cargarReincidentes() {
        // Cancelar cualquier fetch anterior que siga corriendo
        if (reincController) {
            reincController.abort();
        }
        reincController = new AbortController();
        const signal = reincController.signal;

        // Timeout de 35 segundos para no colgar el navegador
        const timeoutId = setTimeout(() => reincController.abort(), 35000);

        const params = new URLSearchParams(window.location.search);
        const url = 'get_reincidentes_zona.php?' + params.toString();

        // Poner spinners en todas las celdas reinc mientras carga
        document.querySelectorAll('.reinc-valor').forEach(s => {
            s.textContent = '⋯'; s.style.color = '#ccc'; s.style.fontSize = '0.85rem';
        });
        document.querySelectorAll('.reinc-total-valor').forEach(s => {
            s.textContent = '⋯'; s.style.color = '#ccc';
        });

        fetch(url, { signal })
            .then(r => {
                clearTimeout(timeoutId);
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(json => {
                if (!json.ok) throw new Error('Respuesta inválida');

                const data = json.data;
                const totalesPorAnomalia = {};

                document.querySelectorAll('.reinc-cell').forEach(td => {
                    const zona     = td.dataset.zona;
                    const agencia  = td.dataset.agencia;
                    const anomalia = td.dataset.anomalia;
                    const tabla    = td.dataset.tabla;
                    const tablacomp= td.dataset.tablacomp;
                    const titulo   = td.dataset.titulo;
                    const ci       = td.dataset.ciclo_inicio;
                    const cf       = td.dataset.ciclo_fin;
                    const ci2      = td.dataset.ciclo_inicio_2;
                    const cf2      = td.dataset.ciclo_fin_2;

                    const val = (data[zona] && data[zona][agencia] && data[zona][agencia][anomalia])
                                 ? parseInt(data[zona][agencia][anomalia]) : 0;

                    totalesPorAnomalia[anomalia] = (totalesPorAnomalia[anomalia] || 0) + val;

                    const span = td.querySelector('.reinc-valor');
                    if (val > 0) {
                        td.style.fontWeight = '600';
                        td.style.color = '#dc3545';
                        const link = document.createElement('a');
                        link.href = 'javascript:void(0)';
                        link.className = 'ea-detail-trigger';
                        link.style.color = '#dc3545';
                        link.dataset.modo       = 'reincidente';
                        link.dataset.tabla      = tabla;
                        link.dataset.tablacomp  = tablacomp;
                        link.dataset.agencia    = agencia;
                        link.dataset.zona       = zona;
                        link.dataset.ciclo_inicio   = ci;
                        link.dataset.ciclo_fin      = cf;
                        link.dataset.ciclo_inicio_2 = ci2;
                        link.dataset.ciclo_fin_2    = cf2;
                        link.dataset.titulo     = titulo;
                        link.textContent        = val.toLocaleString();
                        link.addEventListener('click', abrirModalDesdeLink);
                        td.innerHTML = '';
                        td.appendChild(link);
                    } else {
                        if (span) {
                            span.style.color    = '#e4a1a8';
                            span.style.fontSize = '';
                            span.textContent    = '0';
                        }
                    }
                });

                // Actualizar totales
                document.querySelectorAll('.reinc-total').forEach(td => {
                    const anomalia = td.dataset.anomalia;
                    const total    = totalesPorAnomalia[anomalia] || 0;
                    const span     = td.querySelector('.reinc-total-valor');
                    if (span) { span.style.color = '#842029'; span.textContent = total.toLocaleString(); }
                });
            })
            .catch(err => {
                clearTimeout(timeoutId);
                if (err.name === 'AbortError') {
                    // Cancelado a propósito (timeout o nuevo filtro) — no mostrar error
                    return;
                }
                // Error real: mostrar guión
                document.querySelectorAll('.reinc-valor').forEach(s => {
                    s.textContent = '—'; s.style.color = '#adb5bd'; s.style.fontSize = '';
                });
                document.querySelectorAll('.reinc-total-valor').forEach(s => {
                    s.textContent = '—'; s.style.color = '#adb5bd';
                });
            });
    }

    // Lanzar al cargar la página
    cargarReincidentes();

    // Cancelar el fetch en curso cuando el usuario aplica un nuevo filtro
    // (evita que el fetch anterior cuelgue la navegación)
    document.querySelector('form[method="GET"]')?.addEventListener('submit', () => {
        if (reincController) reincController.abort();
    });

    function abrirModalDesdeLink() {
        const modal     = document.getElementById('ea-modal');
        const modalBody = document.getElementById('ea-modal-body');
        const modalTitle= document.getElementById('ea-modal-title');
        const tabla      = this.dataset.tabla;
        const tablacomp  = this.dataset.tablacomp || '';
        const modo       = this.dataset.modo || 'normal';
        const agencia    = this.dataset.agencia;
        const zona       = this.dataset.zona;
        const c_i        = this.dataset.ciclo_inicio;
        const c_f        = this.dataset.ciclo_fin;
        const c_i2       = this.dataset.ciclo_inicio_2;
        const c_f2       = this.dataset.ciclo_fin_2;
        const titulo     = this.dataset.titulo;

        modalTitle.textContent = 'Detalle: ' + titulo;
        modalBody.innerHTML = '<div style="text-align:center;padding:40px;"><p>Consultando registros...</p></div>';
        modal.style.display = 'block';

        fetch(`get_detalle_anomalia.php?tabla=${tabla}&tablacomp=${tablacomp}&modo=${modo}&agencia=${agencia}&zona=${zona}&ciclo_inicio=${c_i}&ciclo_fin=${c_f}&ciclo_inicio_2=${c_i2}&ciclo_fin_2=${c_f2}`)
            .then(r => r.text())
            .then(html => { modalBody.innerHTML = html; })
            .catch(() => { modalBody.innerHTML = '<p style="color:red;">Error al cargar.</p>'; });
    }
</script>
</body>
</html>
