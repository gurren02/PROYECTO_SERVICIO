<?php
header('Content-Type: application/json');
require "../config/conexion.php";

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'init') {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['error' => 'No se recibió ningún archivo.']); exit;
    }

    $tipo_defecto = preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['tipo_defecto']);
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $original_name = $_FILES['archivo']['name'];

    $directorio_destino = '../tablas/';
    if (!is_dir($directorio_destino)) { mkdir($directorio_destino, 0777, true); }

    $tmp_filename = uniqid('tmp_csv_') . '.csv';
    $ruta_final = $directorio_destino . $tmp_filename;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_final)) {
        echo json_encode(['error' => 'Error al mover el archivo subido al servidor.']); exit;
    }

    // 1. Contar lineas rápido para el Frontend
    $total_lines = 0;
    $handle = fopen($ruta_final, "r");
    while(!feof($handle)){
        if(fgets($handle) !== false) $total_lines++;
    }
    // Restamos la cabecera
    $total_lines = max(0, $total_lines - 1);
    rewind($handle);

    // 2. Leer cabeceras y crear tabla
    $headers = fgetcsv($handle, 10000, ",");
    fclose($handle);

    if (!$headers) {
        unlink($ruta_final);
        echo json_encode(['error' => 'El archivo CSV está vacío o es inválido.']); exit;
    }

    $columnas_sql = [];
    $columnas_a_indexar = [];
    foreach ($headers as $index => $header) {
        $col_name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($header));
        if (empty($col_name)) { $col_name = "columna_" . $index; }
        $columnas_sql[] = "`$col_name` VARCHAR(255) DEFAULT NULL";
        
        $col_upper = strtoupper($col_name);
        if (in_array($col_upper, ['ZONA', 'CICLO', 'AGENCIA', 'RPU'])) {
            $columnas_a_indexar[] = $col_name;
        }
    }

    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = strtolower($tipo_defecto) . $anio . $mes_formateado;

    try {
        $pdo->exec("DROP TABLE IF EXISTS `$nombre_tabla`");
        $create_table_query = "CREATE TABLE `$nombre_tabla` (
                `id_registro` INT AUTO_INCREMENT PRIMARY KEY,
                `anio_carga` INT,
                `mes_carga` INT,
                " . implode(", ", $columnas_sql) . "
            )";
        $pdo->exec($create_table_query);

        // Crear índices para optimizar búsquedas y agrupaciones
        foreach ($columnas_a_indexar as $col) {
            $pdo->exec("ALTER TABLE `$nombre_tabla` ADD INDEX `idx_" . strtolower($col) . "` (`$col`)");
        }
    } catch (PDOException $e) {
        unlink($ruta_final);
        echo json_encode(['error' => 'Error al crear tabla dinámica: ' . $e->getMessage()]); exit;
    }

    echo json_encode([
        'status' => 'ok',
        'tmp_file' => $tmp_filename,
        'original_name' => $original_name,
        'total_lines' => $total_lines,
        'nombre_tabla' => $nombre_tabla
    ]);
    exit;
}

if ($action === 'process_chunk') {
    $tmp_filename = $_POST['tmp_file'];
    $ruta_final = '../tablas/' . basename($tmp_filename); // basename por seguridad
    $start = (int)$_POST['start'];
    $limit = (int)$_POST['limit'];
    
    $tipo_defecto = preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['tipo_defecto']);
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = strtolower($tipo_defecto) . $anio . $mes_formateado;

    if (!file_exists($ruta_final)) {
        echo json_encode(['error' => 'El archivo temporal no existe o se perdió.']); exit;
    }

    $handle = fopen($ruta_final, "r");
    $headers = fgetcsv($handle, 10000, ",");
    $columnas_limpias = [];
    foreach ($headers as $index => $header) {
        $col_name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($header));
        if (empty($col_name)) { $col_name = "columna_" . $index; }
        $columnas_limpias[] = $col_name;
    }

    // Saltar filas (start parameter). Asumimos start=0 es la primera fila de DATOS (después de headers).
    for ($i = 0; $i < $start; $i++) {
        if (fgetcsv($handle) === false) break;
    }

    $placeholders = implode(",", array_fill(0, count($columnas_limpias), "?"));
    $insert_query = "INSERT INTO `$nombre_tabla` 
                (`anio_carga`, `mes_carga`, " . implode(", ", array_map(function($c) { return "`$c`"; }, $columnas_limpias)) . ") 
                VALUES (?, ?, $placeholders)";
    $stmt = $pdo->prepare($insert_query);

    // MEGA OPTIMIZACION: Abrimos la Transacción
    $pdo->beginTransaction();
    $filas_procesadas = 0;

    // Procesamos hasta llegar al Límite
    while ($filas_procesadas < $limit && ($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        if (count($data) === count($columnas_limpias)) {
            $trimmed_data = array_map('trim', $data);
            $params = array_merge([$anio, $mes], $trimmed_data);
            $stmt->execute($params);
            $filas_procesadas++;
        }
    }
    
    // Impactamos la base de datos de golpe
    $pdo->commit();
    fclose($handle);

    echo json_encode([
        'status' => 'ok',
        'processed' => $filas_procesadas
    ]);
    exit;
}

if ($action === 'finish') {
    $tmp_filename = $_POST['tmp_file'];
    $ruta_final = '../tablas/' . basename($tmp_filename);
    
    $tipo_defecto = $_POST['tipo_defecto'];
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $original_name = $_POST['original_name'];
    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = strtolower($tipo_defecto) . $anio . $mes_formateado;

    $stmt_registro = $pdo->prepare("
        INSERT INTO `registro_archivos` 
        (nombre_tabla, tipo_registro, anio_asociado, mes_asociado, nombre_archivo_original, nombre_archivo_fisico) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_registro->execute([$nombre_tabla, $tipo_defecto, $anio, $mes, $original_name, basename($tmp_filename)]);

    if (file_exists($ruta_final)) { unlink($ruta_final); }

    echo json_encode(['status' => 'ok', 'message' => 'Carga completada y bitácora registrada.']);
    exit;
}

echo json_encode(['error' => 'Acción no válida.']);
