<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$tabla       = isset($_GET['tabla'])       ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';
$id_registro = isset($_GET['id_registro']) ? (int)$_GET['id_registro'] : 0;
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';

if ($tabla === '' || $id_registro <= 0) {
    echo "<div style='padding:20px; text-align:center; color:#dc3545;'>Error: Parámetros no válidos.</div>";
    exit;
}

try {
    // Asegurar que la tabla de historial existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `falsos_comentarios_historial` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tabla` VARCHAR(50) NOT NULL,
        `id_registro` INT NOT NULL,
        `rpu` VARCHAR(50) DEFAULT NULL,
        `usuario` VARCHAR(100) NOT NULL,
        `comentario` TEXT NOT NULL,
        `fecha_registro` DATETIME NOT NULL,
        `observacion` TEXT DEFAULT NULL,
        `usuario_observacion` VARCHAR(100) DEFAULT NULL,
        `fecha_observacion` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Asegurar que la columna `rpu` existe en `falsos_comentarios_historial`
    try {
        $pdo->query("SELECT `rpu` FROM `falsos_comentarios_historial` LIMIT 1");
    } catch (PDOException $e_col) {
        try {
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD COLUMN `rpu` VARCHAR(50) DEFAULT NULL");
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD INDEX (`rpu`)");
        } catch (PDOException $ex) {}
    }

    // Asegurar que la tabla de observaciones múltiples existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `falsos_observaciones_historial` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_comentario` INT NOT NULL,
        `usuario` VARCHAR(100) NOT NULL,
        `observacion` TEXT NOT NULL,
        `fecha_registro` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migración automática de observaciones antiguas (si existen)
    try {
        $check_migrar = $pdo->query("SELECT id, observacion, usuario_observacion, fecha_observacion FROM falsos_comentarios_historial WHERE observacion IS NOT NULL AND observacion != ''");
        $viejos = $check_migrar->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($viejos)) {
            $stmt_ins = $pdo->prepare("INSERT INTO falsos_observaciones_historial (id_comentario, usuario, observacion, fecha_registro) VALUES (?, ?, ?, ?)");
            $stmt_upd = $pdo->prepare("UPDATE falsos_comentarios_historial SET observacion = NULL, usuario_observacion = NULL, fecha_observacion = NULL WHERE id = ?");
            foreach ($viejos as $v) {
                $stmt_ins->execute([
                    $v['id'],
                    $v['usuario_observacion'] !== null ? $v['usuario_observacion'] : 'Admin',
                    $v['observacion'],
                    $v['fecha_observacion'] !== null ? $v['fecha_observacion'] : date('Y-m-d H:i:s')
                ]);
                $stmt_upd->execute([$v['id']]);
            }
        }
    } catch (PDOException $e_mig) {
        // En caso de que falle por alguna razón de esquema, omitir silenciosamente
    }

    // Obtener el Rpu de la tabla principal
    $stmt_rpu = $pdo->prepare("SELECT `Rpu` FROM `$tabla` WHERE `id_registro` = ?");
    $stmt_rpu->execute([$id_registro]);
    $rpu = $stmt_rpu->fetchColumn();
    
    if (!$rpu) {
        // Fallback en caso de que no encontremos el registro en la tabla
        $stmt_rpu_hist = $pdo->prepare("SELECT `rpu` FROM `falsos_comentarios_historial` WHERE `tabla` = ? AND `id_registro` = ? LIMIT 1");
        $stmt_rpu_hist->execute([$tabla, $id_registro]);
        $rpu = $stmt_rpu_hist->fetchColumn();
    }

    // Consultar el historial por RPU
    if ($rpu) {
        $stmt = $pdo->prepare("SELECT * FROM `falsos_comentarios_historial` WHERE `rpu` = ? ORDER BY `fecha_registro` DESC");
        $stmt->execute([$rpu]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `falsos_comentarios_historial` WHERE `tabla` = ? AND `id_registro` = ? ORDER BY `fecha_registro` DESC");
        $stmt->execute([$tabla, $id_registro]);
    }
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($comentarios)) {
        echo "<div style='padding:20px; text-align:center; color:#64748b; font-style:italic;'>No hay comentarios registrados en el historial para este registro.</div>";
        exit;
    }

    echo '<div class="historial-list" style="display:flex; flex-direction:column; gap:16px; max-height: 600px; overflow-y:auto; padding-right:8px;">';
    foreach ($comentarios as $c) {
        $id_comentario = (int)$c['id'];
        $fecha_f = date('d/m/Y H:i:s', strtotime($c['fecha_registro']));
        
        echo '<div class="historial-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background-color: #f8fafc; display: flex; gap: 16px; align-items: stretch;">';
        
        // Columna izquierda: Comentario
        echo '  <div style="flex: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0;">';
        
        // Cabecera del comentario (Usuario y fecha)
        echo '    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:2px;">';
        echo '      <span style="font-weight:700; font-size:1.1rem; color:#0f172a;"><span class="material-symbols-rounded" style="vertical-align:middle; font-size:22px; margin-right:4px; color:#074776;">person</span>' . htmlspecialchars($c['usuario']) . '</span>';
        echo '      <span style="font-size:0.95rem; color:#64748b;">' . $fecha_f . '</span>';
        echo '    </div>';
        
        // Texto del comentario
        echo '    <div style="font-size:1.15rem; color:#334155; line-height:1.45; white-space:pre-wrap; word-break:break-all;">' . htmlspecialchars($c['comentario']) . '</div>';
        echo '  </div>'; // Cierra la columna izquierda
        
        // Columna derecha: Historial de Observaciones y Agregar Observación (Tema Azul, 520px de ancho)
        echo '  <div id="obs-col-wrapper-' . $id_comentario . '" style="flex: 0 0 520px; width: 520px; display: flex; flex-direction: column; gap: 10px; border-left: 1px dashed #cbd5e1; padding-left: 16px; justify-content: flex-start; min-width: 520px;">';
        
        // Consultar observaciones de este comentario de la tabla de observaciones múltiples
        $stmt_obs = $pdo->prepare("SELECT * FROM `falsos_observaciones_historial` WHERE `id_comentario` = ? ORDER BY `fecha_registro` ASC");
        $stmt_obs->execute([$id_comentario]);
        $observaciones = $stmt_obs->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($observaciones)) {
            echo '    <div class="observaciones-container" style="display:flex; flex-direction:column; gap:8px;">';
            foreach ($observaciones as $obs) {
                $fecha_obs_f = date('d/m/Y H:i:s', strtotime($obs['fecha_registro']));
                echo '      <div class="observacion-box" style="border-left: 3px solid #0284c7; background-color: #f0f9ff; padding: 8px 12px; border-radius: 0 6px 6px 0; display:flex; flex-direction:column; gap:4px;">';
                echo '        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.95rem; color:#0369a1; border-bottom:1px dashed #bae6fd; padding-bottom:4px;">';
                echo '          <span style="font-weight:700;"><span class="material-symbols-rounded" style="vertical-align:middle; font-size:18px; margin-right:4px; color:#0284c7;">gavel</span>Observación por ' . htmlspecialchars($obs['usuario']) . '</span>';
                echo '          <span>' . $fecha_obs_f . '</span>';
                echo '        </div>';
                echo '        <div style="font-size:1.05rem; color:#0c4a6e; line-height:1.45; white-space:pre-wrap; word-break:break-all;">' . htmlspecialchars($obs['observacion']) . '</div>';
                echo '      </div>';
            }
            echo '    </div>';
        }

        // Si es administrador, mostrar botón de palanca para agregar observación
        if ($rol_usuario === 'admin') {
            // Botón / Icono para agregar observación (inicialmente visible)
            echo '    <div id="obs-toggle-btn-' . $id_comentario . '" style="display:flex; align-items:center;">';
            echo '      <button onclick="toggleFormObservacion(' . $id_comentario . ', true)" style="background:none; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; color:#0284c7; font-size:1.0rem; font-weight:600; padding:4px 8px; border-radius:4px; transition: background 0.2s;" onmouseover="this.style.backgroundColor=\'#f0f9ff\'" onmouseout="this.style.backgroundColor=\'transparent\'">';
            echo '        <span class="material-symbols-rounded" style="font-size: 22px; color: #0284c7; vertical-align:middle;">add_circle</span>';
            echo '        <span>Agregar Observación</span>';
            echo '      </button>';
            echo '    </div>';
            
            // Formulario de observación (inicialmente oculto)
            echo '    <div class="observacion-form-container" id="obs-form-container-' . $id_comentario . '" style="display:none; flex-direction:column; gap:6px;">';
            
            // Loading Spinner for observation (inicialmente oculto)
            echo '      <div id="obs-loading-' . $id_comentario . '" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:15px 0;">';
            echo '        <div class="ea-spinner" style="width: 24px; height: 24px; border-width: 3px;"></div>';
            echo '        <span style="margin-top:5px; color:#64748b; font-size:0.95rem; font-weight:600;">Guardando observación...</span>';
            echo '      </div>';
            
            // Formulario de observación
            echo '      <div id="obs-form-content-' . $id_comentario . '" style="display:flex; flex-direction:column; gap:6px; width:100%;">';
            echo '        <label style="display:block; font-size:0.98rem; font-weight:700; color:#1e293b;">Agregar Observación:</label>';
            echo '        <textarea id="obs-text-' . $id_comentario . '" placeholder="Escribe una observación..." onkeydown="if(event.key === \'Enter\' && !event.shiftKey) { event.preventDefault(); guardarObservacion(' . $id_comentario . '); }" style="width:100%; height:90px; font-size:1.0rem; border:1px solid #cbd5e1; border-radius:4px; padding:6px; outline:none; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>';
            echo '        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:2px;">';
            echo '          <button onclick="toggleFormObservacion(' . $id_comentario . ', false)" style="background-color:#e2e8f0; color:#475569; border:none; border-radius:4px; font-weight:600; font-size:0.95rem; padding:4px 10px; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.backgroundColor=\'#cbd5e1\'" onmouseout="this.style.backgroundColor=\'#e2e8f0\'">Cancelar</button>';
            echo '          <button onclick="guardarObservacion(' . $id_comentario . ')" style="background-color:#0284c7; color:white; border:none; border-radius:4px; font-weight:600; font-size:0.95rem; padding:4px 10px; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.backgroundColor=\'#0369a1\'" onmouseout="this.style.backgroundColor=\'#0284c7\'">Guardar</button>';
            echo '        </div>';
            echo '      </div>';
            
            echo '    </div>';
        }
        
        echo '  </div>'; // Cierra la columna derecha
        
        echo '</div>';
    }
    echo '</div>';

} catch (PDOException $e) {
    echo "<div style='padding:20px; text-align:center; color:#dc3545;'>Error base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
