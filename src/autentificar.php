<?php
// Iniciamos sesión desde el principio para poder usar variables temporales
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// 1. CONFIGURACIÓN DE LA CONEXIÓN UNIVERSAL
// ==========================================
require "../config/conexion.php"; // <--- AQUÍ ESTÁ LA MAGIA Y EL BLINDAJE

// ==========================================
// 2. PROCESAMIENTO DEL LOGIN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Capturamos los datos del formulario (Ya no usamos mysqli_real_escape_string)
    $usuario = trim($_POST["userlog"]);
    $password = $_POST["contrasena"];

    // Guardamos el usuario que intentó entrar por si falla, así lo rellenamos en el login
    $_SESSION['usuario_intento'] = $usuario;

    // Consulta segura usando PDO (Los signos ? bloquean cualquier intento de hackeo)
    $sql = "SELECT * FROM usuarios WHERE userlog = ? AND contrasena = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    
    // Ejecutamos pasando las variables de forma segura
    $stmt->execute([$usuario, $password]);
    
    // Extraemos los datos del usuario si existe
    $datos_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($datos_usuario) {
        // --- INICIO DE SESIÓN EXITOSO ---
        
        // Variables de sesión (Tus variables originales intactas)
        $_SESSION["autentificado"]   = "SI";
        $_SESSION["usuario_actual"]  = $datos_usuario['userlog'];
        $_SESSION['nombre_completo'] = $datos_usuario['nombre_completo'];
        $_SESSION['userlog']         = $datos_usuario['userlog'];
        $_SESSION['rol']             = $datos_usuario['rol'];
        $_SESSION['zona']            = $datos_usuario['zona'];

        // Limpiamos los errores o intentos fallidos previos si existían
        unset($_SESSION['error_login']);
        unset($_SESSION['usuario_intento']);

        // Redirección al panel principal
        header("Location: ../view/index.php");
        exit();
    } else {
        // --- ERROR DE CREDENCIALES ---
        // Guardamos un mensaje de error en la sesión y regresamos al login visual
        $_SESSION['error_login'] = "Usuario o contraseña incorrectos.";
        header("Location: ../view/login.php");
        exit();
    }
} else {
    // Si intentan entrar al archivo por URL directa sin enviar el formulario
    header("Location: ../view/index.php");
    exit();
}

// PDO cierra la conexión automáticamente, no necesitamos mysqli_close();
?>

