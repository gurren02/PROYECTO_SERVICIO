<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="estilos.css">
    <title>INICIAR SESIÓN</title>
</head>
<body>
    <div class="contenedor_login-fondo">
        <div class="contenedor_login">
            <h3 class="login-titulo">Iniciar sesión</h3>
            <div class="login-imagen">
                <img src="multimedia/logologin.png" alt="loginlogo" class="imagen_log">
            </div>
            <div class="login-credenciales">
                <form action="" method="post" id="credenciales" class="credenciales-login">
                    <legend class="titulo-credenciales">Usuario</legend>
                    <input type="text" class="elementologin" placeholder="Usuario" name=userlog" required>
                    <legend class="titulo-credenciales">Contraseña</legend>
                    <input type="password" class="elementologin" placeholder="Contraseña" name="password" required>
                    <buttom class="btn_login" type="button" id="btn_login">Iniciar sesión</buttom>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
