<?php
require 'config/conexion.php';

$pdo->beginTransaction();

try {
    $tabla = 'falsos202606';
    $id_registro = 9999;

    // 1. Insertar registro temporal en la tabla
    $pdo->exec("INSERT INTO `$tabla` (id_registro, Rpu, Nis, Nombre, Comentario) VALUES ($id_registro, '999999999', '999', 'TEST RECORD', '')");

    // 2. Insertar comentario 1 (ID: 10001)
    $pdo->exec("INSERT INTO `falsos_comentarios_historial` (id, tabla, id_registro, usuario, comentario, fecha_registro) 
                VALUES (10001, '$tabla', $id_registro, 'TEST USER', 'Comentario 1', NOW())");

    // 3. Insertar observación para comentario 1 (ID: 20001)
    $pdo->exec("INSERT INTO `falsos_observaciones_historial` (id, id_comentario, usuario, observacion, fecha_registro) 
                VALUES (20001, 10001, 'TEST ADMIN', 'Observacion de Comentario 1', NOW())");

    // 4. Insertar comentario 2 (ID: 10002) - Este es el último comentario, no tiene observaciones
    $pdo->exec("INSERT INTO `falsos_comentarios_historial` (id, tabla, id_registro, usuario, comentario, fecha_registro) 
                VALUES (10002, '$tabla', $id_registro, 'TEST USER', 'Comentario 2', NOW())");

    // Ejecutar la consulta nueva
    $stmt_last_obs = $pdo->prepare("
        SELECT c.id_registro, o.observacion
        FROM falsos_observaciones_historial o
        INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id
        INNER JOIN (
            SELECT c2.id_registro, MAX(o2.id) as max_obs_id
            FROM falsos_observaciones_historial o2
            INNER JOIN falsos_comentarios_historial c2 ON o2.id_comentario = c2.id
            INNER JOIN (
                SELECT id_registro, MAX(id) as max_comment_id
                FROM falsos_comentarios_historial
                WHERE tabla = ?
                GROUP BY id_registro
            ) m_c ON c2.id = m_c.max_comment_id
            GROUP BY c2.id_registro
        ) m ON o.id = m.max_obs_id
    ");

    $stmt_last_obs->execute([$tabla]);
    $results = $stmt_last_obs->fetchAll(PDO::FETCH_ASSOC);

    $obs_found = null;
    foreach ($results as $r) {
        if ($r['id_registro'] == $id_registro) {
            $obs_found = $r['observacion'];
        }
    }

    echo "Escenario 1 (Último comentario no tiene observaciones):\n";
    if ($obs_found === null) {
        echo ">>> CORRECTO: No se retornó ninguna observación para el registro $id_registro\n";
    } else {
        echo ">>> ERROR: Se retornó la observación '$obs_found'\n";
    }

    // 5. Agregar observación para el comentario 2 (ID: 20002)
    $pdo->exec("INSERT INTO `falsos_observaciones_historial` (id, id_comentario, usuario, observacion, fecha_registro) 
                VALUES (20002, 10002, 'TEST ADMIN', 'Observacion de Comentario 2', NOW())");

    $stmt_last_obs->execute([$tabla]);
    $results = $stmt_last_obs->fetchAll(PDO::FETCH_ASSOC);

    $obs_found = null;
    foreach ($results as $r) {
        if ($r['id_registro'] == $id_registro) {
            $obs_found = $r['observacion'];
        }
    }

    echo "\nEscenario 2 (Último comentario ahora tiene observaciones):\n";
    if ($obs_found === 'Observacion de Comentario 2') {
        echo ">>> CORRECTO: Se retornó la observación del último comentario: '$obs_found'\n";
    } else {
        echo ">>> ERROR: Se retornó la observación '" . ($obs_found ?? 'NULL') . "'\n";
    }

} catch (Exception $e) {
    echo "Error durante la prueba: " . $e->getMessage() . "\n";
} finally {
    $pdo->rollBack();
    echo "\nTransacción revertida.\n";
}
