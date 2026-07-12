<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar la sesión de forma segura y poner el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin', 'oficinista'])) {
    header("Location: index.php");
    exit();
}

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

// 3. ELIMINAR USUARIO (Solo Administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    if ($_SESSION['rol'] !== 'admin') {
        $mensaje = 'Operacion no autorizada: Se requieren privilegios de Administrador para eliminar usuarios del sistema.';
        $tipo_mensaje = 'error';
    } else {
        $id_eliminar = (int)$_POST['eliminar_id'];

        // Obtener datos del usuario antes de proceder para generar aviso personalizado
        $stmt_get = $pdo->prepare("SELECT nombre_completo, userlog FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_get->execute([$id_eliminar]);
        $fila_get = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($fila_get) {
            $nombre_eliminado = $fila_get['nombre_completo'];
            $userlog_eliminado = $fila_get['userlog'];

            // Proteger: no eliminar la propia cuenta
            if ($userlog_eliminado === $userlog_sesion) {
                $mensaje = 'Operacion denegada: No es posible eliminar su propia cuenta activa del sistema.';
                $tipo_mensaje = 'error';
            } else {
                try {
                    $stmt_del = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt_del->execute([$id_eliminar]);
                    
                    $mensaje = "El usuario " . htmlspecialchars($nombre_eliminado) . " (" . htmlspecialchars($userlog_eliminado) . ") fue eliminado exitosamente del sistema.";
                    $tipo_mensaje = 'success';
                } catch (PDOException $e) {
                    $mensaje = 'Error durante el proceso: No se pudo eliminar el usuario de la base de datos.';
                    $tipo_mensaje = 'error';
                }
            }
        } else {
            $mensaje = 'El usuario solicitado para eliminar no se encuentra registrado o ya fue removido.';
            $tipo_mensaje = 'error';
        }
    }
}

// ==========================================
// 3b. EDITAR USUARIO (Solo Administradores)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
    if ($_SESSION['rol'] !== 'admin') {
        $mensaje = 'No tienes permisos para modificar usuarios.';
        $tipo_mensaje = 'error';
    } else {
        $id_editar = (int)$_POST['usuario_id'];
        $nombre_completo     = trim($_POST['nombre_completo']);
        $rpe                 = trim($_POST['rpe']);
        $departamento        = trim($_POST['departamento']);
        $userlog             = trim($_POST['userlog']);
        $correo_recuperacion = trim($_POST['correo_recuperacion']);
        $rol                 = trim($_POST['rol']);
        $zona                = ($rol === 'admin') ? null : (isset($_POST['zona']) && trim($_POST['zona']) !== '' ? trim($_POST['zona']) : null);
        $contrasena          = trim($_POST['contrasena']);

        if (empty($nombre_completo) || empty($userlog)) {
            $mensaje = 'Los campos Nombre completo y Usuario son obligatorios.';
            $tipo_mensaje = 'error';
        } elseif ($rol !== 'admin' && empty($zona)) {
            $mensaje = 'La Zona asignada es obligatoria para usuarios con roles distintos a Administrador.';
            $tipo_mensaje = 'error';
        } else {
            // Verificar que el userlog no esté duplicado
            $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE userlog = ? AND id != ? LIMIT 1");
            $stmt_check->execute([$userlog, $id_editar]);
            
            if ($stmt_check->rowCount() > 0) {
                $mensaje = "El nombre de usuario <strong>$userlog</strong> ya está en uso por otro usuario.";
                $tipo_mensaje = 'error';
            } else {
                try {
                    if (!empty($contrasena)) {
                        $stmt_upd = $pdo->prepare("UPDATE usuarios SET nombre_completo = ?, rpe = ?, departamento = ?, userlog = ?, correo_recuperacion = ?, contrasena = ?, rol = ?, zona = ? WHERE id = ?");
                        $stmt_upd->execute([$nombre_completo, $rpe, $departamento, $userlog, $correo_recuperacion, $contrasena, $rol, $zona, $id_editar]);
                    } else {
                        $stmt_upd = $pdo->prepare("UPDATE usuarios SET nombre_completo = ?, rpe = ?, departamento = ?, userlog = ?, correo_recuperacion = ?, rol = ?, zona = ? WHERE id = ?");
                        $stmt_upd->execute([$nombre_completo, $rpe, $departamento, $userlog, $correo_recuperacion, $rol, $zona, $id_editar]);
                    }
                    
                    // Si el usuario editado es el mismo que tiene la sesión activa y se le cambió el rol o login, actualizar la sesión
                    if ($id_editar === (int)$_SESSION['id']) {
                        $_SESSION['rol'] = $rol;
                        $_SESSION['userlog'] = $userlog;
                        $_SESSION['nombre_completo'] = $nombre_completo;
                        if ($rol === 'admin') {
                            unset($_SESSION['zona']);
                        } else {
                            $_SESSION['zona'] = $zona;
                        }
                    }
                    
                    $mensaje = 'Usuario actualizado correctamente.';
                    $tipo_mensaje = 'success';
                } catch (PDOException $e) {
                    $mensaje = 'Error al actualizar usuario: ' . $e->getMessage();
                    $tipo_mensaje = 'error';
                }
            }
        }
    }
}

