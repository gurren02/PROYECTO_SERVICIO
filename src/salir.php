<?php
// 1. Reanudar la sesión existente
session_start();

// 2. Vaciar todas las variables de sesión actuales (nombre_completo, userlog, autentificado, etc.)
session_unset();

// 3. Destruir la sesión completamente del servidor
session_destroy();

// 4. Mostrar alerta y redirigir
echo'
    <script>
    alert("CERRASTE SESIÓN CORRECTAMENTE");
    location.href = "../view/login.php"
    </script>
    ';
?>

