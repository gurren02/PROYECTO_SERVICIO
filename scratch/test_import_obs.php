<?php
require 'config/conexion.php';

$pdo->beginTransaction();

try {
    $tabla = 'falsos202606';
    
    // Preparar registros y limpiar historial viejo para este test (dentro de la transacción)
    $id_1 = 1; // Deberá actualizar comentario
    $id_2 = 2; // Deberá actualizar comentario y observacion
    $id_3 = 3; // Deberá actualizar SOLO observacion
    $id_6 = 6; // Deberá ignorarse (sin cambios en comentario ni en obs)

    $pdo->exec("DELETE FROM `falsos_comentarios_historial` WHERE tabla = '$tabla' AND id_registro IN ($id_1, $id_2, $id_3, $id_6)");

    // Configurar estado inicial en la base de datos
    $pdo->exec("UPDATE `$tabla` SET Comentario = 'comentario inicial 1' WHERE id_registro = $id_1");
    $pdo->exec("UPDATE `$tabla` SET Comentario = 'comentario inicial 2' WHERE id_registro = $id_2");
    $pdo->exec("UPDATE `$tabla` SET Comentario = 'comentario inicial 3' WHERE id_registro = $id_3");
    $pdo->exec("UPDATE `$tabla` SET Comentario = 'comentario inicial 6' WHERE id_registro = $id_6");

    // Comentario inicial para id_3 (para poder agregar observación)
    $pdo->exec("INSERT INTO `falsos_comentarios_historial` (id, tabla, id_registro, usuario, comentario, fecha_registro) 
                VALUES (50003, '$tabla', $id_3, 'sistema', 'comentario inicial 3', NOW())");
    $pdo->exec("INSERT INTO `falsos_observaciones_historial` (id_comentario, usuario, observacion, fecha_registro) 
                VALUES (50003, 'admin', 'observacion inicial 3', NOW())");

    // Comentario inicial para id_6 (no cambiará nada)
    $pdo->exec("INSERT INTO `falsos_comentarios_historial` (id, tabla, id_registro, usuario, comentario, fecha_registro) 
                VALUES (50006, '$tabla', $id_6, 'sistema', 'comentario inicial 6', NOW())");
    $pdo->exec("INSERT INTO `falsos_observaciones_historial` (id_comentario, usuario, observacion, fecha_registro) 
                VALUES (50006, 'admin', 'observacion inicial 6', NOW())");


    // Crear CSV temporal
    $csv_content = "\xEF\xBB\xBFID,Comentarios,Observaciones\n"
                 . "$id_1,comentario modificado 1,observacion_vacia_test\n" // comentario cambia, observacion agregada
                 . "$id_2,comentario modificado 2,observacion modificada 2\n" // ambos cambian
                 . "$id_3,comentario inicial 3,observacion modificada 3\n" // solo observacion cambia
                 . "$id_6,comentario inicial 6,observacion inicial 6\n"; // ninguno cambia

    $temp_file = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($temp_file, $csv_content);

    // Ejecutar lógica de importación
    $handle = fopen($temp_file, 'r');
    $first_line = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    $headers = fgetcsv($handle, 0, $delimiter);
    $bom = pack('H*', 'EFBBBF');
    if (substr($headers[0], 0, 3) === $bom) {
        $headers[0] = substr($headers[0], 3);
    }
    foreach ($headers as $key => $val) {
        $headers[$key] = strtoupper(trim($val));
    }

    $id_idx = array_search('ID', $headers);
    $comment_idx = array_search('COMENTARIOS', $headers);
    if ($comment_idx === false) $comment_idx = array_search('COMENTARIO', $headers);
    $obs_idx = array_search('OBSERVACIONES', $headers);
    if ($obs_idx === false) $obs_idx = array_search('OBSERVACION', $headers);

    $updated_count = 0;
    $skipped_count = 0;

    $stmt_select = $pdo->prepare("SELECT `Comentario` FROM `$tabla` WHERE `id_registro` = ?");
    $stmt_update = $pdo->prepare("UPDATE `$tabla` SET `Comentario` = ? WHERE `id_registro` = ?");
    $stmt_hist   = $pdo->prepare("INSERT INTO `falsos_comentarios_historial` (tabla, id_registro, usuario, comentario, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
    $stmt_last_comment = $pdo->prepare("SELECT id FROM falsos_comentarios_historial WHERE tabla = ? AND id_registro = ? ORDER BY id DESC LIMIT 1");
    $stmt_last_obs_val = $pdo->prepare("SELECT observacion FROM falsos_observaciones_historial WHERE id_comentario = ? ORDER BY id DESC LIMIT 1");
    $stmt_ins_obs      = $pdo->prepare("INSERT INTO falsos_observaciones_historial (id_comentario, usuario, observacion, fecha_registro) VALUES (?, ?, ?, NOW())");

    $usuario_str = "Subido a través de excel";

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($row) <= max($id_idx, $comment_idx, $obs_idx)) {
            continue;
        }
        $id_val = trim($row[$id_idx]);
        $comment_val = trim($row[$comment_idx]);
        $obs_val = ($obs_idx !== false) ? trim($row[$obs_idx]) : '';

        if ($id_val === '' || !is_numeric($id_val)) {
            continue;
        }

        $id_registro = (int)$id_val;

        $stmt_select->execute([$id_registro]);
        $db_row = $stmt_select->fetch(PDO::FETCH_ASSOC);
        if (!$db_row) {
            $skipped_count++;
            continue;
        }

        $db_comment = $db_row['Comentario'] !== null ? trim($db_row['Comentario']) : '';
        $row_updated = false;
        $last_comment_id = null;

        if ($comment_val !== $db_comment) {
            $stmt_update->execute([$comment_val, $id_registro]);
            $stmt_hist->execute([$tabla, $id_registro, $usuario_str, $comment_val]);
            $last_comment_id = (int)$pdo->lastInsertId();
            $row_updated = true;
        }

        if ($obs_idx !== false) {
            if (!$row_updated) {
                $stmt_last_comment->execute([$tabla, $id_registro]);
                $lc_row = $stmt_last_comment->fetch(PDO::FETCH_ASSOC);
                if ($lc_row) {
                    $last_comment_id = (int)$lc_row['id'];
                    $stmt_last_obs_val->execute([$last_comment_id]);
                    $lo_row = $stmt_last_obs_val->fetch(PDO::FETCH_ASSOC);
                    $db_obs = $lo_row ? trim($lo_row['observacion']) : '';
                } else {
                    $last_comment_id = null;
                    $db_obs = '';
                }
            } else {
                $db_obs = '';
            }

            if ($obs_val !== $db_obs) {
                if ($last_comment_id === null) {
                    $stmt_hist->execute([$tabla, $id_registro, $usuario_str, $comment_val]);
                    $last_comment_id = (int)$pdo->lastInsertId();
                }
                $stmt_ins_obs->execute([$last_comment_id, $usuario_str, $obs_val]);
                $row_updated = true;
            }
        }

        if ($row_updated) {
            $updated_count++;
        } else {
            $skipped_count++;
        }
    }

    fclose($handle);
    unlink($temp_file);

    echo "Resultados:\n";
    echo "  Actualizados (esperado 3): $updated_count\n";
    echo "  Ignorados (esperado 1): $skipped_count\n";

    // Validaciones específicas
    // Registro 1
    $stmt_verify1 = $pdo->query("SELECT Comentario FROM `$tabla` WHERE id_registro = $id_1");
    $c1 = $stmt_verify1->fetch(PDO::FETCH_COLUMN);
    $stmt_o1 = $pdo->query("SELECT o.observacion FROM falsos_observaciones_historial o INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id WHERE c.id_registro = $id_1 ORDER BY o.id DESC LIMIT 1");
    $o1 = $stmt_o1->fetch(PDO::FETCH_COLUMN);
    echo "Registro $id_1: Comentario='$c1', Observacion='$o1'\n";

    // Registro 2
    $stmt_verify2 = $pdo->query("SELECT Comentario FROM `$tabla` WHERE id_registro = $id_2");
    $c2 = $stmt_verify2->fetch(PDO::FETCH_COLUMN);
    $stmt_o2 = $pdo->query("SELECT o.observacion FROM falsos_observaciones_historial o INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id WHERE c.id_registro = $id_2 ORDER BY o.id DESC LIMIT 1");
    $o2 = $stmt_o2->fetch(PDO::FETCH_COLUMN);
    echo "Registro $id_2: Comentario='$c2', Observacion='$o2'\n";

    // Registro 3
    $stmt_verify3 = $pdo->query("SELECT Comentario FROM `$tabla` WHERE id_registro = $id_3");
    $c3 = $stmt_verify3->fetch(PDO::FETCH_COLUMN);
    $stmt_o3 = $pdo->query("SELECT o.observacion FROM falsos_observaciones_historial o INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id WHERE c.id_registro = $id_3 ORDER BY o.id DESC LIMIT 1");
    $o3 = $stmt_o3->fetch(PDO::FETCH_COLUMN);
    echo "Registro $id_3: Comentario='$c3', Observacion='$o3'\n";

    // Registro 6
    $stmt_verify6 = $pdo->query("SELECT Comentario FROM `$tabla` WHERE id_registro = $id_6");
    $c6 = $stmt_verify6->fetch(PDO::FETCH_COLUMN);
    $stmt_o6 = $pdo->query("SELECT o.observacion FROM falsos_observaciones_historial o INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id WHERE c.id_registro = $id_6 ORDER BY o.id DESC LIMIT 1");
    $o6 = $stmt_o6->fetch(PDO::FETCH_COLUMN);
    echo "Registro $id_6: Comentario='$c6', Observacion='$o6'\n";

    if ($updated_count === 3 && $skipped_count === 1 && $o1 === 'observacion_vacia_test' && $o2 === 'observacion modificada 2' && $o3 === 'observacion modificada 3' && $o6 === 'observacion inicial 6') {
        echo ">>> TEST EXCELENTE Y CORRECTO <<<\n";
    } else {
        echo ">>> ERROR EN VALORES DEL TEST <<<\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    $pdo->rollBack();
    echo "Transacción revertida.\n";
}
