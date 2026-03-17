<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciamos sesión de forma segura y ponemos el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/index.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <title>SEGUIMIENTO ANOMALÍAS</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>

<main class="idx-main">

    <div class="idx-welcome">
        <div>
            <h1 class="idx-welcome__title">Bienvenido al sistema</h1>
            <p class="idx-welcome__sub">Seguimiento y gestión de anomalías — CFE</p>
        </div>
        <span class="idx-welcome__date" id="idx-date"></span>
    </div>

    <div class="idx-grid">

        <a href="subir.php" class="idx-card idx-card--upload">
            <div class="idx-card__glow"></div>
            <div class="idx-card__icon-wrap">
                <span class="material-symbols-rounded idx-card__icon">upload_file</span>
            </div>
            <div class="idx-card__body">
                <h2 class="idx-card__title">Subir Archivos</h2>
                <p class="idx-card__desc">Carga archivos CSV con datos de anomalías por tipo, año y mes.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <a href="tablas.php" class="idx-card idx-card--tables">
            <div class="idx-card__glow"></div>
            <div class="idx-card__icon-wrap">
                <span class="material-symbols-rounded idx-card__icon">table_chart</span>
            </div>
            <div class="idx-card__body">
                <h2 class="idx-card__title">Gestionar Tablas</h2>
                <p class="idx-card__desc">Visualiza, administra y elimina bases de datos de anomalías registradas.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <a href="preparar_analisis.php" class="idx-card idx-card--calc">
            <div class="idx-card__glow"></div>
            <div class="idx-card__icon-wrap">
                <span class="material-symbols-rounded idx-card__icon">calculate</span>
            </div>
            <div class="idx-card__body">
                <h2 class="idx-card__title">Calcular</h2>
                <p class="idx-card__desc">Ejecuta cálculos y análisis estadísticos sobre los datos de anomalías.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <a href="usuarios.php" class="idx-card idx-card--users">
            <div class="idx-card__glow"></div>
            <div class="idx-card__icon-wrap">
                <span class="material-symbols-rounded idx-card__icon">manage_accounts</span>
            </div>
            <div class="idx-card__body">
                <h2 class="idx-card__title">Gestionar Usuarios</h2>
                <p class="idx-card__desc">Administra cuentas de acceso, registra nuevos usuarios y gestiona perfiles.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

    </div>

</main>

<script>
    (function() {
        const el = document.getElementById('idx-date');
        const dias   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const meses  = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const now = new Date();
        el.textContent = dias[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
    })();
</script>

</body>
</html>

