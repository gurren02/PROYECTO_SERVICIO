<?php
require 'config/conexion.php';

$anomalias = [
    'cancelaciones',
    'estimaciones',
    'consumos_cero',
    'servicios_sin_medicion',
    'correcciones_de_lecturas',
    'anomalias_pendientes',
    'sin_facturar',
    'cargas_directas'
];

$sufijo_actual = "202606";

foreach ($anomalias as $anomalia) {
    $nombre = $anomalia . $sufijo_actual;
    echo "=== $nombre ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE '$nombre'");
    if ($stmt->fetch()) {
        $stmt_cnt = $pdo->query("SELECT UPPER(TRIM(`Agencia`)) as ag, COUNT(*) as cnt FROM `$nombre` GROUP BY ag ORDER BY ag");
        while ($r = $stmt_cnt->fetch(PDO::FETCH_ASSOC)) {
            echo "  Agencia '{$r['ag']}': {$r['cnt']}\n";
        }
    } else {
        echo "  (No existe la tabla)\n";
    }
}
