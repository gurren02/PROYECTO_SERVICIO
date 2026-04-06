<?php
// ==============================================================================
// 1. INICIO DE SESIÓN Y SEGURIDAD (¡SIEMPRE EN LA LÍNEA 1!)
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// 2. CONEXIÓN A LA BASE DE DATOS
require "../config/conexion.php";

// 3. CONFIGURACIÓN CRÍTICA
$columna_agencia = 'Agencia';

// 4. RECIBIR PERIODOS Y FILTROS DESDE LA URL
$p1_mes  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$p1_anio = isset($_GET['a']) ? (int)$_GET['a'] : (int)date('Y');

$filtro_zona  = isset($_GET['zona'])  ? trim($_GET['zona'])  : '';
$filtro_ciclo = isset($_GET['ciclo']) ? trim($_GET['ciclo']) : '';

$p2_mes  = $p1_mes;
$p2_anio = $p1_anio - 1;

$p3_mes  = $p1_mes - 2;
$p3_anio = $p1_anio;
if ($p3_mes <= 0) { $p3_mes += 12; $p3_anio -= 1; }

$sufijos = [
        'actual'      => $p1_anio . str_pad($p1_mes, 2, '0', STR_PAD_LEFT),
        'bimestre'    => $p3_anio . str_pad($p3_mes, 2, '0', STR_PAD_LEFT),
        'ano_anterior'=> $p2_anio . str_pad($p2_mes, 2, '0', STR_PAD_LEFT)
];

function obtenerNombreMes($num) {
    $meses = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
            7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    return $meses[$num] ?? '';
}

$prefijo_titulo = $filtro_ciclo !== '' ? $filtro_ciclo : "34";
if ($filtro_zona !== '') $prefijo_titulo .= " - ZONA " . $filtro_zona;

$titulos_completos = [
        'actual'       => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p1_mes) . " $p1_anio",
        'bimestre'     => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p3_mes) . " $p3_anio",
        'ano_anterior' => $prefijo_titulo . " RESULTADO NIVEL AGENCIA POR CICLO " . obtenerNombreMes($p2_mes) . " $p2_anio"
];

$mapa_agencias = [
        'A'=>'CENTRO','B'=>'NORTE','C'=>'SUR','D'=>'ORIENTE','E'=>'PONIENTE',
        'G'=>'PROGRESO','H'=>'HUNUCMA','J'=>'UMAN','K'=>'ACANCEH','M'=>'CONKAL'
];

$anomalias = ['cancelaciones','estimaciones','consumos_cero','servicios_sin_medicion',
        'correcciones_de_lecturas','anomalias_pendientes','sin_facturar','cargas_directas'];

$resultados = [];
$total_registros_analisis = 0;
$ultima_actualizacion_analisis = 'No disponible';

foreach ($sufijos as $periodo_key => $sufijo) {
    foreach ($mapa_agencias as $letra => $nombre_agencia) {
        foreach ($anomalias as $anomalia) {
            $resultados[$periodo_key][$nombre_agencia][$anomalia] = 0;
        }
    }
}

// 5. MOTOR DE CONSULTAS
$zonas_disponibles  = [];
$ciclos_disponibles = [];
$tablas_involucradas = [];

foreach ($anomalias as $anomalia) {
    $nombre_tabla = $anomalia . $sufijos['actual'];
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt_check->execute([$nombre_tabla]);
    if ($stmt_check->rowCount() > 0) {
        $tablas_involucradas[] = $nombre_tabla;
        try {
            // Obtener zonas únicas
            $stmt_z = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) as z FROM `$nombre_tabla` WHERE `Zona` IS NOT NULL AND `Zona` != ''");
            while($rz = $stmt_z->fetch(PDO::FETCH_ASSOC)) { $zonas_disponibles[] = (int)$rz['z']; }
            
            // Obtener ciclos únicos
            $stmt_c = $pdo->query("SELECT DISTINCT CAST(TRIM(`Ciclo`) AS UNSIGNED) as c FROM `$nombre_tabla` WHERE `Ciclo` IS NOT NULL AND `Ciclo` != ''");
            while($rc = $stmt_c->fetch(PDO::FETCH_ASSOC)) { $ciclos_disponibles[] = (int)$rc['c']; }
        } catch (PDOException $e) {}
    }
}
$zonas_disponibles  = array_unique($zonas_disponibles);  sort($zonas_disponibles);
$ciclos_disponibles = array_unique($ciclos_disponibles); sort($ciclos_disponibles);

