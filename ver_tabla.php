<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="estilos.css">
    <script src="script.js"></script>
    <title>TABLA</title>
</head>
<body>
<header>
    <?php
    include "header.php";
    ?>
    <?php
    include "menu.php";
    ?>
</header>
<main>
    <?php
    // ==========================================
    // 1. CONFIGURACIÓN DE LA BASE DE DATOS (PDO)
    // ==========================================
    $host   = 'localhost';
    $dbname = 'anomalias';
    $user   = 'root';
    $pass   = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("<div style='color: #ff4444;'>Error de conexión a la base de datos: " . $e->getMessage() . "</div>");
    }

    // ==========================================
    // 2. LÓGICA PARA OBTENER DATOS DE LA TABLA
    // ==========================================
    $nombre_tabla_limpio = "";
    $columnas = [];
    $filas = [];
    $error_msj = "";

    if (isset($_GET['tabla'])) {
        // Limpieza estricta: solo letras, números y guiones bajos
        $nombre_tabla_limpio = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']);

        // Verificación de seguridad: asegurar que empiece con "defecto_"
        if (strpos($nombre_tabla_limpio, 'defecto_') === 0) {
            try {
                // Obtener los nombres de las columnas
                $stmt_cols = $pdo->query("SHOW COLUMNS FROM `$nombre_tabla_limpio`");
                $columnas = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);

                // Obtener todos los registros de la tabla
                $stmt_datos = $pdo->query("SELECT * FROM `$nombre_tabla_limpio`");
                $filas = $stmt_datos->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                $error_msj = "❌ Error al leer la tabla: " . $e->getMessage();
            }
        } else {
            $error_msj = "❌ Nombre de tabla no válido.";
        }
    } else {
        $error_msj = "❌ No se ha seleccionado ninguna tabla.";
    }

    // Dar formato al título para que se vea limpio (ej. "defecto_tipo_1" -> "Tipo 1")
    $titulo_mostrar = ucwords(str_replace('_', ' ', str_replace('defecto_', '', $nombre_tabla_limpio)));
    ?>

    <div class="contenedor-ver-tabla" style="width: 100%;">

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <a href="tablas.php" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; transition: 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                ⬅ Atrás
            </a>

            <h3 style="color: #fff; margin: 0; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 1px;">
                <?php echo !empty($titulo_mostrar) ? "Datos: " . htmlspecialchars($titulo_mostrar) : "Visor de Datos"; ?>
            </h3>

            <div style="width: 80px;"></div> </div>

        <?php if (!empty($error_msj)): ?>
            <div style="color: #ff4444; margin-bottom: 15px; padding: 10px; background: rgba(0,0,0,0.5); border-radius: 8px;">
                <?php echo $error_msj; ?>
            </div>
        <?php endif; ?>

        <div style="width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
            <table style="width: 100%; border-collapse: collapse; color: #fff; text-align: left; white-space: nowrap;">
                <thead style="background: rgba(18, 83, 77, 0.8); color: #b5d333;">
                <tr>
                    <?php if (!empty($columnas)): ?>
                        <?php foreach ($columnas as $col): ?>
                            <th style="padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.2); font-weight: 600;">
                                <?php echo htmlspecialchars($col['Field']); ?>
                            </th>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <th style="padding: 12px 15px;">Sin columnas detectadas</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($filas)): ?>
                    <?php foreach ($filas as $fila): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='transparent'">
                            <?php foreach ($columnas as $col): ?>
                                <td style="padding: 10px 15px;">
                                    <?php
                                    $valor = $fila[$col['Field']];
                                    echo htmlspecialchars($valor !== null ? $valor : '-');
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo count($columnas) > 0 ? count($columnas) : 1; ?>" style="padding: 30px; text-align: center; color: #aaa;">
                            Esta tabla no contiene registros actualmente.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>
</body>
</html>