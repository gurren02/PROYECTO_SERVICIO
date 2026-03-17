<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciamos sesión de forma segura y ponemos el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONFIGURACIÓN DE DATOS Y CONEXIÓN UNIVERSAL
require "../config/conexion.php";

$columnas = [];
$filas = [];
$titulo_mostrar = "Visor de Datos";
$error_db = null;
$ultima_actualizacion = 'No disponible';
$total_registros_tabla = 0;

$filtro_zona  = isset($_GET['zona']) ? trim($_GET['zona']) : '';
$filtro_ciclo = isset($_GET['ciclo']) ? trim($_GET['ciclo']) : '';
$nombre_tabla = isset($_GET['tabla']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';

$zonas_disponibles = [];
$ciclos_disponibles = [];

try {
    if ($nombre_tabla !== '') {
        // Obtener última actualización de la bitácora para esta tabla
        $stmt_u = $pdo->prepare("SELECT fecha_subida FROM registro_archivos WHERE nombre_tabla = ? ORDER BY fecha_subida DESC LIMIT 1");
        $stmt_u->execute([$nombre_tabla]);
        $res_u = $stmt_u->fetchColumn();
        if ($res_u) $ultima_actualizacion = date('d/m/Y H:i', strtotime($res_u));

        // Obtener nombres de columnas
        $stmt_cols = $pdo->query("SHOW COLUMNS FROM `$nombre_tabla` ");
        $cols_raw = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols_raw as $c) {
            $columnas[] = $c['Field'];
        }

        $tiene_zona = in_array('Zona', $columnas);
        $tiene_ciclo = in_array('Ciclo', $columnas);

        if ($tiene_zona) {
            $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
            $zonas_disponibles = array_unique($zonas_disponibles);
            sort($zonas_disponibles);
        }

        if ($tiene_ciclo) {
            $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_disponibles[] = (int)$rc['c']; }
            $ciclos_disponibles = array_unique($ciclos_disponibles);
            sort($ciclos_disponibles);
        }

        $where = "WHERE 1=1";
        $params = [];

        if ($tiene_zona && $filtro_zona !== '') {
            $where .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
            $params[] = (int)$filtro_zona;
        }
        if ($tiene_ciclo && $filtro_ciclo !== '') {
            $where .= " AND CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
            $params[] = (int)$filtro_ciclo;
        }

        // Obtener total de registros (con filtros aplicados)
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM `$nombre_tabla` $where");
        $stmt_count->execute($params);
        $total_registros_tabla = $stmt_count->fetchColumn();

        // Obtener datos limitados
        $stmt_datos = $pdo->prepare("SELECT * FROM `$nombre_tabla` $where LIMIT 500");
        $stmt_datos->execute($params);
        $filas = $stmt_datos->fetchAll(PDO::FETCH_ASSOC);

        $titulo_mostrar = ucwords(str_replace('_', ' ', str_replace('defecto_', '', $nombre_tabla)));
    }
} catch (PDOException $e) {
    $error_db = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TABLA - <?php echo htmlspecialchars($titulo_mostrar); ?></title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .ver-tabla-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            font-family: var(--ea-font);
        }
        .ver-tabla-table th, .ver-tabla-table td {
            padding: 9px 12px;
            text-align: left;
            border-bottom: 1px solid var(--ea-border);
            white-space: nowrap;
        }
        .ver-tabla-table th {
            background: #f5f8f6;
            color: var(--ea-muted);
            font-weight: 700;
            text-transform: uppercase;
        }
        .ver-tabla-table tbody tr:hover {
            background: var(--ea-primary-lt);
        }
    </style>
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="ea-main">
    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="tablas.php" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span>
                Atrás
            </a>
            <div>
                <h1 class="ea-page-title"><?php echo htmlspecialchars($titulo_mostrar); ?></h1>
                <p class="ea-page-subtitle">Visor de Datos (Limitado a 500 registros)</p>
            </div>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="ea-card">
            <div class="ea-card__body" style="color: #b03030;">
                Error al cargar la tabla: <?php echo htmlspecialchars($error_db); ?>
            </div>
        </div>
    <?php else: ?>

        <?php if (!empty($zonas_disponibles) || !empty($ciclos_disponibles)): ?>
        <div class="ea-card ea-filters-card">
            <div class="ea-card__header">
                <span class="material-symbols-rounded">filter_alt</span>
                Filtros de consulta
                <?php if($filtro_zona !== '' || $filtro_ciclo !== ''): ?>
                    <div class="ea-filter-tags">
                        <?php if($filtro_zona !== ''): ?>
                            <span class="ea-tag">Zona: <?php echo htmlspecialchars($filtro_zona); ?></span>
                        <?php endif; ?>
                        <?php if($filtro_ciclo !== ''): ?>
                            <span class="ea-tag">Ciclo: <?php echo htmlspecialchars($filtro_ciclo); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="ea-card__body">
                <div class="ea-info-summary" style="display: flex; gap: 20px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--ea-border);">
                    <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--ea-primary); font-size: 20px;">history</span>
                        <div>
                            <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Última actualización</span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);"><?php echo $ultima_actualizacion; ?></span>
                        </div>
                    </div>
                    <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--ea-primary); font-size: 20px;">analytics</span>
                        <div>
                            <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Registros encontrados</span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);"><?php echo number_format($total_registros_tabla); ?></span>
                        </div>
                    </div>
                </div>
                <form method="GET" action="" class="ea-form">
                    <input type="hidden" name="tabla" value="<?php echo htmlspecialchars($_GET['tabla']); ?>">

                    <?php if (!empty($zonas_disponibles)): ?>
                    <div class="ea-form__group">
                        <label class="ea-form__label" for="zona">
                            <span class="material-symbols-rounded">location_on</span>
                            Zona
                        </label>
                        <select id="zona" name="zona" class="ea-form__control">
                            <option value="">TODAS</option>
                            <?php foreach($zonas_disponibles as $z): ?>
                                <option value="<?php echo $z; ?>" <?php echo ($filtro_zona !== '' && (int)$filtro_zona === $z) ? 'selected' : ''; ?>>
                                    <?php echo $z; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($ciclos_disponibles)): ?>
                    <div class="ea-form__group">
                        <label class="ea-form__label" for="ciclo">
                            <span class="material-symbols-rounded">cycle</span>
                            Ciclo
                        </label>
                        <select id="ciclo" name="ciclo" class="ea-form__control">
                            <option value="">TODOS</option>
                            <?php foreach($ciclos_disponibles as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($filtro_ciclo !== '' && (int)$filtro_ciclo === $c) ? 'selected' : ''; ?>>
                                    <?php echo $c; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="ea-btn ea-btn--primary">
                        <span class="material-symbols-rounded">search</span>
                        Aplicar
                    </button>

                    <?php if($filtro_zona !== '' || $filtro_ciclo !== ''): ?>
                        <a href="?tabla=<?php echo urlencode($_GET['tabla']); ?>" class="ea-btn ea-btn--danger">
                            <span class="material-symbols-rounded">clear_all</span>
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="ea-card ea-table-card">
            <div class="ea-card__header">
                <span class="material-symbols-rounded">table_view</span>
                Registros
            </div>
            <div class="ea-table-wrapper">
                <table class="ver-tabla-table">
                    <thead>
                    <tr>
                        <?php foreach ($columnas as $col): ?>
                            <th><?php echo htmlspecialchars($col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filas as $fila): ?>
                        <tr>
                            <?php foreach ($columnas as $col): ?>
                                <td><?php echo htmlspecialchars($fila[$col] ?? '-'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filas)): ?>
                        <tr>
                            <td colspan="<?php echo count($columnas); ?>" style="text-align: center; padding: 20px;">No se encontraron registros.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>
</body>
</html>

