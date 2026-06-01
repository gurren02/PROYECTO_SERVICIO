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

$stmt_db         = $pdo->query("SHOW TABLES");
$todas_las_tablas = $stmt_db->fetchAll(PDO::FETCH_COLUMN);
$todas_las_tablas_flipped = array_flip($todas_las_tablas);

$tipos_esperados = [
        'cancelaciones',
        'estimaciones',
        'consumos_cero',
        'servicios_sin_medicion',
        'correcciones_de_lecturas',
        'anomalias_pendientes',
        'sin_facturar',
        'cargas_directas'
];

$matriz_datos = [];
foreach ($tipos_esperados as $tipo) {
    $matriz_datos[$tipo] = ['p1' => false, 'p2' => false, 'p3' => false];
}

$nombres_meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

if (isset($_REQUEST['mes_objetivo'], $_REQUEST['anio_objetivo'])) {
    $busqueda_activa = true;

    // ---> LA LÓGICA DE SUBIDA (MODAL) AHORA ES ASÍNCRONA VÍA AJAX (CHUNKING NIVEL 3) <---


    $p1_mes  = (int)$_REQUEST['mes_objetivo'];
    $p1_anio = (int)$_REQUEST['anio_objetivo'];

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
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
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
                        $mes_actual = isset($_REQUEST['mes_objetivo']) ? (int)$_REQUEST['mes_objetivo'] : (int)date('n');
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
                           value="<?php echo isset($_REQUEST['anio_objetivo']) ? (int)$_REQUEST['anio_objetivo'] : date('Y'); ?>"
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
        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
            <a href="ejecutar_analisis_zona.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>"
               class="calc-btn-generate ea-report-link" style="flex: 1; justify-content: center; background-color: #d4efdf; color: #196f3d; border: 1px solid #a9dfbf;">
                <span class="material-symbols-rounded">map</span>
                Reporte Nivel Zona
            </a>

            <a href="ejecutar_analisis.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>"
               class="calc-btn-generate ea-report-link" style="flex: 1; justify-content: center; background-color: #d6eaf8; color: #1b4f72; border: 1px solid #aed6f1;">
                <span class="material-symbols-rounded">business</span>
                Reporte Nivel Agencia
            </a>

            <a href="detalle_estimaciones.php?m=<?php echo $p1_mes; ?>&a=<?php echo $p1_anio; ?>"
               class="calc-btn-generate ea-report-link" style="flex: 1; justify-content: center; background-color: #fcf3cf; color: #7d6608; border: 1px solid #f9e79f;">
                <span class="material-symbols-rounded">table_chart</span>
                Desglose de Estimaciones
            </a>
        </div>

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

        <div id="ea-alert-container"></div>

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
                                        <button type="button" 
                                           onclick="abrirModalSubida('<?php echo htmlspecialchars($tipo); ?>', '<?php echo htmlspecialchars($nombre_bonito); ?>', <?php echo $anio_ref; ?>, <?php echo $mes_ref; ?>)"
                                           class="calc-btn-add" style="border:none; cursor:pointer;" >
                                            <span class="material-symbols-rounded">upload</span> Agregar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</main>
<!-- Modal HTML -->
<div id="modal-subida" class="ea-modal">
    <div class="ea-modal__content" style="max-width: 600px;">
        <div class="ea-modal__header">
            <h3 id="modal-title" style="margin: 0; color: #0d6efd;">Subir Archivo</h3>
            <span class="ea-modal__close material-symbols-rounded" onclick="cerrarModalSubida()">close</span>
        </div>
        <div class="ea-modal__body" style="padding: 20px;">
            <p id="modal-desc" style="color: #666; font-size: 0.9rem; margin-bottom: 20px;"></p>
            
            <form action="" method="POST" enctype="multipart/form-data" class="subir-form">
                <input type="hidden" name="mes_objetivo" id="hidden_mes_objetivo" value="<?php echo isset($p1_mes) ? $p1_mes : ''; ?>">
                <input type="hidden" name="anio_objetivo" id="hidden_anio_objetivo" value="<?php echo isset($p1_anio) ? $p1_anio : ''; ?>">
                <input type="hidden" name="tipo_defecto" id="hidden_tipo_defecto" value="">
                <input type="hidden" name="anio" id="hidden_anio" value="">
                <input type="hidden" name="mes" id="hidden_mes" value="">
                
                <div class="subir-form__group" style="margin-bottom: 20px;">
                    <label class="subir-dropzone" for="archivo" id="dropzone-label" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; border: 2px dashed #0d6efd; border-radius: 12px; cursor: pointer; background-color: #f8f9fa; transition: background-color 0.2s;">
                        <span class="material-symbols-rounded" style="font-size: 40px; color: #0d6efd; margin-bottom: 10px;">upload_file</span>
                        <span id="dropzone-text" style="font-weight: 500; text-align: center;">Haz clic para seleccionar un archivo <strong>.csv</strong></span>
                        <span style="font-size: 0.8rem; color: #6c757d; margin-top: 5px;">o arrastra y suelta aquí</span>
                        <input type="file" name="archivo" id="archivo" accept=".csv" required style="display: none;"
                               onchange="document.getElementById('dropzone-text').innerHTML = this.files[0] ? this.files[0].name : 'Haz clic para seleccionar un archivo <strong>.csv</strong>';">
                    </label>
                </div>

                <div style="text-align: right;">
                    <button type="submit" style="background-color: #0d6efd; color: white; padding: 10px 20px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer;">Subir e Importar Datos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .ea-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
    .ea-modal__content { background-color: #fff; margin: 5% auto; width: 90%; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: animatetop 0.3s; }
    @keyframes animatetop { from {top: -300px; opacity: 0} to {top: 0; opacity: 1} }
    .ea-modal__header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .ea-modal__close { cursor: pointer; color: #aaa; transition: 0.2s; }
    .ea-modal__close:hover { color: #333; }
    .subir-dropzone:hover { background-color: #e9ecef !important; }
</style>

<script>
    function abrirModalSubida(tipo, nombreBonito, anio, mes) {
        document.getElementById('hidden_tipo_defecto').value = tipo;
        document.getElementById('hidden_anio').value = anio;
        document.getElementById('hidden_mes').value = mes;
        
        const mesesNombres = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        document.getElementById('modal-title').innerText = "Cargar: " + nombreBonito;
        document.getElementById('modal-desc').innerHTML = "Periodo: <strong>" + mesesNombres[mes] + " " + anio + "</strong>. Selecciona el archivo CSV extraído del sistema.";
        
        document.getElementById('archivo').value = "";
        document.getElementById('dropzone-text').innerHTML = "Haz clic para seleccionar un archivo <strong>.csv</strong>";
        
        document.getElementById('modal-subida').style.display = 'block';
    }

    function cerrarModalSubida() {
        document.getElementById('modal-subida').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('modal-subida')) {
            cerrarModalSubida();
        }
    }

    // ── MD3 Loading overlay al generar reportes ─────────────────────
    document.querySelectorAll('.ea-report-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const overlay = document.createElement('div');
            overlay.className = 'ea-page-overlay';
            overlay.innerHTML = `
                <div class="ea-page-overlay__card">
                    <div class="ea-spinner ea-spinner--lg"></div>
                    <span class="ea-spinner-text">Generando reporte\u2026</span>
                    <span class="ea-spinner-subtext">Esto puede tomar unos segundos</span>
                </div>
            `;
            document.body.appendChild(overlay);
        });
    });

    // ── Nivel 3: Algoritmo de Carga Masiva por Chunks (modal form) ──────────
    document.querySelectorAll('.subir-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length === 0) return;

            const overlay = document.createElement('div');
            overlay.className = 'ea-upload-overlay';
            overlay.innerHTML = `
                <div class="ea-upload-overlay__card">
                    <div class="ea-upload-icon">
                        <span class="material-symbols-rounded">cloud_upload</span>
                    </div>
                    <span class="ea-spinner-text" id="progreso-texto-modal">Iniciando subida...</span>
                    <span class="ea-spinner-subtext" id="progreso-subtexto-modal">Preparando archivo</span>
                    <div class="ea-upload-steps">
                        <div class="ea-upload-step active" id="m-step-1"></div>
                        <div class="ea-upload-step" id="m-step-2"></div>
                        <div class="ea-upload-step" id="m-step-3"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            const txtElement = overlay.querySelector('#progreso-texto-modal');
            const subElement = overlay.querySelector('#progreso-subtexto-modal');
            const step1 = overlay.querySelector('#m-step-1');
            const step2 = overlay.querySelector('#m-step-2');
            const step3 = overlay.querySelector('#m-step-3');

            try {
                // FASE 1: INICIALIZACIÓN
                txtElement.innerText = "Paso 1: Iniciando";
                subElement.innerText = "Subiendo archivo al servidor y leyendo estructura...";
                
                let formData = new FormData(this);
                formData.append('action', 'init');

                let initRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: formData });
                let initData = await initRes.json();
                if(initData.error) throw new Error(initData.error);

                const totalLineas = initData.total_lines;
                const tmpFile = initData.tmp_file;
                const originalName = initData.original_name;
                
                step1.classList.replace('active', 'done');
                step2.classList.add('active');

                // FASE 2: PROCESAMIENTO POR CHUNKS
                txtElement.innerText = "Paso 2: Procesando datos";
                let lineasProcesadas = 0;
                const limitMaximo = 5000;

                while (lineasProcesadas < totalLineas) {
                    let chunkFormData = new FormData();
                    chunkFormData.append('action', 'process_chunk');
                    chunkFormData.append('tmp_file', tmpFile);
                    chunkFormData.append('start', lineasProcesadas);
                    chunkFormData.append('limit', limitMaximo);
                    
                    chunkFormData.append('tipo_defecto', formData.get('tipo_defecto'));
                    chunkFormData.append('anio', formData.get('anio'));
                    chunkFormData.append('mes', formData.get('mes'));

                    let chunkRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: chunkFormData });
                    let chunkData = await chunkRes.json();
                    if(chunkData.error) throw new Error(chunkData.error);

                    lineasProcesadas += chunkData.processed;
                    let porcentaje = Math.min(100, Math.round((lineasProcesadas / totalLineas) * 100));
                    subElement.innerHTML = `<span class="ea-upload-percent">${porcentaje}%</span> Procesando... (${lineasProcesadas} de ${totalLineas} filas)`;
                    
                    if(chunkData.processed < limitMaximo) break;
                }

                step2.classList.replace('active', 'done');
                step3.classList.add('active');

                // FASE 3: FINALIZACIÓN
                txtElement.innerText = "Paso 3: Finalizando";
                subElement.innerText = "Guardando bitácora y refrescando...";

                let finishFormData = new FormData();
                finishFormData.append('action', 'finish');
                finishFormData.append('tmp_file', tmpFile);
                finishFormData.append('tipo_defecto', formData.get('tipo_defecto'));
                finishFormData.append('anio', formData.get('anio'));
                finishFormData.append('mes', formData.get('mes'));
                finishFormData.append('original_name', originalName);

                let finishRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: finishFormData });
                let finishData = await finishRes.json();
                if(finishData.error) throw new Error(finishData.error);

                overlay.remove();
                cerrarModalSubida();
                
                // Mostrar éxito en la página y recargar para ver la nueva tabla
                document.getElementById('ea-alert-container').innerHTML = `
                    <div style='padding:15px; background-color:#d1e7dd; color:#0f5132; margin-bottom:20px; border-radius:8px;'>
                        <strong>¡Éxito!</strong> Se insertaron ${lineasProcesadas} registros. Recargando...
                    </div>`;
                
                setTimeout(() => window.location.reload(), 1500);

            } catch (err) {
                overlay.remove();
                cerrarModalSubida();
                document.getElementById('ea-alert-container').innerHTML = `
                    <div style='padding:15px; background-color:#f8d7da; color:#842029; margin-bottom:20px; border-radius:8px;'>
                        Error crítico: ${err.message}
                    </div>`;
            }
        });
    });
</script>
</body>
</html>