// Obtener última actualización de las tablas involucradas
if (!empty($tablas_involucradas)) {
    $placeholders = implode(',', array_fill(0, count($tablas_involucradas), '?'));
    $stmt_u = $pdo->prepare("SELECT MAX(fecha_subida) FROM registro_archivos WHERE nombre_tabla IN ($placeholders)");
    $stmt_u->execute($tablas_involucradas);
    $res_u = $stmt_u->fetchColumn();
    if ($res_u) $ultima_actualizacion_analisis = date('d/m/Y H:i', strtotime($res_u));
}

foreach ($sufijos as $periodo_key => $sufijo) {
    foreach ($anomalias as $anomalia) {
        $nombre_tabla = $anomalia . $sufijo;
        $stmt_check = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt_check->execute([$nombre_tabla]);
        if ($stmt_check->rowCount() > 0) {
            try {
                $where_sql = "WHERE 1=1";
                $parametros_sql = [];

                // === SOLUCIÓN: Conversión matemática para ignorar ceros a la izquierda ===
                if ($filtro_zona !== '')  {
                    $where_sql .= " AND CAST(TRIM(`Zona`) AS UNSIGNED) = ?";
                    $parametros_sql[] = (int)$filtro_zona;
                }
                if ($filtro_ciclo !== '') {
                    $where_sql .= " AND CAST(TRIM(`Ciclo`) AS UNSIGNED) = ?";
                    $parametros_sql[] = (int)$filtro_ciclo;
                }
                // ========================================================================

                $query = "SELECT UPPER(TRIM(`$columna_agencia`)) as letra_bd, COUNT(*) as total 
                          FROM `$nombre_tabla` $where_sql
                          GROUP BY UPPER(TRIM(`$columna_agencia`))";
                $stmt_data = $pdo->prepare($query);
                $stmt_data->execute($parametros_sql);

                while ($fila = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
                    $letra_bd = $fila['letra_bd'];
                    if (array_key_exists($letra_bd, $mapa_agencias)) {
                        $nombre_real = $mapa_agencias[$letra_bd];
                        $resultados[$periodo_key][$nombre_real][$anomalia] = (int)$fila['total'];
                    } elseif (in_array($letra_bd, $mapa_agencias)) {
                        $resultados[$periodo_key][$letra_bd][$anomalia] = (int)$fila['total'];
                    }
                }
            } catch (PDOException $e) { /* Ignorar columnas faltantes */ }
        }
    }
}
// Calcular total de registros para el mes actual
foreach ($resultados['actual'] as $agencia_data) {
    foreach ($agencia_data as $valor_anomalia) {
        $total_registros_analisis += $valor_anomalia;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Nivel Agencia</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/sidebar_footer.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>

<header>
    <?php include "./menu.php"; ?>
</header>

<main class="ea-main">

    <div class="ea-page-header">
        <div class="ea-page-header__left">
            <a href="preparar_analisis.php" class="ea-btn-back">
                <span class="material-symbols-rounded">arrow_back</span>
                Volver
            </a>
            <div>
                <h1 class="ea-page-title">Reporte de Resultados Nivel Agencia</h1>
                <p class="ea-page-subtitle">
                    Análisis comparativo — <?php echo obtenerNombreMes($p1_mes) . ' ' . $p1_anio; ?>
                </p>
            </div>
        </div>
        <button onclick="window.print()" class="ea-btn-print">
            <span class="material-symbols-rounded">print</span>
            Imprimir / PDF
        </button>
    </div>

    <div class="ea-card ea-filters-card">
        <div class="ea-card__header">
            <span class="material-symbols-rounded">filter_alt</span>
            Filtros de consulta
            <?php if($filtro_zona !== '' || $filtro_ciclo !== ''): ?>
                <div class="ea-filter-tags">
                    <?php if($filtro_zona  !== ''): ?>
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
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);"><?php echo $ultima_actualizacion_analisis; ?></span>
                    </div>
                </div>
                <div class="ea-info-item" style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: var(--ea-primary); font-size: 20px;">analytics</span>
                    <div>
                        <span style="display: block; font-size: 0.7rem; color: var(--ea-muted); text-transform: uppercase; font-weight: 700;">Registros totales</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ea-text);" id="total-registros-placeholder">Cargando...</span>
                    </div>
                </div>
            </div>

            <form method="GET" action="" class="ea-form">
                <input type="hidden" name="m" value="<?php echo $p1_mes; ?>">
                <input type="hidden" name="a" value="<?php echo $p1_anio; ?>">

                <div class="ea-form__group">
                    <label class="ea-form__label" for="zona">
                        <span class="material-symbols-rounded">location_on</span>
                        Zona
                    </label>
                    <select id="zona" name="zona" class="ea-form__control">
                        <option value="">TODAS</option>
                        <?php foreach($zonas_disponibles as $z): ?>
                            <option value="<?php echo $z; ?>" <?php echo ($filtro_zona != '' && (int)$filtro_zona === $z) ? 'selected' : ''; ?>>
                                <?php echo $z; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ea-form__group">
                    <label class="ea-form__label" for="ciclo">
                        <span class="material-symbols-rounded">cycle</span>
                        Ciclo
                    </label>
                    <select id="ciclo" name="ciclo" class="ea-form__control">
                        <option value="">TODOS</option>
                        <?php foreach($ciclos_disponibles as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo ($filtro_ciclo != '' && (int)$filtro_ciclo === $c) ? 'selected' : ''; ?>>
                                <?php echo $c; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="ea-btn ea-btn--primary">
                    <span class="material-symbols-rounded">search</span>
                    Aplicar
                </button>

                <?php if($filtro_zona !== '' || $filtro_ciclo !== ''): ?>
                    <a href="?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>" class="ea-btn ea-btn--danger">
                        <span class="material-symbols-rounded">clear_all</span>
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php
    $bloques = [
            ['key' => 'actual',       'label' => 'MES ACTUAL'],
            ['key' => 'bimestre',     'label' => 'BIMESTRE ANTERIOR'],
            ['key' => 'ano_anterior', 'label' => 'AÑO ANTERIOR (MÓVIL)'],
    ];

    foreach ($bloques as $bloque):
        $periodo = $bloque['key'];
        $totales_columnas  = array_fill_keys($anomalias, 0);
        $gran_total_defecto = 0;
        ?>

        <div class="ea-card ea-table-card">
            <div class="ea-card__header ea-card__header--<?php echo $bloque['key']; ?>">
                <span class="material-symbols-rounded">table_chart</span>
                <?php echo $titulos_completos[$periodo]; ?>
                <span class="ea-period-badge ea-period-badge--<?php echo $bloque['key']; ?>">
                <?php echo $bloque['label']; ?>
            </span>
            </div>
            <div class="ea-table-wrapper">
                <table class="ea-table">
                    <thead>
                    <tr class="ea-table__head-cols">
                        <th class="ea-th-agencia">AGENCIA</th>
                        <th>CANCELACIONES</th>
                        <th>ESTIMACIONES</th>
                        <th>CONSUMO CERO</th>
                        <th>SIN MEDICIÓN</th>
                        <th>CORREC. LECTURAS</th>
                        <th>ANOMALÍAS PEND.</th>
                        <th>SIN FACTURAR</th>
                        <th>CARGAS DIRECTAS</th>
                        <th class="ea-th-defecto">DEFECTO</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mapa_agencias as $letra => $agencia):
                        $suma_fila = 0;
                        ?>
                        <tr>
                            <td class="ea-td-agencia"><?php echo $agencia; ?></td>
                            <?php foreach ($anomalias as $anomalia):
                                $valor = $resultados[$periodo][$agencia][$anomalia];
                                $suma_fila += $valor;
                                $totales_columnas[$anomalia] += $valor;
                                ?>
                                <td class="ea-td-num <?php echo $valor > 0 ? 'ea-td-num--val' : ''; ?>">
                                    <?php if ($valor > 0): ?>
                                        <a href="javascript:void(0)" class="ea-detail-trigger" 
                                           data-tabla="<?php echo $anomalia . $sufijos[$periodo]; ?>" 
                                           data-agencia="<?php echo $letra; ?>" 
                                           data-zona="<?php echo htmlspecialchars($filtro_zona); ?>"
                                           data-ciclo="<?php echo htmlspecialchars($filtro_ciclo); ?>"
                                           data-titulo="<?php echo strtoupper(str_replace('_', ' ', $anomalia)) . ' - ' . $agencia; ?>">
                                            <?php echo number_format($valor); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="ea-zero">0</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="ea-td-defecto"><?php echo number_format($suma_fila); ?></td>
                        </tr>
                        <?php $gran_total_defecto += $suma_fila; ?>
                    <?php endforeach; ?>

                    <tr class="ea-tr-total">
                        <td class="ea-td-agencia">TOTAL</td>
                        <?php foreach ($anomalias as $anomalia): ?>
                            <td class="ea-td-num"><?php echo number_format($totales_columnas[$anomalia]); ?></td>
                        <?php endforeach; ?>
                        <td class="ea-td-defecto"><?php echo number_format($gran_total_defecto); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endforeach; ?>

