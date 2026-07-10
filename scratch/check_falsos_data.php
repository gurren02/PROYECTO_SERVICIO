<?php
require 'config/conexion.php';

echo "=== Estructura de falsos202606 ===\n";
$stmt = $pdo->query("DESCRIBE `falsos202606`");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Primeros 5 registros ===\n";
$stmt = $pdo->query("SELECT id_registro, Rpu, Nis, Nombre, Comentario FROM `falsos202606` LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
