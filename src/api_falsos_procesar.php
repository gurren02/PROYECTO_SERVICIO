<?php
header('Content-Type: application/json');
require "../config/conexion.php";

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$action = isset($_POST['action']) ? $_POST['action'] : '';

$mapa_agencias = [
    'A' => 'CENTRO',
    'B' => 'NORTE',
    'C' => 'SUR',
    'D' => 'ORIENTE',
    'E' => 'PONIENTE',
    'F' => 'MOTUL',
    'G' => 'PROGRESO',
    'H' => 'HUNUCMA',
    'J' => 'UMAN',
    'K' => 'ACANCEH',
    'M' => 'CONKAL'
];

if ($action === 'init') {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['error' => 'No se recibió ningún archivo.']); exit;
    }

    $tipo_defecto = 'falsos';
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $original_name = $_FILES['archivo']['name'];

    $directorio_destino = '../tablas/';
    if (!is_dir($directorio_destino)) { 
        mkdir($directorio_destino, 0777, true); 
    }

    $tmp_filename = uniqid('tmp_falsos_') . '.txt';
    $ruta_final = $directorio_destino . $tmp_filename;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_final)) {
        echo json_encode(['error' => 'Error al mover el archivo subido al de destino.']); exit;
    }

    // Contar líneas reales en el archivo TXT
    $total_lines = 0;
    $handle = fopen($ruta_final, "r");
    while(!feof($handle)){
        $line = fgets($handle);
        if ($line !== false && trim($line) !== '') {
            $total_lines++;
        }
    }
    fclose($handle);

    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = $tipo_defecto . $anio . $mes_formateado;

    try {
        // En lugar de DROP TABLE, creamos la tabla si no existe (con NOT NULL DEFAULT para evitar que NULL inhabilite el UNIQUE)
        $create_table_query = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
                `id_registro` INT AUTO_INCREMENT PRIMARY KEY,
                `anio_carga` INT,
                `mes_carga` INT,
                `Rpu` VARCHAR(50) NOT NULL DEFAULT '',
                `Nis` VARCHAR(50) NOT NULL DEFAULT '',
                `Nombre` VARCHAR(100) NOT NULL DEFAULT '',
                `Direccion` VARCHAR(100) NOT NULL DEFAULT '',
                `Tarifa` VARCHAR(10) NOT NULL DEFAULT '',
                `Codigo` VARCHAR(10) NOT NULL DEFAULT '',
                `Tipo` VARCHAR(50) NOT NULL DEFAULT '',
                `Anomalia` VARCHAR(50) NOT NULL DEFAULT '',
                `Ciclo` INT NOT NULL DEFAULT 0,
                `Agencia` VARCHAR(50) NOT NULL DEFAULT '',
                `Zona` VARCHAR(50) NOT NULL DEFAULT '01',
                `Comentario` TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($create_table_query);

        // Asegurar la conversión de columnas a NOT NULL si la tabla ya existía con esquema antiguo
        $pdo->exec("ALTER TABLE `$nombre_tabla` 
            MODIFY COLUMN `Rpu` VARCHAR(50) NOT NULL DEFAULT '',
            MODIFY COLUMN `Nis` VARCHAR(50) NOT NULL DEFAULT '',
            MODIFY COLUMN `Nombre` VARCHAR(100) NOT NULL DEFAULT '',
            MODIFY COLUMN `Direccion` VARCHAR(100) NOT NULL DEFAULT '',
            MODIFY COLUMN `Tarifa` VARCHAR(10) NOT NULL DEFAULT '',
            MODIFY COLUMN `Codigo` VARCHAR(10) NOT NULL DEFAULT '',
            MODIFY COLUMN `Tipo` VARCHAR(50) NOT NULL DEFAULT '',
            MODIFY COLUMN `Anomalia` VARCHAR(50) NOT NULL DEFAULT '',
            MODIFY COLUMN `Ciclo` INT NOT NULL DEFAULT 0,
            MODIFY COLUMN `Agencia` VARCHAR(50) NOT NULL DEFAULT '',
            MODIFY COLUMN `Zona` VARCHAR(50) NOT NULL DEFAULT '01'
        ");

        // Eliminar posibles registros duplicados existentes para poder crear el índice único
        $pdo->exec("
            DELETE t1 FROM `$nombre_tabla` t1
            INNER JOIN `$nombre_tabla` t2 
            ON t1.id_registro > t2.id_registro 
            AND t1.Rpu = t2.Rpu 
            AND t1.Nis = t2.Nis 
            AND t1.Tipo = t2.Tipo 
            AND t1.Anomalia = t2.Anomalia 
            AND t1.Ciclo = t2.Ciclo 
            AND t1.Agencia = t2.Agencia
        ");

        // Crear el índice único si no existe
        try {
            $pdo->exec("ALTER TABLE `$nombre_tabla` ADD UNIQUE KEY `idx_unique_falso` (`Rpu`, `Nis`, `Tipo`, `Anomalia`, `Ciclo`, `Agencia`)");
        } catch (PDOException $e) {
            // Ignorar si ya existe
        }
    } catch (PDOException $e) {
        unlink($ruta_final);
        echo json_encode(['error' => 'Error al preparar la tabla dinámica para Falsos: ' . $e->getMessage()]); exit;
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
    $ruta_final = '../tablas/' . basename($tmp_filename);
    $start = (int)$_POST['start'];
    $limit = (int)$_POST['limit'];
    $original_name = isset($_POST['original_name']) ? $_POST['original_name'] : '';
    
    $tipo_defecto = 'falsos';
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = $tipo_defecto . $anio . $mes_formateado;

    if (!file_exists($ruta_final)) {
        echo json_encode(['error' => 'El archivo temporal no existe o se perdió.']); exit;
    }
    
    $handle = fopen($ruta_final, "r");
    
    // Saltar líneas hasta la posición de inicio
    $lineas_leidas = 0;
    while ($lineas_leidas < $start && !feof($handle)) {
        $line = fgets($handle);
        if ($line !== false && trim($line) !== '') {
            $lineas_leidas++;
        }
    }

    $insert_query = "INSERT IGNORE INTO `$nombre_tabla` 
        (`anio_carga`, `mes_carga`, `Rpu`, `Nis`, `Nombre`, `Direccion`, `Tarifa`, `Codigo`, `Tipo`, `Anomalia`, `Ciclo`, `Agencia`, `Zona`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insert_query);

    $pdo->beginTransaction();
    $filas_procesadas = 0;

    while ($filas_procesadas < $limit && !feof($handle)) {
        $line = fgets($handle);
        if ($line === false) {
            break;
        }
        if (trim($line) === '') {
            continue;
        }

        // Asegurar que la línea tenga ancho suficiente rellenando con espacios si es necesario
        if (strlen($line) < 132) {
            $line = str_pad($line, 132, ' ');
        }

        // Parseo de ancho fijo
        $first_col = trim(substr($line, 0, 16)); // e.g. 23DW01D032300390
        $rpu       = trim(substr($line, 17, 12)); // e.g. 773000557128
        $nis       = $first_col;                  // Se guarda la primera columna como NIS
        $nombre    = trim(substr($line, 30, 31));
        $direccion = trim(substr($line, 61, 31));
        $tarifa    = trim(substr($line, 92, 2));
        $codigo    = trim(substr($line, 95, 4));
        $tipo      = trim(substr($line, 100, 16));
        $anomalia  = trim(substr($line, 116, 16));

        // Derivar Agencia desde el 7º carácter de la primera columna (index 6)
        $letra_agencia = (strlen($first_col) >= 7) ? strtoupper($first_col[6]) : '';
        $agencia = $mapa_agencias[$letra_agencia] ?? '';

        // Derivar Ciclo desde los primeros 2 caracteres de la primera columna (e.g. 23)
        $ciclo = (int)substr($first_col, 0, 2);

        // Zona por defecto '01'
        $zona = '01';

        $stmt->execute([
            $anio,
            $mes,
            $rpu,
            $nis,
            $nombre,
            $direccion,
            $tarifa,
            $codigo,
            $tipo,
            $anomalia,
            $ciclo,
            $agencia,
            $zona
        ]);

        $filas_procesadas++;
    }
    
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
    
    $tipo_defecto = 'falsos';
    $anio = (int)$_POST['anio'];
    $mes  = (int)$_POST['mes'];
    $original_name = $_POST['original_name'];
    $mes_formateado = str_pad($mes, 2, "0", STR_PAD_LEFT);
    $nombre_tabla = $tipo_defecto . $anio . $mes_formateado;

    // Asegurar que la tabla de registro existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `registro_archivos` (
        `id_archivo` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre_tabla` VARCHAR(100) NOT NULL,
        `tipo_registro` VARCHAR(100) NOT NULL,
        `anio_asociado` INT NOT NULL,
        `mes_asociado` INT NOT NULL,
        `nombre_archivo_original` VARCHAR(255) NOT NULL,
        `nombre_archivo_fisico` VARCHAR(255) NOT NULL,
        `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt_registro = $pdo->prepare("
        INSERT INTO `registro_archivos` 
        (nombre_tabla, tipo_registro, anio_asociado, mes_asociado, nombre_archivo_original, nombre_archivo_fisico) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_registro->execute([$nombre_tabla, $tipo_defecto, $anio, $mes, $original_name, basename($tmp_filename)]);

    if (file_exists($ruta_final)) { 
        unlink($ruta_final); 
    }

    echo json_encode(['status' => 'ok', 'message' => 'Carga de Falsos completada y bitácora registrada.']);
    exit;
}

if ($action === 'get_history') {
    try {
        $stmt = $pdo->prepare("SELECT id_archivo, nombre_tabla, anio_asociado, mes_asociado, nombre_archivo_original, fecha_subida FROM registro_archivos WHERE tipo_registro = 'falsos' ORDER BY fecha_subida DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'ok', 'data' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al obtener historial: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_history') {
    $id_archivo = isset($_POST['id_archivo']) ? (int)$_POST['id_archivo'] : 0;
    if ($id_archivo <= 0) {
        echo json_encode(['error' => 'ID de archivo no válido.']);
        exit;
    }

    try {
        // Consultar el nombre de la tabla para este archivo
        $stmt = $pdo->prepare("SELECT nombre_tabla FROM registro_archivos WHERE id_archivo = ? AND tipo_registro = 'falsos'");
        $stmt->execute([$id_archivo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $nombre_tabla = $row['nombre_tabla'];
            
            // Eliminar tabla dinámica de anomalías
            $pdo->exec("DROP TABLE IF EXISTS `$nombre_tabla`");
            
            // Eliminar registro de bitácora
            $stmt_del = $pdo->prepare("DELETE FROM registro_archivos WHERE id_archivo = ?");
            $stmt_del->execute([$id_archivo]);
            
            echo json_encode(['status' => 'ok', 'message' => 'Datos eliminados correctamente.']);
        } else {
            echo json_encode(['error' => 'No se encontró el registro o no pertenece a Falsos.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al eliminar datos: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Acción no válida en Falsos.']);
