<?php
// Obtener nombre del usuario de la sesión adaptado a tu nueva BD
// Usamos 'nombre_completo', y si no está, caemos al texto por defecto
$nombre_usuario = isset($_SESSION['nombre_completo']) ? htmlspecialchars($_SESSION['nombre_completo']) : 'Usuario no encontrado';

// Como no tienes columna de rol en la BD, usamos el 'userlog' para mantener el diseño visual intacto
$rol_usuario    = isset($_SESSION['userlog']) ? htmlspecialchars($_SESSION['userlog']) : '';
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

<div class="contenedor_header">
    <a href="index.php" class="header-logo-link" aria-label="Inicio">
        <img src="../assets/multimedia/logo-cfe.svg" alt="logo CFE" class="logo-header">
    </a>

    <div class="header-user" id="headerUser">
        <button class="header-user__btn" onclick="toggleUserMenu()" aria-label="Menú de usuario" aria-expanded="false" id="userMenuBtn">
            <span class="material-symbols-rounded header-user__icon">account_circle</span>
        </button>

        <div class="header-user__dropdown" id="userDropdown" role="menu">
            <div class="header-user__info">
                <span class="material-symbols-rounded header-user__avatar-icon">person</span>
                <div class="header-user__details">
                    <span class="header-user__name"><?php echo $nombre_usuario; ?></span>
                    <?php if ($rol_usuario): ?>
                        <span class="header-user__role"><?php echo $rol_usuario; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="header-user__divider"></div>

            <a href="perfil.php" class="header-user__item" role="menuitem">
                <span class="material-symbols-rounded">manage_accounts</span>
                Configurar perfil
            </a>

            <div class="header-user__divider"></div>

            <a href="../src/salir.php" class="header-user__item header-user__item--danger" role="menuitem">
                <span class="material-symbols-rounded">logout</span>
                Cerrar sesión
            </a>
        </div>
    </div>

</div>

<script>
    // 1. LÓGICA DEL DROPDOWN
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        const btn      = document.getElementById('userMenuBtn');
        const isOpen   = dropdown.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', isOpen);
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('headerUser');
        if (wrapper && !wrapper.contains(e.target)) {
            const dropdown = document.getElementById('userDropdown');
            const btn = document.getElementById('userMenuBtn');
            if (dropdown) dropdown.classList.remove('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    });

    // 2. LÓGICA DE DATATABLES (Requiere jQuery cargado previamente)
    $(document).ready(function() {
        var table = $('#tablaDinamica').DataTable({
            "scrollX": false,
            "autoWidth": true,
            "pageLength": 10,
            "deferRender": true,
            "colReorder": false,
            "ordering": false,            // ← Desactiva el ordenamiento de columnas
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/2.0.0/i18n/es-ES.json"
            },
            "dom": 'rt<"bottom"ip><"clear">'
        });

        // Buscador manual personalizado
        $('<input type="text" placeholder="Buscar..." class="custom-search-input">')
            .appendTo('#contenedor-busqueda')
            .on('keyup', function() {
                table.search(this.value).draw();
            });

        // Ajuste de columnas
        setTimeout(function() {
            table.columns.adjust().draw();
        }, 200);

        // Re-ajuste al redimensionar ventana
        $(window).on('resize', function() {
            table.columns.adjust();
        });
    });
</script>

