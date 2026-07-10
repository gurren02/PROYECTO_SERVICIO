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
    <meta name="description" content="SEDEFAC — Seguimiento a los Defectos de la Facturación. Sistema de análisis y control de anomalías CFE.">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/index.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/webp" href="../assets/multimedia/logoconfondo.webp">
    <title>SEDEFAC — Seguimiento a los Defectos de la Facturación</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>

<main class="idx-main">

    <div class="idx-welcome">
        <div class="idx-welcome__brand">
            <img src="../assets/multimedia/logo_sedefac.webp" alt="Logo SEDEFAC" class="idx-welcome__brand-logo">
            <img src="../assets/multimedia/titulocompleto_sedefac.webp" alt="SEDEFAC — Seguimiento a los Defectos de la Facturación" class="idx-welcome__brand-title">
        </div>
        <span class="idx-welcome__date" id="idx-date"></span>
    </div>

    <div class="idx-grid">

        <!-- CARGAR -->
        <a href="subir.php" class="idx-card idx-card--upload">
            <div class="idx-card__bg"></div>
            <div class="idx-card__overlay"></div>
            <div class="idx-card__fade"></div>
            <div class="idx-card__content">
                <div class="idx-card__icon-wrap">
                    <span class="material-symbols-rounded idx-card__icon">upload_file</span>
                </div>
                <h2 class="idx-card__title">Cargar</h2>
                <p class="idx-card__desc">Carga y gestiona datos de defectos por tipo, año y mes desde archivos .CSV y .TXT.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <!-- FIRME -->
        <a href="preparar_analisis.php" class="idx-card idx-card--firme">
            <div class="idx-card__bg"></div>
            <div class="idx-card__overlay"></div>
            <div class="idx-card__fade"></div>
            <div class="idx-card__content">
                <div class="idx-card__icon-wrap">
                    <span class="material-symbols-rounded idx-card__icon">assessment</span>
                </div>
                <h2 class="idx-card__title">Firme</h2>
                <p class="idx-card__desc">Genera reportes de defectos por zona, agencia, estimaciones y comparación de ciclos.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <!-- FALSO -->
        <a href="falsos.php" class="idx-card idx-card--falso">
            <div class="idx-card__bg"></div>
            <div class="idx-card__overlay"></div>
            <div class="idx-card__fade"></div>
            <div class="idx-card__content">
                <div class="idx-card__icon-wrap">
                    <span class="material-symbols-rounded idx-card__icon">gpp_bad</span>
                </div>
                <h2 class="idx-card__title">Falso</h2>
                <p class="idx-card__desc">Seguimiento de lecturas y estimados por agencia.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>

        <!-- USUARIOS (solo admin) -->
        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
        <a href="usuarios.php" class="idx-card idx-card--users">
            <div class="idx-card__bg"></div>
            <div class="idx-card__overlay"></div>
            <div class="idx-card__fade"></div>
            <div class="idx-card__content">
                <div class="idx-card__icon-wrap">
                    <span class="material-symbols-rounded idx-card__icon">manage_accounts</span>
                </div>
                <h2 class="idx-card__title">Usuarios</h2>
                <p class="idx-card__desc">Administra cuentas de acceso, registra nuevos usuarios y gestiona perfiles.</p>
            </div>
            <span class="material-symbols-rounded idx-card__arrow">arrow_forward</span>
        </a>
        <?php endif; ?>

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
