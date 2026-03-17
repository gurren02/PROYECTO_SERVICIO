<?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>

<!-- Estado inicial: sidebar--open (abierto por defecto) -->
<aside class="sidebar sidebar--open" id="sidebar">

    <!-- Cabecera: logo CFE + botón toggle -->
    <div class="sidebar__head">
        <div class="sidebar__logo">
            <img src="../assets/multimedia/logo-cfe.svg" alt="CFE" class="sidebar__logo-img">
        </div>
        <button class="sidebar__toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle menú">
            <span class="material-symbols-rounded" id="sidebarToggleIcon">chevron_left</span>
        </button>
    </div>

    <!-- Navegación principal -->
    <nav class="sidebar__nav">
        <a href="index.php"
           class="sidebar__item <?php echo ($pagina_actual==='index.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Inicio">
            <span class="material-symbols-rounded sidebar__icon">home</span>
            <span class="sidebar__label">Inicio</span>
        </a>

        <a href="subir.php"
           class="sidebar__item <?php echo ($pagina_actual==='subir.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Subir">
            <span class="material-symbols-rounded sidebar__icon">upload_file</span>
            <span class="sidebar__label">Subir</span>
        </a>

        <a href="tablas.php"
           class="sidebar__item <?php echo ($pagina_actual==='tablas.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Tablas">
            <span class="material-symbols-rounded sidebar__icon">table_chart</span>
            <span class="sidebar__label">Tablas</span>
        </a>

        <a href="preparar_analisis.php"
           class="sidebar__item <?php echo ($pagina_actual==='calcular.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Calcular">
            <span class="material-symbols-rounded sidebar__icon">calculate</span>
            <span class="sidebar__label">Calcular</span>
        </a>

        <a href="usuarios.php"
           class="sidebar__item <?php echo ($pagina_actual==='usuarios.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Usuarios">
            <span class="material-symbols-rounded sidebar__icon">manage_accounts</span>
            <span class="sidebar__label">Usuarios</span>
        </a>
    </nav>

    <!-- Footer: info de usuario + cerrar sesión -->
    <?php
    $sb_nombre = isset($_SESSION['nombre_completo']) ? htmlspecialchars($_SESSION['nombre_completo']) : 'Usuario';
    $sb_user   = isset($_SESSION['userlog'])         ? htmlspecialchars($_SESSION['userlog'])         : '';
    ?>
    <div class="sidebar__footer">

        <div class="sidebar__divider"></div>

        <!-- Bloque de usuario — clickeable, lleva a perfil.php -->
        <a href="perfil.php"
           class="sidebar__user <?php echo ($pagina_actual==='perfil.php') ? 'sidebar__user--active' : ''; ?>"
           data-label="<?php echo $sb_nombre; ?>">
            <div class="sidebar__user-avatar">
                <span class="material-symbols-rounded">person</span>
            </div>
            <div class="sidebar__user-info">
                <span class="sidebar__user-name"><?php echo $sb_nombre; ?></span>
                <span class="sidebar__user-role"><?php echo $sb_user; ?></span>
            </div>
        </a>

        <div class="sidebar__divider"></div>

        <!-- Cerrar sesión (rojo) -->
        <a href="../src/salir.php" class="sidebar__item sidebar__item--danger" data-label="Cerrar sesión">
            <span class="material-symbols-rounded sidebar__icon">logout</span>
            <span class="sidebar__label">Cerrar sesión</span>
        </a>

    </div>

</aside>

<script>
    // 1. Al cargar la página, le decimos al body que el menú inicia ABIERTO
    document.addEventListener("DOMContentLoaded", function() {
        document.body.classList.add('con-sidebar-abierto');
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const icon    = document.getElementById('sidebarToggleIcon');
        const isOpen  = sidebar.classList.toggle('sidebar--open');

        // 2. Alternamos la clase en el body para empujar el main
        document.body.classList.toggle('con-sidebar-abierto', isOpen);

        icon.textContent = isOpen ? 'chevron_left' : 'chevron_right';
    }

    function toggleMenu() { toggleSidebar(); }
</script>

