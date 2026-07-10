<?php
require __DIR__ . '/../config/conexion.php';

echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP local time: " . date('Y-m-d H:i:s') . "\n";

$stmt = $pdo->query("SELECT @@global.time_zone AS gt, @@session.time_zone AS st");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "MySQL global time_zone: " . $row['gt'] . "\n";
echo "MySQL session time_zone: " . $row['st'] . "\n";

$stmt = $pdo->query("SELECT NOW() as now");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "MySQL NOW(): " . $row['now'] . "\n";
