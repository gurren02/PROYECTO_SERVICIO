<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$tabla   = isset($_GET['tabla'])   ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';
$agencia = isset($_GET['agencia']) ? trim($_GET['agencia']) : '';
$zona    = isset($_GET['zona'])    ? trim($_GET['zona'])    : '';

// Parámetros de ciclo (Rango 1 y 2)
$ciclo_i  = isset($_GET['ciclo_inicio'])   ? trim($_GET['ciclo_inicio']) : '';
$ciclo_f  = isset($_GET['ciclo_fin'])      ? trim($_GET['ciclo_fin'])    : '';
$ciclo_i2 = isset($_GET['ciclo_inicio_2']) ? trim($_GET['ciclo_inicio_2']) : '';
$ciclo_f2 = isset($_GET['ciclo_fin_2'])    ? trim($_GET['ciclo_fin_2'])    : '';

// Ciclo único (para nivel agencia)
$ciclo_u  = isset($_GET['ciclo']) ? trim($_GET['ciclo']) : '';

if ($tabla === '') {
    echo "Error: Tabla no especificada.";
    exit;
}

$mapa_agencias = [
    'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
    'G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
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
    $params = [];

    if ($agencia !== '') {
        $where .= " AND UPPER(TRIM(`Agencia`)) = ?";
        $params[] = $agencia_busqueda;
    }
    if ($zona !== '') {
        $where .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
        $params[] = (int)$zona;
    }

    // Lógica de Ciclos (Rangos o Único)
    $condiciones_ciclo = [];
    
    // Rango 1
    if ($ciclo_i !== '' && $ciclo_f !== '') {
        $condiciones_ciclo[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_i;
        $params[] = (int)$ciclo_f;
    } elseif ($ciclo_i !== '') {
        $condiciones_ciclo[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_i;
    }

    // Rango 2
    if ($ciclo_i2 !== '' && $ciclo_f2 !== '') {
        $condiciones_ciclo[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) BETWEEN ? AND ?";
        $params[] = (int)$ciclo_i2;
        $params[] = (int)$ciclo_f2;
    } elseif ($ciclo_i2 !== '') {
        $condiciones_ciclo[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_i2;
    }

    // Ciclo único (Agencia)
    if ($ciclo_u !== '' && empty($condiciones_ciclo)) {
        $condiciones_ciclo[] = "CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
        $params[] = (int)$ciclo_u;
    }

    if (!empty($condiciones_ciclo)) {
        $where .= " AND (" . implode(" OR ", $condiciones_ciclo) . ")";
    }

    $stmt = $pdo->prepare("SELECT * FROM `$tabla` $where LIMIT 500");
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "<div style='padding:20px; text-align:center;'>No se encontraron registros detallados para los filtros aplicados.</div>";
        exit;
    }

    echo '<div class="ea-table-wrapper" style="max-height: 60vh; overflow: auto; border: 1px solid var(--ea-border); border-radius: 8px;">';
    echo '<table class="ea-table ea-table--detailed" style="font-size: 0.75rem;">';
    echo '<thead><tr class="ea-table__head-cols">';
    foreach ($columnas as $col) { echo '<th>' . htmlspecialchars($col) . '</th>'; }
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($columnas as $col) { echo '<td>' . htmlspecialchars($fila[$col] ?? '-') . '</td>'; }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    if (count($filas) >= 500) echo "<p style='font-size: 0.7rem; color: #666; margin-top: 10px; font-style: italic;'>* Mostrando los primeros 500 registros.</p>";

} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
