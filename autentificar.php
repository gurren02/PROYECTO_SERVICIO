<?php
// ==========================================
// 1. CONFIGURACIÓN DE LA CONEXIÓN (Directa)
// ==========================================
$host   = 'localhost';
$dbname = 'anomalias';
$user   = 'root';
$pass   = '';

$conectar = mysqli_connect($host, $user, $pass, $dbname);

if (!$conectar) {
    die("Error de conexión: " . mysqli_connect_error());
}

// ==========================================
// 2. PROCESAMIENTO DEL LOGIN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Capturamos los datos del formulario (names: userlog y contrasena)
    $usuario = mysqli_real_escape_string($conectar, $_POST["userlog"]);
    $password = mysqli_real_escape_string($conectar, $_POST["contrasena"]);

    // Consulta para buscar al usuario
    // Asegúrate que en tu tabla 'usuarios' las columnas se llamen 'userlog' y 'contrasena'
    $sql = "SELECT * FROM usuarios WHERE userlog = '$usuario' AND contrasena = '$password'";
    $resultado = mysqli_query($conectar, $sql);

    if ($resultado && mysqli_num_rows($resultado) > 0) {
        // Inicio de sesión exitoso
        session_start();
        $datos_usuario = mysqli_fetch_assoc($resultado);

        $_SESSION["autentificado"] = "SI";
        $_SESSION["usuario_actual"] = $usuario;

        // Redirección al index
        header("Location: index.php");
        exit();
    } else {
        // Error de credenciales
        echo '
        <script>
            alert("USUARIO O CONTRASEÑA INCORRECTOS");
            window.location.href="login.php"; 
        </script>
        ';
    }
} else {
    // Si intentan entrar al archivo sin enviar el formulario
    header("Location: index.php");
    exit();
}

mysqli_close($conectar);
?>