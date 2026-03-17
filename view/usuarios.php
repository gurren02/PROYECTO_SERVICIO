<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar la sesión de forma segura y poner el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// ==========================================
// 2. CONFIGURACIÓN DE LA CONEXIÓN UNIVERSAL
// ==========================================
require "../config/conexion.php"; // Llama a $pdo y soporta XAMPP/Docker

// ==========================================
// 3. ELIMINAR USUARIO
// ==========================================
$mensaje = '';
$tipo_mensaje = '';

$userlog_sesion = isset($_SESSION['userlog']) ? $_SESSION['userlog'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $id_eliminar = (int)$_POST['eliminar_id'];

    // Proteger: no eliminar la propia cuenta (Usando Prepared Statements de PDO)
    $stmt_check = $pdo->prepare("SELECT userlog FROM usuarios WHERE id = ? LIMIT 1");
    $stmt_check->execute([$id_eliminar]);
    $fila_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($fila_check && $fila_check['userlog'] === $userlog_sesion) {
        $mensaje = 'No puedes eliminar tu propia cuenta.';
        $tipo_mensaje = 'error';
    } else {
        try {
            $stmt_del = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt_del->execute([$id_eliminar]);
            
            $mensaje = 'Usuario eliminado correctamente.';
            $tipo_mensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = 'Error al eliminar: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// ==========================================
// 4. OBTENER TODOS LOS USUARIOS CON PDO
// ==========================================
$stmt_all = $pdo->query("SELECT id, nombre_completo, rpe, departamento, userlog, correo_recuperacion FROM usuarios ORDER BY id ASC");
// Guardamos todos los resultados en un arreglo
$usuarios = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/user_estilos.css">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <title>Gestión de Usuarios</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="usr-main">

    <div class="usr-page-header usr-page-header--row">
        <div>
            <h1 class="usr-page-title">Gestión de Usuarios</h1>
            <p class="usr-page-subtitle">Visualiza y administra todas las cuentas registradas en el sistema.</p>
        </div>

        <div class="usr-header-actions">
            <a href="perfil.php" class="usr-btn usr-btn--primary">
                <span class="material-symbols-rounded">manage_accounts</span>
                Mi perfil
            </a>
            <a href="registrar_nuevo.php" class="cr-btn cr-btn--primary">
                <span class="material-symbols-rounded">person_add</span> Registrar Nuevo
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="usr-alert usr-alert--<?php echo $tipo_mensaje; ?>">
            <span class="material-symbols-rounded">
                <?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?>
            </span>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="usr-card usr-table-card">

        <div class="usr-card__header">
            <span class="material-symbols-rounded">group</span>
            Usuarios registrados
        </div>

        <div class="usr-table-wrapper">
            <table class="usr-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre completo</th>
                    <th>RPE</th>
                    <th>Departamento</th>
                    <th>Usuario (login)</th>
                    <th>Correo recuperación</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($usuarios) > 0): ?>
                    <?php foreach ($usuarios as $fila): ?>
                        <tr class="<?php echo ($fila['userlog'] === $userlog_sesion) ? 'usr-table__row--current' : ''; ?>">
                            <td class="usr-table__id"><?php echo $fila['id']; ?></td>
                            <td class="usr-table__name">
                            <span class="usr-table__avatar">
                                <span class="material-symbols-rounded">person</span>
                            </span>
                                <?php echo htmlspecialchars($fila['nombre_completo'] ?? '—'); ?>
                                <?php if ($fila['userlog'] === $userlog_sesion): ?>
                                    <span class="usr-badge usr-badge--you">Tú</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($fila['rpe']) ? htmlspecialchars($fila['rpe']) : '<span class="usr-null">NULL</span>'; ?></td>
                            <td><?php echo !empty($fila['departamento']) ? htmlspecialchars($fila['departamento']) : '<span class="usr-null">NULL</span>'; ?></td>
                            <td>
                                <code class="usr-code"><?php echo htmlspecialchars($fila['userlog']); ?></code>
                            </td>
                            <td><?php echo !empty($fila['correo_recuperacion']) ? htmlspecialchars($fila['correo_recuperacion']) : '<span class="usr-null">NULL</span>'; ?></td>
                            <td>
                                <?php if ($fila['userlog'] !== $userlog_sesion): ?>
                                    <form method="POST" action=""
                                          onsubmit="return confirm('¿Seguro que deseas eliminar a <?php echo htmlspecialchars($fila['nombre_completo']); ?>?')">
                                        <input type="hidden" name="eliminar_id" value="<?php echo $fila['id']; ?>">
                                        <button type="submit" class="usr-btn usr-btn--danger usr-btn--sm">
                                            <span class="material-symbols-rounded">delete</span>
                                            Eliminar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="usr-table__protected">
                                    <span class="material-symbols-rounded">shield_person</span>
                                    Cuenta activa
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="usr-table__empty">
                            <span class="material-symbols-rounded">person_off</span>
                            No hay usuarios registrados.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</main>
</body>
</html>

