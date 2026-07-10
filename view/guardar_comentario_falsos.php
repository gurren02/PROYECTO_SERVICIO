<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

header('Content-Type: application/json');

$tabla       = isset($_POST['tabla'])       ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla']) : '';
$id_registro = isset($_POST['id_registro']) ? (int)$_POST['id_registro'] : 0;
$comentario  = isset($_POST['comentario'])  ? trim($_POST['comentario']) : '';

if ($tabla === '' || $id_registro <= 0 || $comentario === '') {
    echo json_encode(['error' => 'Parámetros no válidos o comentario vacío.']);
    exit;
}

try {
    // Verificar que la tabla existe
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt_check->execute([$tabla]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['error' => 'La tabla especificada no existe.']);
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

    // Asegurar que la columna Comentario existe en la tabla principal
    try {
        $pdo->query("SELECT `Comentario` FROM `$tabla` LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `Comentario` TEXT DEFAULT NULL");
    }

    // Obtener el Rpu de la tabla principal usando el id_registro
    $stmt_rpu = $pdo->prepare("SELECT `Rpu` FROM `$tabla` WHERE `id_registro` = ?");
    $stmt_rpu->execute([$id_registro]);
    $rpu = $stmt_rpu->fetchColumn();

    // 1. Insertar el nuevo comentario en el historial con Rpu
    $nombre_completo = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'Usuario';
    $userlog = isset($_SESSION['userlog']) ? $_SESSION['userlog'] : 'desconocido';
    $usuario_str = $nombre_completo . ' (' . $userlog . ')';
    
    $stmt_hist = $pdo->prepare("INSERT INTO `falsos_comentarios_historial` (tabla, id_registro, rpu, usuario, comentario, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt_hist->execute([$tabla, $id_registro, $rpu, $usuario_str, $comentario]);
    $new_id = $pdo->lastInsertId();

    // 2. Actualizar el último comentario en la tabla principal para todos los registros con este Rpu
    if ($rpu) {
        $stmt = $pdo->prepare("UPDATE `$tabla` SET `Comentario` = ? WHERE `Rpu` = ?");
        $stmt->execute([$comentario, $rpu]);
    } else {
        $stmt = $pdo->prepare("UPDATE `$tabla` SET `Comentario` = ? WHERE `id_registro` = ?");
        $stmt->execute([$comentario, $id_registro]);
    }

    echo json_encode(['status' => 'ok', 'ultimo_comentario' => $comentario, 'id_comentario' => (int)$new_id]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error base de datos: ' . $e->getMessage()]);
}
