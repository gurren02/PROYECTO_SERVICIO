<?php
// Configurar la zona horaria por defecto para coincidir con la hora local
date_default_timezone_set('America/Mexico_City');

// ==============================================================================
// CONEXIÓN UNIVERSAL A LA BASE DE DATOS (Compatible con XAMPP y Docker)
// ==============================================================================

$dbname = 'anomalias'; 
$user   = 'root'; 
$pass   = '';

try {
    // Intento 1: Conectar a la red de Docker (El host se llama 'db')
    // connect_timeout=1 → en XAMPP, falla en máximo 1 segundo en vez de esperar el timeout TCP del sistema (puede ser 10–20s)
    $host = 'db';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4;connect_timeout=1", $user, $pass);
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
