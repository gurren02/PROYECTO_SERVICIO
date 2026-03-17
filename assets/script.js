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
        document.getElementById('userDropdown').classList.remove('is-open');
        document.getElementById('userMenuBtn').setAttribute('aria-expanded', 'false');
    }
});
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
});gb