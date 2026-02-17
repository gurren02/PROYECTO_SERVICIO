function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const main = document.querySelector('main');
    sidebar.classList.toggle('expanded');
    main.classList.toggle('expanded');
}