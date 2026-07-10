<?php
require 'config/conexion.php';

$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tablas en la BD:\n";
print_r($tables);
