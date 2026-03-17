<?php
// ==============================================================================
// CONEXIÓN UNIVERSAL A LA BASE DE DATOS (Compatible con XAMPP y Docker)
// ==============================================================================

$dbname = 'anomalias'; 
$user   = 'root'; 
$pass   = '';

try {
    // Intento 1: Conectar a la red de Docker (El host se llama 'db')
    $host = 'db';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    try {
        // Intento 2: Si falla Docker, intentamos con XAMPP (El host es 'localhost')
        $host = 'localhost';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    } catch (PDOException $e2) {
        // Si ambos fallan, detenemos el sistema y mostramos el error
        die("Error crítico: No se pudo conectar a la base de datos. Asegúrate de tener XAMPP o Docker encendido.");
    }
}
// Si el código llega hasta aquí, significa que la variable $pdo ya está lista para usarse.
?>
