<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";
require "../config/conexion.php";

$tabla    = isset($_GET['tabla'])    ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla']) : '';
$pattern  = isset($_GET['pattern'])  ? trim($_GET['pattern']) : '';
$concepto = isset($_GET['concepto']) ? trim($_GET['concepto']) : 'Detalle';

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

    $where = "WHERE 1=1";
    $params = [];

    if ($pattern !== '') {
        $where .= " AND Anomalia LIKE ?";
        $params[] = '%' . $pattern . '%';
    }

    $query = "SELECT Rpu, Nis, Nombre, Direccion, Tarifa, Codigo, Tipo, Anomalia, Ciclo, Agencia FROM `$tabla` $where LIMIT 500";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "<div style='padding:20px; text-align:center; color:#6c757d;'>No se encontraron registros detallados para los filtros aplicados.</div>";
        exit;
    }

    echo '<div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">';
    echo '  <button onclick="exportarDetalleCSV()" class="ea-btn" style="background-color: #074776; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 0.82rem; padding: 8px 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s;" onmouseover="this.style.backgroundColor=\'#05385d\'" onmouseout="this.style.backgroundColor=\'#074776\'">';
    echo '    <span class="material-symbols-rounded" style="font-size: 16px; color: white;">download</span> Descargar CSV';
    echo '  </button>';
    echo '</div>';
    echo '<div class="ea-table-wrapper" style="max-height: 60vh; overflow: auto; border: 1px solid var(--ea-border, #ced4da); border-radius: 8px;">';
    echo '<table class="ea-table ea-table--detailed" style="font-size: 0.75rem; border-collapse: collapse; width: 100%;">';
    echo '<thead><tr class="ea-table__head-cols" style="background:#f1f5f9; position: sticky; top: 0; z-index: 1;">';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">RPU</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">NIS</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">Nombre</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">Dirección</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:center;">Tarifa</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:center;">Ruta/Código</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">Tipo</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">Anomalía</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:center;">Ciclo</th>';
    echo '<th style="padding: 8px 12px; border-bottom: 1px solid #cbd5e1; text-align:left;">Agencia</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($filas as $fila) {
        echo '<tr>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-family: monospace;">' . htmlspecialchars($fila['Rpu'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($fila['Nis'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 500;">' . htmlspecialchars($fila['Nombre'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($fila['Direccion'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align:center;">' . htmlspecialchars($fila['Tarifa'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align:center;">' . htmlspecialchars($fila['Codigo'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($fila['Tipo'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;"><span style="color:#d97706; font-weight:600;">' . htmlspecialchars($fila['Anomalia'] ?? '-') . '</span></td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align:center;">' . htmlspecialchars($fila['Ciclo'] ?? '-') . '</td>';
        echo '<td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($fila['Agencia'] ?? '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    
    if (count($filas) >= 500) {
        echo "<p style='font-size: 0.7rem; color: #64748b; margin-top: 10px; font-style: italic;'>* Mostrando los primeros 500 registros.</p>";
    }

} catch (PDOException $e) {
    echo "<div style='padding:20px; text-align:center; color:#dc3545;'>Error base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
