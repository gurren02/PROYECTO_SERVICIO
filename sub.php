<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="estilos.css">
    <script src="script.js"></script>
    <title>SUBIR</title>
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
        // 1. CONFIGURACIÓN DE LA BASE DE DATOS
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
        // 2. PROCESAMIENTO DEL ARCHIVO SUBIDO
        // ==========================================
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {

            // Limpiar el nombre del tipo de defecto para usarlo como nombre de tabla
            $tipo_defecto = preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['tipo_defecto']);
            $anio = (int)$_POST['anio'];
            $mes  = (int)$_POST['mes'];

            $archivo_tmp = $_FILES['archivo']['tmp_name'];
            $nombre_archivo = $_FILES['archivo']['name'];
            $ext = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                // Intentar abrir el archivo (detecta automáticamente el separador más común, ajusta si es necesario a ';')
                if (($handle = fopen($archivo_tmp, "r")) !== FALSE) {

                    // Leer la primera fila (encabezados)
                    $headers = fgetcsv($handle, 10000, ",");

                    if ($headers) {
                        $columnas_sql = [];
                        $columnas_limpias = [];

                        // Limpiar los nombres de las columnas para que sean válidos en MySQL
                        foreach ($headers as $index => $header) {
                            // Reemplaza espacios y caracteres raros por guión bajo
                            $col_name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($header));
                            // Si alguna columna viene vacía, asignarle un nombre genérico
                            if (empty($col_name)) {
                                $col_name = "columna_" . $index;
                            }
                            $columnas_limpias[] = $col_name;
                            $columnas_sql[] = "`$col_name` TEXT"; // Por defecto se usa TEXT para evitar truncamientos
                        }

                        // Definir el nombre de la tabla en base al tipo de defecto
                        $nombre_tabla = "defecto_" . strtolower($tipo_defecto);

                        // Crear la tabla dinámicamente si no existe, añadiendo las columnas para distinguirlas
                        $create_table_query = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
                    `id_registro` INT AUTO_INCREMENT PRIMARY KEY,
                    `anio_carga` INT,
                    `mes_carga` INT,
                    " . implode(", ", $columnas_sql) . "
                )";
                        $pdo->exec($create_table_query);

                        // Preparar la consulta de inserción (Prepared Statements para seguridad)
                        $placeholders = implode(",", array_fill(0, count($columnas_limpias), "?"));
                        $insert_query = "INSERT INTO `$nombre_tabla` 
                                (`anio_carga`, `mes_carga`, " . implode(", ", array_map(function($c) { return "`$c`"; }, $columnas_limpias)) . ") 
                                VALUES (?, ?, $placeholders)";

                        $stmt = $pdo->prepare($insert_query);

                        // Recorrer el resto del archivo e insertar fila por fila
                        $filas_insertadas = 0;
                        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                            // Validar que la fila tenga la misma cantidad de columnas que el encabezado
                            if (count($data) === count($columnas_limpias)) {
                                // Unir los valores del formulario (año, mes) con los datos de la fila
                                $params = array_merge([$anio, $mes], $data);
                                $stmt->execute($params);
                                $filas_insertadas++;
                            }
                        }
                        fclose($handle);

                        echo "<p style='color: green;'>✅ Archivo procesado correctamente. Se insertaron $filas_insertadas filas en la tabla <strong>$nombre_tabla</strong>.</p>";
                    } else {
                        echo "<p style='color: red;'>❌ El archivo CSV está vacío o no tiene el formato correcto.</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ No se pudo leer el archivo.</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Por favor, sube un archivo con extensión .csv</p>";
            }
        }
        ?>

        <form action="" method="POST" enctype="multipart/form-data">

            <div>
                <label for="tipo_defecto">Tipo de defecto:</label>
                <select name="tipo_defecto" id="tipo_defecto" required>
                    <option value="" disabled selected>Seleccione un tipo...</option>
                    <option value="tipo_1">Defecto Tipo 1</option>
                    <option value="tipo_2">Defecto Tipo 2</option>
                    <option value="tipo_3">Defecto Tipo 3</option>
                    <option value="tipo_4">Defecto Tipo 4</option>
                    <option value="tipo_5">Defecto Tipo 5</option>
                    <option value="tipo_6">Defecto Tipo 6</option>
                    <option value="tipo_7">Defecto Tipo 7</option>
                    <option value="tipo_8">Defecto Tipo 8</option>
                    <option value="tipo_9">Defecto Tipo 9</option>
                </select>
            </div>
            <br>

            <div>
                <label for="anio">Año:</label>
                <input type="number" name="anio" id="anio" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
            </div>
            <br>

            <div>
                <label for="mes">Mes:</label>
                <input type="number" name="mes" id="mes" value="<?php echo date('m'); ?>" min="1" max="12" required>
            </div>
            <br>

            <div>
                <label for="archivo">Seleccionar Archivo (CSV):</label>
                <input type="file" name="archivo" id="archivo" accept=".csv" required>
            </div>
            <br>
            <div>
                <button type="submit">Subir Archivo</button>
            </div>
        </form>
    </main>
</body>
</html>
