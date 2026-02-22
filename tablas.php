<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>TABLAS</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="script.js"></script>
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
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }

    // ==========================================
    // 2. LÓGICA PARA ELIMINAR TABLA
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_tabla'])) {
        // Limpiamos el nombre de la tabla por seguridad (solo permitimos letras, números y guiones bajos)
        $tabla_a_eliminar = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla_nombre']);

        // Verificación: asegurarnos de que solo se borren tablas que comiencen con "defecto_"
        if (strpos($tabla_a_eliminar, 'defecto_') === 0) {
            try {
                $drop_query = "DROP TABLE IF EXISTS `$tabla_a_eliminar`";
                $pdo->exec($drop_query);
                echo "<div style='color: #b5d333; margin-bottom: 15px; padding: 10px; background: rgba(0,0,0,0.5); border-radius: 8px;'>
                    ✅ La tabla <strong>$tabla_a_eliminar</strong> se eliminó correctamente.
                  </div>";
            } catch (PDOException $e) {
                echo "<div style='color: #ff4444; margin-bottom: 15px;'>❌ Error al eliminar la tabla: " . $e->getMessage() . "</div>";
            }
        }
    }
    ?>

    <div class="contenedor-listado-tablas">
        <h3 style="color: #fff; margin-bottom: 15px;">Gestión de Anomalías Registradas</h3>

        <table style="width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.05); color: #fff; text-align: left; border-radius: 10px; overflow: hidden;">
            <thead style="background: rgba(18, 83, 77, 0.8); color: #b5d333;">
            <tr>
                <th style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.2);">Tipo de Defecto</th>
                <th style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.2);">Años</th>
                <th style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.2);">Meses</th>
                <th style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.2);">Total Registros</th>
                <th style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.2);">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // Buscar todas las tablas que contengan la palabra "defecto_"
            $query_tablas = "SHOW TABLES LIKE 'defecto_%'";
            $stmt_tablas = $pdo->query($query_tablas);

            if ($stmt_tablas && $stmt_tablas->rowCount() > 0) {
                while ($fila_tabla = $stmt_tablas->fetch(PDO::FETCH_NUM)) {
                    $nombre_tabla = $fila_tabla[0];

                    // Consultar los datos dentro de cada tabla para obtener el resumen usando PDO
                    try {
                        $query_resumen = "SELECT 
                                            GROUP_CONCAT(DISTINCT anio_carga ORDER BY anio_carga ASC SEPARATOR ', ') as anios,
                                            GROUP_CONCAT(DISTINCT mes_carga ORDER BY mes_carga ASC SEPARATOR ', ') as meses,
                                            COUNT(*) as total_filas 
                                          FROM `$nombre_tabla`";
                        $stmt_resumen = $pdo->query($query_resumen);
                        $resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);

                        $anios = !empty($resumen['anios']) ? $resumen['anios'] : 'N/A';
                        $meses = !empty($resumen['meses']) ? $resumen['meses'] : 'N/A';
                        $total_filas = $resumen['total_filas'];

                    } catch (PDOException $e) {
                        // Si ocurre un error o la tabla está vacía
                        $anios = 'N/A';
                        $meses = 'N/A';
                        $total_filas = 0;
                    }

                    // Formatear el texto para que se lea mejor (ej. "defecto_tipo_1" -> "Tipo 1")
                    $tipo_mostrar = ucwords(str_replace('_', ' ', str_replace('defecto_', '', $nombre_tabla)));

                    echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.1); transition: background 0.3s;'>";

                    // Columna: Nombre/Tipo
                    echo "<td style='padding: 12px;'><strong>$tipo_mostrar</strong><br><small style='color: #aaa;'>($nombre_tabla)</small></td>";

                    // Columna: Años
                    echo "<td style='padding: 12px;'>$anios</td>";

                    // Columna: Meses
                    echo "<td style='padding: 12px;'>$meses</td>";

                    // Columna: Total de registros
                    echo "<td style='padding: 12px;'>$total_filas</td>";

                    // Columna: Botones de Acción
                    echo "<td style='padding: 12px; display: flex; gap: 10px; align-items: center;'>
                            
                            <form action='ver_tabla.php' method='GET' style='margin: 0;'>
                                <input type='hidden' name='tabla' value='$nombre_tabla'>
                                <button type='submit' style='background: #12534d; color: white; border: 1px solid rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 6px; cursor: pointer; transition: 0.2s;'>
                                    👁️ Ver
                                </button>
                            </form>

                            <form action='' method='POST' style='margin: 0;' onsubmit='return confirm(\"¿Estás completamente seguro de eliminar la tabla $nombre_tabla? Se perderán todos los datos.\");'>
                                <input type='hidden' name='tabla_nombre' value='$nombre_tabla'>
                                <button type='submit' name='eliminar_tabla' style='background: #c0392b; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; transition: 0.2s;'>
                                    🗑️ Eliminar
                                </button>
                            </form>

                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='padding: 20px; text-align: center; color: #aaa;'>No hay tablas registradas en el sistema.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
