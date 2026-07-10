<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$tabla   = isset($_GET['tabla'])   ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';
$tablacomp = isset($_GET['tablacomp']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tablacomp']) : '';
$modo    = isset($_GET['modo'])    ? trim($_GET['modo']) : 'normal';
$agencia = isset($_GET['agencia']) ? trim($_GET['agencia']) : '';
$zona    = isset($_GET['zona'])    ? trim($_GET['zona'])    : '';

// Ciclo único o múltiple
$filtro_ciclo = [];
if (isset($_GET['ciclo'])) {
    if (is_array($_GET['ciclo'])) {
        $filtro_ciclo = $_GET['ciclo'];
    } else {
        $filtro_ciclo = array_filter(explode(',', (string)$_GET['ciclo']), 'strlen');
    }
}

if ($tabla === '') {
    echo "Error: Tabla no especificada.";
    exit;
}

$mapa_agencias = [
    'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
    'F'=>'MOTUL','G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
];

try {
    // Traducir nombre de agencia a letra si es necesario
    $agencia_busqueda = strtoupper($agencia);
    if (in_array($agencia_busqueda, $mapa_agencias)) {
        $agencia_busqueda = array_search($agencia_busqueda, $mapa_agencias);
    }

    $stmt_cols = $pdo->query("SHOW COLUMNS FROM `$tabla` ");
    $columnas = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

    $where = "WHERE 1=1";
    $where .= " AND NOT (CAST(TRIM(`Zona`) AS UNSIGNED) = 1 AND (UPPER(TRIM(`Agencia`)) = 'F' OR UPPER(TRIM(`Agencia`)) = 'MOTUL'))";
    $params = [];

    if ($agencia !== '') {
        $where .= " AND UPPER(TRIM(`Agencia`)) = ?";
        $params[] = $agencia_busqueda;
    }
    if ($zona !== '') {
        $where .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
        $params[] = (int)$zona;
    }

    // Lógica de Ciclo Múltiple
    if (!empty($filtro_ciclo)) {
        $placeholders = [];
        foreach ($filtro_ciclo as $c) {
            $params[] = (int)$c;
            $placeholders[] = '?';
        }
        $ph = implode(',', $placeholders);
        $where .= " AND CAST(TRIM(`Ciclo`) AS UNSIGNED) IN ($ph)";
    }

    if ($modo === 'reincidente' && $tablacomp !== '') {
        $where .= " AND TRIM(`Rpu`) IN (SELECT TRIM(`Rpu`) FROM `$tablacomp` WHERE `Rpu` IS NOT NULL AND TRIM(`Rpu`) != '')";
    }

    $stmt = $pdo->prepare("SELECT * FROM `$tabla` $where LIMIT 500");
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "<div style='padding:20px; text-align:center;'>No se encontraron registros detallados para los filtros aplicados.</div>";
        exit;
    }

    // Calcular reincidencia histórica
    $recurrences = [];
    if ($modo === 'reincidente') {
        $prefix = preg_replace('/[0-9]+$/', '', $tabla);
        $stmt_tables = $pdo->query("SHOW TABLES LIKE '{$prefix}%'");
        $history_tables = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);

        $rpus = [];
        foreach ($filas as $fila) {
            if (isset($fila['Rpu'])) {
                $rpu = trim($fila['Rpu']);
                if ($rpu !== '') {
                    $rpus[$rpu] = true;
                }
            }
        }
        
        if (!empty($rpus)) {
            $rpus_str = implode(',', array_map(function($r) { return "'" . $r . "'"; }, array_keys($rpus)));
            foreach ($history_tables as $ht) {
                try {
                    $q = $pdo->query("SELECT TRIM(`Rpu`) as rpu, COUNT(*) as cnt FROM `$ht` WHERE TRIM(`Rpu`) IN ($rpus_str) GROUP BY TRIM(`Rpu`)");
                    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                        $rpu_val = $r['rpu'];
                        if (!isset($recurrences[$rpu_val])) $recurrences[$rpu_val] = 0;
                        $recurrences[$rpu_val] += (int)$r['cnt'];
                    }
                } catch (Exception $e) {}
            }
        }
    }

    echo '<div class="ea-table-wrapper" style="max-height: 60vh; overflow: auto; border: 1px solid var(--ea-border); border-radius: 8px;">';
    echo '<table class="ea-table ea-table--detailed" style="font-size: 0.75rem;">';
    echo '<thead><tr class="ea-table__head-cols">';
    if ($modo === 'reincidente') echo '<th style="text-align:center;">REINCIDENCIA</th>';
    foreach ($columnas as $col) { echo '<th>' . htmlspecialchars($col) . '</th>'; }
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        if ($modo === 'reincidente') {
            $rpu = isset($fila['Rpu']) ? trim($fila['Rpu']) : '';
            $reinc_count = isset($recurrences[$rpu]) ? $recurrences[$rpu] : 1;
            echo '<td style="text-align:center;">';
            echo '<span class="reinc-history-badge" ';
            echo 'style="display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; background-color:#dc3545; color:white; border-radius:50%; font-weight:bold; font-size:11px; cursor:pointer; transition: all 0.2s;" ';
            echo 'title="Haz clic para ver historial de reincidencias de este RPU" ';
            echo 'data-rpu="' . htmlspecialchars($rpu) . '" ';
            echo 'data-prefix="' . htmlspecialchars($prefix) . '" ';
            echo 'data-tabla="' . htmlspecialchars($tabla) . '">';
            echo $reinc_count;
            echo '</span>';
            echo '</td>';
        }
        foreach ($columnas as $col) { echo '<td>' . htmlspecialchars($fila[$col] ?? '-') . '</td>'; }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    if (count($filas) >= 500) echo "<p style='font-size: 0.7rem; color: #666; margin-top: 10px; font-style: italic;'>* Mostrando los primeros 500 registros.</p>";

} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
