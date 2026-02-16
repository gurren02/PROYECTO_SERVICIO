<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú tipo YouTube</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }

        /* Header */
        header {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background-color: #fff;
            border-bottom: 1px solid #e5e5e5;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .menu-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            margin-right: 16px;
        }

        .menu-toggle span {
            display: block;
            width: 24px;
            height: 2px;
            background-color: #333;
            margin: 5px 0;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #ff0000;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            background-color: #fff;
            width: 72px;
            overflow-y: auto;
            border-right: 1px solid #e5e5e5;
        }

        .sidebar.expanded {
            width: 240px;
        }

        /* Menu Items */
        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0;
            text-decoration: none;
            color: #333;
            cursor: pointer;
        }

        .sidebar.expanded .menu-item {
            flex-direction: row;
            padding: 12px 24px;
            align-items: center;
        }

        .menu-item:hover {
            background-color: #f2f2f2;
        }

        .menu-icon {
            width: 24px;
            height: 24px;
            margin-bottom: 6px;
        }

        .sidebar.expanded .menu-icon {
            margin-bottom: 0;
            margin-right: 24px;
        }

        .menu-text {
            font-size: 10px;
            text-align: center;
        }

        .sidebar.expanded .menu-text {
            font-size: 14px;
            text-align: left;
        }

        /* Main Content */
        main {
            margin-top: 60px;
            margin-left: 72px;
            padding: 20px;
        }

        .sidebar.expanded ~ main {
            margin-left: 240px;
        }
    </style>
</head>
<body>
<nav class="sidebar" id="sidebar">
    <a href="#" class="menu-item">
        <img src="home-icon.png" alt="Inicio" class="menu-icon">
        <div class="menu-text">Inicio</div>
    </a>
    <a href="#" class="menu-item">
        <img src="trending-icon.png" alt="Tendencias" class="menu-icon">
        <div class="menu-text">Tendencias</div>
    </a>
    <a href="#" class="menu-item">
        <img src="library-icon.png" alt="Biblioteca" class="menu-icon">
        <div class="menu-text">Biblioteca</div>
    </a>
    <a href="#" class="menu-item">
        <img src="history-icon.png" alt="Historial" class="menu-icon">
        <div class="menu-text">Historial</div>
    </a>
    <a href="#" class="menu-item">
        <img src="videos-icon.png" alt="Tus videos" class="menu-icon">
        <div class="menu-text">Tus videos</div>
    </a>
    <a href="#" class="menu-item">
        <img src="later-icon.png" alt="Ver más tarde" class="menu-icon">
        <div class="menu-text">Ver más tarde</div>
    </a>
    <a href="#" class="menu-item">
        <img src="like-icon.png" alt="Me gusta" class="menu-icon">
        <div class="menu-text">Me gusta</div>
    </a>
</nav>

<main>
    <h1>Contenido Principal</h1>
    <p>Este es el contenido de la página. El menú lateral se expande y contrae al hacer clic en el botón hamburguesa.</p>
</main>

<script>
    function toggleMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('expanded');
    }
</script>
</body>
</html>