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
    <title>SUBIR ARCHIVOS</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="subir-main">

    <div class="subir-card">

        <div class="subir-card__header">
            <h1 class="subir-card__title">Carga de Archivos CSV</h1>
            <p class="subir-card__subtitle">Registra nuevos datos de anomalías por tipo, año y mes.</p>
        </div>

        <div class="subir-card__body">

            <div id="ea-alert-container"></div>

            <form action="" method="POST" enctype="multipart/form-data" class="subir-form">

                <div class="subir-form__group">
                    <label class="subir-form__label" for="archivos">Seleccionar Archivos</label>
                    <label class="subir-dropzone" for="archivos" id="dropzone-label">
                        <svg class="subir-dropzone__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span class="subir-dropzone__text" id="dropzone-text">Haz clic para seleccionar múltiples archivos <strong>.csv</strong></span>
                        <span class="subir-dropzone__hint">o arrastra y suelta aquí</span>
                        <input type="file" name="archivos[]" id="archivos" accept=".csv" multiple required
                               onchange="document.getElementById('dropzone-text').innerHTML = this.files.length > 0 ? '<strong>' + this.files.length + '</strong> archivo(s) seleccionado(s)' : 'Haz clic para seleccionar múltiples archivos <strong>.csv</strong>';">
                    </label>

                    <!-- Overlay de arrastre a nivel ventana -->
                    <div id="drag-overlay" class="drag-overlay">
                        <div class="drag-overlay__content">
                            <span class="material-symbols-rounded drag-overlay__icon">upload_file</span>
                            <span class="drag-overlay__text">Suelta los archivos aquí</span>
                            <span class="drag-overlay__hint">Se aceptan archivos <strong>.csv</strong></span>
                        </div>
                    </div>
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

    <!-- Modal de Confirmación y Carga -->
    <div id="subir-modal" class="subir-modal">
        <div class="subir-modal__content">
            <div class="subir-modal__header">
                <h3>Archivos a Procesar</h3>
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
        </div>
    </div>

</main>

<style>
    /* ── Drag & Drop: Overlay de ventana completa ── */
    .drag-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(26, 122, 94, 0.12);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        border: 4px dashed var(--color-primary, #1a7a5e);
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    .drag-overlay.active {
        display: flex;
        animation: dragOverlayIn 0.25s ease;
    }
    @keyframes dragOverlayIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    .drag-overlay__content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        background: #fff;
        padding: 40px 60px;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    }
    .drag-overlay__icon {
        font-size: 56px;
        color: var(--color-primary, #1a7a5e);
        animation: dragIconBounce 1.2s ease infinite;
    }
    @keyframes dragIconBounce {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }
    .drag-overlay__text {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--color-text, #1e2b27);
    }
    .drag-overlay__hint {
        font-size: 0.85rem;
        color: var(--color-muted, #6b7c78);
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
    // ── Lógica de Carga Masiva con Interfaz Modal y Detección
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
    const btnAnalizar = document.getElementById('btn-analizar');
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
        // Quita espacios y paréntesis (ej: " (1)", "(2)")
        basename = basename.replace(/\s*\(\d+\)$/, '');
        
        let textType = '', anio = new Date().getFullYear(), mes = new Date().getMonth() + 1;
        let exactType = '';
        
        // Busca patron: (Texto)_(YYYY)(MM)
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
        
        document.querySelector('.subir-modal__desc').innerHTML = `Se detectaron los siguientes datos. Corrige si hay algún error antes de cargar.`;
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

    // ── Drag & Drop: detección a nivel ventana (Windows compatible) ──
    const dragOverlay = document.getElementById('drag-overlay');
    const dropzone = document.getElementById('dropzone-label');
    let dragCounter = 0; // Contador para manejar drag enter/leave en hijos

    // Prevenir comportamiento por defecto del navegador (abrir archivo)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evtName => {
        document.body.addEventListener(evtName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    // Mostrar overlay cuando se arrastra sobre la ventana
    document.body.addEventListener('dragenter', (e) => {
        dragCounter++;
        if (e.dataTransfer && e.dataTransfer.types && e.dataTransfer.types.includes('Files')) {
            dragOverlay.classList.add('active');
        }
    });

    document.body.addEventListener('dragleave', (e) => {
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            dragOverlay.classList.remove('active');
        }
    });

    // Soltar archivos en cualquier parte de la ventana
    document.body.addEventListener('drop', (e) => {
        dragCounter = 0;
        dragOverlay.classList.remove('active');
        dropzone.classList.remove('dragover');

        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length > 0) {
            // Filtrar solo archivos .csv
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
                        <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='15' y1='9' x2='9' y2='15'/><line x1='9' y1='9' x2='15' y2='15'/></svg>
                        Solo se aceptan archivos <strong>.csv</strong>. Ninguno de los archivos arrastrados es válido.
                    </div>`;
                return;
            }

            // Asignar archivos al input
            fileInput.files = dt.files;
            document.getElementById('dropzone-text').innerHTML = '<strong>' + csvCount + '</strong> archivo(s) seleccionado(s)';
            
            // Disparar el flujo de procesamiento
            handleFilesSelection();
        }
    });

    // Efecto visual en el dropzone específico
    dropzone.addEventListener('dragenter', () => dropzone.classList.add('dragover'));
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('dragover', (e) => e.preventDefault());

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
        document.querySelectorAll('.m-select, .m-input').forEach(el => el.disabled = true);
        
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
                Se procesaron correctamente <strong>${totalExitosos}</strong> de <strong>${selectedFiles.length}</strong> archivo(s).
            </div>`;
            
        document.querySelector('.subir-modal__desc').innerHTML = `<strong style="color:var(--color-success-fg)">¡Todas las cargas han sido completadas!</strong> Revisa el estado de cada archivo.`;
    });
</script>
</body>
</html>

