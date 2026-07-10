<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

header('Content-Type: application/json');

$tabla = isset($_POST['tabla']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla']) : '';

if ($tabla === '') {
    echo json_encode(['status' => 'error', 'message' => 'Tabla no especificada.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = isset($_FILES['file']) ? $_FILES['file']['error'] : 'no_file';
    echo json_encode(['status' => 'error', 'message' => 'Error al subir el archivo (Código de error: ' . $errCode . ').']);
    exit;
}

$file_path = $_FILES['file']['tmp_name'];

try {
    // Verificar que la tabla existe
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt_check->execute([$tabla]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'La tabla especificada no existe.']);
        exit;
    }

    // Asegurar que la tabla de historial de comentarios existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `falsos_comentarios_historial` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tabla` VARCHAR(50) NOT NULL,
        `id_registro` INT NOT NULL,
        `rpu` VARCHAR(50) DEFAULT NULL,
        `usuario` VARCHAR(100) NOT NULL,
        `comentario` TEXT NOT NULL,
        `fecha_registro` DATETIME NOT NULL,
        `observacion` TEXT DEFAULT NULL,
        `usuario_observacion` VARCHAR(100) DEFAULT NULL,
        `fecha_observacion` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Asegurar que la columna `rpu` existe en `falsos_comentarios_historial`
    try {
        $pdo->query("SELECT `rpu` FROM `falsos_comentarios_historial` LIMIT 1");
    } catch (PDOException $e_col) {
        try {
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD COLUMN `rpu` VARCHAR(50) DEFAULT NULL");
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD INDEX (`rpu`)");
        } catch (PDOException $ex) {}
    }

    // Asegurar que la tabla de observaciones múltiples existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `falsos_observaciones_historial` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_comentario` INT NOT NULL,
        `usuario` VARCHAR(100) NOT NULL,
        `observacion` TEXT NOT NULL,
        `fecha_registro` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Asegurar que la columna Comentario existe en la tabla principal
    try {
        $pdo->query("SELECT `Comentario` FROM `$tabla` LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `Comentario` TEXT DEFAULT NULL");
    }

    // Abrir el archivo CSV
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo abrir el archivo CSV subido.']);
        exit;
    }

    // Leer la primera línea para detectar el delimitador automáticamente
    $first_line = fgets($handle);
    rewind($handle);

    $comma_count = substr_count($first_line, ',');
    $semicolon_count = substr_count($first_line, ';');
    $delimiter = ($semicolon_count > $comma_count) ? ';' : ',';

    // Leer cabeceras
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers || empty($headers)) {
        fclose($handle);
        echo json_encode(['status' => 'error', 'message' => 'El archivo CSV está vacío o no tiene un formato válido.']);
        exit;
    }

    // Limpiar BOM UTF-8 del primer elemento de cabecera si existe
    $bom = pack('H*', 'EFBBBF');
    if (substr($headers[0], 0, 3) === $bom) {
        $headers[0] = substr($headers[0], 3);
    }

    // Normalizar cabeceras a mayúsculas y sin espacios
    foreach ($headers as $key => $val) {
        $headers[$key] = strtoupper(trim($val));
    }

    // Buscar índices de las columnas clave
    $id_idx = array_search('ID', $headers);
    $comment_idx = array_search('COMENTARIOS', $headers);
    if ($comment_idx === false) {
        $comment_idx = array_search('COMENTARIO', $headers);
    }
    
    $obs_idx = array_search('OBSERVACIONES', $headers);
    if ($obs_idx === false) {
        $obs_idx = array_search('OBSERVACION', $headers);
    }

    if ($id_idx === false || $comment_idx === false) {
        fclose($handle);
        echo json_encode([
            'status' => 'error', 
            'message' => 'No se encontraron las columnas requeridas: "ID" y "Comentarios" en la cabecera del archivo. Asegúrese de que correspondan con el formato exportado.'
        ]);
        exit;
    }

    $updated_count = 0;
    $skipped_count = 0;
    
    // Consultas preparadas
    $stmt_select = $pdo->prepare("SELECT `Rpu`, `Comentario` FROM `$tabla` WHERE `id_registro` = ?");
    $stmt_update = $pdo->prepare("UPDATE `$tabla` SET `Comentario` = ? WHERE `Rpu` = ?");
    $stmt_hist   = $pdo->prepare("INSERT INTO `falsos_comentarios_historial` (tabla, id_registro, rpu, usuario, comentario, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
    
    // Consultas para observaciones
    $stmt_last_comment = $pdo->prepare("SELECT id, comentario FROM falsos_comentarios_historial WHERE rpu = ? ORDER BY id DESC LIMIT 1");
    $stmt_last_obs_val = $pdo->prepare("SELECT observacion FROM falsos_observaciones_historial WHERE id_comentario = ? ORDER BY id DESC LIMIT 1");
    $stmt_ins_obs      = $pdo->prepare("INSERT INTO falsos_observaciones_historial (id_comentario, usuario, observacion, fecha_registro) VALUES (?, ?, ?, NOW())");

    $usuario_str = "Subido a través de excel";

    // Procesar cada fila
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        // Saltar filas que no tengan suficientes columnas
        $min_columns = max($id_idx, $comment_idx);
        if ($obs_idx !== false) {
            $min_columns = max($min_columns, $obs_idx);
        }
        if (count($row) <= $min_columns) {
            continue;
        }

        $id_val = trim($row[$id_idx]);
        $comment_val = trim($row[$comment_idx]);
        $obs_val = ($obs_idx !== false) ? trim($row[$obs_idx]) : '';

        // Ignorar filas sin un ID válido
        if ($id_val === '' || !is_numeric($id_val)) {
            continue;
        }

        $id_registro = (int)$id_val;

        // Consultar el Rpu y comentario actual en la base de datos
        $stmt_select->execute([$id_registro]);
        $db_row = $stmt_select->fetch(PDO::FETCH_ASSOC);
        if (!$db_row) {
            $skipped_count++;
            continue; // Registro no encontrado en esta tabla
        }

        $rpu = trim($db_row['Rpu']);

        // Consultar el último comentario real en el historial por RPU
        $stmt_last_comment->execute([$rpu]);
        $lc_row = $stmt_last_comment->fetch(PDO::FETCH_ASSOC);
        
        $db_comment = $lc_row ? trim($lc_row['comentario']) : '';
        $last_comment_id = $lc_row ? (int)$lc_row['id'] : null;

        $row_updated = false;

        // 1. Si el comentario ha cambiado, actualizar la tabla principal e historial
        if ($comment_val !== $db_comment) {
            $stmt_update->execute([$comment_val, $rpu]);
            $stmt_hist->execute([$tabla, $id_registro, $rpu, $usuario_str, $comment_val]);
            $last_comment_id = (int)$pdo->lastInsertId();
            $row_updated = true;
        }

        // 2. Si la columna de observaciones existe, verificar si ha cambiado
        if ($obs_idx !== false) {
            if (!$row_updated) {
                // Si el comentario no cambió, buscar el ID del último comentario y su observación en la BD por Rpu
                if ($last_comment_id !== null) {
                    $stmt_last_obs_val->execute([$last_comment_id]);
                    $lo_row = $stmt_last_obs_val->fetch(PDO::FETCH_ASSOC);
                    $db_obs = $lo_row ? trim($lo_row['observacion']) : '';
                } else {
                    $db_obs = '';
                }
            } else {
                // Si el comentario cambió y se creó un nuevo comentario, no tiene observaciones aún
                $db_obs = '';
            }

            // Comparar y guardar si la observación en el Excel cambió
            if ($obs_val !== $db_obs) {
                // Si no existía un comentario en el historial, crearlo primero
                if ($last_comment_id === null) {
                    $stmt_hist->execute([$tabla, $id_registro, $rpu, $usuario_str, $comment_val]);
                    $last_comment_id = (int)$pdo->lastInsertId();
                }

                // Insertar observación
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

    echo json_encode([
        'status' => 'ok',
        'message' => 'Importación completada con éxito.',
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ]);

} catch (PDOException $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }
    echo json_encode(['status' => 'error', 'message' => 'Error base de datos: ' . $e->getMessage()]);
}
