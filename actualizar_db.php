<?php
/**
 * SCRIPT DE ACTUALIZACIÓN DE BASE DE DATOS
 * Agrega los campos 'rol' y 'zona' a la tabla 'usuarios' si no existen.
 * Puede ejecutarse desde el navegador (http://localhost/PROYECTO_SERVICIO/actualizar_db.php) o desde la terminal (php actualizar_db.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_cli = (php_sapi_name() === 'cli');

// 1. Cargar conexión a la base de datos
$conexion_path = __DIR__ . '/config/conexion.php';
if (!file_exists($conexion_path)) {
    $err_msg = "Error: No se encontró el archivo de conexión en: $conexion_path";
    if ($is_cli) {
        echo "$err_msg\n";
    } else {
        echo "<div style='color:red; font-family:sans-serif;'>$err_msg</div>";
    }
    exit(1);
}

require $conexion_path;

$success_actions = [];
$errors = [];

try {
    // 2. Verificar columnas existentes en la tabla usuarios
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Agregar columna 'rol' si no existe
    if (!in_array('rol', $columns)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol VARCHAR(50) DEFAULT 'usuario'");
        $success_actions[] = "Columna 'rol' agregada exitosamente a la tabla 'usuarios'.";
    } else {
        $success_actions[] = "La columna 'rol' ya existe en la tabla 'usuarios'.";
    }

    // Agregar columna 'zona' si no existe
    if (!in_array('zona', $columns)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN zona VARCHAR(50) DEFAULT NULL");
        $success_actions[] = "Columna 'zona' agregada exitosamente a la tabla 'usuarios'.";
    } else {
        $success_actions[] = "La columna 'zona' ya existe en la tabla 'usuarios'.";
    }

    // 3. Actualizar usuarios existentes a rol de administrador para asegurar acceso inicial
    $stmt_update = $pdo->prepare("UPDATE usuarios SET rol = 'admin' WHERE rol IS NULL OR rol = '' OR rol = 'usuario'");
    $stmt_update->execute();
    $updated_rows = $stmt_update->rowCount();
    if ($updated_rows > 0) {
        $success_actions[] = "Se actualizaron $updated_rows usuarios existentes al rol 'admin'.";
    }

} catch (PDOException $e) {
    $errors[] = "Error de base de datos durante la migración: " . $e->getMessage();
}

// 4. Renderizar resultados
if ($is_cli) {
    echo "\n=== RESULTADOS DE LA MIGRACIÓN ===\n";
    if (!empty($success_actions)) {
        foreach ($success_actions as $action) {
            echo " [OK] $action\n";
        }
    }
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo " [ERROR] $error\n";
        }
    }
    echo "==================================\n\n";
} else {
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <link rel="icon" type="image/png" href="assets/multimedia/icon_bluebg.png">
        <title>Actualización de Base de Datos</title>
        <style>
            body {
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #f8fafc;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            .card {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 30px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            }
            h1 {
                font-size: 1.5rem;
                margin-top: 0;
                margin-bottom: 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .success-list {
                list-style: none;
                padding: 0;
                margin: 0 0 20px 0;
            }
            .success-list li {
                background: rgba(16, 185, 129, 0.15);
                border-left: 4px solid #10b981;
                padding: 10px 15px;
                margin-bottom: 10px;
                border-radius: 4px;
                font-size: 0.9rem;
            }
            .error-list {
                list-style: none;
                padding: 0;
                margin: 0 0 20px 0;
            }
            .error-list li {
                background: rgba(239, 68, 68, 0.15);
                border-left: 4px solid #ef4444;
                padding: 10px 15px;
                margin-bottom: 10px;
                border-radius: 4px;
                font-size: 0.9rem;
            }
            .info-box {
                background: rgba(245, 158, 11, 0.1);
                border: 1px solid rgba(245, 158, 11, 0.3);
                color: #f59e0b;
                padding: 12px 15px;
                border-radius: 8px;
                font-size: 0.85rem;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⚙️ Migración y Actualización de Tabla Usuarios</h1>
            
            <?php if (!empty($success_actions)): ?>
                <ul class="success-list">
                    <?php foreach ($success_actions as $action): ?>
                        <li>✓ <?php echo htmlspecialchars($action); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li>✗ <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="info-box">
                <strong>⚠️ Nota de Seguridad:</strong> Por motivos de seguridad, una vez ejecutado este script y comprobado que la base de datos se actualizó correctamente, debes eliminar el archivo <code>actualizar_db.php</code> del servidor.
            </div>
        </div>
    </body>
    </html>
    <?php
}
