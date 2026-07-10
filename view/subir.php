<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciamos sesión de forma segura y ponemos el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/subir.css">
    <link rel="stylesheet" href="../assets/ejecutar_analisis.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="../assets/script.js"></script>
    <link rel="icon" type="image/webp" href="../assets/multimedia/logoconfondo.webp">
    <title>SUBIR ARCHIVOS</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="subir-main">

    <div class="subir-card">

        <div class="subir-card__header">
            <h1 class="subir-card__title">Carga de Archivos CSV (Firme)</h1>
            <p class="subir-card__subtitle">Registra nuevos datos de anomalías por tipo, año y mes.</p>
        </div>

        <div class="subir-card__body">

            <div id="ea-alert-container"></div>

            <form action="" method="POST" enctype="multipart/form-data" class="subir-form">

                <div class="subir-form__group">
                    <label class="subir-form__label" for="archivos">Seleccionar Archivos</label>
                    <label class="subir-dropzone" for="archivos" id="dropzone-label">
                        <svg class="subir-dropzone__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 3h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                            <line x1="9" y1="3" x2="9" y2="21"/>
                            <line x1="2" y1="9" x2="22" y2="9"/>
                            <line x1="2" y1="15" x2="22" y2="15"/>
                        </svg>
                        <span class="subir-dropzone__text" id="dropzone-text">Haz clic para seleccionar múltiples archivos <strong>.csv</strong></span>
                        <span class="subir-dropzone__hint">o arrastra y suelta aquí</span>
                        <input type="file" name="archivos[]" id="archivos" accept=".csv" multiple required
                               onchange="document.getElementById('dropzone-text').innerHTML = this.files.length > 0 ? '<strong>' + this.files.length + '</strong> archivo(s) seleccionado(s)' : 'Haz clic para seleccionar múltiples archivos <strong>.csv</strong>';">
                    </label>
                </div>

                <div class="subir-form__footer" style="display: flex; gap: 10px; align-items: center;">
                    <a href="tablas.php" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #00758f; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.88rem; transition: background 0.2s;">
                        <span class="material-symbols-rounded" style="font-size: 18px;">database</span>
                        Base de Datos
                    </a>
                    <button type="button" class="subir-btn" id="btn-analizar" onclick="document.getElementById('archivos').click()">Subir Archivos</button>
                </div>

            </form>
        </div>
    </div>

    <!-- TARJETA PARA ARCHIVOS TXT (FALSOS) -->
    <div class="subir-card" style="margin-top: 30px;">
        <div class="subir-card__header" style="background: #074776;">
            <h1 class="subir-card__title">Carga de Archivos TXT (Falso)</h1>
            <p class="subir-card__subtitle">Registra nuevos datos de anomalías para el módulo Falsos.</p>
        </div>

        <div class="subir-card__body">

            <div id="falsos-alert-container"></div>

            <form action="" method="POST" enctype="multipart/form-data" class="subir-form" id="falsos-upload-form">

                <div class="subir-form__group">
                    <label class="subir-form__label" for="archivos_txt">Seleccionar Archivos TXT</label>
                    <label class="subir-dropzone falsos-dropzone" for="archivos_txt" id="falsos-dropzone-label">
                        <svg class="subir-dropzone__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #074776;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        <span class="subir-dropzone__text" id="falsos-dropzone-text">Haz clic para seleccionar múltiples archivos <strong>.txt</strong></span>
                        <span class="subir-dropzone__hint">o arrastra y suelta aquí</span>
                        <input type="file" name="archivos_txt[]" id="archivos_txt" accept=".txt" multiple required
                               onchange="document.getElementById('falsos-dropzone-text').innerHTML = this.files.length > 0 ? '<strong>' + this.files.length + '</strong> archivo(s) seleccionado(s)' : 'Haz clic para seleccionar múltiples archivos <strong>.txt</strong>';">
                    </label>
                </div>

                <div class="subir-form__footer" style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="abrirModalHistorial()" class="subir-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background-color: #fefce8; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: background 0.2s; height: 42px;" onmouseover="this.style.backgroundColor='#fef9c3'" onmouseout="this.style.backgroundColor='#fefce8'">
                        <span class="material-symbols-rounded" style="font-size: 18px; color: #0f172a;">history</span>
                        Datos cargados
                    </button>
                    <button type="button" class="subir-btn" id="btn-analizar-txt" onclick="document.getElementById('archivos_txt').click()" style="background-color: #074776; height: 42px;">Subir Archivos</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Modal de Confirmación y Carga (CSV) -->
    <div id="subir-modal" class="subir-modal">
        <div class="subir-modal__content">
            <div class="subir-modal__header">
                <h3>Archivos CSV a Procesar</h3>
                <span class="subir-modal__close" id="subir-modal-close">&times;</span>
            </div>
            <div class="subir-modal__body">
                <p class="subir-modal__desc">Se detectaron los siguientes datos. Corrige si hay algún error antes de cargar.</p>
                <div class="subir-modal__list" id="modal-files-list">
                    <!-- Se poblará dinámicamente -->
                </div>
            </div>
            <div class="subir-modal__footer">
                <button type="button" class="subir-btn subir-btn--secondary" id="btn-cancelar">Cancelar</button>
                <button type="button" class="subir-btn" id="btn-comenzar-carga">Comenzar Carga</button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación y Carga (Falsos TXT) -->
    <div id="falsos-subir-modal" class="subir-modal">
        <div class="subir-modal__content">
            <div class="subir-modal__header" style="background-color: #074776;">
                <h3>Archivos TXT (Falsos) a Procesar</h3>
                <span class="subir-modal__close" id="falsos-subir-modal-close">&times;</span>
            </div>
            <div class="subir-modal__body">
                <p class="subir-modal__desc" id="falsos-subir-modal-desc">Se detectaron los siguientes periodos. Corrige si hay algún error antes de cargar.</p>
                <div id="falsos-modal-alert-container"></div>
                <div class="subir-modal__list" id="falsos-modal-files-list">
                    <!-- Se poblará dinámicamente -->
                </div>
            </div>
            <div class="subir-modal__footer">
                <button type="button" class="subir-btn subir-btn--secondary" id="btn-cancelar-txt">Cancelar</button>
                <button type="button" class="subir-btn" id="btn-comenzar-carga-txt" style="background-color: #074776;">Comenzar Carga</button>
            </div>
        </div>
    </div>

    <!-- Modal de Advertencia de Paridad de Ciclo/Mes (Falsos) -->
    <div id="falsos-parity-warning-modal" class="subir-modal" style="z-index: 1200;">
        <div class="subir-modal__content" style="max-width: 500px; margin: 12% auto; border-radius: 12px; overflow: hidden; border: 1px solid #fde68a;">
            <div class="subir-modal__header" style="background: linear-gradient(135deg, #d97706, #ca8a04); padding: 14px 20px; border-bottom: none;">
                <h3 style="margin: 0; color: #fff; font-size: 1.15rem; font-weight: 700; display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-rounded" style="color: white; font-size: 24px;">warning</span>
                    Inconsistencia de Ciclo/Mes
                </h3>
            </div>
            <div class="subir-modal__body" style="padding: 20px; background-color: #ffffff; display: flex; flex-direction: column; gap: 14px;">
                <div style="background-color: #fffbeb; border-left: 4px solid #d97706; padding: 12px 14px; border-radius: 4px;">
                    <p id="falsos-parity-warning-text" style="margin: 0; font-size: 0.95rem; color: #92400e; line-height: 1.5; font-weight: 600;"></p>
                </div>
                <p style="margin: 0; font-size: 0.9rem; color: #475569; line-height: 1.4; font-weight: 500;">
                    El ciclo extraído de este archivo no coincide con la paridad del mes seleccionado.
                </p>
            </div>
            <div class="subir-modal__footer" style="padding: 14px 20px; background-color: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="subir-btn" id="btn-parity-keep-manual" style="background-color: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; font-size: 0.88rem; cursor:pointer;" onclick="resolverInconsistenciaParidad(false)"></button>
                <button type="button" class="subir-btn" id="btn-parity-accept-auto" style="background-color: #ca8a04; color: #ffffff; font-weight: 600; font-size: 0.88rem; cursor:pointer;" onclick="resolverInconsistenciaParidad(true)"></button>
            </div>
        </div>
    </div>

    <!-- Modal de Historial de Archivos Cargados (Falsos) -->
    <div id="ea-historial-modal" class="ea-modal" onclick="if(event.target===this)cerrarModalHistorial()">
        <div class="ea-modal__content">
            <div class="ea-modal__header" style="background-color: #005a9c;">
                <h3 style="margin: 0; color: #ffffff; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: white; font-size: 20px;">history</span>
                    Historial de Datos Cargados
                </h3>
                <span class="ea-modal__close material-symbols-rounded" onclick="cerrarModalHistorial()" style="color: #ffffff; cursor: pointer; font-size: 20px;">close</span>
            </div>
            <div class="ea-modal__body">
                <div id="historial-alert-container"></div>
                <div id="historial-tabla-container" style="max-height: 400px; overflow-y: auto;">
                    <div class="ea-spinner-container">
                        <div class="ea-spinner"></div>
                        <span class="ea-spinner-text" style="margin-top:10px; color:#64748b;">Cargando historial...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<style>
    /* Evitar parpadeos durante drag & drop en los hijos del dropzone */
    .subir-dropzone * {
        pointer-events: none;
    }

    /* Estilos del dropzone de Falsos (TXT) */
    .falsos-dropzone {
        border: 2px dashed #074776 !important;
        background-color: #f9fbf9 !important;
        transition: all 0.25s ease;
    }
    .falsos-dropzone:hover {
        background-color: #e6f2fc !important;
        border-color: #074776 !important;
    }
    .falsos-dropzone.dragover {
        background-color: #e6f2fc !important;
        border-color: #074776 !important;
        border-style: solid !important;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    /* Estilos del Modal del Historial (para Falsos) */
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
        max-width: 800px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        animation: animatetop 0.3s;
        overflow: hidden;
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
    .ea-modal__header h3 { margin: 0; color: #fff; font-size: 1.1rem; }
    .ea-modal__close { cursor: pointer; color: #aaa; transition: 0.2s; }
    .ea-modal__close:hover { color: #333; }
    .ea-modal__body { padding: 20px; }

    /* Spinner */
    .ea-spinner-container {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        padding: 20px;
    }
    .ea-spinner {
        width: 32px;
        height: 32px;
        border: 3px solid #cbd5e1;
        border-top: 3px solid #005a9c;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    .ea-spinner-text {
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 0.9rem;
    }

    /* Tabla */
    .ea-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Segoe UI', system-ui, sans-serif;
    }
    .ea-table th {
        background-color: #334155;
        color: white;
        padding: 8px 12px;
        text-align: left;
    }
    .ea-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
    }

    /* Dropzone activo al arrastrar directamente sobre él */
    .subir-dropzone.dragover {
        background: var(--color-primary-lt, #e8f5f1) !important;
        border-color: var(--color-primary, #1a7a5e) !important;
        border-style: solid !important;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }
</style>

<script>
    // Evitar que el navegador abra el archivo si se suelta fuera de las zonas de arrastre
    window.addEventListener('dragover', (e) => {
        e.preventDefault();
    }, false);
    window.addEventListener('drop', (e) => {
        e.preventDefault();
    }, false);

    function isDragEventValidForCsv(e) {
        if (!e.dataTransfer || !e.dataTransfer.items) return true;
        for (let i = 0; i < e.dataTransfer.items.length; i++) {
            const item = e.dataTransfer.items[i];
            if (item.kind === 'file') {
                const type = item.type.toLowerCase();
                if (type === 'text/plain') {
                    return false;
                }
            }
        }
        return true;
    }

    function isDragEventValidForTxt(e) {
        if (!e.dataTransfer || !e.dataTransfer.items) return true;
        for (let i = 0; i < e.dataTransfer.items.length; i++) {
            const item = e.dataTransfer.items[i];
            if (item.kind === 'file') {
                const type = item.type.toLowerCase();
                if (type.includes('csv') || type.includes('excel') || type.includes('spreadsheet')) {
                    return false;
                }
            }
        }
        return true;
    }

    // Configuración CSV
    const mapNameType = {
        'CargasDirectasDW': 'cargas_directas',
        'SinFacturarDW': 'sin_facturar',
        'AnomaliasPendientesDW': 'anomalias_pendientes',
        'CorreccionLecturasDW': 'correcciones_de_lecturas',
        'SinMedicionDW': 'servicios_sin_medicion',
        'ConsumoCeroDW': 'consumos_cero',
        'EstimacionesDW': 'estimaciones',
        'CancelacionesDW': 'cancelaciones'
    };

    const fileInput = document.getElementById('archivos');
    const modal = document.getElementById('subir-modal');
    const modalClose = document.getElementById('subir-modal-close');
    const btnCancelar = document.getElementById('btn-cancelar');
    const btnComenzar = document.getElementById('btn-comenzar-carga');
    const listContainer = document.getElementById('modal-files-list');

    let selectedFiles = []; 
    let isUploading = false; 
    let rawFilesMap = new Map(); 

    function parseFilename(filename) {
        let basename = filename.replace(/\.csv$/i, '');
        basename = basename.replace(/\s*\(\d+\)$/, '');
        
        let textType = '', anio = new Date().getFullYear(), mes = new Date().getMonth() + 1;
        let exactType = '';
        
        const match = basename.match(/^(.*)_(\d{4})(\d{2})$/);
        if (match) {
            textType = match[1];
            anio = parseInt(match[2], 10);
            mes = parseInt(match[3], 10);
            exactType = mapNameType[textType] || '';
        }
        return { exactType, anio, mes, original: filename };
    }

    const handleFilesSelection = () => {
        if (!fileInput.files || fileInput.files.length === 0) {
            return;
        }
        
        selectedFiles = [];
        rawFilesMap.clear();
        listContainer.innerHTML = '';
        
        document.querySelector('#subir-modal .subir-modal__desc').innerHTML = `Se detectaron los siguientes datos. Corrige si hay algún error antes de cargar.`;
        btnComenzar.innerText = 'Comenzar Carga';
        btnComenzar.disabled = false;
        btnCancelar.innerText = 'Cancelar';
        btnCancelar.disabled = false;
        document.getElementById('ea-alert-container').innerHTML = ''; 
        
        Array.from(fileInput.files).forEach((file, index) => {
            let info = parseFilename(file.name);
            selectedFiles.push({ ...info, id: index });
            rawFilesMap.set(index, file);
        });
        
        renderModalList();
        modal.classList.add('active');
    };

    fileInput.addEventListener('change', handleFilesSelection);

    // ── Drag & Drop: detección precisa e individual ──
    const csvDropzone = document.getElementById('dropzone-label');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evtName => {
        csvDropzone.addEventListener(evtName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    csvDropzone.addEventListener('dragenter', (e) => {
        if (isDragEventValidForCsv(e)) {
            csvDropzone.classList.add('dragover');
        }
    });
    csvDropzone.addEventListener('dragover', (e) => {
        if (isDragEventValidForCsv(e)) {
            csvDropzone.classList.add('dragover');
        }
    });
    csvDropzone.addEventListener('dragleave', () => csvDropzone.classList.remove('dragover'));
    csvDropzone.addEventListener('drop', (e) => {
        csvDropzone.classList.remove('dragover');
        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length > 0) {
            const dt = new DataTransfer();
            let csvCount = 0;
            for (let i = 0; i < droppedFiles.length; i++) {
                if (droppedFiles[i].name.toLowerCase().endsWith('.csv')) {
                    dt.items.add(droppedFiles[i]);
                    csvCount++;
                }
            }

            if (csvCount === 0) {
                document.getElementById('ea-alert-container').innerHTML = `
                    <div class='subir-alert subir-alert--error'>
                        Solo se aceptan archivos <strong>.csv</strong>.
                    </div>`;
                return;
            }

            fileInput.files = dt.files;
            document.getElementById('dropzone-text').innerHTML = '<strong>' + csvCount + '</strong> archivo(s) seleccionado(s)';
            handleFilesSelection();
        }
    });

    const closeModal = () => { if(!isUploading) modal.classList.remove('active'); };
    modalClose.addEventListener('click', closeModal);
    btnCancelar.addEventListener('click', closeModal);

    function renderModalList() {
        selectedFiles.forEach((fileInfo) => {
            const item = document.createElement('div');
            item.className = 'modal-file-item';
            item.dataset.id = fileInfo.id;
            
            let typeSelect = `
                <select class="m-select modal-tipo" data-id="${fileInfo.id}">
                    <option value="" ${fileInfo.exactType===''?'selected':''}>Seleccione anomalía...</option>
                    <option value="cancelaciones" ${fileInfo.exactType==='cancelaciones'?'selected':''}>Cancelaciones</option>
                    <option value="estimaciones" ${fileInfo.exactType==='estimaciones'?'selected':''}>Estimaciones</option>
                    <option value="consumos_cero" ${fileInfo.exactType==='consumos_cero'?'selected':''}>Consumos Cero</option>
                    <option value="servicios_sin_medicion" ${fileInfo.exactType==='servicios_sin_medicion'?'selected':''}>Sin Medición</option>
                    <option value="correcciones_de_lecturas" ${fileInfo.exactType==='correcciones_de_lecturas'?'selected':''}>Correcciones de Lecturas</option>
                    <option value="anomalias_pendientes" ${fileInfo.exactType==='anomalias_pendientes'?'selected':''}>Anomalías Pendientes</option>
                    <option value="sin_facturar" ${fileInfo.exactType==='sin_facturar'?'selected':''}>Sin Facturar</option>
                    <option value="cargas_directas" ${fileInfo.exactType==='cargas_directas'?'selected':''}>Cargas Directas</option>
                </select>
            `;
            
            let monthsObj = {1:'Enero',2:'Febrero',3:'Marzo',4:'Abril',5:'Mayo',6:'Junio',7:'Julio',8:'Agosto',9:'Septiembre',10:'Octubre',11:'Noviembre',12:'Diciembre'};
            let mesSelect = `<select class="m-select modal-mes" data-id="${fileInfo.id}">`;
            for(let m in monthsObj) {
                mesSelect += `<option value="${m}" ${fileInfo.mes==m?'selected':''}>${monthsObj[m]}</option>`;
            }
            mesSelect += `</select>`;
            
            item.innerHTML = `
                <div class="m-item-header">
                    <div class="m-filename" title="${fileInfo.original}">
                        <span class="material-symbols-rounded">description</span> 
                        ${fileInfo.original}
                    </div>
                    <div class="m-status-icon" id="status-${fileInfo.id}"></div>
                </div>
                <div class="m-controls">
                    ${typeSelect}
                    <input type="number" class="m-input modal-anio" data-id="${fileInfo.id}" value="${fileInfo.anio}" min="2000" max="2100">
                    ${mesSelect}
                </div>
                <div class="m-progress-wrapper" id="progress-wrapper-${fileInfo.id}" style="display:none;">
                    <div class="m-progress-bar">
                        <div class="m-progress-fill" id="progress-fill-${fileInfo.id}"></div>
                    </div>
                    <div class="m-progress-text" id="progress-text-${fileInfo.id}">0%</div>
                </div>
            `;
            listContainer.appendChild(item);
        });
    }

    btnComenzar.addEventListener('click', async () => {
        if(btnComenzar.innerText === 'Finalizado') {
            closeModal();
            return;
        }
        if(isUploading) return;
        
        // Guardar cambios manuales realizados por el usuario en el modal
        document.querySelectorAll('.modal-tipo').forEach(sl => {
            let id = parseInt(sl.dataset.id);
            let fn = selectedFiles.find(f => f.id === id);
            if(fn) fn.exactType = sl.value;
        });
        document.querySelectorAll('.modal-anio').forEach(inpt => {
            let id = parseInt(inpt.dataset.id);
            let fn = selectedFiles.find(f => f.id === id);
            if(fn) fn.anio = inpt.value;
        });
        document.querySelectorAll('.modal-mes').forEach(sl => {
            let id = parseInt(sl.dataset.id);
            let fn = selectedFiles.find(f => f.id === id);
            if(fn) fn.mes = sl.value;
        });
        
        // Validar que todos los archivos tengan tipo
        const errors = selectedFiles.filter(f => f.exactType === '');
        if(errors.length > 0) {
            alert('Falta seleccionar tipo de anomalía para algunos archivos.');
            return;
        }
        
        isUploading = true;
        btnComenzar.disabled = true;
        btnCancelar.disabled = true;
        btnComenzar.classList.add('subir-btn--loading');
        modalClose.style.pointerEvents = 'none';
        
        document.getElementById('ea-alert-container').innerHTML = ''; // Limpiar alertas previas
        
        // Inhabilita controles de edición
        document.querySelectorAll('#subir-modal .m-select, #subir-modal .m-input').forEach(el => el.disabled = true);
        
        let totalExitosos = 0;
        
        for(let i=0; i<selectedFiles.length; i++) {
            let doc = selectedFiles[i];
            let fileObj = rawFilesMap.get(doc.id);
            
            let wrapper = document.getElementById(`progress-wrapper-${doc.id}`);
            let fill = document.getElementById(`progress-fill-${doc.id}`);
            let text = document.getElementById(`progress-text-${doc.id}`);
            let status = document.getElementById(`status-${doc.id}`);
            
            wrapper.style.display = 'flex';
            text.innerText = 'Inicializando...';
            fill.style.width = '2%';
            status.innerHTML = `<div class="m-spinner"></div>`;
            
            try {
                // FASE 1: INICIALIZACIÓN
                let formData = new FormData();
                formData.append('action', 'init');
                formData.append('archivo', fileObj);
                formData.append('tipo_defecto', doc.exactType);
                formData.append('anio', doc.anio);
                formData.append('mes', doc.mes);
                
                let initRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: formData });
                let initData = await initRes.json();
                
                if(initData.error) throw new Error(initData.error);
                
                const totalLineas = initData.total_lines;
                const tmpFile = initData.tmp_file;
                const originalName = initData.original_name;
                
                // FASE 2: CHUNKS
                let lineasProcesadas = 0;
                const limitMaximo = 5000;
                
                while(lineasProcesadas < totalLineas) {
                    let chunkFormData = new FormData();
                    chunkFormData.append('action', 'process_chunk');
                    chunkFormData.append('tmp_file', tmpFile);
                    chunkFormData.append('start', lineasProcesadas);
                    chunkFormData.append('limit', limitMaximo);
                    chunkFormData.append('tipo_defecto', doc.exactType);
                    chunkFormData.append('anio', doc.anio);
                    chunkFormData.append('mes', doc.mes);
                    
                    let chunkRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: chunkFormData });
                    let chunkData = await chunkRes.json();
                    
                    if(chunkData.error) throw new Error(chunkData.error);
                    
                    lineasProcesadas += chunkData.processed;
                    let porcentaje = Math.min(100, Math.round((lineasProcesadas / totalLineas) * 100));
                    
                    fill.style.width = porcentaje + '%';
                    text.innerText = porcentaje + '%';
                    
                    if(chunkData.processed < limitMaximo) break;
                }
                
                // FASE 3: FINISH
                fill.style.width = '100%';
                let finishFormData = new FormData();
                finishFormData.append('action', 'finish');
                finishFormData.append('tmp_file', tmpFile);
                finishFormData.append('tipo_defecto', doc.exactType);
                finishFormData.append('anio', doc.anio);
                finishFormData.append('mes', doc.mes);
                finishFormData.append('original_name', originalName);
                
                let finishRes = await fetch('../src/api_csv_procesar.php', { method: 'POST', body: finishFormData });
                let finishData = await finishRes.json();
                
                if(finishData.error) throw new Error(finishData.error);
                
                text.innerText = '¡Completado!';
                fill.classList.add('m-progress-fill--success');
                status.innerHTML = `<span class="material-symbols-rounded m-icon-success">check_circle</span>`;
                totalExitosos++;
                
            } catch(err) {
                text.innerText = 'Error';
                fill.classList.add('m-progress-fill--error');
                status.innerHTML = `<span class="material-symbols-rounded m-icon-error" title="${err.message}">error</span>`;
            }
        }
        
        isUploading = false;
        btnComenzar.innerText = 'Finalizado';
        btnComenzar.disabled = false;
        btnComenzar.classList.remove('subir-btn--loading');
        btnCancelar.innerText = 'Cerrar';
        btnCancelar.disabled = false;
        modalClose.style.pointerEvents = 'auto'; 
        
        document.getElementById('archivos').value = '';
        document.getElementById('dropzone-text').innerHTML = "Haz clic para seleccionar múltiples archivos <strong>.csv</strong>";
        
        document.getElementById('ea-alert-container').innerHTML = `
            <div class='subir-alert subir-alert--success'>
                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>
                Se procesaron correctamente <strong>${totalExitosos}</strong> de <strong>${selectedFiles.length}</strong> archivo(s) CSV.
            </div>`;
            
        document.querySelector('#subir-modal .subir-modal__desc').innerHTML = `<strong style="color:var(--color-success-fg)">¡Todas las cargas han sido completadas!</strong> Revisa el estado de cada archivo.`;
    });


    // Configuración Falsos (TXT)
    const txtFileInput = document.getElementById('archivos_txt');
    const txtDropzone = document.getElementById('falsos-dropzone-label');
    const txtModal = document.getElementById('falsos-subir-modal');
    const txtModalClose = document.getElementById('falsos-subir-modal-close');
    const btnCancelarTxt = document.getElementById('btn-cancelar-txt');
    const btnComenzarTxt = document.getElementById('btn-comenzar-carga-txt');
    const txtListContainer = document.getElementById('falsos-modal-files-list');

    let selectedTxtFiles = [];
    let isTxtUploading = false;
    let rawTxtFilesMap = new Map();

    async function detectCycleFromFile(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                const lines = text.split(/\r?\n/);
                for (let line of lines) {
                    line = line.trim();
                    if (line.length >= 7) {
                        const firstCol = line.substring(0, 16).trim();
                        if (firstCol.length >= 2) {
                            const cycle = parseInt(firstCol.substring(0, 2), 10);
                            if (!isNaN(cycle) && cycle > 0) {
                                resolve(cycle);
                                return;
                            }
                        }
                    }
                }
                resolve(null);
            };
            // Leer solo los primeros 50KB para ser sumamente rápidos y livianos
            const blob = file.slice(0, 50000);
            reader.readAsText(blob);
        });
    }

    let parityInconsistencyQueue = [];
    let currentInconsistencyItem = null;

    function iniciarValidacionParidad() {
        parityInconsistencyQueue = [];
        const monthsObj = {1:'Enero',2:'Febrero',3:'Marzo',4:'Abril',5:'Mayo',6:'Junio',7:'Julio',8:'Agosto',9:'Septiembre',10:'Octubre',11:'Noviembre',12:'Diciembre'};

        selectedTxtFiles.forEach(fileInfo => {
            // Ignorar si el usuario forzó explícitamente el mes incorrecto para este archivo
            if (fileInfo.forzarIncorrecto) return;

            const cycle = fileInfo.detectedCycle;
            if (cycle !== undefined && cycle !== null) {
                const cycleIsEven = (cycle % 2 === 0);
                const monthIsEven = (parseInt(fileInfo.mes, 10) % 2 === 0);
                
                if (cycleIsEven !== monthIsEven) {
                    const origMonthName = monthsObj[fileInfo.mes] || fileInfo.mes;
                    const suggestedMonth = (parseInt(fileInfo.mes, 10) % 12) + 1;
                    const suggestedMonthName = monthsObj[suggestedMonth];
                    
                    parityInconsistencyQueue.push({
                        fileInfo: fileInfo,
                        cycle: cycle,
                        origMonthName: origMonthName,
                        suggestedMonth: suggestedMonth,
                        suggestedMonthName: suggestedMonthName
                    });
                }
            }
        });
        
        procesarSiguienteInconsistencia();
    }

    function procesarSiguienteInconsistencia() {
        if (parityInconsistencyQueue.length === 0) {
            renderBannersInformativos();
            return;
        }
        
        currentInconsistencyItem = parityInconsistencyQueue.shift();
        
        const warningModal = document.getElementById('falsos-parity-warning-modal');
        const warningText = document.getElementById('falsos-parity-warning-text');
        
        warningText.innerHTML = `<strong>${currentInconsistencyItem.fileInfo.original}</strong>:<br>Ciclo ${currentInconsistencyItem.cycle} no corresponde a ${currentInconsistencyItem.origMonthName}.`;
        
        document.getElementById('btn-parity-keep-manual').innerText = `Mantener ${currentInconsistencyItem.origMonthName} (Manual)`;
        document.getElementById('btn-parity-accept-auto').innerText = `Aceptar ${currentInconsistencyItem.suggestedMonthName} (Auto)`;
        
        warningModal.classList.add('active');
    }

    function resolverInconsistenciaParidad(aceptarSugerido) {
        const warningModal = document.getElementById('falsos-parity-warning-modal');
        warningModal.classList.remove('active');
        
        if (currentInconsistencyItem) {
            const fileInfo = currentInconsistencyItem.fileInfo;
            if (aceptarSugerido) {
                const suggestedMonth = currentInconsistencyItem.suggestedMonth;
                fileInfo.mes = suggestedMonth;
                
                const selectEl = document.querySelector(`.modal-mes-txt[data-id="${fileInfo.id}"]`);
                if (selectEl) {
                    selectEl.value = suggestedMonth;
                }
            } else {
                fileInfo.forzarIncorrecto = true;
            }
        }
        
        currentInconsistencyItem = null;
        procesarSiguienteInconsistencia();
    }

    function renderBannersInformativos() {
        const modalAlertContainer = document.getElementById('falsos-modal-alert-container');
        if (!modalAlertContainer) return;
        
        modalAlertContainer.innerHTML = '';
        
        let messages = [];
        const monthsObj = {1:'Enero',2:'Febrero',3:'Marzo',4:'Abril',5:'Mayo',6:'Junio',7:'Julio',8:'Agosto',9:'Septiembre',10:'Octubre',11:'Noviembre',12:'Diciembre'};

        selectedTxtFiles.forEach(fileInfo => {
            const cycle = fileInfo.detectedCycle;
            if (cycle !== undefined && cycle !== null) {
                const cycleIsEven = (cycle % 2 === 0);
                const monthIsEven = (parseInt(fileInfo.mes, 10) % 2 === 0);
                
                if (cycleIsEven !== monthIsEven) {
                    const origMonthName = monthsObj[fileInfo.mes] || fileInfo.mes;
                    messages.push({
                        type: 'danger',
                        text: `<strong>${fileInfo.original}</strong>: Ciclo ${cycle} no corresponde a ${origMonthName} (Carga forzada manualmente).`
                    });
                } else if (fileInfo.mes !== fileInfo.originalMes) {
                    const origMonthName = monthsObj[fileInfo.originalMes] || fileInfo.originalMes;
                    const selectedMonthName = monthsObj[fileInfo.mes] || fileInfo.mes;
                    messages.push({
                        type: 'info',
                        text: `<strong>${fileInfo.original}</strong>: Ajustado de ${origMonthName} a ${selectedMonthName} por ciclo ${cycle}.`
                    });
                }
            }
        });
        
        if (messages.length > 0) {
            let html = `
                <div class="subir-alert subir-alert--warning" style="background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #d97706; padding: 12px 16px; border-radius: 6px; color: #92400e; margin-bottom: 15px; display: flex; flex-direction: column; gap: 6px;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:700; font-size:0.92rem;">
                        <span class="material-symbols-rounded" style="color:#d97706; font-size:18px;">warning</span>
                        Resumen de Consistencia (Ciclo/Mes)
                    </div>
                    <ul style="margin: 0; padding-left: 20px; font-size:0.88rem; line-height: 1.4; display:flex; flex-direction:column; gap:3px;">
            `;
            messages.forEach(m => {
                html += `<li>${m.text}</li>`;
            });
            html += `
                    </ul>
                </div>
            `;
            modalAlertContainer.innerHTML = html;
        }
    }

    function parseFalsosFilename(filename) {
        let basename = filename.replace(/\.txt$/i, '');
        basename = basename.replace(/\s*\(\d+\)$/, '');
        
        let anio = new Date().getFullYear();
        let mes = new Date().getMonth() + 1; // Siempre mes actual
        
        const match = basename.match(/_(\d{4})(\d{2})$/) || basename.match(/(\d{4})(\d{2})$/);
        if (match) {
            anio = parseInt(match[1], 10);
        }
        return { anio, mes, originalMes: mes, original: filename };
    }

    const handleTxtFilesSelection = async () => {
        if (!txtFileInput.files || txtFileInput.files.length === 0) {
            return;
        }

        selectedTxtFiles = [];
        rawTxtFilesMap.clear();
        txtListContainer.innerHTML = '';

        document.getElementById('falsos-subir-modal-desc').innerHTML = `Se detectaron los siguientes periodos. Corrige si hay algún error antes de cargar.`;
        btnComenzarTxt.innerText = 'Comenzar Carga';
        btnComenzarTxt.disabled = false;
        btnCancelarTxt.innerText = 'Cancelar';
        btnCancelarTxt.disabled = false;
        document.getElementById('falsos-alert-container').innerHTML = '';

        // Mostrar indicador de carga/procesamiento de archivos seleccionados
        txtListContainer.innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:30px 0; width:100%;">
                <div class="m-spinner" style="border-top-color: #074776; width: 30px; height: 30px;"></div>
                <span style="margin-top:10px; color:#64748b; font-size:0.95rem; font-weight:600;">Detectando ciclos en los archivos...</span>
            </div>
        `;

        const promises = Array.from(txtFileInput.files).map(async (file, index) => {
            let info = parseFalsosFilename(file.name);
            let cycle = await detectCycleFromFile(file);
            info.detectedCycle = cycle;
            
            selectedTxtFiles.push({ ...info, id: index });
            rawTxtFilesMap.set(index, file);
        });

        await Promise.all(promises);

        // Limpiar spinner y ordenar por id original
        txtListContainer.innerHTML = '';
        selectedTxtFiles.sort((a, b) => a.id - b.id);

        renderTxtModalList();
        
        txtModal.classList.add('active');
        
        // Ejecutar validaciones y sugerencias
        iniciarValidacionParidad();
    };

    txtFileInput.addEventListener('change', handleTxtFilesSelection);

    // Drag & Drop TXT
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evtName => {
        txtDropzone.addEventListener(evtName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    txtDropzone.addEventListener('dragenter', (e) => {
        if (isDragEventValidForTxt(e)) {
            txtDropzone.classList.add('dragover');
        }
    });
    txtDropzone.addEventListener('dragover', (e) => {
        if (isDragEventValidForTxt(e)) {
            txtDropzone.classList.add('dragover');
        }
    });
    txtDropzone.addEventListener('dragleave', () => txtDropzone.classList.remove('dragover'));
    txtDropzone.addEventListener('drop', (e) => {
        txtDropzone.classList.remove('dragover');
        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length > 0) {
            const dt = new DataTransfer();
            let txtCount = 0;
            for (let i = 0; i < droppedFiles.length; i++) {
                if (droppedFiles[i].name.toLowerCase().endsWith('.txt')) {
                    dt.items.add(droppedFiles[i]);
                    txtCount++;
                }
            }

            if (txtCount === 0) {
                document.getElementById('falsos-alert-container').innerHTML = `
                    <div class='subir-alert subir-alert--error'>
                        Solo se aceptan archivos <strong>.txt</strong>.
                    </div>`;
                return;
            }

            txtFileInput.files = dt.files;
            document.getElementById('falsos-dropzone-text').innerHTML = '<strong>' + txtCount + '</strong> archivo(s) seleccionado(s)';
            handleTxtFilesSelection();
        }
    });

    const closeTxtModal = () => { if(!isTxtUploading) txtModal.classList.remove('active'); };
    txtModalClose.addEventListener('click', closeTxtModal);
    btnCancelarTxt.addEventListener('click', closeTxtModal);

    // Escuchar cambios manuales de mes en la lista del modal
    txtListContainer.addEventListener('change', (e) => {
        if (e.target.classList.contains('modal-mes-txt')) {
            const id = parseInt(e.target.dataset.id, 10);
            const fn = selectedTxtFiles.find(f => f.id === id);
            if (fn) {
                fn.mes = parseInt(e.target.value, 10);
                fn.forzarIncorrecto = false; // Resetear la forzada manual al cambiar
                iniciarValidacionParidad();
            }
        }
    });

    function renderTxtModalList() {
        selectedTxtFiles.forEach((fileInfo) => {
            const item = document.createElement('div');
            item.className = 'modal-file-item';
            item.dataset.id = fileInfo.id;

            let monthsObj = {1:'Enero',2:'Febrero',3:'Marzo',4:'Abril',5:'Mayo',6:'Junio',7:'Julio',8:'Agosto',9:'Septiembre',10:'Octubre',11:'Noviembre',12:'Diciembre'};
            let mesSelect = `<select class="m-select modal-mes-txt" data-id="${fileInfo.id}">`;
            for(let m in monthsObj) {
                mesSelect += `<option value="${m}" ${fileInfo.mes==m?'selected':''}>${monthsObj[m]}</option>`;
            }
            mesSelect += `</select>`;

            item.innerHTML = `
                <div class="m-item-header">
                    <div class="m-filename" title="${fileInfo.original}">
                        <span class="material-symbols-rounded" style="color:#074776;">description</span> 
                        ${fileInfo.original}
                    </div>
                    <div class="m-status-icon" id="status-txt-${fileInfo.id}"></div>
                </div>
                <div class="m-controls" style="grid-template-columns: 1fr 1fr;">
                    <input type="number" class="m-input modal-anio-txt" data-id="${fileInfo.id}" value="${fileInfo.anio}" min="2000" max="2100">
                    ${mesSelect}
                </div>
                <div class="m-progress-wrapper" id="progress-wrapper-txt-${fileInfo.id}" style="display:none;">
                    <div class="m-progress-bar">
                        <div class="m-progress-fill" id="progress-fill-txt-${fileInfo.id}"></div>
                    </div>
                    <div class="m-progress-text" id="progress-text-txt-${fileInfo.id}">0%</div>
                </div>
            `;
            txtListContainer.appendChild(item);
        });
    }

    btnComenzarTxt.addEventListener('click', async () => {
        if(btnComenzarTxt.innerText === 'Finalizado') {
            closeTxtModal();
            return;
        }
        if(isTxtUploading) return;

        document.querySelectorAll('.modal-anio-txt').forEach(inpt => {
            let id = parseInt(inpt.dataset.id);
            let fn = selectedTxtFiles.find(f => f.id === id);
            if(fn) fn.anio = inpt.value;
        });
        document.querySelectorAll('.modal-mes-txt').forEach(sl => {
            let id = parseInt(sl.dataset.id);
            let fn = selectedTxtFiles.find(f => f.id === id);
            if(fn) fn.mes = sl.value;
        });

        isTxtUploading = true;
        btnComenzarTxt.disabled = true;
        btnCancelarTxt.disabled = true;
        btnComenzarTxt.classList.add('subir-btn--loading');
        txtModalClose.style.pointerEvents = 'none';

        document.getElementById('falsos-alert-container').innerHTML = '';

        document.querySelectorAll('#falsos-subir-modal .m-select, #falsos-subir-modal .m-input').forEach(el => el.disabled = true);

        let totalExitosos = 0;

        for(let i=0; i<selectedTxtFiles.length; i++) {
            let doc = selectedTxtFiles[i];
            let fileObj = rawTxtFilesMap.get(doc.id);

            let wrapper = document.getElementById(`progress-wrapper-txt-${doc.id}`);
            let fill = document.getElementById(`progress-fill-txt-${doc.id}`);
            let text = document.getElementById(`progress-text-txt-${doc.id}`);
            let status = document.getElementById(`status-txt-${doc.id}`);

            wrapper.style.display = 'flex';
            text.innerText = 'Inicializando...';
            fill.style.width = '2%';
            status.innerHTML = `<div class="m-spinner"></div>`;

            try {
                let formData = new FormData();
                formData.append('action', 'init');
                formData.append('archivo', fileObj);
                formData.append('anio', doc.anio);
                formData.append('mes', doc.mes);

                let initRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: formData });
                let initData = await initRes.json();

                if(initData.error) throw new Error(initData.error);

                const totalLineas = initData.total_lines;
                const tmpFile = initData.tmp_file;
                const originalName = initData.original_name;

                let lineasProcesadas = 0;
                const limitMaximo = 5000;

                while(lineasProcesadas < totalLineas) {
                    let chunkFormData = new FormData();
                    chunkFormData.append('action', 'process_chunk');
                    chunkFormData.append('tmp_file', tmpFile);
                    chunkFormData.append('start', lineasProcesadas);
                    chunkFormData.append('limit', limitMaximo);
                    chunkFormData.append('original_name', originalName);
                    chunkFormData.append('anio', doc.anio);
                    chunkFormData.append('mes', doc.mes);

                    let chunkRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: chunkFormData });
                    let chunkData = await chunkRes.json();

                    if(chunkData.error) throw new Error(chunkData.error);

                    lineasProcesadas += chunkData.processed;
                    let porcentaje = Math.min(100, Math.round((lineasProcesadas / totalLineas) * 100));

                    fill.style.width = porcentaje + '%';
                    text.innerText = porcentaje + '%';

                    if(chunkData.processed < limitMaximo) break;
                }

                fill.style.width = '100%';
                let finishFormData = new FormData();
                finishFormData.append('action', 'finish');
                finishFormData.append('tmp_file', tmpFile);
                finishFormData.append('anio', doc.anio);
                finishFormData.append('mes', doc.mes);
                finishFormData.append('original_name', originalName);

                let finishRes = await fetch('../src/api_falsos_procesar.php', { method: 'POST', body: finishFormData });
                let finishData = await finishRes.json();

                if(finishData.error) throw new Error(finishData.error);

                text.innerText = '¡Completado!';
                fill.classList.add('m-progress-fill--success');
                status.innerHTML = `<span class="material-symbols-rounded m-icon-success">check_circle</span>`;
                totalExitosos++;

            } catch(err) {
                text.innerText = 'Error';
                fill.classList.add('m-progress-fill--error');
                status.innerHTML = `<span class="material-symbols-rounded m-icon-error" title="${err.message}">error</span>`;
            }
        }

        isTxtUploading = false;
        btnComenzarTxt.innerText = 'Finalizado';
        btnComenzarTxt.disabled = false;
        btnComenzarTxt.classList.remove('subir-btn--loading');
        btnCancelarTxt.innerText = 'Cerrar';
        btnCancelarTxt.disabled = false;
        txtModalClose.style.pointerEvents = 'auto';

        document.getElementById('archivos_txt').value = '';
        document.getElementById('falsos-dropzone-text').innerHTML = "Haz clic para seleccionar múltiples archivos <strong>.txt</strong>";

        document.getElementById('falsos-alert-container').innerHTML = `
            <div class='subir-alert subir-alert--success'>
                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>
                Se procesaron correctamente <strong>${totalExitosos}</strong> de <strong>${selectedTxtFiles.length}</strong> archivo(s) TXT.
            </div>`;

        document.getElementById('falsos-subir-modal-desc').innerHTML = `<strong style="color:var(--color-success-fg)">¡Todas las cargas han sido completadas!</strong> Revisa el estado de cada archivo.`;
    });


    // Funciones del historial de archivos cargados (para Falsos)
    function abrirModalHistorial() {
        document.getElementById('ea-historial-modal').style.display = 'block';
        cargarHistorial();
    }

    function cerrarModalHistorial() {
        document.getElementById('ea-historial-modal').style.display = 'none';
        document.getElementById('historial-alert-container').innerHTML = '';
    }

    function cargarHistorial() {
        const container = document.getElementById('historial-tabla-container');
        container.innerHTML = `
            <div class="ea-spinner-container">
                <div class="ea-spinner"></div>
                <span class="ea-spinner-text" style="margin-top:10px; color:#64748b;">Cargando historial...</span>
            </div>
        `;

        const formData = new FormData();
        formData.append('action', 'get_history');

        fetch('../src/api_falsos_procesar.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div style="padding:20px; color:#dc3545;">Error: ${data.error}</div>`;
                return;
            }

            if (!data.data || data.data.length === 0) {
                container.innerHTML = `<div style="padding:20px; text-align:center; color:#64748b;">No hay registros de archivos cargados.</div>`;
                return;
            }

            let html = `
                <table class="ea-table" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 15%; min-width: 90px;">Periodo</th>
                            <th style="width: 48%;">Nombre de Archivo</th>
                            <th style="width: 22%; min-width: 130px;">Fecha de Carga</th>
                            <th style="text-align:center; width: 15%; min-width: 110px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            const meses = {
                1: 'Ene', 2: 'Feb', 3: 'Mar', 4: 'Abr', 5: 'May', 6: 'Jun',
                7: 'Jul', 8: 'Ago', 9: 'Sep', 10: 'Oct', 11: 'Nov', 12: 'Dic'
            };

            data.data.forEach(row => {
                const mesNom = meses[row.mes_asociado] || row.mes_asociado;
                const periodo = `${mesNom} ${row.anio_asociado}`;
                const fecha = new Date(row.fecha_subida).toLocaleString('es-MX', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });

                html += `
                    <tr>
                        <td style="font-weight:600; color:#0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${periodo}</td>
                        <td style="color:#64748b; font-family:monospace; font-size:0.75rem; word-break: break-all; white-space: normal; overflow-wrap: break-word;">${row.nombre_archivo_original}</td>
                        <td style="color:#64748b; font-size:0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${fecha}">${fecha}</td>
                        <td style="text-align:center;">
                            <button onclick="eliminarHistorial(${row.id_archivo}, '${row.nombre_archivo_original.replace(/'/g, "\\'")}')" class="subir-btn" style="background-color:#dc3545; color:white; border:none; border-radius:6px; font-size:0.75rem; padding:6px 10px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-weight:600; height:32px; width: auto;" onmouseover="this.style.backgroundColor='#bd2130'" onmouseout="this.style.backgroundColor='#dc3545'">
                                <span class="material-symbols-rounded" style="font-size:14px; color:white;">delete</span> Eliminar
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = `<div style="padding:20px; color:#dc3545;">Error de red: ${err.message}</div>`;
        });
    }

    function eliminarHistorial(id_archivo, nombre_archivo) {
        if (!confirm(`¿Estás seguro de que deseas eliminar los datos del archivo "${nombre_archivo}"?\nEsta acción es irreversible y borrará la tabla de anomalías correspondiente.`)) {
            return;
        }

        const alertContainer = document.getElementById('historial-alert-container');
        alertContainer.innerHTML = '';

        const formData = new FormData();
        formData.append('action', 'delete_history');
        formData.append('id_archivo', id_archivo);

        fetch('../src/api_falsos_procesar.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alertContainer.innerHTML = `
                    <div style='padding:12px; background-color:#f8d7da; color:#842029; margin-bottom:15px; border-radius:6px; border:1px solid #f5c2c7; font-size:0.85rem;'>
                        <strong>Error:</strong> ${data.error}
                    </div>`;
                return;
            }

            alertContainer.innerHTML = `
                <div style='padding:12px; background-color:#d1e7dd; color:#0f5132; margin-bottom:15px; border-radius:6px; border:1px solid #badbcc; font-size:0.85rem;'>
                    <strong>¡Éxito!</strong> Se eliminaron los datos y el registro del archivo.
                </div>`;

            cargarHistorial();
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        })
        .catch(err => {
            alertContainer.innerHTML = `
                <div style='padding:12px; background-color:#f8d7da; color:#842029; margin-bottom:15px; border-radius:6px; border:1px solid #f5c2c7; font-size:0.85rem;'>
                    <strong>Error de red:</strong> ${err.message}
                </div>`;
        });
    }
</script>
</html>
