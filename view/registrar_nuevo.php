<?php
// 1. SIEMPRE EN LA LÍNEA 1: Iniciar sesión de forma segura y poner el candado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../src/seguridad.php";

// ==========================================
// 2. CONFIGURACIÓN DE LA CONEXIÓN (Directa)
// ==========================================
// Aquí mandamos llamar a $pdo
require "../config/conexion.php";

// ==========================================
// 3. PROCESAR REGISTRO
// ==========================================
$mensaje      = '';
$tipo_mensaje = '';
$form_data    = [
        'nombre_completo'     => '',
        'rpe'                 => '',
        'departamento'        => '',
        'userlog'             => '',
        'correo_recuperacion' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {

    // Recoger valores (Ya no usamos mysqli_real_escape_string porque PDO nos protege automáticamente)
    $nombre_completo     = trim($_POST['nombre_completo']);
    $rpe                 = trim($_POST['rpe']);
    $departamento        = trim($_POST['departamento']);
    $userlog             = trim($_POST['userlog']);
    $correo_recuperacion = trim($_POST['correo_recuperacion']);
    $contrasena          = trim($_POST['contrasena']);
    $confirmar           = trim($_POST['confirmar_contrasena']);

    // Repoblar el formulario si hay error
    $form_data = compact('nombre_completo', 'rpe', 'departamento', 'userlog', 'correo_recuperacion');

    // ---- Validaciones ----
    if (empty($nombre_completo) || empty($userlog) || empty($contrasena)) {
        $mensaje      = 'Los campos Nombre completo, Usuario y Contraseña son obligatorios.';
        $tipo_mensaje = 'error';

    } elseif ($contrasena !== $confirmar) {
        $mensaje      = 'Las contraseñas no coinciden. Verifica e intenta de nuevo.';
        $tipo_mensaje = 'error';

    } else {
        // Verificar que el userlog no exista (Adaptado a PDO)
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE userlog = ? LIMIT 1");
        $stmt_check->execute([$userlog]);
        
        if ($stmt_check->rowCount() > 0) {
            $mensaje      = "El nombre de usuario <strong>$userlog</strong> ya está en uso. Elige otro.";
            $tipo_mensaje = 'error';
        } else {

            // --- NUEVO: GENERAR TOKEN ALFANUMÉRICO DE 6 CARACTERES ---
            $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $token_recuperacion = substr(str_shuffle($caracteres), 0, 6);
            // ---------------------------------------------------------

            // Actualizado para insertar con PDO (Usando los signos de interrogación por seguridad)
            $sql_insert = "INSERT INTO usuarios
                        (nombre_completo, rpe, departamento, userlog, correo_recuperacion, contrasena, token_recuperacion)
                       VALUES
                        (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_insert = $pdo->prepare($sql_insert);

            try {
                // Ejecutamos la consulta pasando las variables de forma limpia
                $stmt_insert->execute([
                    $nombre_completo, 
                    $rpe, 
                    $departamento, 
                    $userlog, 
                    $correo_recuperacion, 
                    $contrasena, 
                    $token_recuperacion
                ]);

                $mensaje      = "Usuario <strong>$userlog</strong> registrado correctamente.";
                $tipo_mensaje = 'success';
                
                // Limpiar formulario tras éxito
                $form_data = [
                        'nombre_completo'     => '',
                        'rpe'                 => '',
                        'departamento'        => '',
                        'userlog'             => '',
                        'correo_recuperacion' => ''
                ];
            } catch (PDOException $e) {
                // Si algo falla en la base de datos, lo atrapamos aquí
                $mensaje      = 'Error al registrar: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
}
// Borramos la línea de mysqli_close() porque PDO cierra la conexión solito
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
    <title>Registrar Nuevo Usuario</title>
</head>
<body>
<header>
    <?php include "./menu.php"; ?>
</header>
<main class="reg-main">

    <div class="reg-page-header">
        <div class="reg-page-header__text">
            <h1 class="reg-page-title">Registrar Nuevo Usuario</h1>
            <p class="reg-page-subtitle">Completa el formulario para dar de alta una nueva cuenta en el sistema.</p>
        </div>
        <a href="usuarios.php" class="reg-btn reg-btn--ghost">
            <span class="material-symbols-rounded">arrow_back</span>
            Volver a usuarios
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="reg-alert reg-alert--<?php echo $tipo_mensaje; ?>">
            <span class="material-symbols-rounded reg-alert__icon">
                <?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?>
            </span>
            <span><?php echo $mensaje; ?></span>
        </div>
    <?php endif; ?>

    <div class="reg-layout">

        <div class="reg-side">
            <div class="reg-side__icon-wrap">
                <span class="material-symbols-rounded reg-side__icon">person_add</span>
            </div>
            <h2 class="reg-side__title">Nueva cuenta</h2>
            <p class="reg-side__desc">Los campos marcados con <span class="reg-required-star">*</span> son obligatorios.</p>

            <ul class="reg-side__checklist">
                <li>
                    <span class="material-symbols-rounded">info</span>
                    El <strong>usuario</strong> debe ser único en el sistema.
                </li>
                <li>
                    <span class="material-symbols-rounded">lock</span>
                    La <strong>contraseña</strong> debe coincidir en ambos campos.
                </li>
                <li>
                    <span class="material-symbols-rounded">mail</span>
                    El correo de recuperación es <strong>opcional</strong>.
                </li>
            </ul>
        </div>

        <div class="reg-card">
            <div class="reg-card__header">
                <span class="material-symbols-rounded">assignment_ind</span>
                Datos del nuevo usuario
            </div>

            <form method="POST" action="" class="reg-form" autocomplete="off">

                <div class="reg-form__group reg-form__group--full">
                    <label class="reg-form__label" for="nombre_completo">
                        <span class="material-symbols-rounded">badge</span>
                        Nombre completo <span class="reg-required-star">*</span>
                    </label>
                    <input type="text" id="nombre_completo" name="nombre_completo"
                           class="reg-form__control"
                           value="<?php echo htmlspecialchars($form_data['nombre_completo']); ?>"
                           placeholder="Ej. Juan Pérez López"
                           required>
                </div>

                <div class="reg-form__row">
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="rpe">
                            <span class="material-symbols-rounded">tag</span>
                            RPE
                        </label>
                        <input type="text" id="rpe" name="rpe"
                               class="reg-form__control"
                               value="<?php echo htmlspecialchars($form_data['rpe']); ?>"
                               placeholder="Ej. 12345">
                    </div>
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="departamento">
                            <span class="material-symbols-rounded">business</span>
                            Departamento
                        </label>
                        <input type="text" id="departamento" name="departamento"
                               class="reg-form__control"
                               value="<?php echo htmlspecialchars($form_data['departamento']); ?>"
                               placeholder="Ej. Distribución">
                    </div>
                </div>

                <div class="reg-form__row">
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="userlog">
                            <span class="material-symbols-rounded">alternate_email</span>
                            Usuario (login) <span class="reg-required-star">*</span>
                        </label>
                        <input type="text" id="userlog" name="userlog"
                               class="reg-form__control"
                               value="<?php echo htmlspecialchars($form_data['userlog']); ?>"
                               placeholder="Ej. jperez"
                               required autocomplete="off">
                    </div>
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="correo_recuperacion">
                            <span class="material-symbols-rounded">mail</span>
                            Correo de recuperación
                        </label>
                        <input type="email" id="correo_recuperacion" name="correo_recuperacion"
                               class="reg-form__control"
                               value="<?php echo htmlspecialchars($form_data['correo_recuperacion']); ?>"
                               placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="reg-form__divider"></div>
                <div class="reg-form__section-title">
                    <span class="material-symbols-rounded">key</span>
                    Contraseña de acceso
                </div>

                <div class="reg-form__row">
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="contrasena">
                            <span class="material-symbols-rounded">lock</span>
                            Contraseña <span class="reg-required-star">*</span>
                        </label>
                        <div class="reg-form__input-wrap">
                            <input type="password" id="contrasena" name="contrasena"
                                   class="reg-form__control"
                                   placeholder="••••••••"
                                   required autocomplete="new-password">
                            <button type="button" class="reg-form__eye" onclick="togglePass('contrasena', this)" tabindex="-1">
                                <span class="material-symbols-rounded">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="reg-form__group">
                        <label class="reg-form__label" for="confirmar_contrasena">
                            <span class="material-symbols-rounded">lock_check</span>
                            Confirmar contraseña <span class="reg-required-star">*</span>
                        </label>
                        <div class="reg-form__input-wrap">
                            <input type="password" id="confirmar_contrasena" name="confirmar_contrasena"
                                   class="reg-form__control"
                                   placeholder="••••••••"
                                   required autocomplete="new-password">
                            <button type="button" class="reg-form__eye" onclick="togglePass('confirmar_contrasena', this)" tabindex="-1">
                                <span class="material-symbols-rounded">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="reg-form__footer">
                    <a href="usuarios.php" class="reg-btn reg-btn--ghost">
                        <span class="material-symbols-rounded">close</span>
                        Cancelar
                    </a>
                    <button type="submit" name="registrar" class="reg-btn reg-btn--primary">
                        <span class="material-symbols-rounded">person_add</span>
                        Registrar usuario
                    </button>
                </div>

            </form>
        </div>
    </div>

</main>

<script>
    function togglePass(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const icon  = btn.querySelector('.material-symbols-rounded');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }
</script>

</body>
</html>