</main>

<div id="ea-modal" class="ea-modal">
    <div class="ea-modal__content">
        <div class="ea-modal__header">
            <h3 id="ea-modal-title">Detalle de Anomalías</h3>
            <div style="display: flex; gap: 15px; align-items: center;">
                <button onclick="exportarPDF()" style="background: none; border: none; cursor: pointer; color: #d9534f; display: flex; align-items: center; gap: 5px; font-weight: 500;">
                    <span class="material-symbols-rounded">picture_as_pdf</span>
                    Exportar PDF
                </button>
                <button onclick="exportarCSV()" style="background: none; border: none; cursor: pointer; color: var(--ea-primary); display: flex; align-items: center; gap: 5px; font-weight: 500;">
                    <span class="material-symbols-rounded">download</span>
                    Exportar CSV
                </button>
                <span class="ea-modal__close material-symbols-rounded">close</span>
            </div>
        </div>
        <div class="ea-modal__body" id="ea-modal-body">
            <div style="text-align: center; padding: 40px;">
                <p>Cargando detalles...</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos del Modal */
    .ea-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(2px);
    }
    .ea-modal__content {
        background-color: #fff;
        margin: 5% auto;
        width: 90%;
        max-width: 1100px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        animation: animatetop 0.3s;
    }
    @keyframes animatetop {
        from {top: -300px; opacity: 0}
        to {top: 0; opacity: 1}
    }
    .ea-modal__header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ea-modal__header h3 { margin: 0; color: var(--ea-primary); font-size: 1.1rem; }
    .ea-modal__close { cursor: pointer; color: #aaa; transition: 0.2s; }
    .ea-modal__close:hover { color: #333; }
    .ea-modal__body { padding: 20px; }
    
    .ea-detail-trigger {
        color: var(--ea-primary);
        text-decoration: none;
        font-weight: 600;
    }
    .ea-detail-trigger:hover {
        text-decoration: underline;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Actualizar total
        const total = <?php echo $total_registros_analisis; ?>;
        const placeholder = document.getElementById('total-registros-placeholder');
        if(placeholder) placeholder.textContent = total.toLocaleString();

        // Lógica del Modal
        const modal = document.getElementById('ea-modal');
        const modalBody = document.getElementById('ea-modal-body');
        const modalTitle = document.getElementById('ea-modal-title');
        const closeBtn = document.querySelector('.ea-modal__close');

        document.querySelectorAll('.ea-detail-trigger').forEach(trigger => {
            trigger.addEventListener('click', function() {
                const tabla = this.dataset.tabla;
                const agencia = this.dataset.agencia;
                const zona = this.dataset.zona;
                const ciclo = this.dataset.ciclo;
                const titulo = this.dataset.titulo;

                modalTitle.textContent = "Detalle: " + titulo;
                modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Consultando registros...</p></div>';
                modal.style.display = 'block';

                fetch(`get_detalle_anomalia.php?tabla=${tabla}&agencia=${agencia}&zona=${zona}&ciclo=${ciclo}`)
                    .then(response => response.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                    })
                    .catch(err => {
                        modalBody.innerHTML = '<p style="color:red;">Error al cargar los datos.</p>';
                    });
            });
        });

        closeBtn.onclick = () => modal.style.display = 'none';
        window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; }
    });

    function exportarCSV() {
        const titulo = document.getElementById('ea-modal-title').innerText.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const table = document.querySelector('#ea-modal-body table');
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [];
            const cols = rows[i].querySelectorAll('td, th');
            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s+)/gm, ' ');
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            csv.push(row.join(','));
        }

        const csvContent = "\uFEFF" + csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", titulo + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportarPDF() {
        const titulo = document.getElementById('ea-modal-title').innerText;
        const nombreArchivo = titulo.replace(/[^a-z0-9]/gi, '_').toLowerCase() + ".pdf";
        const table = document.querySelector('#ea-modal-body table');
        if (!table) return;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4'); // Horizontal (Landscape), puntos, tamaño A4

        doc.setFontSize(14);
        doc.text(titulo, 40, 40);

        doc.autoTable({
            html: table,
            startY: 50,
            theme: 'grid',
            styles: {
                fontSize: 3.5,
                cellPadding: 0.5,
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [52, 58, 64],
                textColor: 255,
                fontSize: 4,
                halign: 'center'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            margin: { top: 50, right: 10, bottom: 20, left: 10 }
        });

        doc.save(nombreArchivo);
    }
</script>
</body>
</html>