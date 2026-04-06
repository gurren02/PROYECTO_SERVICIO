<?php
// ==========================================
// CANDADO DE SEGURIDAD
// Si alguien entra aquí directamente sin darle clic al botón "Enviar", lo regresamos.
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['correo_recuperacion'])) {
    header("Location: ../view/recuperacion.php");
    exit();
}

// ==========================================
// CONEXIÓN UNIVERSAL (PDO — Compatible con XAMPP y Docker)
// ==========================================
require "../config/conexion.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../emails/PHPMailer/src/Exception.php';
require '../emails/PHPMailer/src/PHPMailer.php';
require '../emails/PHPMailer/src/SMTP.php';

// Capturamos el correo de forma segura (sin mysqli)
$correo = trim($_POST['correo_recuperacion']);

// 1. Buscamos si el correo existe en la base de datos (PDO con prepared statement)
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo_recuperacion = ? LIMIT 1");
$stmt->execute([$correo]);

if ($stmt->rowCount() > 0) {

    // 2. Generamos un NUEVO token aleatorio de 6 caracteres
    $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $nuevo_token = substr(str_shuffle($caracteres), 0, 6);

    // 3. Actualizamos la base de datos con PDO (sin SQL Injection)
    $stmt_update = $pdo->prepare("UPDATE usuarios SET token_recuperacion = ? WHERE correo_recuperacion = ?");
    $stmt_update->execute([$nuevo_token, $correo]);

    // 4. Configuración de PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Ajustes del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jesusmutul15@gmail.com';
        $mail->Password   = 'lodesnfsghhaotve';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Destinatario
        $mail->setFrom('no-reply@sistema-anomalias.com', 'Sistema de Anomalias CFE');
        $mail->addAddress($correo);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Clave de Recuperacion - Sistema CFE';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; border: 1px solid #d6e0dd; padding: 20px; max-width: 600px; margin: 0 auto; border-radius: 8px;'>
                <h2 style='color: #1a7a64; border-bottom: 2px solid #e8f5f1; padding-bottom: 10px;'>Solicitud de recuperación</h2>
                <p style='color: #1e2b27; font-size: 16px;'>Has solicitado tu clave para recuperar el acceso a tu cuenta.</p>
                <div style='background: #e8f5f1; border: 1px dashed #1a7a64; border-radius: 8px; padding: 20px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 8px; color: #145e49; margin: 20px 0;'>
                    $nuevo_token
                </div>
                <p style='color: #6b7c78; font-size: 14px;'>Copia este código de 6 caracteres y pégalo en la pantalla de recuperación.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin-top: 30px;' />
                <p style='font-size: 12px; color: #999; text-align: center;'>Si no solicitaste este cambio, puedes ignorar este mensaje y tu contraseña seguirá siendo la misma.</p>
            </div>";

        $mail->send();

        // Redirigir a la pantalla para ingresar el token
        echo "<script>
                alert('El código de recuperación ha sido enviado a tu correo.');
                window.location.href = '../view/ingresar_token.php';
              </script>";
        exit();

    } catch (Exception $e) {
        echo "<script>
                alert('Error al enviar el correo: {$mail->ErrorInfo}');
                window.history.back();
              </script>";
        exit();
    }

} else {
    // Si el correo no está en la base de datos
    echo "<script>
            alert('El correo proporcionado no está registrado en el sistema.');
            window.history.back();
          </script>";
    exit();
}
// PDO cierra la conexión automáticamente — no se necesita cerrar manualmente.
?>
