<?php
// 1. INICIO DE SESIÓN Y SEGURIDAD (¡SIEMPRE EN LA LÍNEA 1, ANTES DEL HTML!)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

// 3. LÓGICA DE PERIODOS Y TIPOS ESPERADOS
$busqueda_activa = false;

$tipos_esperados = [
        'cancelaciones',
        'estimaciones',
        'consumos_cero',
        'servicios_sin_medicion',
        'correcciones_de_lecturas',
        'anomalias_pendientes',
        'sin_facturar'
];

$matriz_datos = [];
foreach ($tipos_esperados as $tipo) {
    $matriz_datos[$tipo] = ['p1' => false, 'p2' => false, 'p3' => false];
}

$nombres_meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mes_objetivo'], $_POST['anio_objetivo'])) {
    $busqueda_activa = true;

    $p1_mes  = (int)$_POST['mes_objetivo'];
    $p1_anio = (int)$_POST['anio_objetivo'];

    $p2_mes  = $p1_mes;
    $p2_anio = $p1_anio - 1;

    $p3_mes  = $p1_mes - 2;
    $p3_anio = $p1_anio;
    if ($p3_mes <= 0) {
        $p3_mes  += 12;
        $p3_anio -= 1;
    }

    $sufijo_p1 = $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
    $sufijo_p2 = $p2_anio . str_pad($p2_mes, 2, '0', STR_PAD_LEFT);
    $sufijo_p3 = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);

    $stmt_db         = $pdo->query("SHOW TABLES");
    $todas_las_tablas = $stmt_db->fetchAll(PDO::FETCH_COLUMN);

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

    // Obtener las fechas de última actualización de la bitácora
    $stmt_fechas = $pdo->query("SELECT nombre_tabla, MAX(fecha_subida) as ultima_fecha FROM registro_archivos GROUP BY nombre_tabla");
    $fechas_registro = $stmt_fechas->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($todas_las_tablas as $tabla) {
        $sufijo_tabla = substr($tabla, -6);
        $tipo_base    = substr($tabla, 0, -6);

        if (array_key_exists($tipo_base, $matriz_datos)) {
            $fecha_u = isset($fechas_registro[$tabla]) ? $fechas_registro[$tabla] : 'Fecha no disponible';
            
            if ($sufijo_tabla === $sufijo_p1) $matriz_datos[$tipo_base]['p1'] = $fecha_u;
            if ($sufijo_tabla === $sufijo_p2) $matriz_datos[$tipo_base]['p2'] = $fecha_u;
            if ($sufijo_tabla === $sufijo_p3) $matriz_datos[$tipo_base]['p3'] = $fecha_u;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparar Análisis</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/preparar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="calc-main">

    <div class="calc-page-header">
        <h1 class="calc-page-title">Configuración de Análisis</h1>
        <p class="calc-page-subtitle">Selecciona el mes y año objetivo. El sistema verificará automáticamente la disponibilidad de datos para los tres periodos de comparación.</p>
    </div>

    <div class="calc-card calc-card--form">
        <div class="calc-card__header">
            <span class="material-symbols-rounded">manage_search</span>
            Selección de periodo objetivo
        </div>
        <div class="calc-card__body">
            <form action="" method="POST" class="calc-form">
                <div class="calc-form__group">
                    <label class="calc-form__label" for="mes_objetivo">
                        <span class="material-symbols-rounded">calendar_month</span>
                        Mes objetivo
                    </label>
                    <select name="mes_objetivo" id="mes_objetivo" class="calc-form__control" required>
                        <?php
                        $mes_actual = isset($_POST['mes_objetivo']) ? (int)$_POST['mes_objetivo'] : (int)date('n');
                        foreach ($nombres_meses as $num => $nombre):
                            ?>
                            <option value="<?php echo $num; ?>" <?php echo ($num === $mes_actual) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="calc-form__group">
                    <label class="calc-form__label" for="anio_objetivo">
                        <span class="material-symbols-rounded">event</span>
                        Año objetivo
                    </label>
                    <input type="number" name="anio_objetivo" id="anio_objetivo"
                           class="calc-form__control" required
                           value="<?php echo isset($_POST['anio_objetivo']) ? (int)$_POST['anio_objetivo'] : date('Y'); ?>"
                           min="2000" max="2100">
                </div>

                <button type="submit" class="calc-btn calc-btn--primary">
                    <span class="material-symbols-rounded">search</span>
                    Buscar datos
                </button>
            </form>
        </div>
    </div>

    <?php if ($busqueda_activa): ?>

        <div class="calc-periods">
            <div class="calc-period-chip">
                <span class="calc-period-chip__dot calc-period-chip__dot--p1"></span>
                <div>
                    <span class="calc-period-chip__label">Periodo objetivo</span>
                    <span class="calc-period-chip__value"><?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?></span>
                </div>
            </div>
            <div class="calc-period-chip">
                <span class="calc-period-chip__dot calc-period-chip__dot--p2"></span>
                <div>
                    <span class="calc-period-chip__label">Año anterior (móvil)</span>
                    <span class="calc-period-chip__value"><?php echo $nombres_meses[$p2_mes] . ' ' . $p2_anio; ?></span>
                </div>
            </div>
            <div class="calc-period-chip">
                <span class="calc-period-chip__dot calc-period-chip__dot--p3"></span>
                <div>
                    <span class="calc-period-chip__label">Bimestre anterior</span>
                    <span class="calc-period-chip__value"><?php echo $nombres_meses[$p3_mes] . ' ' . $p3_anio; ?></span>
                </div>
            </div>
        </div>

        <div class="calc-card calc-card--table">
            <div class="calc-card__header">
                <span class="material-symbols-rounded">fact_check</span>
                Disponibilidad de datos
            </div>
            <div class="calc-table-wrapper">
                <table class="calc-table">
                    <thead>
                    <tr>
                        <th class="calc-table__th-name">Tipo de anomalía</th>
                        <th>
                            <span class="calc-th-period">
                                <span class="calc-th-period__dot calc-th-period__dot--p1"></span>
                                <?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?>
                            </span>
                        </th>
                        <th>
                            <span class="calc-th-period">
                                <span class="calc-th-period__dot calc-th-period__dot--p2"></span>
                                <?php echo $nombres_meses[$p2_mes] . ' ' . $p2_anio; ?>
                            </span>
                        </th>
                        <th>
                            <span class="calc-th-period">
                                <span class="calc-th-period__dot calc-th-period__dot--p3"></span>
                                <?php echo $nombres_meses[$p3_mes] . ' ' . $p3_anio; ?>
                            </span>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($matriz_datos as $tipo => $periodos):
                        $nombre_bonito = ucfirst(str_replace('_', ' ', $tipo));
                        $todos_listos  = $periodos['p1'] && $periodos['p2'] && $periodos['p3'];
                        ?>
                        <tr class="<?php echo $todos_listos ? 'calc-table__row--complete' : ''; ?>">
                            <td class="calc-table__name">
                            <span class="material-symbols-rounded calc-table__name-icon">
                                <?php echo $todos_listos ? 'task_alt' : 'pending'; ?>
                            </span>
                                <?php echo $nombre_bonito; ?>
                            </td>

                            <?php foreach (['p1','p2','p3'] as $p):
                                $anio_ref = ($p==='p1') ? $p1_anio : (($p==='p2') ? $p2_anio : $p3_anio);
                                $mes_ref  = ($p==='p1') ? $p1_mes  : (($p==='p2') ? $p2_mes  : $p3_mes);
                                ?>
                                <td class="calc-table__cell">
                                    <?php if ($periodos[$p]): ?>
                                        <div class="calc-status-container">
                                            <span class="calc-status calc-status--ok">
                                                <span class="material-symbols-rounded">check_circle</span>
                                                Listo
                                            </span>
                                            <span class="calc-update-date">
                                                <span class="material-symbols-rounded">history</span>
                                                <?php 
                                                    if ($periodos[$p] !== 'Fecha no disponible') {
                                                        echo date('d/m/Y H:i', strtotime($periodos[$p])); 
                                                    } else {
                                                        echo $periodos[$p];
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <a href="subir.php?tipo=<?php echo urlencode($tipo); ?>&anio=<?php echo $anio_ref; ?>&mes=<?php echo $mes_ref; ?>"
                                           class="calc-btn-add">
                                            <span class="material-symbols-rounded">upload</span>
                                            Agregar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <a href="ejecutar_analisis.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>"
               class="calc-btn-generate" style="flex: 1; justify-content: center; background-color: #0d6efd;">
                <span class="material-symbols-rounded">business</span>
                Reporte Nivel Agencia
            </a>

            <a href="ejecutar_analisis_zona.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>"
               class="calc-btn-generate" style="flex: 1; justify-content: center; background-color: #198754;">
                <span class="material-symbols-rounded">map</span>
                Reporte Nivel Zona
            </a>
        </div>

    <?php endif; ?>

</main>
</body>
</html>

