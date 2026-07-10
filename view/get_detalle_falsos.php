<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$tabla    = isset($_GET['tabla'])    ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';
$pattern  = isset($_GET['pattern'])  ? trim($_GET['pattern']) : '';
$concepto = isset($_GET['concepto']) ? trim($_GET['concepto']) : 'Detalle';
$agencia  = isset($_GET['agencia'])  ? trim($_GET['agencia']) : '';
$ciclo    = isset($_GET['ciclo']) && $_GET['ciclo'] !== '' ? (int)$_GET['ciclo'] : null;

if ($tabla === '') {
    echo "<div style='padding:20px; text-align:center; color:#dc3545;'>Error: Tabla no especificada.</div>";
    exit;
}

try {
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt_check->execute([$tabla]);
    if (!$stmt_check->fetch()) {
        echo "<div style='padding:20px; text-align:center; color:#6c757d;'>No hay datos registrados para esta tabla aún.</div>";
        exit;
    }

    // Asegurar que la columna Comentario existe en la tabla
    try {
        $pdo->query("SELECT `Comentario` FROM `$tabla` LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `Comentario` TEXT DEFAULT NULL");
        } catch (PDOException $ex) {}
    }

    $where = "WHERE (Tipo = 'Estimado' OR Tipo LIKE '%ESTIM%')";
    $params = [];

    if ($pattern !== '') {
        $where .= " AND Anomalia LIKE ?";
        $params[] = '%' . $pattern . '%';
    }

    if ($agencia !== '') {
        $where .= " AND Agencia = ?";
        $params[] = $agencia;
    }

    if ($ciclo !== null) {
        $where .= " AND Ciclo = ?";
        $params[] = $ciclo;
    }

    $query = "SELECT id_registro, Rpu, Nis, Nombre, Direccion, Tarifa, Codigo, Tipo, Anomalia, Ciclo, Agencia, Comentario FROM `$tabla` $where LIMIT 500";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "<div style='padding:20px; text-align:center; color:#6c757d;'>No se encontraron registros detallados para los filtros aplicados.</div>";
        exit;
    }

    // Asegurar que la columna `rpu` existe en `falsos_comentarios_historial`
    try {
        $pdo->query("SELECT `rpu` FROM `falsos_comentarios_historial` LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD COLUMN `rpu` VARCHAR(50) DEFAULT NULL");
            $pdo->exec("ALTER TABLE `falsos_comentarios_historial` ADD INDEX (`rpu`)");
        } catch (PDOException $ex) {}
    }

    // Intentar migrar los RPUs de comentarios existentes si están vacíos
    try {
        $stmt_all_comments = $pdo->query("SELECT id, tabla, id_registro FROM `falsos_comentarios_historial` WHERE rpu IS NULL");
        $viejos_comments = $stmt_all_comments->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($viejos_comments)) {
            $stmt_upd_rpu = $pdo->prepare("UPDATE `falsos_comentarios_historial` SET rpu = ? WHERE id = ?");
            foreach ($viejos_comments as $vc) {
                $t = preg_replace('/[^a-zA-Z0-9_]/', '', $vc['tabla']);
                $stmt_rpu_orig = $pdo->prepare("SELECT Rpu FROM `$t` WHERE id_registro = ?");
                $stmt_rpu_orig->execute([$vc['id_registro']]);
                $rpu_orig = $stmt_rpu_orig->fetchColumn();
                if ($rpu_orig) {
                    $stmt_upd_rpu->execute([$rpu_orig, $vc['id']]);
                }
            }
        }
    } catch (PDOException $e_mig_rpu) {}

    // Obtener la lista de RPUs de las filas actuales para hacer consultas optimizadas
    $rpus_list = [];
    foreach ($filas as $f) {
        if (isset($f['Rpu']) && trim($f['Rpu']) !== '') {
            $rpus_list[] = trim($f['Rpu']);
        }
    }
    $rpus_list = array_values(array_unique($rpus_list));

    $rpu_ultimo_comentario = [];
    $rpu_ultimo_comentario_id = [];
    $rpu_cant_comments = [];
    $rpu_cant_obs = [];
    $rpu_ultima_observacion = [];

    if (!empty($rpus_list)) {
        $placeholders = implode(',', array_fill(0, count($rpus_list), '?'));
        
        try {
            // 1. Obtener los últimos comentarios para cada RPU
            $stmt_last_comms = $pdo->prepare("
                SELECT c1.rpu, c1.comentario, c1.id
                FROM falsos_comentarios_historial c1
                INNER JOIN (
                    SELECT rpu, MAX(id) as max_id
                    FROM falsos_comentarios_historial
                    WHERE rpu IN ($placeholders)
                    GROUP BY rpu
                ) c2 ON c1.id = c2.max_id
            ");
            $stmt_last_comms->execute($rpus_list);
            while ($row_c = $stmt_last_comms->fetch(PDO::FETCH_ASSOC)) {
                $rpu_ultimo_comentario[$row_c['rpu']] = $row_c['comentario'];
                $rpu_ultimo_comentario_id[$row_c['rpu']] = (int)$row_c['id'];
            }

            // 2. Obtener estado de historial de comentarios y observaciones agrupados por RPU
            $stmt_hist = $pdo->prepare("
                SELECT c.rpu, COUNT(DISTINCT c.id) as cant_comments, COUNT(DISTINCT o.id) as cant_obs
                FROM `falsos_comentarios_historial` c
                LEFT JOIN `falsos_observaciones_historial` o ON o.id_comentario = c.id
                WHERE c.rpu IN ($placeholders)
                GROUP BY c.rpu
            ");
            $stmt_hist->execute($rpus_list);
            while ($row_h = $stmt_hist->fetch(PDO::FETCH_ASSOC)) {
                $rpu_cant_comments[$row_h['rpu']] = (int)$row_h['cant_comments'];
                $rpu_cant_obs[$row_h['rpu']] = (int)$row_h['cant_obs'];
            }

            // 3. Obtener la última observación únicamente del último comentario de cada RPU
            $stmt_last_obs = $pdo->prepare("
                SELECT c.rpu, o.observacion
                FROM falsos_observaciones_historial o
                INNER JOIN falsos_comentarios_historial c ON o.id_comentario = c.id
                INNER JOIN (
                    SELECT c2.rpu, MAX(o2.id) as max_obs_id
                    FROM falsos_observaciones_historial o2
                    INNER JOIN falsos_comentarios_historial c2 ON o2.id_comentario = c2.id
                    INNER JOIN (
                        SELECT rpu, MAX(id) as max_comment_id
                        FROM falsos_comentarios_historial
                        WHERE rpu IN ($placeholders)
                        GROUP BY rpu
                    ) m_c ON c2.id = m_c.max_comment_id
                    GROUP BY c2.rpu
                ) m ON o.id = m.max_obs_id
            ");
            $stmt_last_obs->execute(array_merge($rpus_list, $rpus_list));
            while ($row_o = $stmt_last_obs->fetch(PDO::FETCH_ASSOC)) {
                $rpu_ultima_observacion[$row_o['rpu']] = trim($row_o['observacion']);
            }
        } catch (PDOException $e) {
            // Ignorar si las tablas de historial o esquema no están listas
        }
    }

    // Estilos CSS para el modal
    echo '<style>
        .ea-table--detailed {
            width: max-content !important;
            min-width: max-content !important;
            border-collapse: collapse;
            font-family: "Segoe UI", system-ui, sans-serif;
            font-size: 0.82rem;
            table-layout: auto !important;
        }
        .ea-table--detailed th {
            background-color: #074776 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 8px 12px;
            border: 1.5px solid #ffffff !important;
            text-align: center;
        }
        .ea-table--detailed td {
            padding: 6px 12px;
            border: 1.5px solid #cbd5e1 !important;
            font-size: 0.82rem;
            color: #0f172a;
            vertical-align: middle;
        }
        .ea-table--detailed tbody tr:nth-child(even) td {
            background-color: #f7fee7 !important; /* Verde muy claro */
        }
        .ea-table--detailed tbody tr:nth-child(odd) td {
            background-color: #fefce8 !important; /* Amarillo muy claro */
        }
        .ea-table--detailed tbody tr:hover td {
            background-color: #f1f5f9 !important; /* Efecto hover */
        }
        
        /* Contenedor del comentario e input */
        .comment-cell-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            min-width: 220px;
        }
        .comment-static-text {
            word-break: break-word;
            flex-grow: 1;
            font-weight: 500;
            color: #1e293b;
        }
        .comment-static-text.empty {
            color: #94a3b8;
            font-style: italic;
            font-weight: 400;
        }
        .comment-edit-field {
            width: 100%;
            padding: 4px 6px;
            font-size: 0.8rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-family: inherit;
            outline: none;
            box-sizing: border-box;
        }
        .comment-edit-field:focus {
            border-color: #074776;
            box-shadow: 0 0 0 2px rgba(7, 71, 118, 0.15);
        }

        /* Botones */
        .btn-comment-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .btn-edit-comment {
            color: #074776;
        }
        .btn-edit-comment:hover {
            color: #008f39;
            background-color: rgba(0, 143, 57, 0.08);
        }
        .btn-save-comment {
            color: #16a34a;
        }
        .btn-save-comment:hover {
            background-color: #d1e7dd;
        }
        .btn-cancel-comment {
            color: #dc3545;
        }
        .btn-cancel-comment:hover {
            background-color: #f8d7da;
        }
        .comment-actions-wrapper {
            display: flex;
            gap: 2px;
            align-items: center;
        }
        .comment-cell-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #cbd5e1;
            border-top: 2px solid #074776;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        .ea-table--detailed td:focus {
            outline: 2px dashed #0284c7 !important;
            outline-offset: -2.5px;
            background-color: #e0f2fe !important;
        }
    </style>';

    // Tabla con scroll que abarca todo el cuerpo del modal sin paddings ni bordes extra
    echo '<div class="ea-table-wrapper" style="flex: 1; overflow: auto; border: none; border-radius: 0; background-color: #e6f2fc;">';
    echo '<table class="ea-table ea-table--detailed">';
    echo '<thead><tr class="ea-table__head-cols" style="position: sticky; top: 0; z-index: 1;">';
    echo '<th>RPU</th>';
    echo '<th>NIS</th>';
    echo '<th>Nombre</th>';
    echo '<th>Dirección</th>';
    echo '<th style="text-align:center;">Tarifa</th>';
    echo '<th style="text-align:center;">Ruta/Código</th>';
    echo '<th>Tipo</th>';
    echo '<th>Anomalía</th>';
    echo '<th style="text-align:center;">Ciclo</th>';
    echo '<th>Agencia</th>';
    echo '<th>Comentarios</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($filas as $fila) {
        $id = (int)$fila['id_registro'];
        $rpu = isset($fila['Rpu']) ? trim($fila['Rpu']) : '';
        $comment = isset($rpu_ultimo_comentario[$rpu]) ? trim($rpu_ultimo_comentario[$rpu]) : '';
        $commentEscaped = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

        $lastObs = isset($rpu_ultima_observacion[$rpu]) ? $rpu_ultima_observacion[$rpu] : '';
        $lastObsEscaped = htmlspecialchars($lastObs, ENT_QUOTES, 'UTF-8');
        echo '<tr data-id="' . $id . '" data-last-obs="' . $lastObsEscaped . '">';
        echo '<td style="font-family: monospace;">' . htmlspecialchars($fila['Rpu'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($fila['Nis'] ?? '-') . '</td>';
        echo '<td style="font-weight: 500;">' . htmlspecialchars($fila['Nombre'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($fila['Direccion'] ?? '-') . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($fila['Tarifa'] ?? '-') . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($fila['Codigo'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($fila['Tipo'] ?? '-') . '</td>';
        echo '<td><span style="color:#d97706; font-weight:600;">' . htmlspecialchars($fila['Anomalia'] ?? '-') . '</span></td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($fila['Ciclo'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($fila['Agencia'] ?? '-') . '</td>';
        
        // Comentarios (con botones para ver historial y agregar comentario)
        $hasComment = ($comment !== '');
        $hasObservations = isset($rpu_cant_obs[$rpu]) && ($rpu_cant_obs[$rpu] > 0);
        
        $addCommentColor = $hasComment ? ($hasObservations ? '#ca8a04' : '#008f39') : '#94a3b8';
        $historyColor = $hasObservations ? '#2563eb' : '#94a3b8';

        echo '<td>';
        $latest_comm_id = isset($rpu_ultimo_comentario_id[$rpu]) ? $rpu_ultimo_comentario_id[$rpu] : 0;
        echo '  <div class="comment-cell-container" id="comment-cell-' . $id . '" data-original="' . $commentEscaped . '" data-latest-comment-id="' . $latest_comm_id . '">';
        echo '    <span class="comment-static-text' . ($comment === '' ? ' empty' : '') . '" id="comment-text-' . $id . '" style="max-width:250px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle;" title="' . $commentEscaped . '">';
        echo '      ' . ($comment === '' ? '(Sin comentario)' : $commentEscaped);
        echo '    </span>';
        echo '    <div class="comment-actions-wrapper" id="comment-actions-' . $id . '" style="display:inline-flex; gap:4px; align-items:center;">';
        echo '      <button id="btn-add-comment-' . $id . '" onclick="abrirAgregarComentario(\'' . $tabla . '\', ' . $id . ')" class="btn-comment-action btn-edit-comment" title="Agregar comentario" style="color: ' . $addCommentColor . ';">';
        echo '        <span class="material-symbols-rounded" style="font-size: 16px;">add_comment</span>';
        echo '      </button>';
        echo '      <button id="btn-history-comment-' . $id . '" onclick="abrirHistorialComentarios(\'' . $tabla . '\', ' . $id . ')" class="btn-comment-action btn-edit-comment" title="Ver historial de comentarios" style="color: ' . $historyColor . ';">';
        echo '        <span class="material-symbols-rounded" style="font-size: 16px;">history</span>';
        echo '      </button>';
        echo '    </div>';
        echo '  </div>';
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody></table></div>';
    
    if (count($filas) >= 500) {
        echo "<p style='font-size: 0.7rem; color: #64748b; margin: 10px; font-style: italic;'>* Mostrando los primeros 500 registros.</p>";
    }

} catch (PDOException $e) {
    echo "<div style='padding:20px; text-align:center; color:#dc3545;'>Error base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
