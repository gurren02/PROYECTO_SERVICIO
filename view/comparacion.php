<?php
// 1. INICIO DE SESIÓN Y SEGURIDAD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

$busqueda_activa = false;

$stmt_db_global = $pdo->query("SHOW TABLES");
$todas_las_tablas_flipped = array_flip($stmt_db_global->fetchAll(PDO::FETCH_COLUMN));

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anomalias = [
    'cancelaciones',
    'estimaciones',
    'consumos_cero',
    'servicios_sin_medicion',
    'correcciones_de_lecturas',
    'anomalias_pendientes',
    'sin_facturar',
    'cargas_directas'
];

$anomalias_labels = [
    'cancelaciones' => 'CAN',
    'estimaciones' => 'ESTIM',
    'consumos_cero' => 'CERO',
    'servicios_sin_medicion' => 'SIN MED',
    'correcciones_de_lecturas' => 'CORR LECT',
    'anomalias_pendientes' => 'ANOM PEN',
    'sin_facturar' => 'SIN FACT',
    'cargas_directas' => 'CARGAS DIR'
];

$p1_mes = isset($_REQUEST['mes_objetivo']) ? (int)$_REQUEST['mes_objetivo'] : (isset($_REQUEST['m']) ? (int)$_REQUEST['m'] : (isset($_SESSION['last_m']) ? (int)$_SESSION['last_m'] : (int)date('n')));
$p1_anio = isset($_REQUEST['anio_objetivo']) ? (int)$_REQUEST['anio_objetivo'] : (isset($_REQUEST['a']) ? (int)$_REQUEST['a'] : (isset($_SESSION['last_a']) ? (int)$_SESSION['last_a'] : (int)date('Y')));

$mapa_agencias = [
    'A' => 'CENTRO',
    'B' => 'NORTE',
    'C' => 'SUR',
    'D' => 'ORIENTE',
    'E' => 'PONIENTE',
    'F' => 'MOTUL',
    'G' => 'PROGRESO',
    'H' => 'HUNUCMA',
    'J' => 'UMAN',
    'K' => 'ACANCEH',
    'M' => 'CONKAL'
];

$filtro_agencia = isset($_REQUEST['agencia']) && array_key_exists(trim(strtoupper($_REQUEST['agencia'])), $mapa_agencias) ? trim(strtoupper($_REQUEST['agencia'])) : '';

// Filtro de Zona
$filtro_zona = '';
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    $filtro_zona = isset($_SESSION['zona']) ? trim($_SESSION['zona']) : '';
} else {
    $filtro_zona = isset($_REQUEST['zona']) ? trim($_REQUEST['zona']) : '';
}

$differences = [];
$ciclos_actuales = [];
$sum_bimestral = [];
$sum_mensual = [];
$sum_total = [];
$means = [];
$sufijo_actual = '';
$sufijo_comp = '';
$lbl_comp = '';
$ranking_por_agencia_y_opcion = [];

// Detección de cambio de periodo, agencia o zona para limpiar la persistencia de ciclos
$period_changed = false;
if (isset($_SESSION['last_m']) && $_SESSION['last_m'] !== $p1_mes) {
    $period_changed = true;
}
if (isset($_SESSION['last_a']) && $_SESSION['last_a'] !== $p1_anio) {
    $period_changed = true;
}
$_SESSION['last_m'] = $p1_mes;
$_SESSION['last_a'] = $p1_anio;

$agency_changed = false;
if (isset($_SESSION['last_agencia']) && $_SESSION['last_agencia'] !== $filtro_agencia) {
    $agency_changed = true;
}
$_SESSION['last_agencia'] = $filtro_agencia;

$zona_changed = false;
if (isset($_SESSION['last_zona']) && $_SESSION['last_zona'] !== $filtro_zona) {
    $zona_changed = true;
}
$_SESSION['last_zona'] = $filtro_zona;