// ==========================================
// 4. OBTENER TODOS LOS USUARIOS CON PDO
// ==========================================
$stmt_all = $pdo->query("SELECT id, nombre_completo, rpe, departamento, userlog, correo_recuperacion, rol, zona FROM usuarios ORDER BY id ASC");
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
    <link rel="icon" type="image/webp" href="../assets/multimedia/logo_cf.webp">
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
                    <th>Rol</th>
                    <th>Zona</th>
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
                                <?php
                                $badge_style = 'background: #cbd5e1; color: #475569;'; // Default
                                if ($fila['rol'] === 'admin') {
                                    $badge_style = 'background: #fee2e2; color: #dc2626;';
                                } elseif ($fila['rol'] === 'supervisor') {
                                    $badge_style = 'background: #f3e8ff; color: #7e22ce;';
                                } elseif ($fila['rol'] === 'oficinista') {
                                    $badge_style = 'background: #fef3c7; color: #d97706;';
                                } elseif ($fila['rol'] === 'usuario') {
                                    $badge_style = 'background: #dbeafe; color: #1d4ed8;';
                                }
                                ?>
                                <span class="usr-badge" style="padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; <?php echo $badge_style; ?>">
                                    <?php echo htmlspecialchars($fila['rol'] ?? 'usuario'); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($fila['zona']) ? '<code class="usr-code" style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 600;">' . htmlspecialchars($fila['zona']) . '</code>' : '<span class="usr-null">—</span>'; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                                        <button type="button" class="usr-btn usr-btn--primary usr-btn--sm btn-editar-usuario" 
                                                data-id="<?php echo $fila['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($fila['nombre_completo'] ?? ''); ?>"
                                                data-rpe="<?php echo htmlspecialchars($fila['rpe'] ?? ''); ?>"
                                                data-departamento="<?php echo htmlspecialchars($fila['departamento'] ?? ''); ?>"
                                                data-userlog="<?php echo htmlspecialchars($fila['userlog'] ?? ''); ?>"
                                                data-correo="<?php echo htmlspecialchars($fila['correo_recuperacion'] ?? ''); ?>"
                                                data-rol="<?php echo htmlspecialchars($fila['rol'] ?? 'usuario'); ?>"
                                                data-zona="<?php echo htmlspecialchars($fila['zona'] ?? ''); ?>">
                                            <span class="material-symbols-rounded" style="font-size: 16px;">edit</span>
                                            Editar
                                        </button>
                                        <?php if ($fila['userlog'] !== $userlog_sesion): ?>
                                            <form method="POST" action="" style="margin: 0;"
                                                  onsubmit="return confirm('¿Seguro que deseas eliminar a <?php echo htmlspecialchars($fila['nombre_completo']); ?>?')">
                                                <input type="hidden" name="eliminar_id" value="<?php echo $fila['id']; ?>">
                                                <button type="submit" class="usr-btn usr-btn--danger usr-btn--sm">
                                                    <span class="material-symbols-rounded" style="font-size: 16px;">delete</span>
                                                    Eliminar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="usr-table__protected" style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem;">
                                                <span class="material-symbols-rounded" style="font-size: 16px;">shield_person</span>
                                                Activa
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="usr-table__protected" style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px;">
                                            <span class="material-symbols-rounded" style="font-size: 16px;">visibility</span>
                                            Solo Lectura
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="usr-table__empty">
                            <span class="material-symbols-rounded">person_off</span>
                            No hay usuarios registrados.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal para Editar Usuario (Solo para Administradores) -->
    <?php if ($_SESSION['rol'] === 'admin'): ?>
    <div id="editar-usuario-modal" class="usr-modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div class="usr-modal-content" style="background-color: #fff; margin: auto; padding: 24px; border: 1px solid #cbd5e1; width: 90%; max-width: 500px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                <h3 style="margin: 0; font-size: 1.2rem; color: #074776; display: flex; align-items: center; gap: 8px; font-weight: 700;">
                    <span class="material-symbols-rounded" style="color: #074776;">manage_accounts</span>
                    Editar Datos de Usuario
                </h3>
                <span class="close-modal" style="font-size: 24px; font-weight: bold; cursor: pointer; color: #94a3b8;" onclick="cerrarEditarModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="editar_usuario" value="1">
                <input type="hidden" id="edit-id" name="usuario_id">
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Nombre completo *</label>
                    <input type="text" id="edit-nombre" name="nombre_completo" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">RPE</label>
                        <input type="text" id="edit-rpe" name="rpe" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Departamento</label>
                        <input type="text" id="edit-departamento" name="departamento" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Usuario (login) *</label>
                        <input type="text" id="edit-userlog" name="userlog" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Nueva Contraseña</label>
                        <input type="password" name="contrasena" placeholder="Dejar en blanco" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.85rem; font-family: inherit;">
                    </div>
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Correo recuperación</label>
                    <input type="email" id="edit-correo" name="correo_recuperacion" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Rol *</label>
                        <select id="edit-rol" name="rol" required onchange="toggleEditZonaField()" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; height: 38px; box-sizing: border-box; background: white; font-size: 0.9rem; font-family: inherit;">
                            <option value="usuario">Usuario</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="oficinista">Oficinista</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div id="edit-group-zona">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569;">Zona <span id="edit-zona-star" style="color: red;">*</span></label>
                        <input type="text" id="edit-zona" name="zona" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit;">
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                    <button type="button" class="usr-btn usr-btn--secondary" onclick="cerrarEditarModal()">Cancelar</button>
                    <button type="submit" class="usr-btn usr-btn--primary" style="background-color: #074776;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleEditZonaField() {
            const rol = document.getElementById('edit-rol').value;
            const groupZona = document.getElementById('edit-group-zona');
            const inputZona = document.getElementById('edit-zona');
            const star = document.getElementById('edit-zona-star');
            if (rol === 'admin') {
                inputZona.value = '';
                inputZona.disabled = true;
                inputZona.required = false;
                star.style.display = 'none';
                groupZona.style.opacity = '0.5';
            } else {
                inputZona.disabled = false;
                inputZona.required = true;
                star.style.display = 'inline';
                groupZona.style.opacity = '1';
            }
        }

        function abrirEditarModal(datos) {
            document.getElementById('edit-id').value = datos.id;
            document.getElementById('edit-nombre').value = datos.nombre;
            document.getElementById('edit-rpe').value = datos.rpe;
            document.getElementById('edit-departamento').value = datos.departamento;
            document.getElementById('edit-userlog').value = datos.userlog;
            document.getElementById('edit-correo').value = datos.correo;
            document.getElementById('edit-rol').value = datos.rol;
            document.getElementById('edit-zona').value = datos.zona;
            
            toggleEditZonaField();
            
            document.getElementById('editar-usuario-modal').style.display = 'flex';
        }

        function cerrarEditarModal() {
            document.getElementById('editar-usuario-modal').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
                btn.addEventListener('click', () => {
                    abrirEditarModal({
                        id: btn.dataset.id,
                        nombre: btn.dataset.nombre,
                        rpe: btn.dataset.rpe,
                        departamento: btn.dataset.departamento,
                        userlog: btn.dataset.userlog,
                        correo: btn.dataset.correo,
                        rol: btn.dataset.rol,
                        zona: btn.dataset.zona
                    });
                });
            });
        });
    </script>
    <?php endif; ?>
    </div>

</main>
</body>
</html>

