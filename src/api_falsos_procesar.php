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
        $pdo->exec("DROP TABLE IF EXISTS `$nombre_tabla`");
        $create_table_query = "CREATE TABLE `$nombre_tabla` (
                `id_registro` INT AUTO_INCREMENT PRIMARY KEY,
                `anio_carga` INT,
                `mes_carga` INT,
                `Rpu` VARCHAR(50),
                `Nis` VARCHAR(50),
                `Nombre` VARCHAR(100),
                `Direccion` VARCHAR(100),
                `Tarifa` VARCHAR(10),
                `Codigo` VARCHAR(10),
                `Tipo` VARCHAR(50),
                `Anomalia` VARCHAR(50),
                `Ciclo` INT,
                `Agencia` VARCHAR(50),
                `Zona` VARCHAR(50)
            )";
        $pdo->exec($create_table_query);
    } catch (PDOException $e) {
        unlink($ruta_final);
        echo json_encode(['error' => 'Error al crear tabla dinámica para Falsos: ' . $e->getMessage()]); exit;
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

    // Intentar deducir ciclo por defecto desde el nombre del archivo original
    $ciclo_defecto = null;
    if (preg_match('/c(\d+)/i', $original_name, $m)) {
        $ciclo_defecto = (int)$m[1];
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

    $insert_query = "INSERT INTO `$nombre_tabla` 
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

        // Derivar Ciclo desde los primeros 2 caracteres de la primera columna
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

echo json_encode(['error' => 'Acción no válida en Falsos.']);
