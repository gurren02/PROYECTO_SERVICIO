<?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>

<!-- Estado inicial: sidebar--open (abierto por defecto) -->
<aside class="sidebar sidebar--open" id="sidebar">

    <!-- Cabecera: logo CFE + botón toggle -->
    <div class="sidebar__head">
        <div class="sidebar__logo">
            <img src="../assets/multimedia/logoblanco_sedefac.webp" alt="Logo SEDEFAC" class="sidebar__logo-img sidebar__logo-icon">
            <img src="../assets/multimedia/titulonegativo_sedefac.webp" alt="SEDEFAC" class="sidebar__logo-img sidebar__logo-text">
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
           data-label="Cargar">
            <span class="material-symbols-rounded sidebar__icon">upload_file</span>
            <span class="sidebar__label">Cargar</span>
        </a>



        <?php 
        $active_reportes = in_array($pagina_actual, ['preparar_analisis.php', 'ejecutar_analisis_zona.php', 'ejecutar_analisis.php', 'detalle_estimaciones.php', 'comparacion.php']);
        $active_falsos = ($pagina_actual === 'falsos.php');
        $active_calcular = $active_reportes || $active_falsos;
        ?>
        <div class="sidebar__item-wrapper <?php echo $active_calcular ? 'sidebar__item-wrapper--active' : ''; ?>">
            <div class="sidebar__item" onclick="toggleCalcularSubmenu(event)" data-label="Seguimiento" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <span class="material-symbols-rounded sidebar__icon">calculate</span>
                    <span class="sidebar__label">Seguimiento</span>
                </div>
                <span class="material-symbols-rounded sidebar__arrow" id="calcularArrow">keyboard_arrow_right</span>
            </div>
            
            <div class="sidebar__submenu" id="calcularSubmenu">
                <a href="preparar_analisis.php" 
                   class="sidebar__subitem <?php echo $active_reportes ? 'sidebar__subitem--active' : ''; ?>"
                   data-label="Firme">
                    <span class="material-symbols-rounded" style="font-size: 18px;">assessment</span>
                    <span class="sidebar__label">Firme</span>
                </a>
                <a href="falsos.php" 
                   class="sidebar__subitem <?php echo $active_falsos ? 'sidebar__subitem--active' : ''; ?>"
                   data-label="Falso">
                    <span class="material-symbols-rounded" style="font-size: 18px;">gpp_bad</span>
                    <span class="sidebar__label">Falso</span>
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
        <a href="usuarios.php"
           class="sidebar__item <?php echo ($pagina_actual==='usuarios.php') ? 'sidebar__item--active' : ''; ?>"
           data-label="Usuarios">
            <span class="material-symbols-rounded sidebar__icon">manage_accounts</span>
            <span class="sidebar__label">Usuarios</span>
        </a>
        <?php endif; ?>
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
        
        // Si estamos en una página del submenu de Calcular, abrirlo por defecto
        const submenu = document.getElementById('calcularSubmenu');
        const arrow = document.getElementById('calcularArrow');
        const hasActiveSubitem = submenu && submenu.querySelector('.sidebar__subitem--active');
        if (hasActiveSubitem) {
            submenu.classList.add('sidebar__submenu--open');
            submenu.style.maxHeight = submenu.scrollHeight + "px";
            if (arrow) arrow.style.transform = "rotate(90deg)";
        }
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const icon    = document.getElementById('sidebarToggleIcon');
        const isOpen  = sidebar.classList.toggle('sidebar--open');

        // 2. Alternamos la clase en el body para empujar el main
        document.body.classList.toggle('con-sidebar-abierto', isOpen);

        icon.textContent = isOpen ? 'chevron_left' : 'chevron_right';

        // Si se cierra la sidebar, cerramos también el submenu
        if (!isOpen) {
            const submenu = document.getElementById('calcularSubmenu');
            const arrow = document.getElementById('calcularArrow');
            if (submenu) {
                submenu.classList.remove('sidebar__submenu--open');
                submenu.style.maxHeight = "0px";
            }
            if (arrow) {
                arrow.style.transform = "rotate(0deg)";
            }
        } else {
            // Si se abre y estamos en una página del submenu, lo abrimos
            const submenu = document.getElementById('calcularSubmenu');
            const arrow = document.getElementById('calcularArrow');
            const hasActiveSubitem = submenu && submenu.querySelector('.sidebar__subitem--active');
            if (hasActiveSubitem) {
                submenu.classList.add('sidebar__submenu--open');
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                if (arrow) arrow.style.transform = "rotate(90deg)";
            }
        }
    }

    function toggleMenu() { toggleSidebar(); }

    function toggleCalcularSubmenu(e) {
        const sidebar = document.getElementById('sidebar');
        const submenu = document.getElementById('calcularSubmenu');
        const arrow = document.getElementById('calcularArrow');
        
        if (!sidebar.classList.contains('sidebar--open')) {
            // Si la sidebar está colapsada, la abrimos
            toggleSidebar();
            // Y luego abrimos el submenu
            setTimeout(() => {
                submenu.classList.add('sidebar__submenu--open');
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                if (arrow) arrow.style.transform = "rotate(90deg)";
            }, 300); // Esperar la transición de la sidebar
        } else {
            const isOpen = submenu.classList.toggle('sidebar__submenu--open');
            if (isOpen) {
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                if (arrow) arrow.style.transform = "rotate(90deg)";
            } else {
                submenu.style.maxHeight = "0px";
                if (arrow) arrow.style.transform = "rotate(0deg)";
            }
        }
    }
</script>

