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

$filtro_zona    = isset($_GET['zona'])  ? trim($_GET['zona'])  : '';
$filtro_ciclo   = [];
if (isset($_GET['ciclo'])) {
    if (is_array($_GET['ciclo'])) {
        $filtro_ciclo = $_GET['ciclo'];
    } else {
        $filtro_ciclo = array_filter(explode(',', (string)$_GET['ciclo']), 'strlen');
    }
}

// Filtro independiente para Cargas Directas
$filtro_ciclo_cd = [];
$cd_is_default = !isset($_GET['ciclo_cd']); // true = primera carga, activado por defecto
if (isset($_GET['ciclo_cd'])) {
    if (is_array($_GET['ciclo_cd'])) {
        $filtro_ciclo_cd = $_GET['ciclo_cd'];
    } else {
        $filtro_ciclo_cd = array_filter(explode(',', (string)$_GET['ciclo_cd']), 'strlen');
    }
}

// ICF TOTAL: Ignora filtros de ciclo y toma todos los ciclos (pares e impares)
$icf_total = isset($_GET['icf_total']) && $_GET['icf_total'] === '1';
if ($icf_total) {
    $filtro_ciclo = [];
    $filtro_ciclo_cd = [];
}


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

// Función para formatear rango de ciclos
$formatear_rango = function($arr) {
    return formatearRangoCiclos($arr);
};

// Título dinámico: "RESULTADO ZONA X -CICLO (bim) Y (mens)- MES AÑO"
$zona_titulo = ($filtro_zona !== '') ? $filtro_zona : 'TODAS';

// Construir sección de ciclos
$ciclo_seccion = '';
if ($icf_total) {
    $ciclo_seccion = " (ICF TOTAL)";
} elseif (!empty($filtro_ciclo)) {
    $ciclos_int = array_map('intval', $filtro_ciclo);
    $bimestrales = [];
    $mensuales = [];
    foreach ($ciclos_int as $c) {
        if ($c <= 61) {
            $bimestrales[] = $c;
        } else {
            $mensuales[] = $c;
        }
    }
    
    $partes_ciclo = [];
    if (!empty($bimestrales)) {
        $partes_ciclo[] = $formatear_rango($bimestrales);
    }
    if (!empty($mensuales)) {
        $partes_ciclo[] = $formatear_rango($mensuales);
    }
    
    $ciclo_seccion = " -CICLO " . implode(" Y ", $partes_ciclo) . "-";
}

// Agregar info de ciclos de cargas directas si están filtrados
if (!$icf_total && !empty($filtro_ciclo_cd) && !$cd_is_default) {
    $ciclos_cd_int = array_map('intval', $filtro_ciclo_cd);
    sort($ciclos_cd_int);
    $ciclo_seccion .= " | C.D. CICLOS " . $formatear_rango($ciclos_cd_int);
}

$titulo_reporte = "RESULTADO ZONA" . $ciclo_seccion . " " . obtenerNombreMes($p1_mes) . " $p1_anio";


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
$ciclos_actuales = [];
$ciclos_comparacion = [];
$tablas_involucradas = [];

