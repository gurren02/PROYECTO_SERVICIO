<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar sesión de forma segura y poner el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// ==========================================
// 2. CONFIGURACIÓN DE LA CONEXIÓN UNIVERSAL
// ==========================================
require "../config/conexion.php"; // <--- LLAMAMOS A PDO

// ==========================================
// 3. OBTENER USUARIO DE SESIÓN
// ==========================================
// Usamos 'userlog' que es la variable que guardamos en login.php
$userlog_sesion = isset($_SESSION['userlog']) ? $_SESSION['userlog'] : '';

// ==========================================
// 4. PROCESAR FORMULARIO (UPDATE)
// ==========================================
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    
    // Ya no necesitamos mysqli_real_escape_string, PDO nos protege.
    $nombre_completo      = trim($_POST['nombre_completo']);
    $rpe                  = trim($_POST['rpe']);
    $departamento         = trim($_POST['departamento']);
    $correo_recuperacion  = trim($_POST['correo_recuperacion']);
    $nueva_contrasena     = trim($_POST['nueva_contrasena']);
    $confirmar_contrasena = trim($_POST['confirmar_contrasena']);

    // Validar contraseñas si se intenta cambiar
    if (!empty($nueva_contrasena)) {
        if ($nueva_contrasena !== $confirmar_contrasena) {
            $mensaje = 'Las contraseñas no coinciden.';
            $tipo_mensaje = 'error';
        }
    }

    if ($tipo_mensaje !== 'error') {
        try {
            // Si el usuario escribió una nueva contraseña, actualizamos todo
            if (!empty($nueva_contrasena)) {
                $sql_update = "UPDATE usuarios SET
                            nombre_completo     = ?,
                            rpe                 = ?,
                            departamento        = ?,
                            correo_recuperacion = ?,
                            contrasena          = ?
                           WHERE userlog = ?";
                
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    $nombre_completo, 
                    $rpe, 
                    $departamento, 
                    $correo_recuperacion, 
                    $nueva_contrasena, 
                    $userlog_sesion
                ]);
            } else {
                // Si la dejó en blanco, actualizamos todo MENOS la contraseña
                $sql_update = "UPDATE usuarios SET
                            nombre_completo     = ?,
                            rpe                 = ?,
                            departamento        = ?,
                            correo_recuperacion = ?
                           WHERE userlog = ?";
                           
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    $nombre_completo, 
                    $rpe, 
                    $departamento, 
                    $correo_recuperacion, 
                    $userlog_sesion
                ]);
            }

            $mensaje = 'Perfil actualizado correctamente.';
            $tipo_mensaje = 'success';

            // Actualizamos la sesión para que el nombre en el header cambie al instante
            $_SESSION['nombre_completo'] = $nombre_completo;

        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// ==========================================
// 5. CARGAR DATOS DEL USUARIO ACTUAL CON PDO
// ==========================================
$stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE userlog = ? LIMIT 1");
$stmt_user->execute([$userlog_sesion]);
$datos = $stmt_user->fetch(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="stylesheet" href="../assets/header.css">
    <link rel="stylesheet" href="../assets/user_estilos.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/webp" href="../assets/multimedia/logo_cf.webp">
    <title>Mi Perfil</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="usr-main">

    <div class="usr-page-header usr-page-header--row">
        <div>
            <h1 class="usr-page-title">Mi Perfil</h1>
            <p class="usr-page-subtitle">Administra tu información personal y credenciales de acceso.</p>
        </div>
        <a href="usuarios.php" class="usr-btn usr-btn--ghost">
            <span class="material-symbols-rounded">arrow_back</span>
            Volver a usuarios
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="usr-alert usr-alert--<?php echo $tipo_mensaje; ?>">
            <span class="material-symbols-rounded">
                <?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?>
            </span>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="usr-profile-grid">

        <div class="usr-card usr-profile-avatar-card">
            <div class="usr-avatar-circle">
                <span class="material-symbols-rounded">person</span>
            </div>
            <p class="usr-avatar-name"><?php echo htmlspecialchars($datos['nombre_completo'] ?? '—'); ?></p>
            <span class="usr-badge"><?php echo htmlspecialchars($datos['userlog'] ?? ''); ?></span>
            <?php if (!empty($datos['departamento'])): ?>
                <p class="usr-avatar-dept">
                    <span class="material-symbols-rounded">business</span>
                    <?php echo htmlspecialchars($datos['departamento']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="usr-card usr-profile-form-card">
            <div class="usr-card__header">
                <span class="material-symbols-rounded">edit_note</span>
                Editar información
            </div>

            <form method="POST" action="" class="usr-form">

                <div class="usr-form__section-title">Datos personales</div>

                <div class="usr-form__group">
                    <label class="usr-form__label" for="nombre_completo">
                        <span class="material-symbols-rounded">badge</span> Nombre completo
                    </label>
                    <input type="text" id="nombre_completo" name="nombre_completo" class="usr-form__control"
                           value="<?php echo htmlspecialchars($datos['nombre_completo'] ?? ''); ?>" required>
                </div>

                <div class="usr-form__row">
                    <div class="usr-form__group">
                        <label class="usr-form__label" for="rpe">
                            <span class="material-symbols-rounded">tag</span> RPE
                        </label>
                        <input type="text" id="rpe" name="rpe" class="usr-form__control"
                               value="<?php echo htmlspecialchars($datos['rpe'] ?? ''); ?>">
                    </div>
                    <div class="usr-form__group">
                        <label class="usr-form__label" for="departamento">
                            <span class="material-symbols-rounded">business</span> Departamento
                        </label>
                        <input type="text" id="departamento" name="departamento" class="usr-form__control"
                               value="<?php echo htmlspecialchars($datos['departamento'] ?? ''); ?>">
                    </div>
                </div>

                <div class="usr-form__group">
                    <label class="usr-form__label" for="correo_recuperacion">
                        <span class="material-symbols-rounded">mail</span> Correo de recuperación
                    </label>
                    <input type="email" id="correo_recuperacion" name="correo_recuperacion" class="usr-form__control"
                           value="<?php echo htmlspecialchars($datos['correo_recuperacion'] ?? ''); ?>">
                </div>

                <div class="usr-form__divider"></div>
                <div class="usr-form__section-title">Cambiar contraseña <span class="usr-form__section-hint">(dejar en blanco para no cambiar)</span></div>

                <div class="usr-form__row">
                    <div class="usr-form__group">
                        <label class="usr-form__label" for="nueva_contrasena">
                            <span class="material-symbols-rounded">lock</span> Nueva contraseña
                        </label>
                        <input type="password" id="nueva_contrasena" name="nueva_contrasena" class="usr-form__control"
                               placeholder="••••••••">
                    </div>
                    <div class="usr-form__group">
                        <label class="usr-form__label" for="confirmar_contrasena">
                            <span class="material-symbols-rounded">lock_check</span> Confirmar contraseña
                        </label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" class="usr-form__control"
                               placeholder="••••••••">
                    </div>
                </div>

                <div class="usr-form__footer">
                    <button type="submit" name="actualizar" class="usr-btn usr-btn--primary">
                        <span class="material-symbols-rounded">save</span>
                        Guardar cambios
                    </button>
                </div>

            </form>
        </div>

    </div>

</main>
</body>
</html>

