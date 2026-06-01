<?php
$pdo = new PDO('mysql:host=localhost;dbname=anomalias;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT DISTINCT Clasificacion_Estimacion FROM estimaciones202604");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt2 = $pdo->query("SELECT DISTINCT Grupo_Motivo_Estimacion FROM estimaciones202604");
print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));
