<?php
$pdo = new PDO('mysql:host=localhost;dbname=anomalias;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SHOW TABLES LIKE 'estimaciones%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($tables)) {
    $stmt2 = $pdo->query("SHOW COLUMNS FROM " . $tables[0]);
    $cols = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'Field');
    echo implode(", ", $cols);
}
