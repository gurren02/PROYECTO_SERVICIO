<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciamos sesión de forma segura y ponemos el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// --- CAPTURAR DATOS PREDEFINIDOS DE LA URL ---
$pre_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$pre_anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$pre_mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : date('m');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/subir.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="../assets/script.js"></script>
    <title>SUBIR ARCHIVOS</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="subir-main">

    <div class="subir-card">

        <div class="subir-card__header">
            <h1 class="subir-card__title">Carga de Archivos CSV</h1>
            <p class="subir-card__subtitle">Registra nuevos datos de anomalías por tipo, año y mes.</p>
        </div>

        <div class="subir-card__body">

            <?php
            // ==========================================
            // 1. CONEXIÓN UNIVERSAL A LA BASE DE DATOS
            // ==========================================
            require "../config/conexion.php"; // <--- AQUÍ ESTÁ LA MAGIA

            try {
                // Creamos la tabla de control (bitácora) si no existe
                $tabla_control_query = "CREATE TABLE IF NOT EXISTS `registro_archivos` (
                        `id_archivo` INT AUTO_INCREMENT PRIMARY KEY,
                        `nombre_tabla` VARCHAR(100) NOT NULL,
                        `tipo_registro` VARCHAR(100) NOT NULL,
                        `anio_asociado` INT NOT NULL,
                        `mes_asociado` INT NOT NULL,
                        `nombre_archivo_original` VARCHAR(255) NOT NULL,
                        `nombre_archivo_fisico` VARCHAR(255) NOT NULL,
                        `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                $pdo->exec($tabla_control_query);

            } catch (PDOException $e) {
                die("<div class='subir-alert subir-alert--error'>Error al inicializar la base de datos: " . $e->getMessage() . "</div>");
            }

            // ==========================================
            // 2. PROCESAMIENTO DEL ARCHIVO SUBIDO
            // ==========================================
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {

                $tipo_defecto = preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['tipo_defecto']);
                $anio = (int)$_POST['anio'];
                $mes  = (int)$_POST['mes'];

                $archivo_tmp = $_FILES['archivo']['tmp_name'];
                $nombre_archivo_original = $_FILES['archivo']['name'];
                $ext = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));

                if ($ext === 'csv') {
                    $directorio_destino = 'tablas/';
                    if (!is_dir($directorio_destino)) {
                        mkdir($directorio_destino, 0777, true);
                    }

                    $nombre_archivo_fisico = uniqid($tipo_defecto . '_') . '.csv';
                    $ruta_final = $directorio_destino . $nombre_archivo_fisico;

                    if (move_uploaded_file($archivo_tmp, $ruta_final)) {
                        if (($handle = fopen($ruta_final, "r")) !== FALSE) {
                            $headers = fgetcsv($handle, 10000, ",");

                            if ($headers) {
                                $columnas_sql = [];
                                $columnas_limpias = [];

                                foreach ($headers as $index => $header) {
                                    $col_name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($header));
                                    if (empty($col_name)) {
                                        $col_name = "columna_" . $index;
                                    }
                                    $columnas_limpias[] = $col_name;
                                    $columnas_sql[] = "`$col_name` TEXT";
                                }

                                $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                $nombre_tabla = strtolower($tipo_defecto) . $anio . $mes_formateado;

                                // --- SOLUCIÓN A LOS DUPLICADOS ---
                                $pdo->exec("DROP TABLE IF EXISTS `$nombre_tabla`");
                                // ---------------------------------

                                $create_table_query = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
                                        `id_registro` INT AUTO_INCREMENT PRIMARY KEY,
                                        `anio_carga` INT,
                                        `mes_carga` INT,
                                        " . implode(", ", $columnas_sql) . "
                                    )";
                                $pdo->exec($create_table_query);

                                $placeholders = implode(",", array_fill(0, count($columnas_limpias), "?"));
                                $insert_query = "INSERT INTO `$nombre_tabla` 
                                            (`anio_carga`, `mes_carga`, " . implode(", ", array_map(function($c) { return "`$c`"; }, $columnas_limpias)) . ") 
                                            VALUES (?, ?, $placeholders)";
                                $stmt = $pdo->prepare($insert_query);

                                $filas_insertadas = 0;
                                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                                    if (count($data) === count($columnas_limpias)) {
                                        $params = array_merge([$anio, $mes], $data);
                                        $stmt->execute($params);
                                        $filas_insertadas++;
                                    }
                                }
                                fclose($handle);

                                // Registramos la acción en la bitácora histórica
                                $stmt_registro = $pdo->prepare("
                                        INSERT INTO `registro_archivos` 
                                        (nombre_tabla, tipo_registro, anio_asociado, mes_asociado, nombre_archivo_original, nombre_archivo_fisico) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                $stmt_registro->execute([$nombre_tabla, $tipo_defecto, $anio, $mes, $nombre_archivo_original, $nombre_archivo_fisico]);

                                echo "<div class='subir-alert subir-alert--success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>
                                        Archivo procesado correctamente. Se insertaron <strong>$filas_insertadas</strong> filas en <strong>$nombre_tabla</strong>. <br>
                                        <small><em>(Si existían datos anteriores para este periodo, fueron reemplazados exitosamente).</em></small>
                                      </div>";
                            } else {
                                echo "<div class='subir-alert subir-alert--error'>El archivo CSV está vacío o no tiene el formato correcto.</div>";
                            }
                        } else {
                            echo "<div class='subir-alert subir-alert--error'>No se pudo leer el archivo.</div>";
                        }
                    } else {
                        echo "<div class='subir-alert subir-alert--error'>Error al mover el archivo al servidor.</div>";
                    }
                } else {
                    echo "<div class='subir-alert subir-alert--error'>Por favor, sube un archivo con extensión <strong>.csv</strong></div>";
                }
            }
            ?>

            <form action="" method="POST" enctype="multipart/form-data" class="subir-form">

                <div class="subir-form__group">
                    <label class="subir-form__label" for="tipo_defecto">Tipo de Anomalía (Registro)</label>
                    <select name="tipo_defecto" id="tipo_defecto" class="subir-form__control" required>
                        <option value="" disabled <?php echo empty($pre_tipo) ? 'selected' : ''; ?>>Seleccione un tipo...</option>
                        <option value="cancelaciones" <?php echo ($pre_tipo === 'cancelaciones') ? 'selected' : ''; ?>>Cancelaciones</option>
                        <option value="estimaciones" <?php echo ($pre_tipo === 'estimaciones') ? 'selected' : ''; ?>>Estimaciones</option>
                        <option value="consumos_cero" <?php echo ($pre_tipo === 'consumos_cero') ? 'selected' : ''; ?>>Consumos Cero</option>
                        <option value="servicios_sin_medicion" <?php echo ($pre_tipo === 'servicios_sin_medicion') ? 'selected' : ''; ?>>Servicios Sin Medición</option>
                        <option value="correcciones_de_lecturas" <?php echo ($pre_tipo === 'correcciones_de_lecturas') ? 'selected' : ''; ?>>Correcciones de Lecturas</option>
                        <option value="anomalias_pendientes" <?php echo ($pre_tipo === 'anomalias_pendientes') ? 'selected' : ''; ?>>Anomalías Pendientes</option>
                        <option value="sin_facturar" <?php echo ($pre_tipo === 'sin_facturar') ? 'selected' : ''; ?>>Sin Facturar</option>
                        <option value="cargas_directas" <?php echo ($pre_tipo === 'cargas_directas') ? 'selected' : ''; ?>>Cargas Directas</option>
                    </select>
                </div>

                <div class="subir-form__row">
                    <div class="subir-form__group">
                        <label class="subir-form__label" for="anio">Año</label>
                        <input type="number" name="anio" id="anio" class="subir-form__control"
                               value="<?php echo $pre_anio; ?>" min="2000" max="2100" required>
                    </div>

                    <div class="subir-form__group">
                        <label class="subir-form__label" for="mes">Mes</label>
                        <select name="mes" id="mes" class="subir-form__control" required>
                            <?php
                            $meses = [
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ];
                            foreach ($meses as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($num === (int)$pre_mes) ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="subir-form__group">
                    <label class="subir-form__label" for="archivo">Seleccionar Archivo</label>
                    <label class="subir-dropzone" for="archivo" id="dropzone-label">
                        <svg class="subir-dropzone__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span class="subir-dropzone__text" id="dropzone-text">Haz clic para seleccionar un archivo <strong>.csv</strong></span>
                        <span class="subir-dropzone__hint">o arrastra y suelta aquí</span>
                        <input type="file" name="archivo" id="archivo" accept=".csv" required
                               onchange="document.getElementById('dropzone-text').textContent = this.files[0] ? this.files[0].name : 'Haz clic para seleccionar un archivo .csv';">
                    </label>
                </div>

                <div class="subir-form__footer">
                    <button type="submit" class="subir-btn">Subir Archivo</button>
                </div>

            </form>
        </div>
    </div>

</main>
</body>
</html>

