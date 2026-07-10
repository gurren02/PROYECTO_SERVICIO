<?php
/**
 * DIAGNÓSTICO DE RENDIMIENTO — Solo usar en desarrollo, eliminar en producción.
 * Acceder en: http://localhost/PROYECTO_SERVICIO/diagnostico.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$tiempos = [];
$errores = [];

function t($label, &$arr) {
    $arr[$label] = microtime(true);
}

$ini = microtime(true);
t('inicio', $tiempos);

// ──────────────────────────────────────────────────────────────────────────────
// 1. CONEXIÓN
// ──────────────────────────────────────────────────────────────────────────────
$dbname = 'anomalias';
$user   = 'root';
$pass   = '';
$pdo    = null;
$host_usado = '';

try {
    $host = 'db';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4;connect_timeout=1", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $host_usado = 'Docker (db)';
} catch (PDOException $e) {
    try {
        $host = 'localhost';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $host_usado = 'XAMPP (localhost)';
    } catch (PDOException $e2) {
        $errores[] = "❌ No se pudo conectar: " . $e2->getMessage();
    }
}
t('conexion', $tiempos);

// ──────────────────────────────────────────────────────────────────────────────
// 2. DATOS DEL SERVIDOR
// ──────────────────────────────────────────────────────────────────────────────
$info_servidor = [];
if ($pdo) {
    try {
        $info_servidor['Versión MySQL']   = $pdo->query("SELECT VERSION()")->fetchColumn();
        $info_servidor['Motor']           = $pdo->query("SHOW VARIABLES LIKE 'storage_engine'")->fetchColumn(1) ?? 
                                             $pdo->query("SHOW VARIABLES LIKE 'default_storage_engine'")->fetchColumn(1);
        $info_servidor['max_allowed_packet'] = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetchColumn(1);
        $info_servidor['wait_timeout']    = $pdo->query("SHOW VARIABLES LIKE 'wait_timeout'")->fetchColumn(1);
        $info_servidor['innodb_buffer_pool_size'] = $pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")->fetchColumn(1);
    } catch(PDOException $e) { $errores[] = "Info servidor: " . $e->getMessage(); }
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. LISTADO DE TABLAS Y SUS TAMAÑOS
// ──────────────────────────────────────────────────────────────────────────────
$tablas_info = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT table_name, table_rows, 
                   ROUND((data_length + index_length) / 1024, 1) AS size_kb
            FROM information_schema.tables 
            WHERE table_schema = '$dbname'
            ORDER BY (data_length + index_length) DESC
        ");
        $tablas_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) { $errores[] = "Tablas: " . $e->getMessage(); }
}
t('tablas_listadas', $tiempos);

// ──────────────────────────────────────────────────────────────────────────────
// 4. BENCHMARK DE LAS QUERIES MÁS COSTOSAS (las que se usan en nivel zona)
// ──────────────────────────────────────────────────────────────────────────────
$benchmarks = [];
$anomalias = ['cancelaciones','estimaciones','consumos_cero','servicios_sin_medicion',
              'correcciones_de_lecturas','anomalias_pendientes','sin_facturar','cargas_directas'];

// Determinar sufijo real buscando tablas que existan
$sufijo_encontrado = null;
$tabla_ejemplo = null;
if ($pdo && !empty($tablas_info)) {
    foreach ($tablas_info as $t_info) {
        $tname = $t_info['table_name'];
        foreach ($anomalias as $anom) {
            if (strpos($tname, $anom) === 0 && strlen($tname) === strlen($anom) + 6) {
                $sufijo_encontrado = substr($tname, strlen($anom));
                $tabla_ejemplo = $tname;
                break 2;
            }
        }
    }
}

if ($pdo && $tabla_ejemplo) {
    // Benchmark A: COUNT simple
    $t0 = microtime(true);
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM `$tabla_ejemplo`")->fetchColumn();
        $benchmarks[] = ['query' => "COUNT(*) en `$tabla_ejemplo`", 'result' => "$cnt filas", 'ms' => round((microtime(true)-$t0)*1000)];
    } catch(PDOException $e) { $benchmarks[] = ['query' => "COUNT(*) en `$tabla_ejemplo`", 'result' => '❌ '.$e->getMessage(), 'ms' => 0]; }

    // Benchmark B: DISTINCT Zona con CAST
    $t0 = microtime(true);
    try {
        $zonas = $pdo->query("SELECT DISTINCT CAST(TRIM(`Zona`) AS UNSIGNED) FROM `$tabla_ejemplo` WHERE `Zona` IS NOT NULL AND `Zona` != '' LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
        $benchmarks[] = ['query' => "DISTINCT Zona(CAST) en `$tabla_ejemplo`", 'result' => count($zonas)." zonas", 'ms' => round((microtime(true)-$t0)*1000)];
    } catch(PDOException $e) { $benchmarks[] = ['query' => "DISTINCT Zona(CAST)", 'result' => '❌ '.$e->getMessage(), 'ms' => 0]; }

    // Benchmark C: GROUP BY Zona+Agencia (la query principal)
    $t0 = microtime(true);
    try {
        $r = $pdo->query("SELECT TRIM(`Zona`) as z, UPPER(TRIM(`Agencia`)) as a, COUNT(*) as t FROM `$tabla_ejemplo` WHERE 1=1 GROUP BY TRIM(`Zona`), UPPER(TRIM(`Agencia`))")->fetchAll(PDO::FETCH_ASSOC);
        $benchmarks[] = ['query' => "GROUP BY Zona+Agencia en `$tabla_ejemplo`", 'result' => count($r)." grupos", 'ms' => round((microtime(true)-$t0)*1000)];
    } catch(PDOException $e) { $benchmarks[] = ['query' => "GROUP BY Zona+Agencia", 'result' => '❌ '.$e->getMessage(), 'ms' => 0]; }

    // Benchmark D: ¿Hay una segunda tabla para el JOIN de reincidentes?
    $tabla_comp = null;
    foreach ($tablas_info as $t_info) {
        $tname = $t_info['table_name'];
        $anom_base = substr($tabla_ejemplo, 0, -6);
        if (strpos($tname, $anom_base) === 0 && $tname !== $tabla_ejemplo && strlen($tname) === strlen($anom_base) + 6) {
            $tabla_comp = $tname;
            break;
        }
    }

    if ($tabla_comp) {
        $t0 = microtime(true);
        try {
            $r = $pdo->query("SELECT COUNT(DISTINCT TRIM(t1.`Rpu`)) FROM `$tabla_ejemplo` t1 INNER JOIN `$tabla_comp` t2 ON TRIM(t1.`Rpu`) = TRIM(t2.`Rpu`) WHERE t1.`Rpu` IS NOT NULL AND TRIM(t1.`Rpu`) != '' LIMIT 1")->fetchColumn();
            $benchmarks[] = ['query' => "INNER JOIN Rpu: `$tabla_ejemplo` ↔ `$tabla_comp`", 'result' => "$r reincidentes", 'ms' => round((microtime(true)-$t0)*1000)];
        } catch(PDOException $e) { $benchmarks[] = ['query' => "INNER JOIN Rpu", 'result' => '❌ '.$e->getMessage(), 'ms' => 0]; }
    } else {
        $benchmarks[] = ['query' => "JOIN de reincidentes", 'result' => '⚠️ No hay segunda tabla con sufijo diferente para comparar', 'ms' => 0];
    }
}
t('benchmarks', $tiempos);

$total_ms = round((microtime(true) - $ini) * 1000);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="assets/multimedia/icon_bluebg.png">
<title>Diagnóstico de Rendimiento</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
    h1 { color: #0f172a; }
    h2 { color: #334155; margin-top: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
    .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th { background: #334155; color: white; padding: 8px 12px; text-align: left; }
    td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
    tr:hover td { background: #f8fafc; }
    .ok { color: #16a34a; font-weight: 700; }
    .warn { color: #d97706; font-weight: 700; }
    .err { color: #dc2626; font-weight: 700; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .badge-blue { background: #dbeafe; color: #1d4ed8; }
    .badge-green { background: #dcfce7; color: #15803d; }
    .badge-red { background: #fee2e2; color: #dc2626; }
    .badge-yellow { background: #fef9c3; color: #854d0e; }
    .ms { font-weight: 700; }
    .total-banner { background: #0f172a; color: white; border-radius: 10px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
</style>
</head>
<body>
<div class="total-banner">
    <div><strong>Diagnóstico de Rendimiento — PROYECTO_SERVICIO</strong><br>
    <small>Conectado a: <strong><?php echo htmlspecialchars($host_usado ?: 'Sin conexión'); ?></strong></small></div>
    <div style="font-size: 1.4rem; font-weight: 800;"><?php echo $total_ms; ?> ms total</div>
</div>

<?php if (!empty($errores)): ?>
<div class="card" style="border-left: 4px solid #dc2626;">
    <h2 style="color:#dc2626; margin-top:0">❌ Errores encontrados</h2>
    <?php foreach($errores as $e): ?><p class="err"><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0">🖥️ Información del servidor de BD</h2>
    <table>
        <tr><th>Variable</th><th>Valor</th></tr>
        <tr><td>Host usado</td><td><span class="badge badge-blue"><?php echo htmlspecialchars($host_usado); ?></span></td></tr>
        <?php foreach($info_servidor as $k => $v): ?>
        <tr><td><?php echo htmlspecialchars($k); ?></td><td><?php echo htmlspecialchars((string)$v); ?></td></tr>
        <?php endforeach; ?>
        <tr><td>PHP version</td><td><?php echo phpversion(); ?></td></tr>
        <tr><td>max_execution_time ini</td><td><?php echo ini_get('max_execution_time'); ?>s</td></tr>
        <tr><td>memory_limit ini</td><td><?php echo ini_get('memory_limit'); ?></td></tr>
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0">📋 Tablas en la BD "<?php echo $dbname; ?>"</h2>
    <?php if (empty($tablas_info)): ?>
        <p class="warn">⚠️ No se encontraron tablas. La BD de XAMPP podría estar vacía o no tener las tablas de anomalías.</p>
    <?php else: ?>
    <p>Sufijo detectado: <strong><?php echo $sufijo_encontrado ?? 'ninguno'; ?></strong> 
    (tabla ejemplo: <code><?php echo $tabla_ejemplo ?? 'N/A'; ?></code>)</p>
    <table>
        <tr><th>Tabla</th><th>Filas (aprox.)</th><th>Tamaño KB</th><th>Estado</th></tr>
        <?php foreach($tablas_info as $t_info):
            $filas = (int)($t_info['table_rows'] ?? 0);
            $kb = (float)($t_info['size_kb'] ?? 0);
            if ($filas > 50000) $badge = '<span class="badge badge-red">MUY GRANDE</span>';
            elseif ($filas > 10000) $badge = '<span class="badge badge-yellow">GRANDE</span>';
            else $badge = '<span class="badge badge-green">OK</span>';
        ?>
        <tr>
            <td><code><?php echo htmlspecialchars($t_info['table_name']); ?></code></td>
            <td><?php echo number_format($filas); ?></td>
            <td><?php echo $kb; ?></td>
            <td><?php echo $badge; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="margin-top:0">⚡ Benchmark de queries críticas</h2>
    <?php if (empty($benchmarks)): ?>
        <p class="warn">⚠️ No se encontraron tablas de anomalías para hacer benchmark. ¿Está vacía la BD de XAMPP?</p>
    <?php else: ?>
    <table>
        <tr><th>Query</th><th>Resultado</th><th>Tiempo</th><th>Diagnóstico</th></tr>
        <?php foreach($benchmarks as $b):
            $ms = (int)$b['ms'];
            if ($ms > 5000) $diag = '<span class="badge badge-red">🔴 MUY LENTO — causa el cuelgue</span>';
            elseif ($ms > 2000) $diag = '<span class="badge badge-yellow">🟡 LENTO</span>';
            elseif ($ms > 500) $diag = '<span class="badge badge-yellow">🟡 Aceptable</span>';
            else $diag = '<span class="badge badge-green">🟢 Rápido</span>';
        ?>
        <tr>
            <td><code><?php echo htmlspecialchars($b['query']); ?></code></td>
            <td><?php echo htmlspecialchars($b['result']); ?></td>
            <td class="ms <?php echo $ms > 2000 ? 'err' : ($ms > 500 ? 'warn' : 'ok'); ?>"><?php echo $ms; ?> ms</td>
            <td><?php echo $diag; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="margin-top:0">⏱️ Tiempos de ejecución internos</h2>
    <table>
        <tr><th>Etapa</th><th>ms desde inicio</th></tr>
        <?php $prev = $ini; foreach($tiempos as $label => $t): ?>
        <tr><td><?php echo $label; ?></td><td><?php echo round(($t - $ini)*1000); ?> ms</td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card" style="background:#fef9c3; border: 1px solid #fde047;">
    <strong>☑️ Próximo paso:</strong> Una vez identificado el problema, elimina este archivo 
    (<code>diagnostico.php</code>) antes de pasar a producción.
</div>
</body>
</html>
