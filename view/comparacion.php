<?php
// 1. INICIO DE SESIÓN Y SEGURIDAD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

$busqueda_activa = false;

$stmt_db_global = $pdo->query("SHOW TABLES");
$todas_las_tablas_flipped = array_flip($stmt_db_global->fetchAll(PDO::FETCH_COLUMN));

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anomalias = [
    'cancelaciones',
    'estimaciones',
    'consumos_cero',
    'servicios_sin_medicion',
    'correcciones_de_lecturas',
    'anomalias_pendientes',
    'sin_facturar'
];

$anomalias_labels = [
    'cancelaciones' => 'CAN BIM',
    'estimaciones' => 'ESTIM BIM',
    'consumos_cero' => 'CERO BIM',
    'servicios_sin_medicion' => 'SIN MED BIM',
    'correcciones_de_lecturas' => 'CORR LECT BIM',
    'anomalias_pendientes' => 'ANM PEN BIM',
    'sin_facturar' => 'SIN FACT BIM'
];

$p1_mes = isset($_REQUEST['mes_objetivo']) ? (int)$_REQUEST['mes_objetivo'] : (int)date('n');
$p1_anio = isset($_REQUEST['anio_objetivo']) ? (int)$_REQUEST['anio_objetivo'] : (int)date('Y');

$differences = [];
$ciclos_actuales = [];
$sum_bimestral = [];
$sum_mensual = [];
$sum_total = [];
$means = [];
$sufijo_actual = '';
$sufijo_comp = '';
$lbl_comp = '';

if (isset($_REQUEST['mes_objetivo'], $_REQUEST['anio_objetivo'])) {
    $busqueda_activa = true;

    // Calcular bimestre anterior
    $p3_mes = $p1_mes - 2;
    $p3_anio = $p1_anio;
    if ($p3_mes <= 0) {
        $p3_mes += 12;
        $p3_anio -= 1;
    }

    $sufijo_actual = $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT);
    $sufijo_comp = $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT);
    $lbl_comp = $nombres_meses[$p3_mes] . ' ' . $p3_anio;

    // Obtener tablas de base de datos (usando la consulta global del inicio)
    $todas_las_tablas = $todas_las_tablas_flipped;

    // 1. Obtener ciclos que existan en el mes actual únicamente
    foreach ($anomalias as $anomalia) {
        $nombre_tabla = $anomalia . $sufijo_actual;
        if (isset($todas_las_tablas[$nombre_tabla])) {
            try {
                $stmt = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ciclos_actuales[] = (int)$r['c'];
                }
            } catch (PDOException $e) {}
        }
    }
    $ciclos_actuales = array_unique($ciclos_actuales);
    sort($ciclos_actuales);

    if (!empty($ciclos_actuales)) {
        // 2. Obtener conteos de anomalías agrupados por ciclo para mes actual
        $counts_actual = [];
        foreach ($anomalias as $anomalia) {
            $counts_actual[$anomalia] = [];
            $nombre_tabla = $anomalia . $sufijo_actual;
            if (isset($todas_las_tablas[$nombre_tabla])) {
                try {
                    $stmt = $pdo->query("SELECT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c, COUNT(*) as cnt FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != '' GROUP BY TRIM(`Ciclo`)");
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_actual[$anomalia][(int)$r['c']] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }
        }

        // 3. Obtener conteos de anomalías agrupados por ciclo para mes comparación (bimestre anterior)
        $counts_comp = [];
        foreach ($anomalias as $anomalia) {
            $counts_comp[$anomalia] = [];
            $nombre_tabla = $anomalia . $sufijo_comp;
            if (isset($todas_las_tablas[$nombre_tabla])) {
                try {
                    $stmt = $pdo->query("SELECT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c, COUNT(*) as cnt FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != '' GROUP BY TRIM(`Ciclo`)");
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $counts_comp[$anomalia][(int)$r['c']] = (int)$r['cnt'];
                    }
                } catch (PDOException $e) {}
            }
        }

        // 4. Calcular diferencias
        $sum_bimestral = array_fill_keys(array_merge($anomalias, ['defectos']), 0);
        $sum_mensual = array_fill_keys(array_merge($anomalias, ['defectos']), 0);
        $sum_total = array_fill_keys(array_merge($anomalias, ['defectos']), 0);

        foreach ($ciclos_actuales as $c) {
            $differences[$c] = [];
            $is_bim = ($c <= 61);
            $row_sum = 0;

            foreach ($anomalias as $anomalia) {
                $act = isset($counts_actual[$anomalia][$c]) ? $counts_actual[$anomalia][$c] : 0;
                $comp = isset($counts_comp[$anomalia][$c]) ? $counts_comp[$anomalia][$c] : 0;
                $diff = $act - $comp;
                $differences[$c][$anomalia] = $diff;
                $row_sum += $diff;

                if ($is_bim) {
                    $sum_bimestral[$anomalia] += $diff;
                } else {
                    $sum_mensual[$anomalia] += $diff;
                }
                $sum_total[$anomalia] += $diff;
            }

            $differences[$c]['defectos'] = $row_sum;
            if ($is_bim) {
                $sum_bimestral['defectos'] += $row_sum;
            } else {
                $sum_mensual['defectos'] += $row_sum;
            }
            $sum_total['defectos'] += $row_sum;
        }

        // 5. Calcular el valor absoluto máximo por columna (solo para ciclos actuales)
        $max_diffs = [];
        foreach (array_merge($anomalias, ['defectos']) as $col) {
            $vals = [];
            foreach ($ciclos_actuales as $c) {
                $vals[] = abs($differences[$c][$col]);
            }
            $max_diffs[$col] = empty($vals) ? 0 : max($vals);
        }
    }
}

