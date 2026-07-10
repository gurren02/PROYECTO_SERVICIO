<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$rpu    = isset($_GET['rpu'])    ? trim($_GET['rpu']) : '';
$prefix = isset($_GET['prefix']) ? preg_replace('/[^a-zA-Z_]/', '', $_GET['prefix']) : '';
$tabla  = isset($_GET['tabla'])  ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';

if ($rpu === '' || $prefix === '' || $tabla === '') {
    echo "<div style='padding: 10px; color: red;'>Error: Parámetros incompletos.</div>";
    exit;
}

try {
    // 1. Obtener las columnas de la tabla de referencia
    $stmt_cols = $pdo->query("SHOW COLUMNS FROM `$tabla`");
    $columnas = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

    // 2. Obtener todas las tablas históricas para este prefijo
    $stmt_tables = $pdo->query("SHOW TABLES LIKE '{$prefix}%'");
    $history_tables = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);
    
    // Ordenar tablas de más recientes a más antiguas
    // Las tablas terminan en YYYYMM, así que una ordenación alfabética descendente las pone en orden cronológico inverso
    rsort($history_tables);

    $historial_filas = [];

    // 3. Buscar el RPU en cada una de las tablas
    foreach ($history_tables as $ht) {
        // Extraer el período del nombre de la tabla (los últimos 6 caracteres ej: 202607)
        if (preg_match('/(\d{6})$/', $ht, $matches)) {
            $suffix = $matches[1];
            $year = substr($suffix, 0, 4);
            $month = (int)substr($suffix, 4, 2);
            $meses = [
                1=>'ENE', 2=>'FEB', 3=>'MAR', 4=>'ABR', 5=>'MAY', 6=>'JUN',
                7=>'JUL', 8=>'AGO', 9=>'SEP', 10=>'OCT', 11=>'NOV', 12=>'DIC'
            ];
            $periodo_lbl = isset($meses[$month]) ? $meses[$month] . ' ' . $year : $suffix;
        } else {
            $periodo_lbl = $ht;
        }

        try {
            // Verificar si la tabla tiene la columna Rpu antes de consultar
            $cols_ht = $pdo->query("SHOW COLUMNS FROM `$ht` LIKE 'Rpu'")->fetchAll();
            if (empty($cols_ht)) continue;

            $stmt = $pdo->prepare("SELECT * FROM `$ht` WHERE TRIM(`Rpu`) = ?");
            $stmt->execute([$rpu]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $row['_periodo_lbl'] = $periodo_lbl;
                $row['_tabla_origen'] = $ht;
                $historial_filas[] = $row;
            }
        } catch (Exception $e) {
            // Ignorar errores individuales de tablas (ej. tablas corruptas o bloqueadas)
        }
    }

    if (empty($historial_filas)) {
        echo "<div style='padding:15px; color:#666; font-style:italic;'>No se encontraron registros históricos para el RPU " . htmlspecialchars($rpu) . ".</div>";
        exit;
    }

    // 4. Renderizar la tabla de historial
    echo '<div style="padding: 12px; background-color: #f8fafc; border-left: 4px solid #dc3545; border-radius: 0 8px 8px 0; margin: 10px 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">';
    echo '<div style="margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">';
    echo '<span style="font-weight: 700; color: #1e293b; font-size: 0.85rem;">';
    echo '  <span class="material-symbols-rounded" style="font-size: 16px; vertical-align: middle; margin-right: 4px; color: #dc3545;">history</span>';
    echo '  Historial de Reincidencias para RPU: <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:0.8rem;">' . htmlspecialchars($rpu) . '</code>';
    echo '</span>';
    echo '<span style="font-size: 0.72rem; color: #64748b; font-weight: 500;">Total de apariciones: <strong>' . count($historial_filas) . '</strong></span>';
    echo '</div>';
    
    echo '<div style="max-height: 250px; overflow-x: auto; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">';
    echo '<table class="ea-table ea-table--detailed" style="font-size: 0.72rem; margin: 0; width: 100%; min-width: max-content;">';
    echo '<thead>';
    echo '<tr style="background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; z-index: 10;">';
    echo '<th style="padding: 6px 10px; font-weight: 700; color: #334155; text-align: left;">PERIODO</th>';
    foreach ($columnas as $col) {
        // Omitir columnas internas
        if (in_array($col, ['id_registro', 'anio_carga', 'mes_carga'])) continue;
        echo '<th style="padding: 6px 10px; font-weight: 700; color: #334155;">' . htmlspecialchars($col) . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($historial_filas as $fila) {
        echo '<tr style="border-bottom: 1px solid #e2e8f0; hover { background-color: #f8fafc; }">';
        echo '<td style="padding: 6px 10px; font-weight: bold; color: #0f172a; text-align: left; background-color: #f8fafc; border-right: 1px solid #e2e8f0;">' . htmlspecialchars($fila['_periodo_lbl']) . '</td>';
        foreach ($columnas as $col) {
            if (in_array($col, ['id_registro', 'anio_carga', 'mes_carga'])) continue;
            $val = isset($fila[$col]) ? trim($fila[$col]) : '';
            if ($val === '') $val = '-';
            echo '<td style="padding: 6px 10px; color: #475569;">' . htmlspecialchars($val) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';

} catch (PDOException $e) {
    echo "<div style='padding: 10px; color: red;'>Error en base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
