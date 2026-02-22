<?php
session_start();
session_destroy();
echo'
    <script>
    alert("CERRASTE SESIÓN CORRECTAMENTE");
    location.href = "login.php"
    </script>
    ';
?>
