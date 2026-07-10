<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

header('Content-Type: application/json');

$id_comentario = isset($_POST['id_comentario']) ? (int)$_POST['id_comentario'] : 0;
$observacion   = isset($_POST['observacion'])   ? trim($_POST['observacion']) : '';
$rol_usuario   = isset($_SESSION['rol'])        ? $_SESSION['rol'] : '';

if ($rol_usuario !== 'admin') {
    echo json_encode(['error' => 'Permisos insuficientes. Sólo administradores pueden realizar observaciones.']);
    exit;
}

if ($id_comentario <= 0 || $observacion === '') {
    echo json_encode(['error' => 'Parámetros no válidos o observación vacía.']);
    exit;
}

try {
    // Actualizar la observación en el historial de comentarios
    $nombre_completo = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'Admin';
    $userlog = isset($_SESSION['userlog']) ? $_SESSION['userlog'] : 'desconocido';
    $usuario_str = $nombre_completo . ' (' . $userlog . ')';

    $stmt = $pdo->prepare("INSERT INTO `falsos_observaciones_historial` (`id_comentario`, `usuario`, `observacion`, `fecha_registro`) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$id_comentario, $usuario_str, $observacion]);

    echo json_encode(['status' => 'ok']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error base de datos: ' . $e->getMessage()]);
}