// Función helper para mapa de calor — misma escala que reporte Zona
// Función helper para mapa de calor — escala relativa al valor máximo de cada columna
function getHeatmapStyle($value, $max) {
    if ($value == 0) {
        return 'background-color: #ffffff; color: #1e2b27;'; // Neutro blanco
    }
    if ($max <= 0) {
        return 'background-color: #ffffff; color: #1e2b27;';
    }
    
    $ratio = abs($value) / $max;
    if ($value > 0) {
        // Positivos (Incremento de Anomalías) - Escala de amarillo a un rojo ligeramente más intenso (#E57373)
        if ($ratio <= 0.10) return 'background-color: #FFFFDF; color: #1e2b27;';
        if ($ratio <= 0.20) return 'background-color: #FFFFB8; color: #1e2b27;';
        if ($ratio <= 0.30) return 'background-color: #FFFF94; color: #1e2b27;'; // Amarillo original
        if ($ratio <= 0.40) return 'background-color: #FDF190; color: #1e2b27;';
        if ($ratio <= 0.50) return 'background-color: #FCEB93; color: #1e2b27;'; // Nivel original
        if ($ratio <= 0.60) return 'background-color: #F9D08D; color: #1e2b27;';
        if ($ratio <= 0.70) return 'background-color: #EBCA8F; color: #1e2b27;'; // Nivel original
        if ($ratio <= 0.80) return 'background-color: #F1AA87; color: #1e2b27;';
        if ($ratio <= 0.90) return 'background-color: #F08B82; color: #1e2b27;';
        return 'background-color: #E57373; color: #1e2b27; font-weight: bold;';    // Rojo ligeramente más intenso
    } else {
        // Negativos (Reducción de Anomalías) - Escala dentro de los límites originales (#E8F5E9 a #81C784)
        if ($ratio <= 0.10) return 'background-color: #F4FBF5; color: #1e2b27;';
        if ($ratio <= 0.20) return 'background-color: #E8F5E9; color: #1e2b27;'; // Verde original
        if ($ratio <= 0.30) return 'background-color: #D8EED9; color: #1e2b27;';
        if ($ratio <= 0.40) return 'background-color: #C8E6C9; color: #1e2b27;'; // Nivel original
        if ($ratio <= 0.50) return 'background-color: #B8DEC0; color: #1e2b27;';
        if ($ratio <= 0.60) return 'background-color: #A5D6A7; color: #1e2b27;'; // Nivel original
        if ($ratio <= 0.70) return 'background-color: #96CE9D; color: #1e2b27;';
        if ($ratio <= 0.80) return 'background-color: #89C791; color: #1e2b27;';
        return 'background-color: #81C784; color: #1e2b27; font-weight: bold;';    // Máximo original
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparación de Anomalías</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/preparar_analisis.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .comp-table-wrapper {
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background-color: #fff;
            border: 1px solid #000;
            margin-top: 20px;
        }
        .comp-table {
            width: auto;
            table-layout: auto !important;
            border-collapse: collapse;
            font-family: Arial, Calibri, sans-serif;
        }
        .comp-table th, .comp-table td {
            border: 1px solid #000;
            padding: 4px 12px;
            text-align: center;
            font-size: 14px;
            min-width: 80px;
        }
        .comp-table th {
            font-weight: bold;
        }
        
        /* Título principal — verde fuerte igual que zona */
        .th-main-title {
            background-color: #1A7A5E !important;
            color: #fff !important;
            font-size: 16px !important;
            font-weight: bold !important;
            letter-spacing: 0.05em;
            text-align: center !important;
            text-transform: uppercase;
            padding: 8px !important;
        }
        /* Columna CICLO — blanca como th-zona-ag */
        .th-ciclo {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 14px !important;
            font-weight: bold !important;
            min-width: 80px;
        }
        /* Anomalías — intercalado azul/verde igual que zona */
        .th-group     { background-color: #AFD5F3 !important; color: #000 !important; font-weight: bold !important; font-size: 13px !important; white-space: nowrap !important; }
        .th-group-alt { background-color: #AADEC0 !important; color: #000 !important; font-weight: bold !important; font-size: 13px !important; white-space: nowrap !important; }
        /* Columna DEFECTOS — azul oscuro como zona */
        .th-defect {
            background-color: #104861 !important;
            color: #fff !important;
            font-weight: bold !important;
            font-size: 13px !important;
        }

        /* Row heights and styles */
        .comp-table tbody tr {
            height: 28px;
        }
        .comp-table tbody td {
            font-size: 15px;
            font-weight: 500;
            background-color: #fff;
            color: #000;
        }
        /* Celda de CICLO en el cuerpo */
        .td-ciclo {
            background-color: #fff !important;
            color: #000 !important;
            font-weight: bold !important;
            font-size: 14px !important;
            text-align: center !important;
        }
        
        .row-summary {
            background-color: #f1f3f5 !important;
            font-weight: bold !important;
        }
        .row-summary td {
            background-color: #f1f3f5 !important;
            font-weight: bold !important;
            font-size: 15px !important;
            color: #000 !important;
        }
        /* Fila separadora bimestral/mensual */
        .row-separator td {
            background-color: #0b5ed7 !important;
            font-size: 11px !important;
            font-weight: bold !important;
            color: #fff !important;
            text-align: center !important;
            padding: 4px 8px !important;
            letter-spacing: 0.08em;
            border-top: 2px solid #0a58ca !important;
            border-bottom: 2px solid #0a58ca !important;
        }

        .comp-table-card {
            margin-top: 15px;
            background-color: #fff;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid var(--ea-border);
        }

        /* ========= ESTILOS PARA IMPRESIÓN PDF ========= */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            @page {
                size: landscape;
                margin: 10mm;
            }
            body, .calc-main {
                background-color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            header, 
            .calc-card--form, 
            .calc-page-header, 
            .ea-page-header, 
            .ea-btn-print, 
            .ea-btn-excel,
            .ea-btn-back,
            .ea-page-overlay {
                display: none !important;
            }
            .comp-table-card {
                box-shadow: none !important;
                border: none !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
                width: fit-content !important;
                padding: 0 !important;
            }
            .comp-table-wrapper {
                overflow: visible !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="calc-main">

    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="preparar_analisis.php?mes_objetivo=<?php echo $p1_mes; ?>&anio_objetivo=<?php echo $p1_anio; ?>" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span> Volver
            </a>
            <div>
                <h1 class="ea-page-title">Comparación de Ciclos por Anomalía</h1>
                <p class="ea-page-subtitle">Calcula las diferencias absolutas de anomalías por ciclo comparando el mes seleccionado con su bimestre anterior. Únicamente se incluyen los ciclos existentes en el mes actual.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($busqueda_activa && !empty($ciclos_actuales)): ?>
                <button onclick="exportarExcelComparacion()" class="ea-btn-excel" style="background-color: #fff; color: #2E7D32; border: 1.5px solid #2E7D32; padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#E8F5E9';this.style.boxShadow='0 2px 8px rgba(46,125,50,0.15)'" onmouseout="this.style.backgroundColor='#fff';this.style.boxShadow='none'">
                    <span class="material-symbols-rounded" style="font-size: 20px;">download</span> Excel
                </button>
                <button onclick="window.print()" class="ea-btn-print">
                    <span class="material-symbols-rounded">print</span> Imprimir PDF
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="calc-card calc-card--form">
        <div class="calc-card__header">
            <span class="material-symbols-rounded">calendar_view_month</span>
            Selección de Periodo Objetivo
        </div>
        <div class="calc-card__body">
            <form action="" method="GET" class="calc-form">
                <div class="calc-form__group">
                    <label class="calc-form__label" for="mes_objetivo">
                        <span class="material-symbols-rounded">calendar_month</span>
                        Mes
                    </label>
                    <select name="mes_objetivo" id="mes_objetivo" class="calc-form__control" required>
                        <?php foreach ($nombres_meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($num === $p1_mes) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="calc-form__group">
                    <label class="calc-form__label" for="anio_objetivo">
                        <span class="material-symbols-rounded">event</span>
                        Año
                    </label>
                    <input type="number" name="anio_objetivo" id="anio_objetivo"
                           class="calc-form__control" required
                           value="<?php echo $p1_anio; ?>"
                           min="2000" max="2100">
                </div>

                <button type="submit" class="calc-btn calc-btn--primary">
                    <span class="material-symbols-rounded">calculate</span>
                    Comparar Ciclos
                </button>
            </form>
        </div>
    </div>

    <?php if ($busqueda_activa): ?>
        <?php if (empty($ciclos_actuales)): ?>
            <div class="ea-alert-card" style="padding: 20px; background-color: #f8d7da; color: #842029; border-radius: 8px; margin-top: 20px; border: 1px solid #f1aeb5;">
                <span class="material-symbols-rounded" style="vertical-align: middle; margin-right: 8px;">error</span>
                No se encontraron datos de anomalías para el mes de <strong><?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?></strong>.
            </div>
        <?php else: ?>
            <div class="comp-table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--ea-primary); margin: 0;">Periodo: <?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?> vs <?php echo $lbl_comp; ?></h2>
                        <p style="font-size: 0.85rem; color: var(--ea-muted); margin: 0; margin-top: 2px;">Los valores corresponden a la diferencia (Actual - Anterior). Las celdas están coloreadas respecto a la media absoluta de su columna.</p>
                    </div>
                </div>
                
                <div class="comp-table-wrapper">
                    <table class="comp-table" id="tabla-comparacion-main">
                        <thead>
                            <tr>
                                <th colspan="9" class="th-main-title">COMPARACIÓN — <?php echo $nombres_meses[$p1_mes] . ' ' . $p1_anio; ?> vs <?php echo $lbl_comp; ?></th>
                            </tr>
                            <tr>
                                <th class="th-ciclo">CICLO</th>
                                <?php
                                $anomalias_header_labels = [
                                    'cancelaciones'            => 'CANCELACIONES',
                                    'estimaciones'             => 'ESTIMACIONES',
                                    'consumos_cero'            => 'CONSUMO CERO',
                                    'servicios_sin_medicion'   => 'SIN MEDICIÓN',
                                    'correcciones_de_lecturas' => 'CORR. LECTURA',
                                    'anomalias_pendientes'     => 'ANOM. PEN.',
                                    'sin_facturar'             => 'SIN FACTURAR',
                                ];
                                $idx_h = 0;
                                foreach ($anomalias as $anomalia):
                                    $cls = ($idx_h % 2 === 0) ? 'th-group' : 'th-group-alt';
                                    $idx_h++;
                                    $lbl = $anomalias_header_labels[$anomalia] ?? strtoupper(str_replace('_', ' ', $anomalia));
                                ?>
                                    <th class="<?php echo $cls; ?>"><?php echo $lbl; ?></th>
                                <?php endforeach; ?>
                                <th class="th-defect">DEFECTOS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $prev_is_bim = null;
                            foreach ($ciclos_actuales as $c):
                                $cur_is_bim = ($c <= 40);
                                // Insertar separador al pasar de bimestrales (<=40) a mensuales (>40)
                                if ($prev_is_bim === true && !$cur_is_bim):
                            ?>
                                <tr class="row-separator">
                                    <td colspan="9">── CICLOS MENSUALES (> 40) ──</td>
                                </tr>
                            <?php
                                endif;
                                $prev_is_bim = $cur_is_bim;
                            ?>
                                <tr>
                                    <td class="td-ciclo"><?php echo $c; ?></td>
                                    <?php foreach ($anomalias as $anomalia): 
                                        $val = $differences[$c][$anomalia];
                                        $style = getHeatmapStyle($val, $max_diffs[$anomalia]);
                                    ?>
                                        <td style="<?php echo $style; ?>"><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                    <?php endforeach; ?>
                                    <?php 
                                        $val_def = $differences[$c]['defectos'];
                                        $style_def = getHeatmapStyle($val_def, $max_diffs['defectos']);
                                    ?>
                                    <td style="<?php echo $style_def; ?>"><?php echo ($val_def > 0 ? '+' : '') . $val_def; ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Fila Bimestral -->
                            <tr class="row-summary">
                                <td>bimestral</td>
                                <?php foreach ($anomalias as $anomalia): 
                                    $val = $sum_bimestral[$anomalia];
                                ?>
                                    <td><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo ($sum_bimestral['defectos'] > 0 ? '+' : '') . $sum_bimestral['defectos']; ?></td>
                            </tr>

                            <!-- Fila Mensual -->
                            <tr class="row-summary">
                                <td>mensual</td>
                                <?php foreach ($anomalias as $anomalia): 
                                    $val = $sum_mensual[$anomalia];
                                ?>
                                    <td><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo ($sum_mensual['defectos'] > 0 ? '+' : '') . $sum_mensual['defectos']; ?></td>
                            </tr>

                            <!-- Fila Total -->
                            <tr class="row-summary">
                                <td>total</td>
                                <?php foreach ($anomalias as $anomalia): 
                                    $val = $sum_total[$anomalia];
                                ?>
                                    <td><?php echo ($val > 0 ? '+' : '') . $val; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo ($sum_total['defectos'] > 0 ? '+' : '') . $sum_total['defectos']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
    // MD3 Loading overlay al enviar el form
    document.querySelector('.calc-form').addEventListener('submit', function() {
        const overlay = document.createElement('div');
        overlay.className = 'ea-page-overlay';
        overlay.innerHTML = `
            <div class="ea-page-overlay__card">
                <div class="ea-spinner ea-spinner--lg"></div>
                <span class="ea-spinner-text">Procesando comparación\u2026</span>
                <span class="ea-spinner-subtext">Calculando diferencias y mapa de calor</span>
            </div>
        `;
        document.body.appendChild(overlay);
    });

    async function exportarExcelComparacion() {
        const table = document.getElementById('tabla-comparacion-main');
        if (!table) return;
        
        // Mostrar overlay de carga mientras se genera el Excel
        const overlay = document.createElement('div');
        overlay.className = 'ea-page-overlay';
        overlay.innerHTML = `
            <div class="ea-page-overlay__card">
                <div class="ea-spinner ea-spinner--lg"></div>
                <span class="ea-spinner-text">Generando archivo Excel\u2026</span>
                <span class="ea-spinner-subtext">Creando libro de trabajo y aplicando estilos</span>
            </div>
        `;
        document.body.appendChild(overlay);

        try {
            const wb = new ExcelJS.Workbook();
            const ws = wb.addWorksheet('Comparación');

            const B = {style:'thin', color:{argb:'FF000000'}};
            const borders = {top:B,bottom:B,left:B,right:B};

            function rgbA(s){
                if(!s||s==='transparent'||s.includes('0, 0, 0, 0')) return null;
                const m=s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                if(!m) return null;
                return 'FF'+[m[1],m[2],m[3]].map(n=>parseInt(n).toString(16).padStart(2,'0')).join('').toUpperCase();
            }

            // Grid ocupado por rowspan/colspan
            const occ={};
            const isO=(r,c)=>occ[r+','+c]===true;
            const setO=(r,c)=>{occ[r+','+c]=true;};

            let exR=1;
            const allTrs = table.querySelectorAll('thead tr, tbody tr');
            const totalColSpan = 9;

            allTrs.forEach(tr => {
                let col=1;
                
                tr.querySelectorAll('th, td').forEach(cell => {
                    while(isO(exR,col)) col++;
                    
                    let cs = parseInt(cell.getAttribute('colspan') || 1);
                    const rs = parseInt(cell.getAttribute('rowspan') || 1);

                    if (cs >= 50) {
                        cs = totalColSpan;
                    }

                    const comp = window.getComputedStyle(cell);
                    let bg = rgbA(comp.backgroundColor);
                    let fc = rgbA(comp.color) || 'FF000000';
                    let bold = comp.fontWeight === 'bold' || parseInt(comp.fontWeight) >= 600;
                    let sz = 10;
                    
                    if (cell.classList.contains('th-main-title')) { 
                        sz = 13; 
                    } else if (cell.tagName === 'TH') {
                        sz = 11;
                    }

                    let t = cell.textContent.trim();
                    let val = t;
                    let isNumeric = false;
                    
                    if (t !== '') {
                        let cleaned = t;
                        if (t.startsWith('+')) {
                            cleaned = t.substring(1);
                        }
                        // Validar si es numérico
                        if (!isNaN(cleaned) && !cleaned.includes('%')) {
                            val = parseFloat(cleaned);
                            isNumeric = true;
                        }
                    }

                    const ec = ws.getCell(exR, col);
                    ec.value = val;
                    
                    if (isNumeric && col > 1) {
                        ec.numFmt = '+#,##0;-#,##0;0';
                    } else if (isNumeric) {
                        ec.numFmt = '#,##0';
                    }

                    let align = { horizontal: 'center', vertical: 'middle', wrapText: true };
                    
                    // Si es el título o el separador mensual, alinear al centro
                    if (cell.classList.contains('th-main-title') || cell.parentElement.classList.contains('row-separator')) {
                        align.horizontal = 'center';
                        align.wrapText = false;
                    }
                    
                    ec.alignment = align;
                    ec.border = borders;
                    if (bg) {
                        ec.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                    }
                    ec.font = { name: 'Arial', size: sz, bold: bold, color: { argb: fc } };

                    // Merge
                    if (cs > 1 || rs > 1) {
                        ws.mergeCells(exR, col, exR + rs - 1, col + cs - 1);
                        for (let r = 0; r < rs; r++) {
                            for (let c = 0; c < cs; c++) {
                                if (r === 0 && c === 0) continue;
                                const mc = ws.getCell(exR + r, col + c);
                                mc.border = borders;
                                if (bg) mc.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                            }
                        }
                    }

                    for(let r=0;r<rs;r++) for(let c=0;c<cs;c++) if(r>0||c>0) setO(exR+r,col+c);
                    col+=cs;
                });
                exR++;
            });

            // Configurar anchos de columna
            ws.getColumn(1).width = 15; // CICLO
            for (let c = 2; c <= 9; c++) {
                ws.getColumn(c).width = 18; // Anomalías y defectos
            }

            // Alturas de filas
            ws.eachRow((row, idx) => {
                if (idx === 1) {
                    row.height = 32;
                } else if (idx === 2) {
                    row.height = 26;
                } else {
                    // Si es la fila separadora, hacerla un poco más alta
                    const cellVal = row.getCell(1).value;
                    if (typeof cellVal === 'string' && cellVal.includes('──')) {
                        row.height = 22;
                    } else {
                        row.height = 18;
                    }
                }
            });

            const buf = await wb.xlsx.writeBuffer();
            const blob = new Blob([buf], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'comparacion_ciclos_<?php echo $p1_anio . str_pad($p1_mes, 2, "0", STR_PAD_LEFT); ?>.xlsx';
            a.click();
            URL.revokeObjectURL(a.href);
        } catch (err) {
            console.error(err);
            alert('Error al generar el archivo Excel.');
        } finally {
            document.body.removeChild(overlay);
        }
    }
</script>
</body>
</html>
