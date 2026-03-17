<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar sesión segura y candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gestión de Tablas y Archivos</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../assets/tablas.css">
    <link rel="stylesheet" href="../assets/estilos.css">
    <script src="../assets/script.js"></script>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main>
    <?php
    // ==========================================
    // CONEXIÓN UNIVERSAL A LA BASE DE DATOS
    // ==========================================
    require "../config/conexion.php"; 

    // ==========================================
    // ELIMINAR UN ARCHIVO INDIVIDUAL
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_archivo'])) {
        $id_archivo = (int)$_POST['id_archivo'];

        $stmt_info = $pdo->prepare("SELECT nombre_archivo_fisico, nombre_archivo_original FROM registro_archivos WHERE id_archivo = ?");
        $stmt_info->execute([$id_archivo]);
        $archivo_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($archivo_info) {
            $ruta_archivo = 'tablas/' . $archivo_info['nombre_archivo_fisico'];

            $stmt_delete = $pdo->prepare("DELETE FROM registro_archivos WHERE id_archivo = ?");
            $stmt_delete->execute([$id_archivo]);

            if (file_exists($ruta_archivo)) { unlink($ruta_archivo); }

            echo "<div class='alerta alerta-exito'><span class='material-icons'>check_circle</span> El registro del archivo <strong>" . htmlspecialchars($archivo_info['nombre_archivo_original']) . "</strong> se eliminó. La tabla permanece intacta.</div>";
        }
    }

    // ==========================================
    // ELIMINAR TABLA COMPLETA Y SUS ARCHIVOS
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_tabla'])) {
        $tabla_a_eliminar = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla_nombre']);

        if ($tabla_a_eliminar !== 'usuarios' && $tabla_a_eliminar !== 'registro_archivos') {
            try {
                $stmt_archivos = $pdo->prepare("SELECT nombre_archivo_fisico FROM registro_archivos WHERE nombre_tabla = ?");
                $stmt_archivos->execute([$tabla_a_eliminar]);
                $archivos_vinculados = $stmt_archivos->fetchAll(PDO::FETCH_ASSOC);

                foreach ($archivos_vinculados as $archivo) {
                    $ruta = 'tablas/' . $archivo['nombre_archivo_fisico'];
                    if (file_exists($ruta)) { unlink($ruta); }
                }

                $stmt_del_registro = $pdo->prepare("DELETE FROM registro_archivos WHERE nombre_tabla = ?");
                $stmt_del_registro->execute([$tabla_a_eliminar]);

                $drop_query = "DROP TABLE IF EXISTS `$tabla_a_eliminar`";
                $pdo->exec($drop_query);

                echo "<div class='alerta alerta-exito'><span class='material-icons'>check_circle</span> La tabla <strong>$tabla_a_eliminar</strong> y sus archivos se eliminaron físicamente de la base de datos.</div>";
            } catch (PDOException $e) {
                echo "<div class='alerta alerta-error'><span class='material-icons'>error</span> Error al eliminar: " . $e->getMessage() . "</div>";
            }
        }
    }
    ?>

    <div class="contenedor-listado">
        <div class="cabecera-seccion">
            <h3>Gestión de Anomalías Registradas</h3>
            <p>Listado basado en las tablas reales existentes en la base de datos.</p>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-moderna">
                <thead>
                <tr>
                    <th>Tipo de Anomalía</th>
                    <th>Resumen BD</th>
                    <th>Total Registros</th>
                    <th style="min-width: 350px;">Archivos / Historial</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php
                // --- Obtener tablas reales de MySQL ---
                $stmt_db = $pdo->query("SHOW TABLES");
                $todas_las_tablas = $stmt_db->fetchAll(PDO::FETCH_COLUMN);

                // Tablas que NO queremos listar
                $excluir = ['usuarios', 'registro_archivos'];

                $conteo_tablas = 0;

                foreach ($todas_las_tablas as $nombre_tabla) {
                    if (in_array($nombre_tabla, $excluir)) continue;

                    $conteo_tablas++;

                    // Obtener resumen de datos
                    try {
                        $stmt_resumen = $pdo->query("SELECT 
                            GROUP_CONCAT(DISTINCT anio_carga ORDER BY anio_carga ASC SEPARATOR ', ') as anios,
                            GROUP_CONCAT(DISTINCT mes_carga  ORDER BY mes_carga  ASC SEPARATOR ', ') as meses,
                            COUNT(*) as total_filas FROM `$nombre_tabla` LIMIT 1");
                        $resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);
                        $anios = $resumen['anios'] ?? 'N/A';
                        $meses = $resumen['meses'] ?? 'N/A';
                        $total_filas = $resumen['total_filas'] ?? 0;
                    } catch (Exception $e) {
                        $anios = 'N/A'; $meses = 'N/A'; $total_filas = 'Err';
                    }

                    $tipo_mostrar = ucwords(str_replace('_', ' ', $nombre_tabla));

                    // Buscar si hay archivos registrados para esta tabla
                    $stmt_archivos_tabla = $pdo->prepare("SELECT id_archivo, nombre_archivo_original, anio_asociado, mes_asociado, fecha_subida FROM registro_archivos WHERE nombre_tabla = ? ORDER BY fecha_subida DESC");
                    $stmt_archivos_tabla->execute([$nombre_tabla]);
                    $archivos_bd = $stmt_archivos_tabla->fetchAll(PDO::FETCH_ASSOC);

                    echo "<tr>";
                    echo "<td><strong style='color: var(--teal-deep);'>$tipo_mostrar</strong><br><small class='texto-mutado'>$nombre_tabla</small></td>";
                    echo "<td><span style='font-size:0.85rem;'><strong style='color:var(--teal-mid);'>Año:</strong> $anios<br><strong style='color:var(--teal-mid);'>Mes:</strong> $meses</span></td>";
                    echo "<td><span class='badge'>$total_filas</span></td>";

                    echo "<td>";
                    if (count($archivos_bd) > 0) {
                        foreach ($archivos_bd as $archivo) {
                            $fecha = date('d/m/Y H:i', strtotime($archivo['fecha_subida']));
                            $nombre_esc = htmlspecialchars($archivo['nombre_archivo_original']);
                            echo "
                            <div class='archivo-item'>
                                <div style='display:flex; justify-content:space-between; align-items:center;'>
                                    <span class='nombre-archivo'><span class='material-icons icono-pequeno'>insert_drive_file</span> $nombre_esc</span>
                                    <form action='' method='POST' style='margin:0;' onsubmit='return confirm(\"¿Eliminar solo el archivo? La tabla se mantendrá.\");'>
                                        <input type='hidden' name='id_archivo' value='{$archivo['id_archivo']}'>
                                        <button type='submit' name='eliminar_archivo' class='btn-eliminar-archivo'><span class='material-icons' style='font-size:18px;'>delete_outline</span></button>
                                    </form>
                                </div>
                                <div style='display:flex; gap:6px;'><span class='chip'>Año {$archivo['anio_asociado']}</span><span class='chip'>Mes {$archivo['mes_asociado']}</span><span class='chip'>↑ $fecha</span></div>
                            </div>";
                        }
                    } else {
                        echo "<span class='texto-mutado' style='font-style:italic;'>Archivo CSV eliminado (Datos conservados en BD)</span>";
                    }
                    echo "</td>";

                    echo "<td>
                            <div class='grupo-acciones'>
                                <form action='ver_tabla.php' method='GET'>
                                    <input type='hidden' name='tabla' value='$nombre_tabla'>
                                    <button type='submit' class='btn btn-ver'><span class='material-icons'>visibility</span> Ver BD</button>
                                </form>
                                <form action='' method='POST' onsubmit='return confirm(\"BORRADO TOTAL: ¿Eliminar la tabla $nombre_tabla de la BD?\");'>
                                    <input type='hidden' name='tabla_nombre' value='$nombre_tabla'>
                                    <button type='submit' name='eliminar_tabla' class='btn btn-eliminar-tabla'><span class='material-icons'>delete_forever</span> Borrar Todo</button>
                                </form>
                            </div>
                          </td>";
                    echo "</tr>";
                }

                if ($conteo_tablas === 0) {
                    echo "<tr><td colspan='5' class='estado-vacio'>No existen tablas de datos en la base de datos.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>

