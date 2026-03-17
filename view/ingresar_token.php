<?php
// ==========================================
// 1. LÓGICA DE ACTUALIZACIÓN DE CONTRASEÑA
// ==========================================
require "../config/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_pass'])) {

    // Limpiamos los datos recibidos
    $token                = trim(mysqli_real_escape_string($conectar, $_POST['token']));
    $nueva_contrasena     = trim($_POST['nueva_contrasena']);
    $confirmar_contrasena = trim($_POST['confirmar_contrasena']);

    // Validaciones básicas
    if (empty($token) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
        echo "<script>alert('Todos los campos son obligatorios.');</script>";
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        echo "<script>alert('Las contraseñas no coinciden. Inténtalo de nuevo.');</script>";
    } else {
        // Buscamos si existe un usuario con ese token exacto
        $query_token = "SELECT id FROM usuarios WHERE token_recuperacion = '$token' LIMIT 1";
        $resultado   = mysqli_query($conectar, $query_token);

        if (mysqli_num_rows($resultado) > 0) {
            // Si el token es válido, actualizamos la contraseña y BORRAMOS el token por seguridad
            $sql_update = "UPDATE usuarios 
                           SET contrasena = '$nueva_contrasena', 
                               token_recuperacion = NULL 
                           WHERE token_recuperacion = '$token'";

            if (mysqli_query($conectar, $sql_update)) {
                echo "<script>
                        alert('¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.');
                        window.location.href = 'login.php';
                      </script>";
                exit();
            } else {
                echo "<script>alert('Error al actualizar la contraseña en la base de datos.');</script>";
            }
        } else {
            // El token no existe o ya fue usado
            echo "<script>alert('El código de recuperación es incorrecto o ha caducado.');</script>";
        }
    }
}

mysqli_close($conectar);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    <title>CAMBIAR CONTRASEÑA</title>
</head>
<body>

<div class="login-page-wrapper">
    <div class="contenedor_login-fondo">
        <div class="contenedor_login">
            <h3 class="login-titulo">Nueva Contraseña</h3>

            <div class="login-imagen">
                <img src="../assets/multimedia/cfe.png" alt="loginlogo" class="imagen_log"
                     onerror="this.outerHTML='<div class=\'logo-placeholder\'>⚡</div>'">
            </div>

            <p style="color: rgba(255,255,255,0.8); font-size: 0.85rem; text-align: center; margin-bottom: 1rem; line-height: 1.4;">
                Ingresa el código de 6 dígitos enviado a tu correo y tu nueva contraseña.
            </p>

            <div class="login-credenciales">
                <form action="" method="post" id="credenciales" class="credenciales-login">

                    <legend class="titulo-credenciales">Código de recuperación</legend>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                            </svg>
                        </span>
                        <input type="text" class="elementologin" placeholder="Ej: A1B2C3" name="token" maxlength="6" style="text-transform: uppercase;" required>
                    </div>

                    <legend class="titulo-credenciales">Nueva Contraseña</legend>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input type="password" class="elementologin" placeholder="••••••••" name="nueva_contrasena" required>
                    </div>

                    <legend class="titulo-credenciales">Confirmar Contraseña</legend>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M9 16l2 2 4-4"/>
                            </svg>
                        </span>
                        <input type="password" class="elementologin" placeholder="••••••••" name="confirmar_contrasena" required>
                    </div>

                    <div class="row-extras" style="justify-content: center; margin-top: 1rem;">
                        <a href="login.php" class="link-forgot">Cancelar y volver al login</a>
                    </div>

                    <button class="btn_login" type="submit" name="cambiar_pass">
                        Guardar cambios &nbsp; ✓
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>