// Filtros disponibles (desde tablas del período actual y del de comparación)
foreach ($anomalias as $anomalia) {
    $nombre_tabla_act = $anomalia . $sufijos['actual'];
    if (isset($todas_las_tablas[$nombre_tabla_act])) {
        $tablas_involucradas[] = $nombre_tabla_act;
        try {
            $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla_act` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
            $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_act` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_actuales[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
    
    $nombre_tabla_comp = $anomalia . $sufijos['bimestre'];
    if (isset($todas_las_tablas[$nombre_tabla_comp])) {
        try {
            $stmt_cc = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla_comp` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_cc->fetch(PDO::FETCH_ASSOC)) { $ciclos_comparacion[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
}
$zonas_disponibles  = array_unique($zonas_disponibles);  sort($zonas_disponibles);
$ciclos_actuales = array_unique($ciclos_actuales);
$ciclos_comparacion = array_unique($ciclos_comparacion);
$ciclos_disponibles = array_unique(array_merge($ciclos_actuales, $ciclos_comparacion)); sort($ciclos_disponibles);

// Ciclos exclusivos de cargas_directas (para el filtro independiente)
$ciclos_cd_disponibles = [];
$tabla_cd_actual = 'cargas_directas' . $sufijos['actual'];
if (isset($todas_las_tablas[$tabla_cd_actual])) {
    try {
        $stmt_cd = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$tabla_cd_actual` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
        while($rcd = $stmt_cd->fetch(PDO::FETCH_ASSOC)) { $ciclos_cd_disponibles[] = (int)$rcd['c']; }
    } catch (PDOException $e) {}
}
sort($ciclos_cd_disponibles);

$is_even_month = ($p1_mes % 2 === 0);
$ciclos_bimestrales = [];
$ciclos_mensuales = [];

foreach ($ciclos_disponibles as $c) {
    if ($c >= 1 && $c <= 61) {
        if ($icf_total) {
            $ciclos_bimestrales[] = $c;
        } elseif ($is_even_month && $c % 2 === 0) {
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
function buildCicloWhere($filtro_ciclo, &$params, $alias = '') {
    $col = $alias ? "`$alias`.`Ciclo`" : '`Ciclo`';
    if (!empty($filtro_ciclo)) {
        $placeholders = [];
        foreach ($filtro_ciclo as $c) {
            $params[] = (int)$c;
            $placeholders[] = '?';
        }
        $ph = implode(',', $placeholders);
        return " AND CAST(TRIM($col) AS UNSIGNED) IN ($ph)";
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
            // Cargas directas SIEMPRE usa su propio filtro independiente
            // y SIEMPRE filtra solo zona 1
            if ($anomalia === 'cargas_directas') {
                // Forzar zona 1 para cargas directas, independiente del filtro general
                $where_sql = "WHERE 1=1 AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
                $parametros_sql = [1];
                if (!empty($filtro_ciclo_cd)) {
                    $where_sql .= buildCicloWhere($filtro_ciclo_cd, $parametros_sql);
                }
                // Si está vacío (default o sin selección): no filtra ciclos → trae todo de zona 1
            } else {
                $where_sql .= buildCicloWhere($filtro_ciclo, $parametros_sql);
            }

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

        /* El wrapper se ajusta al ancho REAL de la tabla, no al 100% del contenedor */
        .ea-table-wrapper { 
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            background-color: #fff;
            border: 1px solid #000;
        }
        .ea-table { 
            width: auto;
            table-layout: auto !important; 
            border-collapse: collapse; 
            font-family: Arial, Calibri, sans-serif; 
        }

        .ea-td-agencia, .ea-th-agencia { 
            white-space: nowrap !important; 
            overflow: visible !important; 
            text-overflow: clip !important;
            padding: 4px 8px !important;
            width: auto !important;
            min-width: 80px;
            text-align: center !important;
            font-size: 14px !important;
        }
        th.ea-th-agencia:nth-child(1), 
        td.ea-td-agencia:nth-child(1) {
            min-width: 100px !important;
        }

        .b-left { border-left: 1px solid #000 !important; }

        /* ── Título general ── */
        .ea-table__head-main {
            background-color: #1A7A5E;
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            padding: 8px;
            border: 1px solid #000;
            font-family: Arial, sans-serif;
            text-transform: uppercase;
        }

        /* Encabezados anomalías: intercalados con contraste claro */
        .th-group {
            background-color: #AFD5F3 !important;
            color: #000 !important;
            font-weight: bold !important;
            text-align: center !important;
            font-size: 13px !important;
            padding: 2px 4px !important;
            border: 1px solid #000 !important;
            white-space: nowrap !important;
        }
        .th-group-alt {
            background-color: #AADEC0 !important;
            color: #000 !important;
            font-weight: bold !important;
            text-align: center !important;
            font-size: 13px !important;
            padding: 2px 4px !important;
            border: 1px solid #000 !important;
            white-space: nowrap !important;
        }

        /* Espacio extra para grupos específicos con etiquetas largas */
        th[data-anom-group="cancelaciones"],
        th[data-anom-group="correcciones_de_lecturas"],
        th[data-anom-group="cargas_directas"] {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }

        /* Sub-encabezados (meses, DIF): FONDO BLANCO */
        .th-sub {
            background-color: #fff !important;
            color: #000 !important;
            font-weight: bold !important;
            font-size: 13px !important;
            text-align: center !important;
            padding: 1px 2px !important;
            border: 1px solid #000 !important;
            width: 3.33% !important;
        }

        /* DEFECTOS encabezados: azul fuerte oscuro con letras blancas */
        .th-defecto-group {
            background-color: #104861 !important;
            color: #fff !important;
            font-weight: bold !important;
            text-align: center !important;
            font-size: 13px !important;
            padding: 2px 4px !important;
            border: 1px solid #000 !important;
        }
        .th-defecto-sub {
            background-color: #104861 !important;
            color: #fff !important;
            font-weight: bold !important;
            font-size: 13px !important;
            text-align: center !important;
            padding: 4px 2px !important;
            border: 1px solid #000 !important;
            width: 3.33% !important;
        }
        .th-defecto-evol {
            background-color: #104861 !important;
            color: #fff !important;
            font-weight: bold !important;
            font-size: 13px !important;
            text-align: center !important;
            border: 1px solid #000 !important;
        }

        /* AGENCIA encabezado */
        .th-zona-ag {
            background-color: #fff !important;
            color: #000 !important;
            font-weight: bold !important;
            vertical-align: middle !important;
            border: 1px solid #000 !important;
            font-size: 14px !important;
            width: 10% !important;
        }

        /* ── Celdas de datos: FONDO BLANCO, texto NEGRO ── */
        .ea-table tbody tr { border-bottom: 1px solid #000; height: 1px !important; }
        .ea-table tbody td { height: 1px !important; padding-top: 0 !important; padding-bottom: 0 !important; }

        .ea-td-num {
            text-align: center; padding: 2px 8px !important;
            min-width: 32px;
            font-variant-numeric: tabular-nums;
            font-size: 16px; color: #000;
            background-color: #fff;
            border: 1px solid #000;
            min-width: 45px;
            width: 3.33% !important;
            font-weight: 500 !important;
            line-height: 0.9 !important;
        }
        .ea-td-num--val { font-weight: 500 !important; color: #000; }
        .ea-td-num a { 
            font-weight: 500 !important; 
            text-decoration: none !important; 
            color: #000 !important; 
        }

        .ea-td-agencia {
            background-color: #fff !important;
            color: #000 !important;
            font-weight: normal !important;
            font-size: 14px !important;
            border: 1px solid #000 !important;
            text-align: center !important;
            width: 10% !important;
        }

        /* DIF: icono izquierda + número */
        .td-dif { 
            font-weight: bold !important; 
            font-size: 15px; color: #000 !important; 
            min-width: 50px;
            width: 3.2% !important;
            white-space: nowrap;
            text-align: left !important;
            padding: 0 2px 0 4px !important;
            line-height: 0.9 !important;
        }
        .dif-content {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            width: auto;
        }
        .dif-icon-svg {
            flex: 0 0 16px;
            width: 16px !important;
            height: 10px !important;
            margin-right: 2px;
            flex-shrink: 0;
        }
        .dif-content span {
            display: inline-block;
            text-align: left;
            font-variant-numeric: tabular-nums;
        }
        /* Compensación de espacio para números sin signo menos para alinear cifras */
        .dif-pos .dif-content span,
        .dif-zero .dif-content span {
            margin-left: 6px; 
        }
        .dif-pos { color: #000 !important; }
        .dif-neg { color: #000 !important; }
        .dif-zero { color: #000 !important; }
        /* SVG icon — control total de tamaño y forma */
        .dif-icon-svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }

        /* DEFECTOS celdas de datos */
        .ea-td-defecto {
            text-align: center; padding: 0 3px !important;
            font-weight: 500 !important; font-variant-numeric: tabular-nums;
            font-size: 16px; color: #000;
            background-color: #fff;
            border: 1px solid #000;
            min-width: 45px;
            width: 3.33% !important;
            line-height: 0.9 !important;
        }
        .ea-td-defecto.td-dif { font-weight: bold !important; background-color: #E3F2FD !important; }

        /* TOTAL row: celdas de meses BLANCAS, solo DIF se colorea. */
        .ea-tr-total td {
            background-color: #fff !important;
            color: #000 !important;
            border-top: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            border-left: 1px solid #000 !important;
            border-right: 1px solid #000 !important;
            font-size: 15px !important;
            font-weight: 700 !important;
        }
        .ea-tr-total .ea-td-agencia { font-weight: bold !important; }
        
        /* DIF en TOTAL: verde claro si negativo, amarillo si positivo */
        .ea-tr-total .td-dif.dif-neg { background-color: #C6E0B4 !important; }
        .ea-tr-total .td-dif.dif-pos { background-color: #FFFF00 !important; }

        .ea-zero { color: #000; font-size: 15px; font-weight: 600; }

        /* Columna % Evol */
        .ea-td-evol {
            text-align: center; padding: 1px 3px;
            font-size: 13px;
            border: 1px solid #000;
            min-width: 48px; width: 3% !important;
            font-weight: 700 !important;
            color: #000 !important;
        }
        .th-evol {
            background-color: #fff !important;
            color: #000 !important; font-weight: bold !important;
            font-size: 12px !important; text-align: center !important;
            padding: 4px 2px !important; border: 1px solid #000 !important;
            width: 3% !important;
        }
        .th-defecto-evol {
            background-color: #104861 !important;
            color: #fff !important; font-weight: bold !important;
            font-size: 12px !important; text-align: center !important;
            padding: 4px 2px !important; border: 1px solid #000 !important;
            width: 3% !important;
        }

        /* Columnas extras ocultas por defecto */
        .reinc-col  { display: none; }
        .evol-col   { display: none; }
        .extras-reinc-visible .reinc-col { display: table-cell; }
        .extras-evol-visible  .evol-col  { display: table-cell; }
        .reinc-col.th-sub, .reinc-col.th-group, .reinc-col.th-group-alt { background-color: #fce4ec !important; color: #000 !important; }
        td.reinc-col { background-color: #fdf5f6; }
        .ea-tr-total td.reinc-col { background-color: #f8d7da !important; color: #000 !important; }

        /* Extras: Reincidencias y Evolución */
        .btn-extras-wrapper {
            position: relative;
            display: inline-block;
        }
        .btn-extras-toggle {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 6px; border: 1px solid #f5c790;
            background: #fde8c8; color: #7d4000; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-extras-toggle:hover { background: #fbd5a0; border-color: #f0a94a; }
        .btn-extras-toggle.active { background: #f0883e; color: #fff; border-color: #f0883e; }
        .extras-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%; right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 10px 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            z-index: 50; margin-top: 5px;
        }
        .extras-dropdown-menu.open { display: block; }
        .extras-dropdown-menu label {
            display: flex; align-items: center; gap: 7px;
            font-size: 0.88rem; cursor: pointer;
            padding: 6px 8px; border-radius: 4px; transition: background 0.15s;
        }
        .extras-dropdown-menu label:hover { background: #f1f3f5; }
        .extras-dropdown-menu .extras-badge {
            display: inline-block; width: 10px; height: 10px;
            border-radius: 50%; margin-left: auto;
        }

        /* Botón y dropdown de Anomalías */
        .btn-anom-wrapper {
            position: relative;
            display: inline-block;
        }
        .btn-anom-toggle {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 6px; border: 1px solid #a3d9a5;
            background: #d4edda; color: #2d6a4f; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-anom-toggle:hover { background: #c1e6c9; border-color: #82c785; }
        .btn-anom-toggle.active { background: #56ab5e; color: #fff; border-color: #56ab5e; }
        .anom-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            min-width: 260px;
            max-height: 420px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            z-index: 50;
            margin-top: 5px;
        }
        .anom-dropdown-menu.open { display: block; }
        .anom-dropdown-menu .anom-section-title {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            color: #6c757d; letter-spacing: 0.05em;
            padding: 4px 6px 2px 6px; margin-top: 4px;
        }
        .anom-dropdown-menu label {
            display: flex; align-items: center; gap: 7px;
            font-size: 0.85rem; cursor: pointer;
            padding: 5px 8px; border-radius: 4px; transition: background 0.15s;
        }
        .anom-dropdown-menu label:hover { background: #f1f3f5; }
        .anom-dropdown-menu .anom-select-all {
            background: #f8f9fa; margin-bottom: 4px; font-weight: 600;
        }
        .anom-dropdown-menu .anom-cd-extra {
            background: #eaf6ec;
            border-left: 3px solid #56ab5e;
            margin-left: 20px;
            font-size: 0.8rem;
            color: #2d6a4f;
            padding: 4px 8px;
        }
        .anom-dropdown-menu .anom-cd-extra:hover { background: #d4edda; }

        /* ========= ESTILOS PARA IMPRESIÓN PDF ========= */
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
            /* Ocultar elementos de UI que no tienen sentido en el PDF */
            header, 
            .ea-filters-card, 
            .ea-btn-print, 
            .ea-btn-excel,
            .btn-extras-wrapper,
            .btn-anom-wrapper,
            .ea-btn-back,
            .ea-btn-switch-report,
            .ea-modal,
            .ea-page-overlay,
            .ea-spinner-container,
            #ea-modal {
                display: none !important;
            }
            .ea-table-wrapper {
                overflow: visible !important;
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
            /* Asegurar que la tabla no sea restringida y mantenga proporciones web */
            .ea-table {
                width: max-content !important;
            }
            /* Forzar el display del header si hubiera configuraciones globales que lo colapsan */
            .ea-page-header {
                display: flex !important;
                justify-content: center !important;
                margin-bottom: 20px;
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
            <a href="preparar_analisis.php?mes_objetivo=<?php echo $p1_mes; ?>&anio_objetivo=<?php echo $p1_anio; ?>" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span> Volver
            </a>
            <div>
                <h1 class="ea-page-title">Reporte Unificado: Nivel Zona</h1>
                <p class="ea-page-subtitle">Comparativa: <?php echo obtenerNombreMes($p1_mes).' '.$p1_anio; ?> vs <?php echo $lbl_comp; ?></p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button onclick="exportarExcelZona()" class="ea-btn-excel" style="background-color: #fff; color: #2E7D32; border: 1.5px solid #2E7D32; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#E8F5E9';this.style.boxShadow='0 2px 8px rgba(46,125,50,0.15)'" onmouseout="this.style.backgroundColor='#fff';this.style.boxShadow='none'">
                <span class="material-symbols-rounded" style="font-size: 20px;">download</span> Excel
            </button>
            <button onclick="window.print()" class="ea-btn-print">
                <span class="material-symbols-rounded">print</span> Imprimir
            </button>
            <a href="ejecutar_analisis.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d6eaf8; color: #1b4f72; border: 1px solid #aed6f1; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#aed6f1'" onmouseout="this.style.backgroundColor='#d6eaf8'">
                <span class="material-symbols-rounded" style="font-size: 18px;">business</span>
                Ir a Nivel Agencia
            </a>
            <?php 
                $params_est = "?m=$p1_mes&a=$p1_anio";
                if ($filtro_zona !== '') $params_est .= "&zona=" . urlencode($filtro_zona);
                if (!empty($filtro_ciclo)) $params_est .= "&ciclo=" . urlencode(implode(",", $filtro_ciclo));
                $params_est .= "&origen=zona";
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
                <?php if($icf_total || $filtro_zona !== '' || !empty($filtro_ciclo) || !empty($filtro_ciclo_cd)): ?>
                    <div class="ea-filter-tags" style="margin-left: 10px;">
                        <?php if($icf_total): ?>
                            <span class="ea-tag" style="background-color: #e3f2fd; color: #0d47a1; border-color: #90caf9;">ICF TOTAL (Todos los ciclos)</span>
                        <?php endif; ?>
                        <?php if($filtro_zona !== ''): ?>
                            <span class="ea-tag">Zona: <?php echo htmlspecialchars($filtro_zona); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($filtro_ciclo)): ?>
                            <span class="ea-tag">Ciclo: <?php echo htmlspecialchars(formatearRangoCiclos($filtro_ciclo)); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($filtro_ciclo_cd) && !$cd_is_default): ?>
                            <span class="ea-tag" style="background-color: #d4edda; color: #2d6a4f;">C.D. Ciclo: <?php echo htmlspecialchars(formatearRangoCiclos($filtro_ciclo_cd)); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            
            <div class="ea-comp-switch">
                <?php 
                    $extra_params_nav = ($filtro_zona?'&zona='.$filtro_zona:'').(!empty($filtro_ciclo)?'&ciclo='.implode(',', $filtro_ciclo):'').(!empty($filtro_ciclo_cd)?'&ciclo_cd='.implode(',', $filtro_ciclo_cd):'').($icf_total?'&icf_total=1':'');
                ?>

                <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&comp=bimestre<?php echo $extra_params_nav; ?>" 
                   class="ea-comp-btn <?php echo $tipo_comp === 'bimestre' ? 'ea-comp-btn--active' : 'ea-comp-btn--inactive'; ?>">
                    vs Bimestral
                </a>
                <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>&comp=anual<?php echo $extra_params_nav; ?>" 
                   class="ea-comp-btn <?php echo $tipo_comp === 'anual' ? 'ea-comp-btn--active' : 'ea-comp-btn--inactive'; ?>">
                    vs Anual
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
                <input type="hidden" name="filtrado_aplicado" value="1">
                <input type="hidden" name="m" value="<?php echo $p1_mes; ?>">
                <input type="hidden" name="a" value="<?php echo $p1_anio; ?>">
                <input type="hidden" name="comp" value="<?php echo htmlspecialchars($tipo_comp); ?>">
                <?php if($icf_total): ?>
                    <input type="hidden" name="icf_total" value="1">
                <?php endif; ?>

                <?php // Hidden inputs para preservar el filtro CD al enviar el form general ?>
                <?php if (!$cd_is_default && !empty($filtro_ciclo_cd)): ?>
                    <?php foreach($filtro_ciclo_cd as $cd_val): ?>
                        <input type="hidden" name="ciclo_cd[]" value="<?php echo htmlspecialchars($cd_val); ?>">
                    <?php endforeach; ?>
                <?php endif; ?>

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
                        Ciclos Bim. (1-40)
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
                    <?php if($icf_total || $filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                        <?php 
                            // Limpiar general preserva el estado de CD
                            $limpiar_url = "?m=$p1_mes&a=$p1_anio&clear_filters=1";
                            if (!$cd_is_default && !empty($filtro_ciclo_cd)) {
                                $limpiar_url .= '&ciclo_cd=' . implode(',', $filtro_ciclo_cd);
                            }
                        ?>
                        <a href="<?php echo $limpiar_url; ?>" class="ea-btn ea-btn--danger">Limpiar</a>
                    <?php endif; ?>

                </div>
                <div style="display: flex; align-items: flex-end; margin-left: auto; gap: 8px;">
                    <div class="btn-extras-wrapper" id="extras-wrapper">
                        <button type="button" class="btn-extras-toggle" id="btn-extras-toggle">
                            <span class="material-symbols-rounded" style="font-size: 17px;">auto_awesome</span>
                            Extras
                        </button>
                        <div class="extras-dropdown-menu" id="extras-dropdown">
                            <label id="icf-total-label">
                                <input type="checkbox" id="chk-icf-total" <?php echo $icf_total ? 'checked' : ''; ?> />
                                <span>ICF Total</span>
                                <span class="extras-badge" style="background:#e3f2fd; border:1px solid #90caf9;"></span>
                            </label>
                            <label id="extras-reinc-label">
                                <input type="checkbox" id="chk-extras-reinc" />
                                <span>Reincidencias</span>
                                <span class="extras-badge" style="background:#f8d7da; border:1px solid #f1aeb5;"></span>
                            </label>
                            <label id="extras-evol-label">
                                <input type="checkbox" id="chk-extras-evol" />
                                <span>% Evolución</span>
                                <span class="extras-badge" style="background:#FFF3CD; border:1px solid #f5c790;"></span>
                            </label>
                        </div>


                    </div>
                    <div class="btn-anom-wrapper" id="anom-wrapper">
                        <button type="button" class="btn-anom-toggle active" id="btn-anom-toggle">
                            <span class="material-symbols-rounded" style="font-size: 17px;">electric_bolt</span>
                            Anomalías
                            <span id="anom-desel-badge" style="display:none; background: rgba(255,255,255,0.35); padding: 1px 6px; border-radius: 10px; font-size: 0.75rem; margin-left: 2px;"></span>
                        </button>
                        <div class="anom-dropdown-menu" id="anom-dropdown">
                            <label class="anom-select-all">
                                <input type="checkbox" id="anom-select-all" checked /> <strong>(Seleccionar todo)</strong>
                            </label>
                            <hr style="margin: 4px 0; border-color: #eee;">
                            <?php
                            $anomalias_labels_ui = [
                                'cancelaciones'           => 'Cancelaciones',
                                'estimaciones'            => 'Estimaciones',
                                'consumos_cero'           => 'Consumo Cero',
                                'servicios_sin_medicion'  => 'Sin Medición',
                                'correcciones_de_lecturas'=> 'Corrección de Lec.',
                                'anomalias_pendientes'    => 'Anomalías Pendientes',
                                'sin_facturar'            => 'Sin Facturar',
                                'cargas_directas'         => 'Cargas Directas',
                            ];
                            foreach ($anomalias as $anom_key):
                                $anom_label = $anomalias_labels_ui[$anom_key] ?? ucwords(str_replace('_',' ',$anom_key));
                            ?>
                                <label>
                                    <input type="checkbox" class="chk-anom" data-anom="<?php echo $anom_key; ?>" checked />
                                    <?php echo htmlspecialchars($anom_label); ?>
                                </label>

                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    // Helper: devuelve inline style de bg/color para la celda % Evol según el valor
    function evolStyle($evol, $max) {
        if ($evol === null) return 'background:#fff; color:#1e2b27;';
        $v = (float)$evol;
        if (abs($v) < 0.01) return 'background:#fff; color:#1e2b27;';
        if ($max <= 0) return 'background:#fff; color:#1e2b27;';
 
        $ratio = abs($v) / $max;
        if ($v > 0) {
            // Positivos (Incremento de Anomalías) - Escala de amarillo a un rojo ligeramente más intenso (#E57373)
            if ($ratio <= 0.10) return 'background: #FFFFDF; color: #1e2b27;';
            if ($ratio <= 0.20) return 'background: #FFFFB8; color: #1e2b27;';
            if ($ratio <= 0.30) return 'background: #FFFF94; color: #1e2b27;'; // Amarillo original
            if ($ratio <= 0.40) return 'background: #FDF190; color: #1e2b27;';
            if ($ratio <= 0.50) return 'background: #FCEB93; color: #1e2b27;'; // Nivel original
            if ($ratio <= 0.60) return 'background: #F9D08D; color: #1e2b27;';
            if ($ratio <= 0.70) return 'background: #EBCA8F; color: #1e2b27;'; // Nivel original
            if ($ratio <= 0.80) return 'background: #F1AA87; color: #1e2b27;';
            if ($ratio <= 0.90) return 'background: #F08B82; color: #1e2b27;';
            return 'background: #E57373; color: #1e2b27; font-weight: bold;';
        } else {
            // Negativos (Reducción de Anomalías) - Escala dentro de los límites originales (#E8F5E9 a #81C784)
            if ($ratio <= 0.10) return 'background: #F4FBF5; color: #1e2b27;';
            if ($ratio <= 0.20) return 'background: #E8F5E9; color: #1e2b27;'; // Verde original
            if ($ratio <= 0.30) return 'background: #D8EED9; color: #1e2b27;';
            if ($ratio <= 0.40) return 'background: #C8E6C9; color: #1e2b27;'; // Nivel original
            if ($ratio <= 0.50) return 'background: #B8DEC0; color: #1e2b27;';
            if ($ratio <= 0.60) return 'background: #A5D6A7; color: #1e2b27;'; // Nivel original
            if ($ratio <= 0.70) return 'background: #96CE9D; color: #1e2b27;';
            if ($ratio <= 0.80) return 'background: #89C791; color: #1e2b27;';
            return 'background: #81C784; color: #1e2b27; font-weight: bold;';
        }
    }
    ?>

    <div class="ea-table-card" id="tabla-zona-card" style="margin-top: 15px;">
        <div class="ea-table-wrapper">
            <table class="ea-table" id="tabla-zona-main">
                <thead>
                <tr>
                    <th colspan="100" class="ea-table__head-main">
                        <?php echo $titulo_reporte; ?>
                    </th>
                </tr>

                <tr>
                    <th rowspan="2" class="th-zona-ag" style="border-right: 1px solid #000;">AGENCIA</th>

                    <?php 
                    $anomalias_labels = [
                        'cancelaciones' => 'CANCELACIONES',
                        'estimaciones' => 'ESTIMACIONES', 
                        'consumos_cero' => 'CONSUMO CERO',
                        'servicios_sin_medicion' => 'SIN MED',
                        'correcciones_de_lecturas' => 'CORRECCION DE LEC',
                        'anomalias_pendientes' => 'ANOMALIAS PEN',
                        'sin_facturar' => 'SIN FACT',
                        'cargas_directas' => 'CARGAS DIRECTA'
                    ];
                    $idx_anom = 0;
                    foreach ($anomalias as $anomalia): 
                        $grp_class = ($idx_anom % 2 === 0) ? 'th-group' : 'th-group-alt';
                        $idx_anom++;
                        $label_text = $anomalias_labels[$anomalia] ?? strtoupper(str_replace('_', ' ', $anomalia));
                    ?>
                        <th colspan="4" class="<?php echo $grp_class; ?> b-left" data-anom-group="<?php echo $anomalia; ?>"><?php echo $label_text; ?></th>
                        <th colspan="1" class="<?php echo $grp_class; ?> reinc-col" data-anom-group="<?php echo $anomalia; ?>" style="background-color: #fce4ec !important; color: #000 !important;">REINC.</th>
                    <?php endforeach; ?>

                    <th colspan="4" class="th-defecto-group b-left">DEFECTOS TOTALES</th>
                </tr>

                <tr>
                    <?php foreach ($anomalias as $anomalia): ?>
                        <th class="th-sub b-left" data-anom-sub="<?php echo $anomalia; ?>"><?php echo $th_actual; ?></th>
                        <th class="th-sub" data-anom-sub="<?php echo $anomalia; ?>"><?php echo $th_bimestre; ?></th>
                        <th class="th-sub" data-anom-sub="<?php echo $anomalia; ?>">DIF</th>
                        <th class="th-evol evol-col" data-anom-sub="<?php echo $anomalia; ?>">% Evol</th>
                        <th class="th-sub reinc-col" data-anom-sub="<?php echo $anomalia; ?>" style="background-color: #fce4ec !important; color: #000 !important;">REINC.</th>
                    <?php endforeach; ?>

                    <th class="th-defecto-sub b-left"><?php echo $th_actual; ?></th>
                    <th class="th-defecto-sub"><?php echo $th_bimestre; ?></th>
                    <th class="th-defecto-sub">DIF</th>
                    <th class="th-defecto-evol evol-col">% Evol</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($lista_zonas)): ?>
                    <tr><td colspan="100" style="text-align:center; padding: 30px;">No se encontraron datos de Zonas y Agencias para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php
                    $totales_columnas_actual = array_fill_keys($anomalias, 0);
                    $totales_columnas_bimestre = array_fill_keys($anomalias, 0);
                    $totales_columnas_reinc = array_fill_keys($anomalias, 0);
                    $gran_defecto_actual = 0;
                    $gran_defecto_bimestre = 0;

                    // Orden personalizado de agencias
                    $orden_agencias = ['CENTRO','NORTE','SUR','ORIENTE','PONIENTE','PROGRESO','HUNUCMA','UMAN','ACANCEH','CONKAL'];
                    
                    // Recopilar todas las agencias de todas las zonas
                    $todas_agencias = [];
                    foreach ($lista_zonas as $z) {
                        foreach (array_keys($combinaciones_existentes[$z]) as $ag) {
                            if (!isset($todas_agencias[$ag])) {
                                $todas_agencias[$ag] = $z; // guardar la zona de origen
                            }
                        }
                    }
                    
                    // Ordenar según el orden personalizado
                    $agencias_ordenadas = [];
                    foreach ($orden_agencias as $ag_ord) {
                        if (isset($todas_agencias[$ag_ord])) {
                            $agencias_ordenadas[] = ['agencia' => $ag_ord, 'zona' => $todas_agencias[$ag_ord]];
                            unset($todas_agencias[$ag_ord]);
                        }
                    }
                    // Agregar agencias que no estén en el orden personalizado al final
                    foreach ($todas_agencias as $ag => $z) {
                        $agencias_ordenadas[] = ['agencia' => $ag, 'zona' => $z];
                    }

                    // Pre-calcular promedios ajustados para las columnas de % Evol en el Reporte Zona
                    $evols_por_columna = [];
                    $evols_def_columna = [];
                    foreach ($agencias_ordenadas as $ag_info) {
                        $ag_name = $ag_info['agencia'];
                        $z_name = $ag_info['zona'];
                        $def_act = 0;
                        $def_bim = 0;
                        foreach ($anomalias as $anomalia) {
                            $val_act = $resultados['actual'][$z_name][$ag_name][$anomalia] ?? 0;
                            $val_bim = $resultados['bimestre'][$z_name][$ag_name][$anomalia] ?? 0;
                            $def_act += $val_act;
                            $def_bim += $val_bim;
                            $diff = $val_act - $val_bim;
                            $ev = ($val_bim != 0) ? (($diff / $val_bim) * 100) : null;
                            if ($ev !== null && abs($ev) > 0.01) {
                                $evols_por_columna[$anomalia][] = abs($ev);
                            }
                        }
                        $diff_def = $def_act - $def_bim;
                        $ev_def = ($def_bim != 0) ? (($diff_def / $def_bim) * 100) : null;
                        if ($ev_def !== null && abs($ev_def) > 0.01) {
                            $evols_def_columna[] = abs($ev_def);
                        }
                    }
                    $max_evol = [];
                    foreach ($anomalias as $anomalia) {
                        $vals = isset($evols_por_columna[$anomalia]) ? $evols_por_columna[$anomalia] : [];
                        $max_evol[$anomalia] = empty($vals) ? 0 : max($vals);
                    }
                    $max_evol['defectos'] = empty($evols_def_columna) ? 0 : max($evols_def_columna);

                    foreach ($agencias_ordenadas as $ag_info):
                        $agencia = $ag_info['agencia'];
                        $zona = $ag_info['zona'];
                        $defecto_fila_actual = 0;
                        $defecto_fila_bimestre = 0;
                            ?>
                            <tr>
                                <td class="ea-td-agencia" style="border-right: 1px solid #000;"><?php echo $agencia; ?></td>

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

                                    <td class="b-left ea-td-num <?php echo $val_actual > 0 ? 'ea-td-num--val' : ''; ?>" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="actual" data-raw="<?php echo $val_actual; ?>">
                                        <?php if ($val_actual > 0): ?>
                                            <a href="javascript:void(0)" class="ea-detail-trigger" 
                                               data-tabla="<?php echo $anomalia . $sufijos['actual']; ?>" 
                                               data-agencia="<?php echo $agencia; ?>" 
                                               data-zona="<?php echo $zona; ?>"
                                               data-ciclo="<?php echo htmlspecialchars(implode(',', $filtro_ciclo)); ?>"
                                               data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ')'; ?>">
                                                <?php echo number_format($val_actual); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="ea-zero">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ea-td-num <?php echo $val_bimestre > 0 ? 'ea-td-num--val' : ''; ?>" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="bimestre" data-raw="<?php echo $val_bimestre; ?>">
                                        <?php if ($val_bimestre > 0): ?>
                                            <a href="javascript:void(0)" class="ea-detail-trigger" 
                                               data-tabla="<?php echo $anomalia . $sufijos['bimestre']; ?>" 
                                               data-agencia="<?php echo $agencia; ?>" 
                                               data-zona="<?php echo $zona; ?>"
                                               data-ciclo="<?php echo htmlspecialchars(implode(',', $filtro_ciclo)); ?>"
                                               data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ') ' . $th_bimestre; ?>">
                                                <?php echo number_format($val_bimestre); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="ea-zero">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ea-td-num td-dif <?php echo $clase_dif; ?>" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="dif">
                                        <div class="dif-content">
                                            <?php if ($diferencia > 0): ?>
                                                <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="0,0 10,0 5,10" fill="#C00000"/></svg>
                                                <span><?php echo number_format($diferencia); ?></span>
                                            <?php elseif ($diferencia < 0): ?>
                                                <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="5,0 10,10 0,10" fill="#548235"/></svg>
                                                <span><?php echo number_format($diferencia); ?></span>
                                            <?php else: ?>
                                                <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><rect x="1" y="3" width="8" height="1.5" fill="#FFB347"/><rect x="1" y="6" width="8" height="1.5" fill="#FFB347"/></svg>
                                                <span>0</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php
                                    $evol = ($val_bimestre != 0) ? (($diferencia / $val_bimestre) * 100) : null;
                                    $evol_style = evolStyle($evol, $max_evol[$anomalia]);
                                    ?>
                                    <td class="ea-td-evol evol-col" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="evol" data-raw-act="<?php echo $val_actual; ?>" data-raw-bim="<?php echo $val_bimestre; ?>" style="<?php echo $evol_style; ?>">
                                        <?php echo $evol !== null ? (($evol > 0 ? '+' : '') . number_format($evol, 1) . '%') : '—'; ?>
                                    </td>
                                    <td class="ea-td-num reinc-cell reinc-col" 
                                        data-zona="<?php echo htmlspecialchars($zona); ?>"
                                        data-agencia="<?php echo htmlspecialchars($agencia); ?>"
                                        data-anomalia="<?php echo htmlspecialchars($anomalia); ?>"
                                        data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="reinc"
                                        data-tabla="<?php echo $anomalia . $sufijos['actual']; ?>"
                                        data-tablacomp="<?php echo $anomalia . $sufijos['bimestre']; ?>"
                                        data-ciclo="<?php echo htmlspecialchars(implode(',', $filtro_ciclo)); ?>"
                                        data-titulo="REINCIDENTES - <?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia . ' (ZONA ' . $zona . ')'; ?>"
                                        style="background-color: #fdf5f6;">
                                        <span class="reinc-valor"><span class="ea-dots-loader"><span></span><span></span><span></span></span></span>
                                    </td>

                                <?php endforeach; ?>

                                <?php
                                $dif_defecto = $defecto_fila_actual - $defecto_fila_bimestre;
                                $clase_dif_defecto = $dif_defecto > 0 ? 'dif-pos' : ($dif_defecto < 0 ? 'dif-neg' : 'dif-zero');
                                $signo_defecto = $dif_defecto > 0 ? '+' : '';
                                $evol_def = ($defecto_fila_bimestre != 0) ? (($dif_defecto / $defecto_fila_bimestre) * 100) : null;
                                $evol_def_style = evolStyle($evol_def, $max_evol['defectos']);
                                ?>
                                <td class="b-left ea-td-defecto"><?php echo number_format($defecto_fila_actual); ?></td>
                                <td class="ea-td-defecto"><?php echo number_format($defecto_fila_bimestre); ?></td>
                                <td class="ea-td-defecto td-dif <?php echo $clase_dif_defecto; ?>">
                                    <div class="dif-content">
                                        <?php if ($dif_defecto > 0): ?>
                                            <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="0,0 10,0 5,10" fill="#C00000"/></svg>
                                            <span><?php echo number_format($dif_defecto); ?></span>
                                        <?php elseif ($dif_defecto < 0): ?>
                                            <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="5,0 10,10 0,10" fill="#548235"/></svg>
                                            <span><?php echo number_format($dif_defecto); ?></span>
                                        <?php else: ?>
                                            <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><rect x="1" y="3" width="8" height="1.5" fill="#FFB347"/><rect x="1" y="6" width="8" height="1.5" fill="#FFB347"/></svg>
                                            <span>0</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="ea-td-evol evol-col" style="<?php echo $evol_def_style; ?>">
                                    <?php echo $evol_def !== null ? (($evol_def > 0 ? '+' : '') . number_format($evol_def, 1) . '%') : '—'; ?>
                                </td>
                            </tr>
                            <?php
                            $gran_defecto_actual += $defecto_fila_actual;
                            $gran_defecto_bimestre += $defecto_fila_bimestre;
                    endforeach;
                    ?>

                    <tr class="ea-tr-total">
                        <td class="ea-td-agencia" style="border-right: 1px solid #000; text-align: center;">TOTAL</td>

                        <?php foreach ($anomalias as $anomalia):
                            $tot_act = $totales_columnas_actual[$anomalia];
                            $tot_bim = $totales_columnas_bimestre[$anomalia];
                            $tot_dif = $tot_act - $tot_bim;
                            $clase_tot_dif = $tot_dif > 0 ? 'dif-pos' : ($tot_dif < 0 ? 'dif-neg' : 'dif-zero');
                            $signo_tot = $tot_dif > 0 ? '+' : '';
                            ?>
                            <td class="b-left ea-td-num" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="actual" data-raw="<?php echo $tot_act; ?>"><?php echo number_format($tot_act); ?></td>
                            <td class="ea-td-num" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="bimestre" data-raw="<?php echo $tot_bim; ?>"><?php echo number_format($tot_bim); ?></td>
                            <?php 
                            $bg_tot_dif = '';
                            if ($tot_dif < 0) $bg_tot_dif = 'background-color: #C6E0B4 !important; color: #000 !important;'; 
                            elseif ($tot_dif > 0) $bg_tot_dif = 'background-color: #FFFF00 !important; color: #000 !important;';
                            ?>
                            <td class="ea-td-num td-dif <?php echo $clase_tot_dif; ?>" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="dif" style="<?php echo $bg_tot_dif; ?>">
                                <div class="dif-content">
                                    <?php if ($tot_dif > 0): ?>
                                        <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="0,0 10,0 5,10" fill="#C00000"/></svg>
                                        <span><?php echo number_format($tot_dif); ?></span>
                                    <?php elseif ($tot_dif < 0): ?>
                                        <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="5,0 10,10 0,10" fill="#548235"/></svg>
                                        <span><?php echo number_format($tot_dif); ?></span>
                                    <?php else: ?>
                                        <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><rect x="1" y="3" width="8" height="1.5" fill="#FFB347"/><rect x="1" y="6" width="8" height="1.5" fill="#FFB347"/></svg>
                                        <span>0</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php
                            $tot_evol = ($tot_bim != 0) ? (($tot_dif / $tot_bim) * 100) : null;
                            $tot_evol_style = evolStyle($tot_evol, $max_evol[$anomalia]);
                            ?>
                            <td class="ea-td-evol evol-col" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="evol" data-raw-act="<?php echo $tot_act; ?>" data-raw-bim="<?php echo $tot_bim; ?>" style="<?php echo $tot_evol_style; ?>">
                                <?php echo $tot_evol !== null ? (($tot_evol > 0 ? '+' : '') . number_format($tot_evol, 1) . '%') : '—'; ?>
                            </td>
                            <td class="ea-td-num reinc-total reinc-col" data-anom-cell="<?php echo $anomalia; ?>" data-cell-type="reinc" data-anomalia="<?php echo htmlspecialchars($anomalia); ?>" style="background-color: #f8d7da; color: #842029; font-weight: bold;">
                                <span class="reinc-total-valor"><span class="ea-dots-loader"><span></span><span></span><span></span></span></span>
                            </td>
                        <?php endforeach; ?>

                        <?php
                        $gran_dif_defecto = $gran_defecto_actual - $gran_defecto_bimestre;
                        $clase_gran_dif = $gran_dif_defecto > 0 ? 'dif-pos' : ($gran_dif_defecto < 0 ? 'dif-neg' : 'dif-zero');
                        $signo_gran = $gran_dif_defecto > 0 ? '+' : '';
                        $gran_evol_def = ($gran_defecto_bimestre != 0) ? (($gran_dif_defecto / $gran_defecto_bimestre) * 100) : null;
                        $gran_evol_style = evolStyle($gran_evol_def, $max_evol['defectos']);
                        ?>
                        <td class="b-left ea-td-defecto"><?php echo number_format($gran_defecto_actual); ?></td>
                        <td class="ea-td-defecto"><?php echo number_format($gran_defecto_bimestre); ?></td>
                        <?php 
                        $bg_gran_dif = 'background-color: #104861 !important; color: #fff !important;';
                        ?>
                        <td class="ea-td-defecto td-dif <?php echo $clase_gran_dif; ?>" style="background-color: #104861 !important; color: #fff !important;">
                            <div class="dif-content">
                                <?php if ($gran_dif_defecto > 0): ?>
                                    <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="0,0 10,0 5,10" fill="#C00000"/></svg>
                                    <span style="color: #fff !important;"><?php echo number_format($gran_dif_defecto); ?></span>
                                <?php elseif ($gran_dif_defecto < 0): ?>
                                    <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="5,0 10,10 0,10" fill="#548235"/></svg>
                                    <span style="color: #fff !important;"><?php echo number_format($gran_dif_defecto); ?></span>
                                <?php else: ?>
                                    <svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><rect x="1" y="3" width="8" height="1.5" fill="#FFB347"/><rect x="1" y="6" width="8" height="1.5" fill="#FFB347"/></svg>
                                    <span style="color: #fff !important;">0</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="ea-td-evol evol-col" id="def-gran-evol" style="<?php echo $gran_evol_style; ?>">
                            <?php echo $gran_evol_def !== null ? (($gran_evol_def > 0 ? '+' : '') . number_format($gran_evol_def, 1) . '%') : '—'; ?>
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
            <div class="ea-spinner-container">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text">Cargando detalles…</span>
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
        color: #000 !important;
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
                const tablacomp = this.dataset.tablacomp || '';
                const modo = this.dataset.modo || 'normal';
                const agencia = this.dataset.agencia;
                const zona = this.dataset.zona;
                const ciclo  = this.dataset.ciclo || '';
                const titulo = this.dataset.titulo;

                modalTitle.textContent = "Detalle: " + titulo;
                modalBody.innerHTML = '<div class="ea-spinner-container"><div class="ea-spinner"></div><span class="ea-spinner-text">Consultando registros…</span><span class="ea-spinner-subtext">Preparando tabla de datos</span></div>';
                modal.style.display = 'block';

                fetch(`get_detalle_anomalia.php?tabla=${tabla}&tablacomp=${tablacomp}&modo=${modo}&agencia=${agencia}&zona=${zona}&ciclo=${ciclo}`)
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

        // Poner loaders animados en todas las celdas reinc mientras carga
        document.querySelectorAll('.reinc-valor').forEach(s => {
            s.innerHTML = '<span class="ea-dots-loader"><span></span><span></span><span></span></span>';
        });
        document.querySelectorAll('.reinc-total-valor').forEach(s => {
            s.innerHTML = '<span class="ea-dots-loader"><span></span><span></span><span></span></span>';
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
                    const ciclo    = td.dataset.ciclo !== undefined ? td.dataset.ciclo : '';

                    const val = (data[zona] && data[zona][agencia] && data[zona][agencia][anomalia])
                                 ? parseInt(data[zona][agencia][anomalia]) : 0;

                    totalesPorAnomalia[anomalia] = (totalesPorAnomalia[anomalia] || 0) + val;

                    const span = td.querySelector('.reinc-valor');
                    if (val > 0) {
                        td.style.fontWeight = '600';
                        td.style.color = '#000';
                        const link = document.createElement('a');
                        link.href = 'javascript:void(0)';
                        link.className = 'ea-detail-trigger';
                        link.style.color = '#000';
                        link.dataset.modo       = 'reincidente';
                        link.dataset.tabla      = tabla;
                        link.dataset.tablacomp  = tablacomp;
                        link.dataset.agencia    = agencia;
                        link.dataset.zona       = zona;
                        link.dataset.ciclo          = ciclo;
                        link.dataset.titulo     = titulo;
                        link.textContent        = val.toLocaleString();
                        link.addEventListener('click', abrirModalDesdeLink);
                        td.innerHTML = '';
                        td.appendChild(link);
                    } else {
                        if (span) {
                            span.style.color    = '#000';
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
                    if (span) { span.style.color = '#000'; span.textContent = total.toLocaleString(); }
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
                    s.innerHTML = '—'; s.style.color = '#adb5bd';
                });
                document.querySelectorAll('.reinc-total-valor').forEach(s => {
                    s.innerHTML = '—'; s.style.color = '#adb5bd';
                });
            });
    }

    // Lanzar al cargar la página
    cargarReincidentes();

    // Cancelar el fetch en curso cuando el usuario aplica un nuevo filtro
    // (evita que el fetch anterior cuelgue la navegación)
    document.querySelector('form[method="GET"]')?.addEventListener('submit', () => {
        if (reincController) reincController.abort();
        // Mostrar overlay MD3 de carga al aplicar filtros
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

    function abrirModalDesdeLink() {
        const modal     = document.getElementById('ea-modal');
        const modalBody = document.getElementById('ea-modal-body');
        const modalTitle= document.getElementById('ea-modal-title');
        const tabla      = this.dataset.tabla;
        const tablacomp  = this.dataset.tablacomp || '';
        const modo       = this.dataset.modo || 'normal';
        const agencia    = this.dataset.agencia;
        const zona       = this.dataset.zona;
        const ciclo      = this.dataset.ciclo || '';
        const titulo     = this.dataset.titulo;

        modalTitle.textContent = 'Detalle: ' + titulo;
        modalBody.innerHTML = '<div class="ea-spinner-container"><div class="ea-spinner"></div><span class="ea-spinner-text">Consultando registros…</span><span class="ea-spinner-subtext">Preparando tabla de datos</span></div>';
        modal.style.display = 'block';

        fetch(`get_detalle_anomalia.php?tabla=${tabla}&tablacomp=${tablacomp}&modo=${modo}&agencia=${agencia}&zona=${zona}&ciclo=${ciclo}`)
            .then(r => r.text())
            .then(html => { modalBody.innerHTML = '<div class="ea-fade-in">' + html + '</div>'; })
            .catch(() => { modalBody.innerHTML = '<p style="color:red;">Error al cargar.</p>'; });
    }

    // ─── LÓGICA DEL DROPDOWN "EXTRAS" (Reincidencias + Evolución) ─────────────
    (function() {
        const wrapper   = document.getElementById('extras-wrapper');
        const btnToggle = document.getElementById('btn-extras-toggle');
        const dropdown  = document.getElementById('extras-dropdown');
        const chkIcf    = document.getElementById('chk-icf-total');
        const chkReinc  = document.getElementById('chk-extras-reinc');
        const chkEvol   = document.getElementById('chk-extras-evol');
        const card      = document.getElementById('tabla-zona-card');

        if (!btnToggle || !dropdown) return;

        btnToggle.addEventListener('click', function(e) {
            e.stopPropagation(); e.preventDefault();
            dropdown.classList.toggle('open');
        });
        dropdown.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', e => {
            if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
        });

        function applyExtras() {
            // Reincidencias
            if (card) card.classList.toggle('extras-reinc-visible', chkReinc.checked);
            // Evolución + fix colspan del encabezado de grupo
            if (card) card.classList.toggle('extras-evol-visible', chkEvol.checked);
            
            const evolColspan = chkEvol.checked ? 4 : 3;
            // Ajustar colspan de los grupos de anomalías (cada uno incluye col evol)
            document.querySelectorAll('[data-anom-group]:not(.reinc-col)').forEach(th => {
                th.colSpan = evolColspan;
            });
            // Ajustar colspan del grupo DEFECTOS
            const defGrp = document.querySelector('.th-defecto-group');
            if (defGrp) defGrp.colSpan = evolColspan;
            
            // Badge del botón
            const anyActive = chkIcf.checked || chkReinc.checked || chkEvol.checked;
            btnToggle.classList.toggle('active', anyActive);

            // Bloquear filtros si ICF Total está activo
            const cicloDropdowns = document.querySelectorAll('.ea-form__group:has(.ea-dropdown-checkboxes)');
            cicloDropdowns.forEach(group => {
                if (chkIcf.checked) {
                    group.style.opacity = '0.5';
                    group.style.pointerEvents = 'none';
                    group.style.filter = 'grayscale(0.5)';
                } else {
                    group.style.opacity = '1';
                    group.style.pointerEvents = 'auto';
                    group.style.filter = 'none';
                }
            });
        }

        if (chkIcf) {
            chkIcf.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                if (this.checked) {
                    params.set('icf_total', '1');
                    // Al activar ICF Total, eliminamos los ciclos de la URL para que sea un total real
                    params.delete('ciclo');
                } else {
                    params.delete('icf_total');
                }
                window.location.href = '?' + params.toString();
            });
        }

        chkReinc.addEventListener('change', applyExtras);
        chkEvol.addEventListener('change', applyExtras);

        applyExtras();
    })();

    // ─── LÓGICA DEL DROPDOWN "ANOMALÍAS" ─────────────────────────────────────
    (function() {
        const btnToggle  = document.getElementById('btn-anom-toggle');
        const dropdown   = document.getElementById('anom-dropdown');
        const wrapper    = document.getElementById('anom-wrapper');
        const selectAll  = document.getElementById('anom-select-all');
        const chkAnoms   = Array.from(document.querySelectorAll('.chk-anom'));
        const chkCdTodos = document.getElementById('chk-cd-todos');
        const badge      = document.getElementById('anom-desel-badge');
        const cdExtraRow = document.getElementById('anom-cd-extra-row');

        if (!btnToggle || !dropdown) return;

        const anomOrder = <?php echo json_encode($anomalias); ?>;

        function updateColumnVisibility() {
            const table = document.getElementById('tabla-zona-main');
            if (!table) return;

            const activeAnoms = new Set(
                chkAnoms.filter(c => c.checked).map(c => c.dataset.anom)
            );

            // Ocultar columnas: display:none en todas las celdas de esa anomalía
            // Para colapso real, también ponemos width:0 y padding:0 (cross-browser)
            table.querySelectorAll('[data-anom-group]').forEach(th => {
                const show = activeAnoms.has(th.dataset.anomGroup);
                th.style.display  = show ? '' : 'none';
            });
            table.querySelectorAll('[data-anom-sub]').forEach(th => {
                const show = activeAnoms.has(th.dataset.anomSub);
                if (show) {
                    th.style.display = '';
                    th.style.width = '';
                    th.style.maxWidth = '';
                    th.style.padding = '';
                    th.style.overflow = '';
                } else {
                    th.style.display  = 'none';
                    th.style.width    = '0';
                    th.style.maxWidth = '0';
                    th.style.padding  = '0';
                    th.style.overflow = 'hidden';
                }
            });
            table.querySelectorAll('[data-anom-cell]').forEach(td => {
                const show = activeAnoms.has(td.dataset.anomCell);
                if (show) {
                    td.style.display  = '';
                    td.style.width    = '';
                    td.style.maxWidth = '';
                    td.style.padding  = '';
                    td.style.overflow = '';
                } else {
                    td.style.display  = 'none';
                    td.style.width    = '0';
                    td.style.maxWidth = '0';
                    td.style.padding  = '0';
                    td.style.overflow = 'hidden';
                }
            });

            // Recalcular totales de DEFECTOS
            recalcularDefectos(activeAnoms);

            // Actualizar badge del botón
            const deselCount = chkAnoms.length - chkAnoms.filter(c => c.checked).length;
            if (deselCount > 0) {
                badge.style.display = 'inline';
                badge.textContent = deselCount + ' ocult.';
            } else {
                badge.style.display = 'none';
            }

            // Sincronizar "seleccionar todo"
            selectAll.checked = chkAnoms.every(c => c.checked);
        }

        function recalcularDefectos(activeAnoms) {
            const table = document.getElementById('tabla-zona-main');
            if (!table) return;

            // Primero: calcular los nuevos valores de evol para cada fila y encontrar el máximo absoluto
            const rowData = [];
            let maxEvol = 0;

            table.querySelectorAll('tbody tr').forEach(row => {
                let sumAct = 0, sumBim = 0;

                // Sumar solo las celdas de anomalías activas
                anomOrder.forEach(anom => {
                    if (!activeAnoms.has(anom)) return;
                    const cellAct = row.querySelector(`[data-anom-cell="${anom}"][data-cell-type="actual"]`);
                    const cellBim = row.querySelector(`[data-anom-cell="${anom}"][data-cell-type="bimestre"]`);
                    if (cellAct) sumAct += parseInt(cellAct.dataset.raw || cellAct.textContent.replace(/[^\d]/g, '')) || 0;
                    if (cellBim) sumBim += parseInt(cellBim.dataset.raw || cellBim.textContent.replace(/[^\d]/g, '')) || 0;
                });

                const dif = sumAct - sumBim;
                let evolVal = null;
                if (sumBim !== 0) {
                    evolVal = (dif / sumBim) * 100;
                    if (Math.abs(evolVal) > 0.01) {
                        const absEvol = Math.abs(evolVal);
                        if (absEvol > maxEvol) {
                            maxEvol = absEvol;
                        }
                    }
                }

                rowData.push({
                    row: row,
                    sumAct: sumAct,
                    sumBim: sumBim,
                    dif: dif,
                    evolVal: evolVal
                });
            });

            // Segundo: aplicar los valores y calcular colores usando maxEvol
            rowData.forEach(data => {
                const row = data.row;
                const sumAct = data.sumAct;
                const sumBim = data.sumBim;
                const dif = data.dif;
                const evolVal = data.evolVal;

                const allTds = row.querySelectorAll('td:not([data-anom-cell])');
                const defActCell = allTds[1];
                const defBimCell = allTds[2];
                const defDifCell = allTds[3];
                const defEvolCell = allTds[4];

                if (defActCell) defActCell.textContent = sumAct.toLocaleString();
                if (defBimCell) defBimCell.textContent = sumBim.toLocaleString();
                if (defDifCell) {
                    const difContent = defDifCell.querySelector('.dif-content');
                    if (difContent) {
                        if (dif > 0) {
                            const pts = '0,0 10,0 5,10';
                            const color = '#C00000';
                            const textStyle = row.classList.contains('ea-tr-total') ? 'style="color:#fff !important;"' : '';
                            difContent.innerHTML = `<svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="${pts}" fill="${color}"/></svg><span ${textStyle}>${dif.toLocaleString()}</span>`;
                        } else if (dif < 0) {
                            const pts = '5,0 10,10 0,10';
                            const color = '#548235';
                            const textStyle = row.classList.contains('ea-tr-total') ? 'style="color:#fff !important;"' : '';
                            difContent.innerHTML = `<svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none"><polygon points="${pts}" fill="${color}"/></svg><span ${textStyle}>${dif.toLocaleString()}</span>`;
                        } else {
                            const color = '#FFB347';
                            const textStyle = row.classList.contains('ea-tr-total') ? 'style="color:#fff !important;"' : '';
                            const pts = `<rect x="1" y="3" width="8" height="1.5" fill="${color}"/><rect x="1" y="6" width="8" height="1.5" fill="${color}"/>`;
                            difContent.innerHTML = `<svg class="dif-icon-svg" width="24" height="13" viewBox="0 0 10 10" preserveAspectRatio="none">${pts}</svg><span ${textStyle}>0</span>`;
                        }
                    }
                    if (row.classList.contains('ea-tr-total')) {
                        defDifCell.style.setProperty('background-color', '#104861', 'important');
                        defDifCell.style.color = '#fff';
                    }
                }

                if (defEvolCell) {
                    let evolText = '—', bg = '#fff', fg = '#1e2b27';
                    if (evolVal !== null) {
                        evolText = (evolVal > 0 ? '+' : '') + evolVal.toFixed(1) + '%';
                        const a = Math.abs(evolVal);

                        if (a < 0.01) {
                            bg = '#fff'; fg = '#1e2b27';
                        } else if (maxEvol > 0) {
                            const ratio = a / maxEvol;
                            if (evolVal > 0) {
                                // Positivos (Incremento de Anomalías) - Escala de amarillo a rojo
                                if (ratio <= 0.10) bg = '#FFFFDF';
                                else if (ratio <= 0.20) bg = '#FFFFB8';
                                else if (ratio <= 0.30) bg = '#FFFF94';
                                else if (ratio <= 0.40) bg = '#FDF190';
                                else if (ratio <= 0.50) bg = '#FCEB93';
                                else if (ratio <= 0.60) bg = '#F9D08D';
                                else if (ratio <= 0.70) bg = '#EBCA8F';
                                else if (ratio <= 0.80) bg = '#F1AA87';
                                else if (ratio <= 0.90) bg = '#F08B82';
                                else { bg = '#E57373'; fg = '#1e2b27'; }
                            } else {
                                // Negativos (Reducción de Anomalías) - Escala verde
                                if (ratio <= 0.10) bg = '#F4FBF5';
                                else if (ratio <= 0.20) bg = '#E8F5E9';
                                else if (ratio <= 0.30) bg = '#D8EED9';
                                else if (ratio <= 0.40) bg = '#C8E6C9';
                                else if (ratio <= 0.50) bg = '#B8DEC0';
                                else if (ratio <= 0.60) bg = '#A5D6A7';
                                else if (ratio <= 0.70) bg = '#96CE9D';
                                else if (ratio <= 0.80) bg = '#89C791';
                                else { bg = '#81C784'; fg = '#1e2b27'; }
                            }
                        }
                    }
                    defEvolCell.style.background = bg;
                    defEvolCell.style.color = fg;
                    defEvolCell.style.fontWeight = '700';
                    defEvolCell.textContent = evolText;
                }
            });
        }

        // Toggle dropdown
        btnToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            dropdown.classList.toggle('open');
        });

        dropdown.addEventListener('click', function(e) { e.stopPropagation(); });

        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
        });

        // Select All
        selectAll.addEventListener('change', function() {
            chkAnoms.forEach(chk => chk.checked = this.checked);
            updateColumnVisibility();
        });

        // Cada anomalía
        chkAnoms.forEach(chk => {
            chk.addEventListener('change', function() {
                // Si se deselecciona cargas_directas, deshabilitar la casilla extra
                if (this.dataset.anom === 'cargas_directas' && cdExtraRow) {
                    cdExtraRow.style.opacity = this.checked ? '1' : '0.4';
                    if (chkCdTodos) chkCdTodos.disabled = !this.checked;
                }
                updateColumnVisibility();
            });
        });

        // Casilla "Todos los ciclos" de cargas directas → recarga con/sin param ciclo_cd
        if (chkCdTodos) {
            chkCdTodos.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                if (this.checked) {
                    // Independiente: borrar ciclo_cd → default = todos
                    params.delete('ciclo_cd');
                } else {
                    // Usar el filtro general: enviar ciclo_cd vacío para que el backend lo respete
                    // En realidad, el comportamiento actual es que sin ciclo_cd = todos los ciclos.
                    // Con ciclo_cd vacío = ningún ciclo. 
                    // Para "seguir el filtro general" necesitamos indicar que no es default:
                    params.delete('ciclo_cd');
                    const cicloGeneral = params.getAll('ciclo');
                    // Pasamos los ciclos generales como ciclo_cd
                    params.delete('ciclo_cd');
                    if (cicloGeneral.length > 0) {
                        cicloGeneral.forEach(v => params.append('ciclo_cd', v));
                    } else {
                        // Sin filtro general = usar todos → marcar con param especial vacío
                        // Para distinguirlo de "default", usamos el mismo valor que cdApply
                        // Aquí no hay ciclos seleccionados = todos = borrar param
                    }
                }
                window.location.href = '?' + params.toString();
            });
        }

        // Inicializar visibilidad
        updateColumnVisibility();
    })();

    // ─── COMPRESIÓN AUTOMÁTICA DE COLUMNAS SEGÚN DÍGITOS ─────────────────────
    // Detecta el máximo número de dígitos en cada columna y ajusta el ancho.
    // 1 dígito → muy angosta, 2 dígitos → angosta, 3 dígitos → algo angosta
    // ─────────────────────────────────────────────────────────────────────────
    (function comprimirColumnasAngostas() {
        const table = document.getElementById('tabla-zona-main');
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Recopilar el máximo de dígitos por [anomalia|tipo]
        const combos = new Map();

        tbody.querySelectorAll('td[data-anom-cell]').forEach(td => {
            const anom = td.dataset.anomCell;
            const type = td.dataset.cellType;
            if (!anom || (type !== 'actual' && type !== 'bimestre' && type !== 'dif')) return;

            const key = anom + '|' + type;
            if (!combos.has(key)) combos.set(key, { maxDigits: 0, cells: [] });

            const entry = combos.get(key);
            entry.cells.push(td);

            let raw = 0;
            if (type === 'dif') {
                const spans = td.querySelectorAll('.dif-content span');
                if (spans.length > 0) {
                    const txt = spans[spans.length - 1].textContent;
                    raw = parseInt(txt.replace(/[^\d]/g, '')) || 0;
                }
            } else {
                raw = parseInt(td.dataset.raw) || 0;
            }

            const digits = String(raw).length;
            if (digits > entry.maxDigits) entry.maxDigits = digits;
        });

        // Tabla de parámetros por nivel de dígitos
        const config = {
            1: { minWidth: '24px', width: '1.8%',  padding: '0 1px', fontSize: '15px', thPad: '3px 1px' },
            2: { minWidth: '30px', width: '2.2%',  padding: '0 2px', fontSize: '16px', thPad: '3px 2px' },
            3: { minWidth: '38px', width: '2.8%',  padding: '0 2px', fontSize: '16px', thPad: '3px 2px' },
        };
        // Para DIF: reducir el ancho y padding derecho (el izquierdo se mantiene en 3px por el icono)
        const configDif = {
            1: { minWidth: '36px', width: '2.2%',  padding: '0 1px 0 3px', fontSize: '15px', thPad: '3px 1px' },
            2: { minWidth: '42px', width: '2.5%',  padding: '0 1px 0 3px', fontSize: '16px', thPad: '3px 1px' },
            3: { minWidth: '48px', width: '2.9%',  padding: '0 2px 0 3px', fontSize: '16px', thPad: '3px 2px' },
        };

        combos.forEach((entry, key) => {
            const [anom, type] = key.split('|');
            const cfgMap = type === 'dif' ? configDif : config;
            const cfg = cfgMap[entry.maxDigits];
            if (!cfg || entry.cells.length === 0) return;

            entry.cells.forEach(td => {
                td.style.minWidth  = cfg.minWidth;
                td.style.width     = cfg.width;
                td.style.padding   = cfg.padding;
                td.style.setProperty('padding', cfg.padding, 'important');
                td.style.fontSize  = cfg.fontSize;
            });

            const thSubs = table.querySelectorAll(`th[data-anom-sub="${anom}"]`);
            let thIdx = -1;
            if (type === 'actual') thIdx = 0;
            else if (type === 'bimestre') thIdx = 1;
            else if (type === 'dif') thIdx = 2;

            if (thIdx >= 0 && thSubs[thIdx]) {
                thSubs[thIdx].style.minWidth = cfg.minWidth;
                thSubs[thIdx].style.width    = cfg.width;
                thSubs[thIdx].style.padding  = cfg.thPad;
            }
        });
    })();
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    function exportarPDFZona() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a3'); // A3 landscape para tablas anchas

        const titulo = "<?php echo 'Reporte Nivel Zona — ' . obtenerNombreMes($p1_mes) . ' ' . $p1_anio; ?>";

        doc.setFontSize(13);
        doc.text(titulo, 30, 35);

        const table = document.getElementById('tabla-zona-main');
        if (!table) return;

        // Recolectar filas visibles (excluir display:none)
        const rows = [];
        table.querySelectorAll('tr').forEach(tr => {
            const cells = [];
            tr.querySelectorAll('th, td').forEach(cell => {
                // Saltar celdas ocultas
                if (cell.style.display === 'none') return;
                let text = cell.innerText.trim();
                cells.push({
                    content: text,
                    colSpan: parseInt(cell.getAttribute('colspan') || 1),
                    rowSpan: parseInt(cell.getAttribute('rowspan') || 1),
                    styles: {}
                });
            });
            if (cells.length > 0) rows.push(cells);
        });

        doc.autoTable({
            body: rows.slice(2), // saltar encabezado principal redundante
            startY: 50,
            theme: 'grid',
            styles: {
                fontSize: 5.5,
                cellPadding: 2,
                textColor: [0, 0, 0],
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [200, 210, 225],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            alternateRowStyles: { fillColor: [250, 250, 250] },
            html: '#tabla-zona-main',
            includeHiddenHtml: false,
        });

        // Vista previa antes de guardar
        const blob = doc.output('blob');
        const url = URL.createObjectURL(blob);
        const preview = window.open(url, '_blank');
        if (!preview) {
            // Si el popup fue bloqueado, guardar directamente
            doc.save("reporte_zona_<?php echo $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT); ?>.pdf");
        }
    }
</script>

<!-- ExcelJS para exportación Excel con estilos -->
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
async function exportarExcelZona() {
    const table = document.getElementById('tabla-zona-main');
    if (!table) return;
    const card = document.getElementById('tabla-zona-card');
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Zona');

    const B = {style:'thin', color:{argb:'FF000000'}};
    const borders = {top:B,bottom:B,left:B,right:B};
    const centerAlign = {horizontal:'center',vertical:'middle',wrapText:false};

    function isVis(cell) {
        if (cell.style.display==='none') return false;
        if (cell.classList.contains('reinc-col') && !card.classList.contains('extras-reinc-visible')) return false;
        if (cell.classList.contains('evol-col') && !card.classList.contains('extras-evol-visible')) return false;
        const ag = cell.dataset.anomGroup||cell.dataset.anomSub||cell.dataset.anomCell;
        if (ag) { const c=document.querySelector(`.chk-anom[data-anom="${ag}"]`); if(c&&!c.checked) return false; }
        return true;
    }
    function rgbA(s){if(!s||s==='transparent'||s.includes('0, 0, 0, 0'))return null;const m=s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);if(!m)return null;return'FF'+[m[1],m[2],m[3]].map(n=>parseInt(n).toString(16).padStart(2,'0')).join('').toUpperCase();}

    // Grid ocupado por rowspan/colspan
    const occ={};
    const isO=(r,c)=>occ[r+','+c]===true;
    const setO=(r,c)=>{occ[r+','+c]=true;};

    let exR=1;
    const allTrs = table.querySelectorAll('thead tr, tbody tr');

    // ── CALCULAR TOTAL DE COLUMNAS VISIBLES ──
    // Usamos la primera fila de datos para contar cuántas celdas reales hay visibles
    let totalColSpan = 0;
    const sampleRow = table.querySelector('tbody tr'); 
    if (sampleRow) {
        sampleRow.querySelectorAll('td').forEach(td => {
            if (isVis(td)) totalColSpan += parseInt(td.getAttribute('colspan') || 1);
        });
    }

    const difCols = new Set();

    allTrs.forEach(tr => {

        let col=1;
        const isTotal = tr.classList.contains('ea-tr-total');
        tr.querySelectorAll('th, td').forEach(cell => {
            if(!isVis(cell)) return;
            while(isO(exR,col)) col++;
            
            let cs = parseInt(cell.getAttribute('colspan') || 1);
            const rs = parseInt(cell.getAttribute('rowspan') || 1);

            // Si es el encabezado principal con un colspan exagerado (como 100), lo ajustamos al ancho real
            if (cs >= 50 && totalColSpan > 0) {
                cs = totalColSpan;
            }


            // ── Estilo y Valor ──
            const comp = window.getComputedStyle(cell);
            let bg = rgbA(comp.backgroundColor);
            let fc = rgbA(comp.color) || 'FF000000';
            let bold = comp.fontWeight === 'bold' || parseInt(comp.fontWeight) >= 600;
            let sz = 9;
            if (cell.classList.contains('ea-table__head-main')) { sz = 12; }

            let val;
            const isDif = cell.classList.contains('td-dif');
            if (isDif) {
                for (let i = 0; i < cs; i++) {
                    difCols.add(col + i);
                }
                const dc = cell.querySelector('.dif-content');
                if (dc) {
                    const sp = dc.querySelector('span');
                    const nt = sp ? sp.textContent.trim() : '0';
                    const nv = parseInt(nt.replace(/[^\-\d]/g, '')) || 0;
                    let icon, ic;
                    if (nv > 0) { icon = '▼'; ic = 'FFC00000'; }
                    else if (nv < 0) { icon = '▲'; ic = 'FF548235'; }
                    else { icon = '='; ic = 'FFFFB347'; }
                    
                    // Si es la fila de total general, el texto suele ser blanco sobre fondo oscuro
                    let textCol = fc; 
                    
                    // Calcular el padding para alinear icon a la izquierda y número a la derecha
                    const padCount = Math.max(1, 5 - nt.length);
                    const padding = ' '.repeat(padCount);
                    
                    val = { richText: [
                        { text: icon, font: { color: { argb: ic }, bold: true, size: 12, name: 'Arial' } },
                        { text: padding, font: { name: 'Consolas', size: 9 } },
                        { text: nt, font: { color: { argb: textCol }, bold: bold, size: 9, name: 'Consolas' } }
                    ] };
                } else { val = cell.textContent.trim(); }
            } else {
                let t = cell.textContent.trim();
                if (cell.querySelector('.ea-dots-loader')) t = '';
                
                // Intentar convertir a número si no es un porcentaje y no está vacío
                if (t !== '' && !t.includes('%') && !isNaN(t.replace(/,/g, ''))) {
                    val = parseFloat(t.replace(/,/g, ''));
                } else {
                    val = t;
                }
            }


            // Forzar colores específicos si fallan (como en el header principal)
            if (cell.classList.contains('ea-table__head-main')) { bg = 'FF2E7D32'; fc = 'FFFFFFFF'; }


            // ── Escribir celda ──
            const ec = ws.getCell(exR, col);
            ec.value = val;
            
            if (typeof val === 'number') {
                ec.numFmt = '#,##0';
            }

            let align = { horizontal: 'center', vertical: 'middle', wrapText: false };
            if (cell.classList.contains('ea-td-agencia')) { align.horizontal = 'left'; }
            if (isDif) { align.horizontal = 'left'; }
            
            ec.alignment = align;
            ec.border = borders;
            if (bg) ec.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
            ec.font = { name: 'Arial', size: sz, bold: bold, color: { argb: fc } };

            // Merge
            if (cs > 1 || rs > 1) {
                ws.mergeCells(exR, col, exR + rs - 1, col + cs - 1);
                // Aplicar estilo a celdas merged
                for (let r = 0; r < rs; r++) {
                    for (let c = 0; c < cs; c++) {
                        if (r === 0 && c === 0) continue;
                        const mc = ws.getCell(exR + r, col + c);
                        mc.border = borders;
                        if (bg) mc.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                    }
                }
            }

            // Marcar ocupadas
            for(let r=0;r<rs;r++) for(let c=0;c<cs;c++) if(r>0||c>0) setO(exR+r,col+c);
            col+=cs;
        });
        exR++;
    });

    // ── Anchos de columna ──
    const maxC = ws.columnCount;
    for(let c=1;c<=maxC;c++){
        if (c === 1) {
            ws.getColumn(c).width = 12;
        } else if (difCols.has(c)) {
            ws.getColumn(c).width = 9;
        } else {
            ws.getColumn(c).width = 7;
        }
    }
    // Altura de filas de datos compacta
    ws.eachRow((row,idx)=>{ if(idx>2) row.height=16; });

    // ── Descargar ──
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='reporte_zona_<?php echo $p1_anio . str_pad($p1_mes, 2, "0", STR_PAD_LEFT); ?>.xlsx';
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>
</body>
</html>
