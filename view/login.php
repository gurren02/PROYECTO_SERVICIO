<?php
// Iniciamos sesión para leer los posibles errores
session_start();

// Si ya está autentificado, lo mandamos al index para que no vea el login de nuevo
if (isset($_SESSION['autentificado']) && $_SESSION['autentificado'] === "SI") {
    header("Location: index.php");
    exit();
}

// Recuperamos el mensaje de error y el usuario ingresado previamente (si existen)
$mensaje_error = isset($_SESSION['error_login']) ? $_SESSION['error_login'] : '';
$usuario_previo = isset($_SESSION['usuario_intento']) ? $_SESSION['usuario_intento'] : '';

// Una vez leídos, los borramos para que no aparezcan si el usuario refresca la página
unset($_SESSION['error_login']);
unset($_SESSION['usuario_intento']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    <title>INICIAR SESIÓN</title>
</head>
<body>

<div class="login-page-wrapper">
    <div class="contenedor_login-fondo">
        <div class="contenedor_login">
            <h3 class="login-titulo">Iniciar sesión</h3>

            <div class="login-imagen">
                <img src="../assets/multimedia/cfe.png" alt="loginlogo" class="imagen_log"
                     onerror="this.outerHTML='<div class=\'logo-placeholder\'>⚡</div>'">
            </div>

            <div class="login-credenciales">

                <?php if (!empty($mensaje_error)): ?>
                    <div class="mensaje-error-login">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?php echo htmlspecialchars($mensaje_error); ?></span>
                    </div>
                <?php endif; ?>

                <form action="../src/autentificar.php" method="post" id="credenciales" class="credenciales-login">

                    <legend class="titulo-credenciales">Usuario</legend>
                    <div class="input-wrapper">
                            <span class="input-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"
                                     viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path
                                            d="M2 7l10 7 10-7"/></svg>
                            </span>
                        <input type="text" class="elementologin" placeholder="Usuario" name="userlog"
                               value="<?php echo htmlspecialchars($usuario_previo); ?>" required>
                    </div>

                    <legend class="titulo-credenciales">Contraseña</legend>
                    <div class="input-wrapper">
                            <span class="input-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"
                                     viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path
                                            d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                        <input type="password" class="elementologin" placeholder="Contraseña" name="contrasena" id="contrasena" required>
                    </div>

                    <div class="row-extras">
                        <a href="recuperacion.php" class="link-forgot">¿Olvidaste tu contraseña?</a>
                    </div>
                    <button class="btn_login" type="submit" id="btn_login">
                        Iniciar Sesión &nbsp; →
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>

