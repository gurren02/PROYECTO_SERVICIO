<?php
require 'config/conexion.php';

// Iniciamos una transacción para no dejar datos de prueba persistentes
$pdo->beginTransaction();

try {
    $tabla = 'falsos202606';
    
    // Crear un archivo CSV temporal de prueba
    // Probaremos delimitador ',' y codificación UTF-8
    $csv_content = "\xEF\xBB\xBFID,Rpu,Nis,Nombre,Comentarios,Observaciones\n"
                 . "7,778050300545,69DW01K546960040,31ETV0119L ESC TELESECUND 119,hola modificado desde excel,alguna observacion vieja\n"
                 . "2,778050500161,69DW01K356920010,HDA HOTEL SOTUTA DE PEON S.A.,comentario nuevo excel,otra observacion\n"
                 . "1,778970200097,69DW01K306910008,MUNICIPIO DE MERIDA YUCATAN,,sin comentario\n"; // El registro 1 tiene comentario vacío, igual que en BD

    $temp_file = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($temp_file, $csv_content);

    // --- LOGICA DE IMPORTACION ---
    $handle = fopen($temp_file, 'r');
    if (!$handle) {
        throw new Exception("No se pudo abrir el archivo CSV de prueba.");
    }

    $first_line = fgets($handle);
    rewind($handle);

    $comma_count = substr_count($first_line, ',');
    $semicolon_count = substr_count($first_line, ';');
    $delimiter = ($semicolon_count > $comma_count) ? ';' : ',';

    echo "Delimitador detectado: '$delimiter'\n";

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        throw new Exception("Archivo vacío o inválido.");
    }

    // Limpiar BOM
    $bom = pack('H*', 'EFBBBF');
    if (substr($headers[0], 0, 3) === $bom) {
        $headers[0] = substr($headers[0], 3);
    }

    foreach ($headers as $key => $val) {
        $headers[$key] = strtoupper(trim($val));
    }

    print_r($headers);

    $id_idx = array_search('ID', $headers);
    $comment_idx = array_search('COMENTARIOS', $headers);
    if ($comment_idx === false) {
        $comment_idx = array_search('COMENTARIO', $headers);
    }

    echo "Índice ID: $id_idx, Índice Comentarios: $comment_idx\n";

    if ($id_idx === false || $comment_idx === false) {
        throw new Exception("Cabeceras ID o Comentarios no encontradas.");
    }

    $updated_count = 0;
    $skipped_count = 0;

    $stmt_select = $pdo->prepare("SELECT `Comentario` FROM `$tabla` WHERE `id_registro` = ?");
    $stmt_update = $pdo->prepare("UPDATE `$tabla` SET `Comentario` = ? WHERE `id_registro` = ?");
    $stmt_hist   = $pdo->prepare("INSERT INTO `falsos_comentarios_historial` (tabla, id_registro, usuario, comentario, fecha_registro) VALUES (?, ?, ?, ?, NOW())");

    $usuario_str = "Subido a través de excel";

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($row) <= max($id_idx, $comment_idx)) {
            continue;
        }

        $id_val = trim($row[$id_idx]);
        $comment_val = trim($row[$comment_idx]);

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

        if ($comment_val !== $db_comment) {
            $stmt_update->execute([$comment_val, $id_registro]);
            $stmt_hist->execute([$tabla, $id_registro, $usuario_str, $comment_val]);
            $updated_count++;
            echo "Actualizado registro $id_registro: '$db_comment' -> '$comment_val'\n";
        } else {
            $skipped_count++;
            echo "Ignorado registro $id_registro (sin cambios): '$db_comment'\n";
        }
    }

    fclose($handle);
    unlink($temp_file);

    // Validar en la BD que los datos se actualizaron correctamente
    echo "\n=== Verificación de datos en BD (dentro de transacción) ===\n";
    $stmt_verify = $pdo->query("SELECT id_registro, Comentario FROM `$tabla` WHERE id_registro IN (7, 2, 1)");
    while ($row = $stmt_verify->fetch(PDO::FETCH_ASSOC)) {
        echo "Registro {$row['id_registro']}: Comentario = '{$row['Comentario']}'\n";
    }

    // Verificar historial
    echo "\n=== Verificación de historial de comentarios ===\n";
    $stmt_hist_verify = $pdo->query("SELECT * FROM `falsos_comentarios_historial` ORDER BY id DESC LIMIT 2");
    while ($row = $stmt_hist_verify->fetch(PDO::FETCH_ASSOC)) {
        echo "Historial {$row['id_registro']}: Usuario = '{$row['usuario']}', Comentario = '{$row['comentario']}'\n";
    }

    if ($updated_count === 2 && $skipped_count === 1) {
        echo "\n>>> PRUEBA EXITOSA <<<\n";
    } else {
        echo "\n>>> PRUEBA FALLIDA (conteo incorrecto de cambios) <<<\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Deshacer todos los cambios de prueba
    $pdo->rollBack();
    echo "Transacción revertida.\n";
}
