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
    <link rel="icon" type="image/webp" href="../assets/multimedia/logoconfondo.webp">
    <title>RECUPERAR CONTRASEÑA</title>
</head>
<body>

<div class="login-page-wrapper">
    <div class="contenedor_login-fondo">
        <div class="contenedor_login">
            <h3 class="login-titulo">Recuperar acceso</h3>

            <div class="login-imagen">
                <img src="../assets/multimedia/favicon-cfe.svg" alt="loginlogo" class="imagen_log"
                     onerror="this.outerHTML='<div class=\'logo-placeholder\'>⚡</div>'">
            </div>

            <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; text-align: center; margin-bottom: 1.5rem; line-height: 1.4;">
                Ingresa tu correo de recuperación y te enviaremos las instrucciones.
            </p>

            <div class="login-credenciales">
                <form action="../src/procesar_recuperacion.php" method="post" id="credenciales" class="credenciales-login">

                    <legend class="titulo-credenciales">Correo electrónico</legend>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"
                                 viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input type="email" class="elementologin" placeholder="ejemplo@correo.com" name="correo_recuperacion" required>
                    </div>

                    <div class="row-extras" style="justify-content: center;">
                        <a href="login.php" class="link-forgot">Volver a iniciar sesión</a>
                    </div>

                    <button class="btn_login" type="submit" id="btn_recuperar">
                        Enviar enlace &nbsp; →
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>

