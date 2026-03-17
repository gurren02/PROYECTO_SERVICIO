<?php
// archivo: seguridad.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["autentificado"]) || $_SESSION["autentificado"] != "SI") {
    // Si no está logueado, lo pateamos a la pantalla de login, no al index
    header("Location: ../view/login.php");
    exit();
}
?>