if (($period_changed || $agency_changed || $zona_changed) && !isset($_GET['filtrado_aplicado'])) {
    unset($_SESSION['filtro_ciclo']);
    unset($_GET['ciclo']);
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

// Obtener filtro_ciclo
$filtro_ciclo = [];
if (isset($_GET['ciclo'])) {
    if (is_array($_GET['ciclo'])) {
        $filtro_ciclo = $_GET['ciclo'];
    } else {
        $filtro_ciclo = array_filter(explode(',', (string)$_GET['ciclo']), 'strlen');
    }
}

$icf_total = isset($_REQUEST['icf_total']) && $_REQUEST['icf_total'] === '1';
if ($icf_total) {
    $filtro_ciclo = [];
}

// Obtener todas las zonas disponibles en el mes actual
$sufijo_actual = $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
$todas_las_tablas = $todas_las_tablas_flipped;
$zonas_disponibles = [];
foreach ($anomalias as $anomalia) {
    $nombre_tabla_act = $anomalia . $sufijo_actual;
    if (isset($todas_las_tablas[$nombre_tabla_act])) {
        try {
            $stmt_z = $pdo->query("SELECT DISTINCT `Zona` FROM `$nombre_tabla_act` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while ($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) {
                $zonas_disponibles[] = (int)trim($rz['Zona']);
            }
        } catch (PDOException $e) {}
    }
}
$zonas_disponibles = array_unique($zonas_disponibles);
sort($zonas_disponibles);

// Mapa de ciclos disponibles por zona (para filtrado dinámico en el frontend).
// Se consultan AMBOS períodos (actual + comparación) para que coincida exactamente
// con los ciclos que se muestran en el dropdown ($ciclos_disponibles = actual ∪ comparación).
// Si un ciclo aparece en cualquiera de los dos períodos para una zona, no se deshabilita.
$ciclos_por_zona = [];
// Pre-calcular el sufijo de comparación (bimestre anterior) en línea — $sufijo_comp aún no existe aquí.
$_p3_mes_mapa  = $p1_mes - 2;
$_p3_anio_mapa = $p1_anio;
if ($_p3_mes_mapa <= 0) { $_p3_mes_mapa += 12; $_p3_anio_mapa -= 1; }
$_sufijos_mapa = [
    $sufijo_actual,
    $_p3_anio_mapa . str_pad($_p3_mes_mapa, 2, '0', STR_PAD_LEFT),
];
foreach ($_sufijos_mapa as $_sufijo_mapa) {
    foreach ($anomalias as $_anom_mapa) {
        $_tabla_mapa = $_anom_mapa . $_sufijo_mapa;
        if (!isset($todas_las_tablas[$_tabla_mapa])) continue;
        try {
            $_stmt_mapa = $pdo->query("SELECT DISTINCT `Zona`, `Ciclo`, `Agencia` FROM `$_tabla_mapa` WHERE `Zona` IS NOT NULL AND `Zona` != '' AND `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while ($_r = $_stmt_mapa->fetch(PDO::FETCH_ASSOC)) {
                $_z = (int)trim($_r['Zona']);
                $_c = (int)trim($_r['Ciclo']);
                $_a = strtoupper(trim($_r['Agencia']));
                if ($_z === 1 && ($_a === 'F' || $_a === 'MOTUL')) {
                    continue;
                }
                if (!isset($ciclos_por_zona[$_z])) $ciclos_por_zona[$_z] = [];
                $ciclos_por_zona[$_z][$_c] = true;
            }
        } catch (PDOException $e) {}
    }
}
foreach ($ciclos_por_zona as $_z => &$_set) {
    $_set = array_keys($_set);
    sort($_set);
}
unset($_set);

// Mapa extendido: zona → agencia → [ciclos] (para filtrar por zona+agencia simultáneamente)
// También incluye clave especial '' (vacía) = "sin filtro de zona" para filtrar solo por agencia.
$ciclos_por_zona_agencia = [];
foreach ($_sufijos_mapa as $_sufijo_mapa) {
    foreach ($anomalias as $_anom_mapa) {
        $_tabla_mapa = $_anom_mapa . $_sufijo_mapa;
        if (!isset($todas_las_tablas[$_tabla_mapa])) continue;
        try {
            $_stmt_mapa2 = $pdo->query("SELECT DISTINCT `Zona`, `Agencia`, `Ciclo` FROM `$_tabla_mapa` WHERE `Zona` IS NOT NULL AND `Zona` != '' AND `Agencia` IS NOT NULL AND `Agencia` != '' AND `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while ($_r = $_stmt_mapa2->fetch(PDO::FETCH_ASSOC)) {
                $_z  = (int)trim($_r['Zona']);
                $_ag = strtoupper(trim($_r['Agencia']));
                $_c  = (int)trim($_r['Ciclo']);
                if ($_z === 1 && ($_ag === 'F' || $_ag === 'MOTUL')) {
                    continue;
                }
                // Indexado por zona
                if (!isset($ciclos_por_zona_agencia[$_z]))        $ciclos_por_zona_agencia[$_z] = [];
                if (!isset($ciclos_por_zona_agencia[$_z][$_ag]))  $ciclos_por_zona_agencia[$_z][$_ag] = [];
                $ciclos_por_zona_agencia[$_z][$_ag][$_c] = true;
                // Indexado sin zona (clave '') para filtrar solo por agencia
                if (!isset($ciclos_por_zona_agencia['']))        $ciclos_por_zona_agencia[''] = [];
                if (!isset($ciclos_por_zona_agencia[''][$_ag]))  $ciclos_por_zona_agencia[''][$_ag] = [];
                $ciclos_por_zona_agencia[''][$_ag][$_c] = true;
            }
        } catch (PDOException $e) {}
    }
}
// Convertir sets a arrays ordenados
foreach ($ciclos_por_zona_agencia as $_z => &$_agencias_set) {
    foreach ($_agencias_set as $_ag => &$_cset) {
        $_cset = array_keys($_cset);
        sort($_cset);
    }
}
unset($_agencias_set, $_cset);

$busqueda_activa = true;
if ($busqueda_activa) {

    // Calcular bimestre anterior
    $p3_mes = $p1_mes - 2;
    $p3_anio = $p1_anio;
    if ($p3_mes <= 0) {
        $p3_mes += 12;
        $p3_anio -= 1;
    }

    $sufijo_comp = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);
    $lbl_comp = $nombres_meses[$p3_mes] . ' ' . $p3_anio;

    // Helper para construir cláusulas WHERE dinámicas con filtros de ciclo, agencia y zona
    $build_where = function($tabla, &$params, $require_ciclo = true) use ($filtro_agencia, $filtro_zona) {
        $where_clauses = [];
        if ($require_ciclo) {
            $where_clauses[] = "`Ciclo` IS NOT NULL AND `Ciclo` != ''";
        }
        if ($filtro_agencia !== '') {
            $where_clauses[] = "UPPER(TRIM(`Agencia`)) = ?";
            $params[] = $filtro_agencia;
        }
        if (strpos($tabla, 'cargas_directas') === 0) {
            $where_clauses[] = "`Zona` IN ('1', '01')";
        } else {
            if ($filtro_zona !== '') {
                $where_clauses[] = "`Zona` IN (?, ?)";
                $params[] = (string)(int)$filtro_zona;
                $params[] = str_pad((int)$filtro_zona, 2, '0', STR_PAD_LEFT);
            }
        }
        $where_clauses[] = "NOT (`Zona` IN ('1', '01') AND (`Agencia` IN ('F', 'MOTUL', 'f', 'motul')))";
        return empty($where_clauses) ? "" : " WHERE " . implode(" AND ", $where_clauses);
    };

    // 1. Obtener todos los ciclos disponibles en el mes actual y en el de comparación
    // De forma global (sin filtro de agencia y zona) para que coincida exactamente con ejecutar_analisis_zona.php y se muestren todos en el dropdown.
    $build_cycle_where = function($tabla, &$params) {
        $where_clauses = ["`Ciclo` IS NOT NULL AND `Ciclo` != ''"];
        if (strpos($tabla, 'cargas_directas') === 0) {
            $where_clauses[] = "`Zona` IN ('1', '01')";
        }
        $where_clauses[] = "NOT (`Zona` IN ('1', '01') AND (`Agencia` IN ('F', 'MOTUL', 'f', 'motul')))";
        return " WHERE " . implode(" AND ", $where_clauses);
    };

    $ciclos_actuales_crudos = [];
    $zonas_disponibles  = [];
    foreach ($anomalias as $anomalia) {
        $nombre_tabla_act = $anomalia . $sufijo_actual;
        if (isset($todas_las_tablas[$nombre_tabla_act])) {
            try {
                $stmt_z = $pdo->query("SELECT DISTINCT `Zona` FROM `$nombre_tabla_act` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
                while ($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) {
                    $zonas_disponibles[] = (int)trim($rz['Zona']);
                }
            } catch (PDOException $e) {}
        }
    }
    $zonas_disponibles = array_unique($zonas_disponibles);
    sort($zonas_disponibles);
    foreach ($anomalias as $anomalia) {
        $nombre_tabla_act = $anomalia . $sufijo_actual;
        if (isset($todas_las_tablas[$nombre_tabla_act])) {
            try {
                $p_act = [];
                $w_act = $build_cycle_where($nombre_tabla_act, $p_act);
                $stmt = $pdo->prepare("SELECT DISTINCT `Ciclo` FROM `$nombre_tabla_act` $w_act");
                $stmt->execute($p_act);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ciclos_actuales_crudos[] = (int)trim($r['Ciclo']);
                }
            } catch (PDOException $e) {}
        }

        $nombre_tabla_comp = $anomalia . $sufijo_comp;
        if (isset($todas_las_tablas[$nombre_tabla_comp])) {
            try {
                $p_comp = [];
                $w_comp = $build_cycle_where($nombre_tabla_comp, $p_comp);
                $stmt = $pdo->prepare("SELECT DISTINCT `Ciclo` FROM `$nombre_tabla_comp` $w_comp");
                $stmt->execute($p_comp);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ciclos_comparacion_crudos[] = (int)trim($r['Ciclo']);
                }
            } catch (PDOException $e) {}
        }
    }
    $ciclos_actuales_crudos = array_unique($ciclos_actuales_crudos);
    $ciclos_comparacion_crudos = array_unique($ciclos_comparacion_crudos);
    $ciclos_disponibles = array_unique(array_merge($ciclos_actuales_crudos, $ciclos_comparacion_crudos));
    sort($ciclos_disponibles);

    // Separar ciclos en bimestrales y mensuales según paridad
    $is_even_month = ($p1_mes % 2 === 0);
    $ciclos_bimestrales = [];
    $ciclos_mensuales = [];
    foreach ($ciclos_disponibles as $c) {
        if ($c >= 1 && $c <= 61) {
            $coincide_paridad = ($is_even_month && $c % 2 === 0) || (!$is_even_month && $c % 2 !== 0);
            if ($coincide_paridad) {
                $ciclos_bimestrales[] = $c;
            }
        } elseif ($c >= 62 && $c <= 84) {
            $ciclos_mensuales[] = $c;
        }
    }

    // Conteo de registros por ciclo en la tabla de estimaciones del periodo actual (global, sin filtros de agencia y zona, para coincidir con ejecutar_analisis_zona.php)
    $conteos_por_ciclo = [];
    $tabla_estimaciones_actual = 'estimaciones' . $sufijo_actual;
    if (isset($todas_las_tablas[$tabla_estimaciones_actual])) {
        try {
            $stmt_cnt = $pdo->query("SELECT `Ciclo`, COUNT(*) as cnt FROM `$tabla_estimaciones_actual` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != '' GROUP BY `Ciclo`");
            while ($r = $stmt_cnt->fetch(PDO::FETCH_ASSOC)) {
                $c_val = (int)trim($r['Ciclo']);
                $cnt_val = (int)$r['cnt'];
                $conteos_por_ciclo[$c_val] = $cnt_val;
            }
        } catch (PDOException $e) {}
    }

    // Aplicar regla de continuidad y mayor a 8 registros
    $ciclos_actuales_filtrados = [];
    $start_cycle = $is_even_month ? 2 : 1;
    for ($c = $start_cycle; $c <= 61; $c += 2) {
        $count = isset($conteos_por_ciclo[$c]) ? $conteos_por_ciclo[$c] : 0;
        if ($count > 8) {
            $ciclos_actuales_filtrados[] = $c;
        } else {
            break;
        }
    }
    for ($c = 62; $c <= 84; $c++) {
        $count = isset($conteos_por_ciclo[$c]) ? $conteos_por_ciclo[$c] : 0;
        if ($count > 8) {
            $ciclos_actuales_filtrados[] = $c;
        }
    }

    // Excepción Ciclo 80: Si el ciclo 79 es válido y existen datos para el ciclo 80 en cargas directas, el ciclo 80 es válido.
    if (in_array(79, $ciclos_actuales_filtrados)) {
        $tabla_cd_actual = 'cargas_directas' . $sufijo_actual;
        if (isset($todas_las_tablas[$tabla_cd_actual])) {
            try {
                $stmt_cd_check = $pdo->prepare("SELECT COUNT(*) FROM `$tabla_cd_actual` WHERE `Ciclo` = '80'");
                $stmt_cd_check->execute();
                if ((int)$stmt_cd_check->fetchColumn() > 0) {
                    if (!in_array(80, $ciclos_actuales_filtrados)) {
                        $ciclos_actuales_filtrados[] = 80;
                    }
                }
            } catch (PDOException $e) {}
        }
    }

    if (empty($ciclos_actuales_filtrados)) {
        $ciclos_actuales_filtrados = $ciclos_disponibles;
    }

    // Obtener los ciclos que realmente tienen datos para la agencia y zona seleccionadas
    $ciclos_con_datos_agencia = [];
    if ($filtro_agencia !== '') {
        $build_agency_cycle_where = function($tabla, &$params) use ($filtro_agencia, $filtro_zona) {
            $where_clauses = ["`Ciclo` IS NOT NULL AND `Ciclo` != ''", "UPPER(TRIM(`Agencia`)) = ?"];
            $params[] = $filtro_agencia;
            if (strpos($tabla, 'cargas_directas') === 0) {
                $where_clauses[] = "`Zona` IN ('1', '01')";
            } else {
                if ($filtro_zona !== '') {
                    $where_clauses[] = "`Zona` IN (?, ?)";
                    $params[] = (string)(int)$filtro_zona;
                    $params[] = str_pad((int)$filtro_zona, 2, '0', STR_PAD_LEFT);
                }
            }
            $where_clauses[] = "NOT (`Zona` IN ('1', '01') AND (`Agencia` IN ('F', 'MOTUL', 'f', 'motul')))";
            return " WHERE " . implode(" AND ", $where_clauses);
        };

        foreach ($anomalias as $anomalia) {
            $nombre_tabla_act = $anomalia . $sufijo_actual;
            if (isset($todas_las_tablas[$nombre_tabla_act])) {
                try {
                    $p_act = [];
                    $w_act = $build_agency_cycle_where($nombre_tabla_act, $p_act);
                    $stmt = $pdo->prepare("SELECT DISTINCT `Ciclo` FROM `$nombre_tabla_act` $w_act");
                    $stmt->execute($p_act);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ciclos_con_datos_agencia[] = (int)trim($r['Ciclo']);
                    }
                } catch (PDOException $e) {}
            }

            $nombre_tabla_comp = $anomalia . $sufijo_comp;
            if (isset($todas_las_tablas[$nombre_tabla_comp])) {
                try {
                    $p_comp = [];
                    $w_comp = $build_agency_cycle_where($nombre_tabla_comp, $p_comp);
                    $stmt = $pdo->prepare("SELECT DISTINCT `Ciclo` FROM `$nombre_tabla_comp` $w_comp");
                    $stmt->execute($p_comp);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ciclos_con_datos_agencia[] = (int)trim($r['Ciclo']);
                    }
                } catch (PDOException $e) {}
            }
        }
        $ciclos_con_datos_agencia = array_unique($ciclos_con_datos_agencia);
    }

    // Preseleccionar todos los ciclos válidos en primera carga o cuando se cambie de periodo, agencia o zona
    $es_primera_carga = !isset($_GET['ciclo']) && !isset($_SESSION['filtro_ciclo']) && !isset($_GET['filtrado_aplicado']);
    // Solo aplicar auto-reset por cambio de zona/período/agencia cuando NO es un envío explícito del formulario.
    // Si es envío explícito (filtrado_aplicado), respetamos los ciclos que el usuario seleccionó (o desmarcó via JS).
    if (($period_changed || $agency_changed || $zona_changed) && !isset($_GET['filtrado_aplicado'])) {
        $es_primera_carga = true;
        $filtro_ciclo = [];
    }

    if ($es_primera_carga && empty($filtro_ciclo)) {
        foreach (array_merge($ciclos_bimestrales, $ciclos_mensuales) as $c) {
            if (in_array($c, $ciclos_actuales_filtrados)) {
                if ($filtro_agencia !== '') {
                    if (in_array($c, $ciclos_con_datos_agencia)) {
                        $filtro_ciclo[] = (string)$c;
                    }
                } else {
                    $filtro_ciclo[] = (string)$c;
                }
            }
        }
        // Salvaguarda: si tras aplicar la regla de estimaciones > 8 para la agencia seleccionada,
        // no quedó preseleccionado ningún ciclo, preseleccionamos TODOS los ciclos que tengan datos para esa agencia.
        if ($filtro_agencia !== '' && empty($filtro_ciclo)) {
            foreach ($ciclos_con_datos_agencia as $c) {
                if (in_array($c, $ciclos_bimestrales) || in_array($c, $ciclos_mensuales)) {
                    $filtro_ciclo[] = (string)$c;
                }
            }
        }
        $_SESSION['filtro_ciclo'] = $filtro_ciclo;
    }

    // Filtrar los ciclos para que correspondan ÚNICAMENTE a los disponibles para la zona/agencia seleccionadas
    $ciclos_validos_filtro = null;
    if ($filtro_zona !== '' && $filtro_agencia !== '') {
        $ciclos_validos_filtro = $ciclos_por_zona_agencia[(int)$filtro_zona][$filtro_agencia] ?? [];
    } elseif ($filtro_zona !== '') {
        $ciclos_validos_filtro = $ciclos_por_zona[(int)$filtro_zona] ?? [];
    } elseif ($filtro_agencia !== '') {
        $ciclos_validos_filtro = $ciclos_por_zona_agencia[''][$filtro_agencia] ?? [];
    }

    if ($ciclos_validos_filtro !== null) {
        $filtro_ciclo = array_filter($filtro_ciclo, function($c) use ($ciclos_validos_filtro) {
            return in_array((int)$c, $ciclos_validos_filtro);
        });
    }

    // Los ciclos que realmente mostraremos en la tabla principal y ranking
    $ciclos_actuales = array_map('intval', $filtro_ciclo);
    sort($ciclos_actuales);

    if (!empty($ciclos_actuales)) {
        $counts_actual = [];
        foreach ($anomalias as $anomalia) {
            $counts_actual[$anomalia] = [];
            $nombre_tabla = $anomalia . $sufijo_actual;
            if (isset($todas_las_tablas[$nombre_tabla])) {
                try {
                    $p_counts_act = [];
                    $w_counts_act = $build_where($nombre_tabla, $p_counts_act);
                    $stmt = $pdo->prepare("SELECT `Ciclo`, COUNT(*) as cnt FROM `$nombre_tabla` $w_counts_act GROUP BY `Ciclo`");
                    $stmt->execute($p_counts_act);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_actual[$anomalia][(int)trim($r['Ciclo'])] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }
        }

        // 3. Obtener conteos de anomalías agrupados por ciclo para mes comparación (bimestre anterior)
        $counts_comp = [];
        foreach ($anomalias as $anomalia) {
            $counts_comp[$anomalia] = [];
            $nombre_tabla = $anomalia . $sufijo_comp;
            if (isset($todas_las_tablas[$nombre_tabla])) {
                try {
                    $p_counts_comp = [];
                    $w_counts_comp = $build_where($nombre_tabla, $p_counts_comp);
                    $stmt = $pdo->prepare("SELECT `Ciclo`, COUNT(*) as cnt FROM `$nombre_tabla` $w_counts_comp GROUP BY `Ciclo`");
                    $stmt->execute($p_counts_comp);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_comp[$anomalia][(int)trim($r['Ciclo'])] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }
        }

        // 4. Calcular diferencias
        $sum_bimestral = array_fill_keys(array_merge($anomalias, ['defectos']), 0);
        $sum_mensual = array_fill_keys(array_merge($anomalias, ['defectos']), 0);
        $sum_total = array_fill_keys(array_merge($anomalias, ['defectos']), 0);

        foreach ($ciclos_actuales as $c) {
            $differences[$c] = [];
            $row_sum = 0;

            foreach ($anomalias as $anomalia) {
                $act = isset($counts_actual[$anomalia][$c]) ? $counts_actual[$anomalia][$c] : 0;
                $comp = isset($counts_comp[$anomalia][$c]) ? $counts_comp[$anomalia][$c] : 0;
                $diff = $act - $comp;
                $differences[$c][$anomalia] = $diff;
                $row_sum += $diff;
            }

            $differences[$c]['defectos'] = $row_sum;
        }

        // Calcular sumas bimestral, mensual y total por anomalía
        foreach ($anomalias as $anomalia) {
            if ($anomalia === 'cargas_directas') {
                // Para cargas_directas, sumamos sobre todos los ciclos que tengan registros (para coincidir con ejecutar_analisis_zona.php que no los filtra por el ciclo general)
                $todos_ciclos_cd = array_unique(array_merge(
                    array_keys($counts_actual['cargas_directas']),
                    array_keys($counts_comp['cargas_directas'])
                ));
                foreach ($todos_ciclos_cd as $c) {
                    $act = $counts_actual['cargas_directas'][$c] ?? 0;
                    $comp = $counts_comp['cargas_directas'][$c] ?? 0;
                    $diff = $act - $comp;
                    $is_bim = ($c <= 61);
                    if ($is_bim) {
                        $sum_bimestral['cargas_directas'] += $diff;
                    } else {
                        $sum_mensual['cargas_directas'] += $diff;
                    }
                    $sum_total['cargas_directas'] += $diff;
                }
            } else {
                // Para las demás anomalías, sumamos sobre los ciclos seleccionados en la tabla
                foreach ($ciclos_actuales as $c) {
                    $diff = $differences[$c][$anomalia];
                    $is_bim = ($c <= 61);
                    if ($is_bim) {
                        $sum_bimestral[$anomalia] += $diff;
                    } else {
                        $sum_mensual[$anomalia] += $diff;
                    }
                    $sum_total[$anomalia] += $diff;
                }
            }
        }

        // Sumar todos los totales de anomalías para obtener los totales de defectos
        foreach ($anomalias as $anomalia) {
            $sum_bimestral['defectos'] += $sum_bimestral[$anomalia];
            $sum_mensual['defectos'] += $sum_mensual[$anomalia];
            $sum_total['defectos'] += $sum_total[$anomalia];
        }

        // 5. Calcular el máximo positivo y negativo por columna por separado (solo para ciclos actuales).
        // Esto garantiza que el pico positivo y el pico negativo de cada columna reciban siempre ratio=1.0.
        $max_diffs_pos = [];
        $max_diffs_neg = [];
        foreach (array_merge($anomalias, ['defectos']) as $col) {
            $max_pos = 0;
            $max_neg = 0;
            foreach ($ciclos_actuales as $c) {
                $v = (float)($differences[$c][$col] ?? 0);
                if ($v > 0 && $v > $max_pos) $max_pos = $v;
                if ($v < 0 && abs($v) > $max_neg) $max_neg = abs($v);
            }
            $max_diffs_pos[$col] = $max_pos;
            $max_diffs_neg[$col] = $max_neg;
        }
    }

    // 6. Ranking de peor aumento por agencia (siempre, independiente del filtro)
    // Pre-calcular ranking para todas las anomalías y para la opción "default" (excluyendo estimaciones y consumos_cero)
    if (!empty($ciclos_actuales)) {
        // Pre-cargar todos los conteos agrupados por Agencia y Ciclo para evitar N+1 queries
        $counts_actual_por_agencia = [];
        $counts_comp_por_agencia = [];

        foreach ($anomalias as $anomalia) {
            $counts_actual_por_agencia[$anomalia] = [];
            $counts_comp_por_agencia[$anomalia] = [];

            $tabla_act  = $anomalia . $sufijo_actual;
            $tabla_comp = $anomalia . $sufijo_comp;

            // Período Actual
            if (isset($todas_las_tablas[$tabla_act])) {
                try {
                    $where_global = "WHERE `Ciclo` IS NOT NULL AND `Ciclo` != '' AND `Agencia` IS NOT NULL AND `Agencia` != ''";
                    $params_global = [];
                    if ($anomalia === 'cargas_directas') {
                        $where_global .= " AND `Zona` IN ('1', '01')";
                    } else {
                        if ($filtro_zona !== '') {
                            $where_global .= " AND `Zona` IN (?, ?)";
                            $params_global[] = (string)(int)$filtro_zona;
                            $params_global[] = str_pad((int)$filtro_zona, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $where_global .= " AND NOT (`Zona` IN ('1', '01') AND (`Agencia` IN ('F', 'MOTUL', 'f', 'motul')))";

                    $stmt = $pdo->prepare("SELECT UPPER(TRIM(`Agencia`)) as ag, `Ciclo`, COUNT(*) as cnt FROM `$tabla_act` $where_global GROUP BY UPPER(TRIM(`Agencia`)), `Ciclo`");
                    $stmt->execute($params_global);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_actual_por_agencia[$anomalia][$r['ag']][(int)trim($r['Ciclo'])] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }

            // Período Comparación
            if (isset($todas_las_tablas[$tabla_comp])) {
                try {
                    $where_global = "WHERE `Ciclo` IS NOT NULL AND `Ciclo` != '' AND `Agencia` IS NOT NULL AND `Agencia` != ''";
                    $params_global = [];
                    if ($anomalia === 'cargas_directas') {
                        $where_global .= " AND `Zona` IN ('1', '01')";
                    } else {
                        if ($filtro_zona !== '') {
                            $where_global .= " AND `Zona` IN (?, ?)";
                            $params_global[] = (string)(int)$filtro_zona;
                            $params_global[] = str_pad((int)$filtro_zona, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $where_global .= " AND NOT (`Zona` IN ('1', '01') AND (`Agencia` IN ('F', 'MOTUL', 'f', 'motul')))";

                    $stmt = $pdo->prepare("SELECT UPPER(TRIM(`Agencia`)) as ag, `Ciclo`, COUNT(*) as cnt FROM `$tabla_comp` $where_global GROUP BY UPPER(TRIM(`Agencia`)), `Ciclo`");
                    $stmt->execute($params_global);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_comp_por_agencia[$anomalia][$r['ag']][(int)trim($r['Ciclo'])] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }
        }

        foreach ($mapa_agencias as $letra_ag => $nombre_ag) {
            $ranking_por_agencia_y_opcion[$letra_ag] = [
                'nombre' => $nombre_ag,
                'opciones' => []
            ];
            
            $diffs_agencia = [];
            $letra_ag_upper = strtoupper(trim($letra_ag));
            
            foreach ($anomalias as $anomalia) {
                $diffs_agencia[$anomalia] = [];
                
                foreach ($ciclos_actuales as $c) {
                    $act_val  = isset($counts_actual_por_agencia[$anomalia][$letra_ag_upper][$c]) ? $counts_actual_por_agencia[$anomalia][$letra_ag_upper][$c] : 0;
                    $comp_val = isset($counts_comp_por_agencia[$anomalia][$letra_ag_upper][$c]) ? $counts_comp_por_agencia[$anomalia][$letra_ag_upper][$c] : 0;
                    $diffs_agencia[$anomalia][$c] = $act_val - $comp_val;
                }
            }
            
            // 1. Opción "default" (excluye 'estimaciones' y 'consumos_cero')
            $best_ciclo = null;
            $best_anomalia = null;
            $best_val = -999999;
            foreach ($anomalias as $anomalia) {
                if ($anomalia === 'estimaciones' || $anomalia === 'consumos_cero') continue;
                foreach ($ciclos_actuales as $c) {
                    $val = isset($diffs_agencia[$anomalia][$c]) ? $diffs_agencia[$anomalia][$c] : 0;
                    if ($val > $best_val) {
                        $best_val = $val;
                        $best_ciclo = $c;
                        $best_anomalia = $anomalia;
                    }
                }
            }
            
            $ranking_por_agencia_y_opcion[$letra_ag]['opciones']['default'] = [
                'ciclo' => $best_ciclo,
                'anomalia' => $best_anomalia ? ($anomalias_labels[$best_anomalia] ?? strtoupper($best_anomalia)) : 'Ninguna',
                'valor' => ($best_ciclo !== null) ? $best_val : 0
            ];
            
            // 2. Cada anomalía individual
            foreach ($anomalias as $anomalia) {
                $best_ciclo = null;
                $best_val = -999999;
                foreach ($ciclos_actuales as $c) {
                    $val = isset($diffs_agencia[$anomalia][$c]) ? $diffs_agencia[$anomalia][$c] : 0;
                    if ($val > $best_val) {
                        $best_val = $val;
                        $best_ciclo = $c;
                    }
                }
                $ranking_por_agencia_y_opcion[$letra_ag]['opciones'][$anomalia] = [
                    'ciclo' => $best_ciclo,
                    'anomalia' => $anomalias_labels[$anomalia] ?? strtoupper($anomalia),
                    'valor' => ($best_ciclo !== null) ? $best_val : 0
                ];
            }
            $ranking_por_agencia_y_opcion[$letra_ag]['valores'] = $diffs_agencia;
        }
    }
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
    
    if (!empty($bimestrales)) {
        if (count($bimestrales) === 1) {
            $partes[] = (string)$bimestrales[0];
        } else {
            $partes[] = min($bimestrales) . " AL " . max($bimestrales);
        }
    }
    
    if (!empty($mensuales)) {
        if (count($mensuales) === 1) {
            $partes[] = (string)$mensuales[0];
        } else {
            $partes[] = min($mensuales) . " AL " . max($mensuales);
        }
    }
    
    return implode(" Y ", $partes);
}

function obtenerTextoRangoFiltro($ciclos_completo, $filtro_ciclo, $icf_total = false) {
    if ($icf_total) {
        return "TODOS";
    }
    if (empty($filtro_ciclo)) {
        $seleccionados = $ciclos_completo;
    } else {
        $seleccionados = array_intersect($ciclos_completo, array_map('intval', $filtro_ciclo));
    }
    if (empty($seleccionados)) {
        return "Ninguno";
    } elseif (count($seleccionados) === 1) {
        return (string)reset($seleccionados);
    } else {
        return min($seleccionados) . " AL " . max($seleccionados);
    }
}

// Función helper para mapa de calor — escala homologada de 10 niveles, amarilla-roja para positivos y verde para negativos.
// Recibe max_pos (máximo de valores positivos de la columna) y max_neg (máximo en abs de valores negativos),
// usando el máximo del mismo signo que el valor para que el pico siempre tenga ratio=1.0.
function getHeatmapStyle($value, $max_pos, $max_neg = null) {
    if ($max_neg === null) $max_neg = $max_pos; // compatibilidad retroactiva
    $v = (float)$value;
    if ($v > 0) {
        $max = $max_pos;
    } else {
        $max = $max_neg;
    }
    if ($value === null || $max <= 0) return 'background: #FFFFF0 !important; color: #000000 !important;';
    if (abs($v) < 0.01) return 'background: #FFFFF0 !important; color: #000000 !important;';
    
    $ratio = abs($v) / $max;
    if ($v > 0) {
        // Positivos (Incremento de anomalías - "Malo")
        if ($ratio <= 0.10) return 'background: #FFFFDF !important; color: #000000 !important;';
        if ($ratio <= 0.20) return 'background: #FFFFB8 !important; color: #000000 !important;';
        if ($ratio <= 0.30) return 'background: #FFFF94 !important; color: #000000 !important;';
        if ($ratio <= 0.40) return 'background: #FDF190 !important; color: #000000 !important;';
        if ($ratio <= 0.50) return 'background: #FCEB93 !important; color: #000000 !important;';
        if ($ratio <= 0.60) return 'background: #F9D08D !important; color: #000000 !important;';
        if ($ratio <= 0.70) return 'background: #EBCA8F !important; color: #000000 !important;';
        if ($ratio <= 0.80) return 'background: #F1AA87 !important; color: #000000 !important;';
        if ($ratio <= 0.90) return 'background: #F08B82 !important; color: #000000 !important;';
        return 'background: #F26A6A !important; color: #000000 !important;';
    } else {
        // Negativos (Reducción de anomalías - "Bueno")
        if ($ratio <= 0.10) return 'background: #CAEC07 !important; color: #000000 !important;';
        if ($ratio <= 0.20) return 'background: #B2E005 !important; color: #000000 !important;';
        if ($ratio <= 0.30) return 'background: #9AD204 !important; color: #000000 !important;';
        if ($ratio <= 0.40) return 'background: #76BE01 !important; color: #000000 !important;';
        if ($ratio <= 0.50) return 'background: #5DAD01 !important; color: #000000 !important;';
        if ($ratio <= 0.60) return 'background: #50A403 !important; color: #000000 !important;';
        if ($ratio <= 0.70) return 'background: #3C8F01 !important; color: #000000 !important;';
        if ($ratio <= 0.80) return 'background: #2D7C01 !important; color: #FFFFFF !important;';
        if ($ratio <= 0.90) return 'background: #195D00 !important; color: #FFFFFF !important;'; // Para verdes oscuros, usamos texto blanco
        return 'background: #114C00 !important; color: #FFFFFF !important;'; // Verde militar fuerte personalizado
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="../assets/multimedia/logo_cf.webp">
    <title>Comparación de Anomalías</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/preparar_analisis.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .comp-table-wrapper {
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background-color: #fff;
            border: 1px solid #000;
            margin-top: 20px;
        }
        .comp-table {
            width: auto;
            table-layout: auto !important;
            border-collapse: collapse;
            font-family: Arial, Calibri, sans-serif;
        }
        .comp-table th {
            border: 1px solid #000;
            padding: 0px 2px !important;
            text-align: center;
            font-size: 13px;
            min-width: 55px;
        }
        .comp-table td {
            border: 1px solid #000;
            padding: 0px 2px !important;
            text-align: center;
            font-size: 16px;
            min-width: 55px;
        }
        
        /* Título principal — verde fuerte igual que zona */
        .th-main-title {
            background-color: #1A7A5E !important;
            color: #fff !important;
            font-size: 16px !important;
            font-weight: bold !important;
            letter-spacing: 0.05em;
            text-align: center !important;
            text-transform: uppercase;
            padding: 4px !important;
        }
        /* Columna CICLO — blanca como th-zona-ag */
        .th-ciclo {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 13px !important;
            font-weight: bold !important;
            min-width: 55px;
        }
        /* Anomalías — intercalado azul/verde igual que zona */
        .th-group     { background-color: #AFD5F3 !important; color: #000 !important; font-weight: bold !important; font-size: 13px !important; white-space: nowrap !important; padding: 0px 3px !important; }
        .th-group-alt { background-color: #AADEC0 !important; color: #000 !important; font-weight: bold !important; font-size: 13px !important; white-space: nowrap !important; padding: 0px 3px !important; }
        /* Columna DEFECTOS — azul oscuro como zona */
        .th-defect {
            background-color: #104861 !important;
            color: #fff !important;
            font-weight: bold !important;
            font-size: 13px !important;
            padding: 0px 3px !important;
        }

        /* Row heights and styles */
        .comp-table tbody tr {
            height: 20px;
        }
        .comp-table tbody td {
            font-size: 16px;
            font-weight: normal;
            background-color: #fff;
            color: #000;
        }
        /* Celda de CICLO en el cuerpo */
        .td-ciclo {
            background-color: #fff !important;
            color: #000 !important;
            font-weight: bold !important;
            font-size: 16px !important;
            text-align: center !important;
        }
        /* Columna DEF (última columna) en negrita */
        .comp-table tbody td:last-child {
            font-weight: bold !important;
        }
        
        .row-summary {
            background-color: #f1f3f5 !important;
            font-weight: bold !important;
        }
        .row-summary td {
            background-color: #f1f3f5 !important;
            font-weight: bold !important;
            font-size: 16px !important;
            color: #000 !important;
        }
        /* Reducir tamaño de letra de las etiquetas BIMESTRAL, MENSUAL, TOTAL */
        .row-summary td:first-child {
            font-size: 12px !important;
        }
        /* Fila separadora bimestral/mensual */
        .row-separator td {
            background-color: #0b5ed7 !important;
            font-size: 11px !important;
            font-weight: bold !important;
            color: #fff !important;
            text-align: center !important;
            padding: 2px 4px !important;
            letter-spacing: 0.08em;
            border-top: 2px solid #0a58ca !important;
            border-bottom: 2px solid #0a58ca !important;
        }

        .comp-table-card {
            margin-top: 10px;
            background-color: #fff;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid var(--ea-border);
        }

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
            body, .calc-main {
                background-color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            header, 
            .calc-card--form, 
            .calc-page-header, 
            .ea-page-header, 
            .ea-btn-print, 
            .ea-btn-excel,
            .ea-btn-back,
            .ea-page-overlay {
                display: none !important;
            }
            .comp-table-card {
                box-shadow: none !important;
                border: none !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
                width: fit-content !important;
                padding: 0 !important;
            }
            .comp-table-wrapper {
                overflow: visible !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
        
        .opt-filtro-ranking {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            color: #333;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .opt-filtro-ranking:hover {
            background: #f1f3f5;
            color: #000;
        }
        .opt-filtro-ranking.active {
            background: #E8F0FE;
            color: #1A73E8;
            font-weight: 600;
        }
    </style>
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="calc-main">

    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="preparar_analisis.php?mes_objetivo=<?php echo $p1_mes; ?>&anio_objetivo=<?php echo $p1_anio; ?>" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span> Volver
            </a>
            <div>
                <h1 class="ea-page-title">Comparación de Ciclos por Anomalía</h1>
                <p class="ea-page-subtitle">Calcula las diferencias absolutas de anomalías por ciclo comparando el mes seleccionado con su bimestre anterior. Únicamente se incluyen los ciclos existentes en el mes actual.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($busqueda_activa && !empty($ciclos_actuales)): ?>
                <button onclick="exportarExcelComparacion()" class="ea-btn-excel" style="background-color: #fff; color: #2E7D32; border: 1.5px solid #2E7D32; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#E8F5E9';this.style.boxShadow='0 2px 8px rgba(46,125,50,0.15)'" onmouseout="this.style.backgroundColor='#fff';this.style.boxShadow='none'">
                    <span class="material-symbols-rounded" style="font-size: 20px;">download</span> Excel
                </button>
                <button onclick="window.print()" class="ea-btn-print">
                    <span class="material-symbols-rounded">print</span> Imprimir PDF
                </button>
            <?php endif; ?>
            <a href="ejecutar_analisis_zona.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d4efdf; color: #196f3d; border: 1px solid #a9dfbf; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#a9dfbf'" onmouseout="this.style.backgroundColor='#d4efdf'">
                <span class="material-symbols-rounded" style="font-size: 18px;">map</span>
                Ir a Nivel Zona
            </a>
            <a href="ejecutar_analisis.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #d6eaf8; color: #1b4f72; border: 1px solid #aed6f1; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#aed6f1'" onmouseout="this.style.backgroundColor='#d6eaf8'">
                <span class="material-symbols-rounded" style="font-size: 18px;">business</span>
                Ir a Nivel Agencia
            </a>
            <?php 
                $params_est = "?m=$p1_mes&a=$p1_anio";
                if ($filtro_zona !== '') $params_est .= "&zona=" . urlencode($filtro_zona);
            ?>
            <a href="detalle_estimaciones.php<?php echo $params_est; ?>" class="ea-btn-switch-report" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #fcf3cf; color: #7d6608; border: 1px solid #f9e79f; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.18s, transform 0.1s;" onmouseover="this.style.backgroundColor='#f9e79f'" onmouseout="this.style.backgroundColor='#fcf3cf'">
                <span class="material-symbols-rounded" style="font-size: 18px;">table_chart</span>
                Ir a Estimaciones
            </a>
        </div>
    </div>

    <div class="ea-card ea-filters-card" style="margin-bottom: 10px;">
        <div class="ea-card__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">filter_alt</span> Filtros de consulta
                <?php if($icf_total || $filtro_agencia !== '' || $filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                    <div class="ea-filter-tags" style="margin-left: 10px;">
                        <?php if($icf_total): ?>
                            <span class="ea-tag" style="background-color: #e3f2fd; color: #0d47a1; border-color: #90caf9;">ICF TOTAL (Todos los ciclos)</span>
                        <?php endif; ?>
                        <?php if($filtro_zona !== ''): ?>
                            <span class="ea-tag">Zona: <?php echo htmlspecialchars($filtro_zona); ?></span>
                        <?php endif; ?>
                        <?php if($filtro_agencia !== ''): ?>
                            <span class="ea-tag">Agencia: <?php echo htmlspecialchars($filtro_agencia); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($filtro_ciclo)): ?>
                            <span class="ea-tag">Ciclo: <?php echo htmlspecialchars(formatearRangoCiclos($filtro_ciclo)); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ea-card__body">
            <form method="GET" action="" class="ea-form calc-form" style="flex-wrap: wrap; display: flex; gap: 20px;">
                <input type="hidden" name="filtrado_aplicado" value="1">
                <?php if($icf_total): ?>
                    <input type="hidden" name="icf_total" value="1">
                <?php endif; ?>

                <div class="ea-form__group">
                    <label class="ea-form__label" for="mes_objetivo"><span class="material-symbols-rounded">calendar_month</span> Mes</label>
                    <select name="mes_objetivo" id="mes_objetivo" class="ea-form__control" style="width: 130px;" required>
                        <?php foreach ($nombres_meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($num === $p1_mes) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label" for="anio_objetivo"><span class="material-symbols-rounded">event</span> Año</label>
                    <input type="number" name="anio_objetivo" id="anio_objetivo" class="ea-form__control" style="width: 90px;" required
                           value="<?php echo $p1_anio; ?>" min="2000" max="2100">
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label" for="zona"><span class="material-symbols-rounded">location_on</span> Zona</label>
                    <select id="zona" name="zona" class="ea-form__control" style="width: 120px;" <?php echo (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') ? 'disabled' : ''; ?>>
                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin'): ?>
                            <option value="<?php echo htmlspecialchars($_SESSION['zona']); ?>" selected><?php echo htmlspecialchars($_SESSION['zona']); ?></option>
                        <?php else: ?>
                            <option value="">TODAS</option>
                            <?php foreach($zonas_disponibles as $z): ?>
                                <option value="<?php echo $z; ?>" <?php echo ($filtro_zona !== '' && (int)$filtro_zona === $z) ? 'selected' : ''; ?>>
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
                    <label class="ea-form__label" for="agencia"><span class="material-symbols-rounded">business</span> Agencia</label>
                    <select name="agencia" id="agencia" class="ea-form__control" style="width: 200px;">
                        <option value="">TODAS LAS AGENCIAS</option>
                        <?php foreach ($mapa_agencias as $letra => $nombre): 
                            if ((int)$filtro_zona === 1 && $letra === 'F') {
                                continue;
                            }
                        ?>
                            <option value="<?php echo $letra; ?>" <?php echo ($filtro_agencia === $letra) ? 'selected' : ''; ?>>
                                <?php echo $letra . ' - ' . $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label">
                        <span class="material-symbols-rounded">cycle</span>
                        Ciclos Bim. (1-40)
                    </label>
                    <div class="ea-dropdown-checkboxes" style="position: relative;">
                        <div class="ea-form__control ea-dropdown-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-width: 140px; background: #fff;">
                            <span class="ea-dropdown-text"><?php echo htmlspecialchars(obtenerTextoRangoFiltro($ciclos_bimestrales, $filtro_ciclo, $icf_total)); ?></span>
                            <span class="material-symbols-rounded" style="font-size: 1.2rem; pointer-events: none;">arrow_drop_down</span>
                        </div>
                        <div class="ea-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; max-height: 400px; overflow-y: auto; overflow-x: hidden; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10; margin-top: 5px;">
                            <div style="display: flex; gap: 5px; padding: 5px; margin-bottom: 5px;">
                                <button type="button" class="mode-rango active" style="flex:1; border: 1px solid var(--ea-primary); background: var(--ea-primary); color: #fff; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Rango</button>
                                <button type="button" class="mode-indiv" style="flex:1; border: 1px solid #ced4da; background: #f8f9fa; color: #333; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Individual</button>
                            </div>
                            <label style="display: block; margin-bottom: 2px; font-size: 0.9rem; cursor: pointer; padding: 5px; background: #f1f3f5;">
                                <input type="checkbox" class="chkSelectAllCiclo" /> <strong>(Seleccionar todo)</strong>
                            </label>
                            <hr style="margin: 4px 0; border-color: #eee;">
                            <?php foreach($ciclos_bimestrales as $c): 
                                $no_pasa_regla = !in_array($c, $ciclos_actuales_filtrados);
                                $lbl_style = $no_pasa_regla ? 'color: #d32f2f; font-weight: bold;' : '';
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
                        Ciclos Mens. (62-84)
                    </label>
                    <div class="ea-dropdown-checkboxes" style="position: relative;">
                        <div class="ea-form__control ea-dropdown-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-width: 140px; background: #fff;">
                            <span class="ea-dropdown-text"><?php echo htmlspecialchars(obtenerTextoRangoFiltro($ciclos_mensuales, $filtro_ciclo, $icf_total)); ?></span>
                            <span class="material-symbols-rounded" style="font-size: 1.2rem; pointer-events: none;">arrow_drop_down</span>
                        </div>
                        <div class="ea-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; max-height: 400px; overflow-y: auto; overflow-x: hidden; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10; margin-top: 5px;">
                            <div style="display: flex; gap: 5px; padding: 5px; margin-bottom: 5px;">
                                <button type="button" class="mode-rango active" style="flex:1; border: 1px solid var(--ea-primary); background: var(--ea-primary); color: #fff; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Rango</button>
                                <button type="button" class="mode-indiv" style="flex:1; border: 1px solid #ced4da; background: #f8f9fa; color: #333; border-radius: 4px; padding: 4px; cursor: pointer; font-size: 0.8rem;">Individual</button>
                            </div>
                            <label style="display: block; margin-bottom: 2px; font-size: 0.9rem; cursor: pointer; padding: 5px; background: #f1f3f5;">
                                <input type="checkbox" class="chkSelectAllCiclo" /> <strong>(Seleccionar todo)</strong>
                            </label>
                            <hr style="margin: 4px 0; border-color: #eee;">
                            <?php foreach($ciclos_mensuales as $c): 
                                $no_pasa_regla = !in_array($c, $ciclos_actuales_filtrados);
                                $lbl_style = $no_pasa_regla ? 'color: #d32f2f; font-weight: bold;' : '';
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
                        <span class="material-symbols-rounded">search</span> Comparar Ciclos
                    </button>
                    <?php if($icf_total || $filtro_agencia !== '' || $filtro_zona !== '' || !empty($filtro_ciclo)): ?>
                        <?php 
                            $limpiar_url = "?mes_objetivo=$p1_mes&anio_objetivo=$p1_anio";
                        ?>
                        <a href="<?php echo $limpiar_url; ?>" class="ea-btn ea-btn--danger">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($busqueda_activa): ?>
        <?php if (empty($ciclos_actuales)): ?>
            <div class="ea-alert-card" style="padding: 20px; background-color: #f8d7da; color: #842029; border-radius: 8px; margin-top: 20px; border: 1px solid #f1aeb5;">
                <span class="material-symbols-rounded" style="vertical-align: middle; margin-right: 8px;">error</span>
                No se encontraron datos de anomalías para el mes de <strong><?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?></strong>.
            </div>
        <?php else: ?>
            <div class="comp-table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--ea-primary); margin: 0;">Periodo: <span style="color: #2E7D32; font-weight: bold;"><?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?></span> vs <span style="color: #2E7D32; font-weight: bold;"><?php echo $lbl_comp; ?></span><?php echo ($filtro_agencia !== '') ? " (Agencia: $filtro_agencia - " . $mapa_agencias[$filtro_agencia] . ")" : ""; ?></h2>
                        <p style="font-size: 0.85rem; color: var(--ea-muted); margin: 0; margin-top: 2px;">Los valores corresponden a la diferencia (Actual - Anterior). Las celdas están coloreadas respecto al valor máximo absoluto de su columna.</p>
                    </div>
                </div>

                <?php /* ---- Contenedor flex: tabla principal (izq) + ranking (der) ---- */ ?>
                <div style="display:flex; align-items:flex-start; gap:52px; flex-wrap:nowrap;">

                    <!-- Tabla principal -->
                    <div class="comp-table-wrapper">
                    <table class="comp-table" id="tabla-comparacion-main">
                        <thead>
                            <tr>
                                <?php
                                $titulo_comparacion = "COMPARACIÓN — " . $nombres_meses[$p1_mes] . ' ' . $p1_anio . ' vs ' . $lbl_comp;
                                if ($filtro_agencia !== '') {
                                    $titulo_comparacion .= " — AGENCIA " . $filtro_agencia . " (" . $mapa_agencias[$filtro_agencia] . ")";
                                }
                                ?>
                                <th colspan="10" class="th-main-title"><?php echo $titulo_comparacion; ?></th>
                            </tr>
                            <tr>
                                <th class="th-ciclo">CICLO</th>
                                <?php
                                $anomalias_header_labels = [
                                    'cancelaciones'            => 'CAN',
                                    'estimaciones'             => 'ESTIM',
                                    'consumos_cero'            => 'CERO',
                                    'servicios_sin_medicion'   => 'SIN MED',
                                    'correcciones_de_lecturas' => 'CORR LECT',
                                    'anomalias_pendientes'     => 'ANOM PEN',
                                    'sin_facturar'             => 'SIN FACT',
                                    'cargas_directas'          => 'CARGAS DIR'
                                ];
                                $idx_h = 0;
                                foreach ($anomalias as $anomalia):
                                    $cls = ($idx_h % 2 === 0) ? 'th-group' : 'th-group-alt';
                                    $idx_h++;
                                    $lbl = $anomalias_header_labels[$anomalia] ?? strtoupper(str_replace('_', ' ', $anomalia));
                                ?>
                                    <th class="<?php echo $cls; ?>"><?php echo $lbl; ?></th>
                                <?php endforeach; ?>
                                <th class="th-defect">DEF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $prev_is_bim = null;
                            foreach ($ciclos_actuales as $c):
                                $cur_is_bim = ($c <= 61);
                                if ($prev_is_bim === true && !$cur_is_bim):
                            ?>
                                <tr class="row-separator">
                                    <td colspan="10">── CICLOS MENSUALES ──</td>
                                </tr>
                            <?php
                                endif;
                                $prev_is_bim = $cur_is_bim;
                            ?>
                                <tr>
                                    <td class="td-ciclo"><?php echo $c; ?></td>
                                    <?php foreach ($anomalias as $anomalia):
                                        $val   = $differences[$c][$anomalia];
                                        $style = getHeatmapStyle($val, $max_diffs_pos[$anomalia], $max_diffs_neg[$anomalia]);
                                    ?>
                                        <td style="<?php echo $style; ?>"><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                    <?php endforeach; ?>
                                    <?php
                                        $val_def   = $differences[$c]['defectos'];
                                        $style_def = getHeatmapStyle($val_def, $max_diffs_pos['defectos'], $max_diffs_neg['defectos']);
                                    ?>
                                    <td style="<?php echo $style_def; ?>"><?php echo ($val_def > 0 ? '+' : '') . $val_def; ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="row-summary">
                                <td>BIMESTRAL</td>
                                <?php foreach ($anomalias as $anomalia): $val = $sum_bimestral[$anomalia]; ?>
                                    <td><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo ($sum_bimestral['defectos'] > 0 ? '+' : '') . $sum_bimestral['defectos']; ?></td>
                            </tr>
                            <tr class="row-summary">
                                <td>MENSUAL</td>
                                <?php foreach ($anomalias as $anomalia): $val = $sum_mensual[$anomalia]; ?>
                                    <td><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo ($sum_mensual['defectos'] > 0 ? '+' : '') . $sum_mensual['defectos']; ?></td>
                            </tr>
                            <tr class="row-summary">
                                <td>TOTAL</td>
                                <?php foreach ($anomalias as $anomalia): 
                                    $val = $sum_total[$anomalia];
                                    $style_total = '';
                                    if ($val > 0) {
                                        $style_total = 'background: #FC0100 !important; color: #FFFFFF !important;';
                                    } elseif ($val < 0) {
                                        $style_total = 'background: #B5E6A5 !important; color: #000000 !important;';
                                    }
                                ?>
                                    <td style="<?php echo $style_total; ?>"><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <?php 
                                    $val_def = $sum_total['defectos'];
                                    $style_total_def = '';
                                    if ($val_def > 0) {
                                        $style_total_def = 'background: #FC0100 !important; color: #FFFFFF !important;';
                                    } elseif ($val_def < 0) {
                                        $style_total_def = 'background: #B5E6A5 !important; color: #000000 !important;';
                                    }
                                ?>
                                <td style="<?php echo $style_total_def; ?>"><?php echo ($val_def > 0 ? '+' : '') . $val_def; ?></td>
                            </tr>
                        </tbody>
                    </table>
                    </div><!-- /comp-table-wrapper tabla principal -->

                    <?php /* ---- Tabla ranking a la derecha ---- */ ?>
                    <?php if (!empty($ranking_por_agencia_y_opcion)): ?>
                    <div class="comp-table-wrapper" style="flex:0 0 auto; align-self:flex-start;">
                        <table class="comp-table" id="tabla-ranking-agencias">
                            <thead>
                                <tr>
                                    <th colspan="4" class="th-main-title">MAYOR AUMENTO POR AGENCIA</th>
                                </tr>
                                <tr>
                                    <th class="th-ciclo" style="padding:1px 4px !important; text-align:left;">AGENCIA</th>
                                    <th class="th-group" style="padding:1px 3px !important; position: relative;">
                                        <div style="display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;">
                                            CICLO
                                            <button type="button" id="btn-filtro-ranking-ciclo" style="background: transparent; border: none; color: inherit; cursor: pointer; display: flex; align-items: center; padding: 0;">
                                                <span class="material-symbols-rounded" style="font-size: 1.1rem; pointer-events: none;">arrow_drop_down</span>
                                            </button>
                                        </div>
                                        <!-- Dropdown Menu for Ciclo -->
                                        <div id="menu-filtro-ranking-ciclo" style="display: none; position: absolute; top: 100%; left: 0; min-width: 120px; background: #fff; border: 1px solid #ced4da; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; padding: 6px; text-align: left; font-weight: normal;">
                                            <label style="display: block; font-weight: bold; font-size: 0.75rem; color: #666; padding: 4px 8px; border-bottom: 1px solid #eee; margin-bottom: 4px;">FILTRAR CICLO</label>
                                            <button type="button" class="opt-filtro-ranking opt-filtro-ranking-ciclo active" data-value="todos">TODOS</button>
                                            <?php foreach ($ciclos_actuales as $c): ?>
                                                <button type="button" class="opt-filtro-ranking opt-filtro-ranking-ciclo" data-value="<?php echo $c; ?>"><?php echo $c; ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </th>
                                    <th class="th-group-alt" style="padding:1px 3px !important; position: relative;">
                                        <div style="display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;">
                                            DEFECTO
                                            <button type="button" id="btn-filtro-ranking-anomalia" style="background: transparent; border: none; color: inherit; cursor: pointer; display: flex; align-items: center; padding: 0;">
                                                <span class="material-symbols-rounded" style="font-size: 1.1rem; pointer-events: none;">arrow_drop_down</span>
                                            </button>
                                        </div>
                                        <!-- Dropdown Menu -->
                                        <div id="menu-filtro-ranking-anomalia" style="display: none; position: absolute; top: 100%; right: 0; min-width: 185px; background: #fff; border: 1px solid #ced4da; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; padding: 6px; text-align: left; font-weight: normal;">
                                            <label style="display: block; font-weight: bold; font-size: 0.75rem; color: #666; padding: 4px 8px; border-bottom: 1px solid #eee; margin-bottom: 4px;">FILTRAR DEFECTO</label>
                                            <button type="button" class="opt-filtro-ranking active" data-value="default">TOTAL</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="cancelaciones">CANCELACIONES</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="estimaciones">ESTIMACIONES</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="consumos_cero">CONSUMO CERO</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="servicios_sin_medicion">SIN MEDICIÓN</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="correcciones_de_lecturas">CORR. LECTURA</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="anomalias_pendientes">ANOM. PENDIENTES</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="sin_facturar">SIN FACTURAR</button>
                                            <button type="button" class="opt-filtro-ranking" data-value="cargas_directas">CARGAS DIRECTAS</button>
                                        </div>
                                    </th>
                                    <th class="th-defect" style="padding:1px 3px !important;">DIF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Ordenar las agencias por su valor default descendente
                                $agencias_ordenadas = array_keys($mapa_agencias);
                                if ((int)$filtro_zona === 1) {
                                      $agencias_ordenadas = array_filter($agencias_ordenadas, function($a) { return $a !== 'F'; });
                                }
                                usort($agencias_ordenadas, function($a, $b) use ($ranking_por_agencia_y_opcion) {
                                    $valA = $ranking_por_agencia_y_opcion[$a]['opciones']['default']['valor'] ?? 0;
                                    $valB = $ranking_por_agencia_y_opcion[$b]['opciones']['default']['valor'] ?? 0;
                                    return $valB - $valA;
                                });
                                
                                foreach ($agencias_ordenadas as $letra): 
                                    $nombre = $mapa_agencias[$letra];
                                    $info = $ranking_por_agencia_y_opcion[$letra]['opciones']['default'] ?? null;
                                    $val = $info ? (int)$info['valor'] : 0;
                                    $formatted_val = ($val > 0 ? '+' : '') . $val;
                                    if ($val > 0) {
                                        $bg_style = 'background:#F26A6A !important; color:#000 !important;';
                                    } elseif ($val < 0) {
                                        $bg_style = 'background:#95D081 !important; color:#000 !important;';
                                    } else {
                                        $bg_style = 'background:#FFF9D0 !important; color:#000 !important;';
                                    }
                                ?>
                                <tr class="row-ranking-agencia" data-agencia="<?php echo $letra; ?>">
                                    <td class="td-ciclo td-ranking-nombre" style="text-align:left !important; padding:1px 5px !important;"><?php echo $letra . ' — ' . $nombre; ?></td>
                                    <td class="td-ciclo td-ranking-ciclo" style="padding:1px 3px !important;"><?php echo $info ? $info['ciclo'] : ''; ?></td>
                                    <td class="td-ranking-anomalia" style="padding:1px 3px !important;"><?php echo $info ? $info['anomalia'] : ''; ?></td>
                                    <td class="td-ranking-valor" style="<?php echo $bg_style; ?> font-weight:bold; padding:1px 3px !important;"><?php echo $formatted_val; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><!-- /comp-table-wrapper ranking -->
                    <?php endif; ?>

                </div><!-- /flex-row -->
            </div><!-- /comp-table-card -->

        <?php endif; ?>
    <?php endif; ?>


</main>

<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
    // Lógica para dropdowns checkboxes de ciclos
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
            const checked = chkCiclos.filter(c => c.checked && !c.disabled);
            
            if (checked.length === 0) {
                text.textContent = 'Ninguno';
            } else if (checked.length === 1) {
                text.textContent = checked[0].value;
            } else {
                const vals = checked.map(c => parseInt(c.value)).sort((a, b) => a - b);
                text.textContent = vals[0] + ' AL ' + vals[vals.length - 1];
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

                const checkedIndices = chkCiclos.map((c, i) => (c.checked && !c.disabled) ? i : -1).filter(idx => idx !== -1);
                if (checkedIndices.length > 0) {
                    const minIdx = Math.min(...checkedIndices);
                    const maxIdx = Math.max(...checkedIndices);
                    for (let i = minIdx; i <= maxIdx; i++) {
                        if (!chkCiclos[i].disabled) {
                            chkCiclos[i].checked = true;
                        }
                    }
                    const enabled = chkCiclos.filter(c => !c.disabled);
                    chkSelectAll.checked = enabled.length > 0 && enabled.every(c => c.checked);
                    updateText();
                }
            });
        }

        chkSelectAll.addEventListener('change', (e) => {
            chkCiclos.forEach(chk => {
                if (!chk.disabled) {
                    chk.checked = e.target.checked;
                }
            });
            updateText();
            rangeStartIdx = null;
            lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
        });

        chkCiclos.forEach((chk, idx) => {
            chk.addEventListener('change', (e) => {
                if (mode === 'rango') {
                    if (rangeStartIdx === null) {
                        const valToSet = chkCiclos[idx].checked;
                        for (let i = 0; i <= idx; i++) {
                            if (!chkCiclos[i].disabled) {
                                chkCiclos[i].checked = valToSet;
                            }
                        }
                        rangeStartIdx = idx;
                        lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                        lblCiclos[idx].style.backgroundColor = '#e2e3e5';
                    } else {
                        const start = Math.min(rangeStartIdx, idx);
                        const end = Math.max(rangeStartIdx, idx);
                        const valToSet = chkCiclos[rangeStartIdx].checked;
                        for (let i = start; i <= end; i++) {
                            if (!chkCiclos[i].disabled) {
                                chkCiclos[i].checked = valToSet;
                            }
                        }
                        rangeStartIdx = null;
                        lblCiclos.forEach(lbl => lbl.style.backgroundColor = 'transparent');
                    }
                }
                const enabled = chkCiclos.filter(c => !c.disabled);
                chkSelectAll.checked = enabled.length > 0 && enabled.every(c => c.checked);
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

        if(chkCiclos.length > 0) {
            const enabled = chkCiclos.filter(c => !c.disabled);
            chkSelectAll.checked = enabled.length > 0 && enabled.every(c => c.checked);
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

    // MD3 Loading overlay al enviar el form
    document.querySelector('.calc-form').addEventListener('submit', function() {
        const overlay = document.createElement('div');
        overlay.className = 'ea-page-overlay';
        overlay.innerHTML = `
            <div class="ea-page-overlay__card">
                <div class="ea-spinner ea-spinner--lg"></div>
                <span class="ea-spinner-text">Procesando comparación\u2026</span>
                <span class="ea-spinner-subtext">Calculando diferencias y mapa de calor</span>
            </div>
        `;
        document.body.appendChild(overlay);
    });

    // ── Filtrado dinámico de ciclos por zona + agencia ─────────────────────
    // Cuando cambia zona O agencia, oculta ciclos sin datos para esa combinación.
    // No toca el estilo rojo/negrilla de la regla $no_pasa_regla.
    (function () {
        const ciclosPorZona          = <?php echo json_encode($ciclos_por_zona, JSON_NUMERIC_CHECK); ?>;
        const ciclosPorZonaAgencia   = <?php echo json_encode($ciclos_por_zona_agencia, JSON_NUMERIC_CHECK); ?>;

        const selectZona    = document.getElementById('zona');
        const selectAgencia = document.getElementById('agencia');
        if (!selectZona) return;

        function calcularCiclosDisponibles() {
            const zonaVal    = selectZona.value;                          // '' = TODAS
            const agenciaVal = selectAgencia ? selectAgencia.value : ''; // '' = TODAS

            // Caso 1: zona + agencia seleccionadas → intersección
            if (zonaVal !== '' && agenciaVal !== '') {
                const porZonaAg = ciclosPorZonaAgencia[zonaVal];
                return (porZonaAg && porZonaAg[agenciaVal]) ? porZonaAg[agenciaVal] : [];
            }

            // Caso 2: solo zona
            if (zonaVal !== '') {
                return (ciclosPorZona[zonaVal]) ? ciclosPorZona[zonaVal] : [];
            }

            // Caso 3: solo agencia
            if (agenciaVal !== '') {
                const porAgencia = ciclosPorZonaAgencia[''];
                return (porAgencia && porAgencia[agenciaVal]) ? porAgencia[agenciaVal] : null;
            }

            // Caso 4: ninguno → sin restricción
            return null;
        }

        function aplicarFiltroZonaCiclos() {
            const ciclosDisponibles = calcularCiclosDisponibles();

            document.querySelectorAll('.chk-ciclo').forEach(function (chk) {
                const ciclo = parseInt(chk.value);
                const lbl = chk.closest('label');
                const sinDatos = ciclosDisponibles !== null && !ciclosDisponibles.includes(ciclo);

                if (sinDatos) {
                    chk.disabled = true;
                    chk.checked = false;
                    if (lbl) lbl.style.display = 'none';
                } else {
                    chk.disabled = false;
                    if (lbl) lbl.style.display = '';
                }
            });

            document.querySelectorAll('.ea-dropdown-checkboxes').forEach(function (dropdown) {
                const chkAll = dropdown.querySelector('.chkSelectAllCiclo');
                const chks = Array.from(dropdown.querySelectorAll('.chk-ciclo'));
                const textEl = dropdown.querySelector('.ea-dropdown-text');
                if (!chkAll || chks.length === 0) return;

                const enabled = chks.filter(c => !c.disabled);
                chkAll.checked = enabled.length > 0 && enabled.every(c => c.checked);

                const checked = chks.filter(c => c.checked && !c.disabled);
                if (textEl) {
                    if (checked.length === 0) {
                        textEl.textContent = 'Ninguno';
                    } else if (checked.length === 1) {
                        textEl.textContent = checked[0].value;
                    } else {
                        const vals = checked.map(c => parseInt(c.value)).sort((a, b) => a - b);
                        textEl.textContent = vals[0] + ' AL ' + vals[vals.length - 1];
                    }
                }
            });
        }

        selectZona.addEventListener('change', aplicarFiltroZonaCiclos);
        if (selectAgencia) selectAgencia.addEventListener('change', aplicarFiltroZonaCiclos);

        // Aplicar al cargar si ya hay filtros seleccionados
        aplicarFiltroZonaCiclos();
    })();
    // ──────────────────────────────────────────────────────

    async function exportarExcelComparacion() {
        const table = document.getElementById('tabla-comparacion-main');
        if (!table) return;
        
        // Mostrar overlay de carga mientras se genera el Excel
        const overlay = document.createElement('div');
        overlay.className = 'ea-page-overlay';
        overlay.innerHTML = `
            <div class="ea-page-overlay__card">
                <div class="ea-spinner ea-spinner--lg"></div>
                <span class="ea-spinner-text">Generando archivo Excel\u2026</span>
                <span class="ea-spinner-subtext">Creando libro de trabajo y aplicando estilos</span>
            </div>
        `;
        document.body.appendChild(overlay);

        try {
            const wb = new ExcelJS.Workbook();
            const ws = wb.addWorksheet('Comparación');

            const B = {style:'thin', color:{argb:'FF000000'}};
            const borders = {top:B,bottom:B,left:B,right:B};

            function rgbA(s){
                if(!s||s==='transparent'||s.includes('0, 0, 0, 0')) return null;
                const m=s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                if(!m) return null;
                return 'FF'+[m[1],m[2],m[3]].map(n=>parseInt(n).toString(16).padStart(2,'0')).join('').toUpperCase();
            }

            // Grid ocupado por rowspan/colspan
            const occ={};
            const isO=(r,c)=>occ[r+','+c]===true;
            const setO=(r,c)=>{occ[r+','+c]=true;};

            let exR=1;
            const allTrs = table.querySelectorAll('thead tr, tbody tr');
            const totalColSpan = 10;

            allTrs.forEach(tr => {
                let col=1;
                
                tr.querySelectorAll('th, td').forEach(cell => {
                    while(isO(exR,col)) col++;
                    
                    let cs = parseInt(cell.getAttribute('colspan') || 1);
                    const rs = parseInt(cell.getAttribute('rowspan') || 1);

                    if (cs >= 50) {
                        cs = totalColSpan;
                    }

                    const comp = window.getComputedStyle(cell);
                    let bg = rgbA(comp.backgroundColor);
                    let fc = rgbA(comp.color) || 'FF000000';
                    let bold = comp.fontWeight === 'bold' || parseInt(comp.fontWeight) >= 600;
                    let sz = 10;
                    
                    if (cell.classList.contains('th-main-title')) { 
                        sz = 13; 
                    } else if (cell.tagName === 'TH') {
                        sz = 11;
                    }

                    let t = cell.textContent.trim();
                    let val = t;
                    let isNumeric = false;
                    
                    if (t !== '') {
                        let cleaned = t;
                        if (t.startsWith('+')) {
                            cleaned = t.substring(1);
                        }
                        // Validar si es numérico
                        if (!isNaN(cleaned) && !cleaned.includes('%')) {
                            val = parseFloat(cleaned);
                            isNumeric = true;
                        }
                    }

                    const ec = ws.getCell(exR, col);
                    ec.value = val;
                    
                    if (isNumeric && col > 1) {
                        ec.numFmt = '+#,##0;-#,##0;0';
                    } else if (isNumeric) {
                        ec.numFmt = '#,##0';
                    }

                    let align = { horizontal: 'center', vertical: 'middle', wrapText: true };
                    
                    // Si es el título o el separador mensual, alinear al centro
                    if (cell.classList.contains('th-main-title') || cell.parentElement.classList.contains('row-separator')) {
                        align.horizontal = 'center';
                        align.wrapText = false;
                    }
                    
                    ec.alignment = align;
                    ec.border = borders;
                    if (bg) {
                        ec.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                    }
                    ec.font = { name: 'Arial', size: sz, bold: bold, color: { argb: fc } };

                    // Merge
                    if (cs > 1 || rs > 1) {
                        ws.mergeCells(exR, col, exR + rs - 1, col + cs - 1);
                        for (let r = 0; r < rs; r++) {
                            for (let c = 0; c < cs; c++) {
                                if (r === 0 && c === 0) continue;
                                const mc = ws.getCell(exR + r, col + c);
                                mc.border = borders;
                                if (bg) mc.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                            }
                        }
                    }

                    for(let r=0;r<rs;r++) for(let c=0;c<cs;c++) if(r>0||c>0) setO(exR+r,col+c);
                    col+=cs;
                });
                exR++;
            });

            // Configurar anchos de columna
            ws.getColumn(1).width = 12; // CICLO
            for (let c = 2; c <= 10; c++) {
                ws.getColumn(c).width = 14; // Anomalías y defectos
            }

            // Alturas de filas
            ws.eachRow((row, idx) => {
                if (idx === 1) {
                    row.height = 32;
                } else if (idx === 2) {
                    row.height = 26;
                } else {
                    // Si es la fila separadora, hacerla un poco más alta
                    const cellVal = row.getCell(1).value;
                    if (typeof cellVal === 'string' && cellVal.includes('──')) {
                        row.height = 22;
                    } else {
                        row.height = 18;
                    }
                }
            });

            const buf = await wb.xlsx.writeBuffer();
            const blob = new Blob([buf], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'comparacion_ciclos_<?php echo $p1_anio . str_pad($p1_mes, 2, "0", STR_PAD_LEFT) . ($filtro_agencia !== "" ? "_" . $filtro_agencia : ""); ?>.xlsx';
            a.click();
            URL.revokeObjectURL(a.href);
        } catch (err) {
            console.error(err);
            alert('Error al generar el archivo Excel.');
        } finally {
            document.body.removeChild(overlay);
        }
    }

    // Lógica para filtro de anomalía y ciclo en tabla de ranking
    const rankingData = <?php echo json_encode($ranking_por_agencia_y_opcion ?? []); ?>;
    (function() {
        let selectedAnomalia = 'default';
        let selectedCiclo = 'todos';

        const btnFiltro = document.getElementById('btn-filtro-ranking-anomalia');
        const menuFiltro = document.getElementById('menu-filtro-ranking-anomalia');
        const btnFiltroCiclo = document.getElementById('btn-filtro-ranking-ciclo');
        const menuFiltroCiclo = document.getElementById('menu-filtro-ranking-ciclo');

        if (btnFiltro && menuFiltro) {
            btnFiltro.addEventListener('click', function(e) {
                e.stopPropagation();
                // Cerrar el otro si está abierto
                if (menuFiltroCiclo) menuFiltroCiclo.style.display = 'none';

                const isHidden = menuFiltro.style.display === 'none';
                if (isHidden) {
                    if (menuFiltro.parentNode !== document.body) {
                        document.body.appendChild(menuFiltro);
                    }
                    menuFiltro.style.display = 'block';
                    menuFiltro.style.width = '185px';
                    menuFiltro.style.right = 'auto';
                    
                    const rect = btnFiltro.getBoundingClientRect();
                    menuFiltro.style.position = 'absolute';
                    menuFiltro.style.top = (rect.bottom + window.scrollY) + 'px';
                    
                    const menuWidth = menuFiltro.offsetWidth;
                    menuFiltro.style.left = (rect.right + window.scrollX - menuWidth) + 'px';
                } else {
                    menuFiltro.style.display = 'none';
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('#menu-filtro-ranking-anomalia') && e.target !== btnFiltro) {
                    menuFiltro.style.display = 'none';
                }
            });

            const opciones = menuFiltro.querySelectorAll('.opt-filtro-ranking');
            opciones.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    opciones.forEach(o => {
                        o.classList.remove('active');
                        o.style.background = 'transparent';
                        o.style.color = '#333';
                        o.style.fontWeight = 'normal';
                    });
                    opt.classList.add('active');
                    opt.style.background = '#E8F0FE';
                    opt.style.color = '#1A73E8';
                    opt.style.fontWeight = '600';

                    selectedAnomalia = opt.getAttribute('data-value');
                    actualizarYOrdenarRanking();
                    
                    menuFiltro.style.display = 'none';
                });
            });
        }

        if (btnFiltroCiclo && menuFiltroCiclo) {
            btnFiltroCiclo.addEventListener('click', function(e) {
                e.stopPropagation();
                // Cerrar el otro si está abierto
                if (menuFiltro) menuFiltro.style.display = 'none';

                const isHidden = menuFiltroCiclo.style.display === 'none';
                if (isHidden) {
                    if (menuFiltroCiclo.parentNode !== document.body) {
                        document.body.appendChild(menuFiltroCiclo);
                    }
                    menuFiltroCiclo.style.display = 'block';
                    menuFiltroCiclo.style.width = '120px';
                    menuFiltroCiclo.style.right = 'auto';
                    
                    const rect = btnFiltroCiclo.getBoundingClientRect();
                    menuFiltroCiclo.style.position = 'absolute';
                    menuFiltroCiclo.style.top = (rect.bottom + window.scrollY) + 'px';
                    
                    const menuWidth = menuFiltroCiclo.offsetWidth;
                    menuFiltroCiclo.style.left = (rect.right + window.scrollX - menuWidth) + 'px';
                } else {
                    menuFiltroCiclo.style.display = 'none';
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('#menu-filtro-ranking-ciclo') && e.target !== btnFiltroCiclo) {
                    menuFiltroCiclo.style.display = 'none';
                }
            });

            const opcionesCiclo = menuFiltroCiclo.querySelectorAll('.opt-filtro-ranking-ciclo');
            opcionesCiclo.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    opcionesCiclo.forEach(o => {
                        o.classList.remove('active');
                        o.style.background = 'transparent';
                        o.style.color = '#333';
                        o.style.fontWeight = 'normal';
                    });
                    opt.classList.add('active');
                    opt.style.background = '#E8F0FE';
                    opt.style.color = '#1A73E8';
                    opt.style.fontWeight = '600';

                    selectedCiclo = opt.getAttribute('data-value');
                    actualizarYOrdenarRanking();
                    
                    menuFiltroCiclo.style.display = 'none';
                });
            });
        }

        function actualizarYOrdenarRanking() {
            const tbody = document.querySelector('#tabla-ranking-agencias tbody');
            if (!tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll('.row-ranking-agencia'));
            
            const listAnomalias = <?php echo json_encode($anomalias); ?>;
            const labelsAnomalias = <?php echo json_encode($anomalias_labels); ?>;
            const listCiclos = <?php echo json_encode($ciclos_actuales); ?>;

            rows.forEach(row => {
                const letra = row.getAttribute('data-agencia');
                if (!rankingData[letra]) return;
                
                const diffs_agencia = rankingData[letra]['valores'] || {};
                
                let bestCiclo = null;
                let bestAnomalia = null;
                let bestVal = -999999;
                
                // Determinar ciclos a evaluar
                let ciclosEvaluar = [];
                if (selectedCiclo === 'todos') {
                    ciclosEvaluar = listCiclos;
                } else {
                    ciclosEvaluar = [parseInt(selectedCiclo)];
                }
                
                // Determinar anomalías a evaluar
                let anomaliasEvaluar = [];
                if (selectedAnomalia === 'default') {
                    anomaliasEvaluar = listAnomalias.filter(a => a !== 'estimaciones' && a !== 'consumos_cero');
                } else {
                    anomaliasEvaluar = [selectedAnomalia];
                }
                
                // Encontrar el peor aumento (máximo valor)
                anomaliasEvaluar.forEach(anom => {
                    ciclosEvaluar.forEach(c => {
                        const val = (diffs_agencia[anom] && diffs_agencia[anom][c] !== undefined) ? Number(diffs_agencia[anom][c]) : 0;
                        if (val > bestVal) {
                            bestVal = val;
                            bestCiclo = c;
                            bestAnomalia = anom;
                        }
                    });
                });
                
                const valCell = row.querySelector('.td-ranking-valor');
                if (bestCiclo !== null && bestAnomalia !== null && bestVal !== -999999) {
                    row.querySelector('.td-ranking-ciclo').textContent = bestCiclo;
                    row.querySelector('.td-ranking-anomalia').textContent = labelsAnomalias[bestAnomalia] || bestAnomalia.toUpperCase();
                    const val = bestVal;
                    valCell.textContent = (val > 0 ? '+' : '') + val.toLocaleString();
                    
                    if (val > 0) {
                        valCell.style.cssText = 'background:#F26A6A !important; color:#000 !important; font-weight:bold; padding:1px 3px !important;';
                    } else if (val < 0) {
                        valCell.style.cssText = 'background:#95D081 !important; color:#000 !important; font-weight:bold; padding:1px 3px !important;';
                    } else {
                        valCell.style.cssText = 'background:#FFF9D0 !important; color:#000 !important; font-weight:bold; padding:1px 3px !important;';
                    }
                    
                    row.style.display = '';
                    row.setAttribute('data-val', bestVal);
                } else {
                    row.querySelector('.td-ranking-ciclo').textContent = '-';
                    row.querySelector('.td-ranking-anomalia').textContent = '-';
                    valCell.textContent = '0';
                    valCell.style.cssText = 'background:#FFF9D0 !important; color:#000 !important; font-weight:bold; padding:1px 3px !important;';
                    row.style.display = '';
                    row.setAttribute('data-val', 0);
                }
            });
            
            // Re-ordenar las filas en el DOM
            const sortedRows = rows.sort((a, b) => {
                const valA = parseInt(a.getAttribute('data-val') || 0);
                const valB = parseInt(b.getAttribute('data-val') || 0);
                return valB - valA;
            });
            
            // Re-insertar en tbody
            sortedRows.forEach(row => tbody.appendChild(row));
        }

        // Dinámicamente deshabilitar/ocultar MOTUL (F) del dropdown de agencias si la zona es 1
        const selectZona = document.getElementById('zona');
        const selectAgencia = document.getElementById('agencia');
        
        function actualizarAgenciasPorZona() {
            if (!selectZona || !selectAgencia) return;
            const zonaVal = selectZona.value;
            const optMotul = selectAgencia.querySelector('option[value="F"]');
            if (optMotul) {
                if (zonaVal === '1') {
                    optMotul.disabled = true;
                    optMotul.style.display = 'none';
                    if (selectAgencia.value === 'F') {
                        selectAgencia.value = '';
                    }
                } else {
                    optMotul.disabled = false;
                    optMotul.style.display = '';
                }
            }
        }
        
        if (selectZona) {
            selectZona.addEventListener('change', actualizarAgenciasPorZona);
            actualizarAgenciasPorZona();
        }
    })();
</script>
</body>
</html>
