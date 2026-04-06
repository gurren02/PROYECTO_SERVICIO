<?php
/**
 * get_reincidentes_zona.php
 * Calcula reincidentes via intersección en PHP — evita JOIN sin índice en MySQL.
 *
 * Estrategia:
 *   1. Cargar el SET de RPUs de la tabla de comparación en un array PHP (O(n)).
 *   2. Leer la tabla actual fila a fila, verificando contra el set PHP (O(1) por fila).
 *   Esto es fundamentalmente más rápido que un JOIN en MySQL sin índices.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include "../src/seguridad.php";
require "../config/conexion.php";

set_time_limit(45);
header('Content-Type: application/json; charset=utf-8');

// Parámetros
$p1_mes  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$p1_anio = isset($_GET['a']) ? (int)$_GET['a'] : (int)date('Y');
$tipo_comp      = (isset($_GET['comp']) && $_GET['comp'] === 'anual') ? 'anual' : 'bimestre';
$filtro_zona    = isset($_GET['zona'])           ? trim($_GET['zona'])           : '';
$ciclo_inicio   = isset($_GET['ciclo_inicio'])   ? trim($_GET['ciclo_inicio'])   : '';
$ciclo_fin      = isset($_GET['ciclo_fin'])      ? trim($_GET['ciclo_fin'])      : '';
$ciclo_inicio_2 = isset($_GET['ciclo_inicio_2']) ? trim($_GET['ciclo_inicio_2']) : '';
$ciclo_fin_2    = isset($_GET['ciclo_fin_2'])    ? trim($_GET['ciclo_fin_2'])    : '';

// Calcular sufijos
$p3_mes = $p1_mes - 2; $p3_anio = $p1_anio;
if ($p3_mes <= 0) { $p3_mes += 12; $p3_anio -= 1; }

$sufijo_actual = $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
if ($tipo_comp === 'anual') {
    $sufijo_comp = ($p1_anio - 1) . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
} else {
    $sufijo_comp = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);
}

$mapa_agencias = [
    'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
    'G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
];

$anomalias = ['cancelaciones','estimaciones','consumos_cero','servicios_sin_medicion',
              'correcciones_de_lecturas','anomalias_pendientes','sin_facturar','cargas_directas'];

// Una sola verificación de tablas
$todas_las_tablas = array_flip($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));

// Helper: construir WHERE de ciclos
function buildCicloCol($ciclo_inicio, $ciclo_fin, $ciclo_inicio_2, $ciclo_fin_2, &$params) {
    $conds = [];
    if ($ciclo_inicio !== '' && $ciclo_fin !== '') {
        $conds[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_inicio; $params[] = (int)$ciclo_fin;
    } elseif ($ciclo_inicio !== '') {
        $conds[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_inicio;
    }
    if ($ciclo_inicio_2 !== '' && $ciclo_fin_2 !== '') {
        $conds[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_inicio_2; $params[] = (int)$ciclo_fin_2;
    } elseif ($ciclo_inicio_2 !== '') {
        $conds[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_inicio_2;
    }
    return empty($conds) ? '' : " AND (" . implode(" OR ", $conds) . ")";
}

$resultado = []; // [zona][agencia][anomalia] = total

foreach ($anomalias as $anomalia) {
    $tabla_actual = $anomalia . $sufijo_actual;
    $tabla_comp   = $anomalia . $sufijo_comp;

    if (!isset($todas_las_tablas[$tabla_actual]) || !isset($todas_las_tablas[$tabla_comp])) continue;

    try {
        // ── PASO 1: Cargar el SET de RPUs de la tabla de comparación ─────────────
        // SELECT solo la columna Rpu — mucho más rápido que un JOIN completo.
        // Verificamos si existe la columna Rpu primero.
        $cols_comp = $pdo->query("SHOW COLUMNS FROM `$tabla_comp` LIKE 'Rpu'")->fetchAll();
        $cols_actual = $pdo->query("SHOW COLUMNS FROM `$tabla_actual` LIKE 'Rpu'")->fetchAll();
        if (empty($cols_comp) || empty($cols_actual)) continue;

        $stmt_comp = $pdo->query(
            "SELECT TRIM(`Rpu`) FROM `$tabla_comp`
             WHERE `Rpu` IS NOT NULL AND TRIM(`Rpu`) != ''
             LIMIT 200000"
        );
        // HashSet: O(1) lookup, sin consumir RAM excesiva
        $rpu_set = [];
        while ($rpu = $stmt_comp->fetchColumn()) {
            $rpu_set[$rpu] = true;
        }

        if (empty($rpu_set)) continue;

        // ── PASO 2: Leer tabla actual con filtros aplicados ─────────────────────
        $where = "WHERE `Rpu` IS NOT NULL AND TRIM(`Rpu`) != ''";
        $params = [];

        if ($filtro_zona !== '') {
            $where .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
            $params[] = (int)$filtro_zona;
        }
        $where .= buildCicloCol($ciclo_inicio, $ciclo_fin, $ciclo_inicio_2, $ciclo_fin_2, $params);

        $stmt_actual = $pdo->prepare(
            "SELECT TRIM(`Rpu`) as rpu, TRIM(`Zona`) as zona, UPPER(TRIM(`Agencia`)) as agencia
             FROM `$tabla_actual` $where"
        );
        $stmt_actual->execute($params);

        // ── PASO 3: Intersección en PHP ─────────────────────────────────────────
        $conteo = []; // [zona][agencia] = set de RPUs reincidentes únicos
        while ($row = $stmt_actual->fetch(PDO::FETCH_ASSOC)) {
            $rpu  = $row['rpu'];
            $zona = $row['zona'];
            $ag   = $row['agencia'];
            if ($zona === '' || $zona === null) continue;
            if (!isset($rpu_set[$rpu])) continue; // No reincidente

            // Mapear agencia
            if (array_key_exists($ag, $mapa_agencias)) $nombre_ag = $mapa_agencias[$ag];
            elseif (in_array($ag, $mapa_agencias))     $nombre_ag = $ag;
            else                                        $nombre_ag = 'OTRA';

            // Contar RPUs únicos por zona+agencia
            if (!isset($conteo[$zona][$nombre_ag][$rpu])) {
                $conteo[$zona][$nombre_ag][$rpu] = true;
            }
        }

        // ── PASO 4: Convertir sets a conteos ────────────────────────────────────
        foreach ($conteo as $zona => $agencias) {
            foreach ($agencias as $ag => $rpus) {
                $total = count($rpus);
                if (!isset($resultado[$zona][$ag][$anomalia])) {
                    $resultado[$zona][$ag][$anomalia] = 0;
                }
                $resultado[$zona][$ag][$anomalia] += $total;
            }
        }

    } catch (PDOException $e) {
        // Ignorar tablas sin columna Rpu u otros errores de esquema
    }
}

echo json_encode(['ok' => true, 'data' => $resultado], JSON_UNESCAPED_UNICODE);
