<?php
$pdo = new PDO('mysql:host=localhost;dbname=anomalias;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT DISTINCT Motivo_Estimacion FROM estimaciones202604");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
