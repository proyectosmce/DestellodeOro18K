<?php
// api/forgot_password.php
header('Content-Type: application/json');
require_once '../config/db.php';
@include '../config/env.php'; // Opcional: credenciales locales (gitignored)

// Cargar PHPMailer
$phpmailer_path = '../libs/PHPMailer/PHPMailer.php';
$smtp_path = '../libs/PHPMailer/SMTP.php';
$exception_path = '../libs/PHPMailer/Exception.php';

if (!file_exists($phpmailer_path) || !file_exists($smtp_path) || !file_exists($exception_path)) {
    echo json_encode(['success' => false, 'message' => 'Error: No se han subido los archivos de PHPMailer a la carpeta libs/']);
    exit;
}

require_once $phpmailer_path;
require_once $smtp_path;
require_once $exception_path;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->role) || !isset($data->email)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$role = $data->role;
$email = $data->email;

try {
    // 1. Verificar si el usuario existe con ese rol y correo
    $stmt = $conn->prepare("SELECT id, username, name FROM users WHERE role = :role AND email = :email LIMIT 1");
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        // Por seguridad, aveces es mejor decir que se enviÃ³ el correo igual si existe, 
        // pero el usuario pidiÃ³ "confirmar el correo registrado", asÃ­ que daremos error si no coincide.
        echo json_encode(['success' => false, 'message' => 'El correo no coincide con el rol seleccionado.']);
        exit;
    }
    
    // 2. Generar token
    $token = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // 3. Guardar token en la base de datos
    $updateStmt = $conn->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
    $updateStmt->bindParam(':token', $token);
    $updateStmt->bindParam(':expiry', $expiry);
    $updateStmt->bindParam(':id', $user['id']);
    $updateStmt->execute();
    
    // 4. Enviar correo con PHPMailer
    $mail = new PHPMailer(true);

    try {
        // ConfiguraciÃ³n del servidor SMTP - EL USUARIO DEBE COMPLETAR ESTO
        $mail->isSMTP();
$mail->Host       = getenv('SMTP_HOST') ?: ($ENV_SMTP_HOST ?? 'smtp.gmail.com'); // Servidor SMTP
$mail->SMTPAuth   = true;
$mail->Username   = getenv('SMTP_USER') ?: ($ENV_SMTP_USER ?? '');
$mail->Password   = getenv('SMTP_PASS') ?: ($ENV_SMTP_PASS ?? ''); // ContraseÃ±a de aplicaciÃ³n
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = getenv('SMTP_PORT') ?: ($ENV_SMTP_PORT ?? 587);
$mail->CharSet    = 'UTF-8';

        // Emisor y receptor
        // Usa el mismo correo autenticado para que Gmail no lo reemplace como "me"
        $mail->setFrom($mail->Username, 'Destello de Oro 18K');
        // Responder a un buzÃ³n de la marca (opcional)
        $mail->addReplyTo('no-reply@destellodeoro.com', 'Destello de Oro 18K');
        $mail->addAddress($email, $user['name']);

        // Contenido del correo
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // Ajustar la ruta si el proyecto estÃ¡ en una subcarpeta
        $base_url = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF'], 2);
        $reset_link = $base_url . "/reset_password.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Restablecer Contraseña - Destello de Oro 18K';
        $mail->Body    = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                :root { color-scheme: light; }
                body { margin:0; padding:0; background:#f7f5f0; font-family: 'Segoe UI', Arial, sans-serif; }
                .wrapper { width:100%; background:#f7f5f0; padding:24px 12px; }
                .card {
                    max-width:600px; margin:0 auto; background:#ffffff; border-radius:14px;
                    border:1px solid #e8e2d2; box-shadow:0 10px 30px rgba(0,0,0,0.06); overflow:hidden;
                }
                .header {
                    padding:20px 24px;
                    background:linear-gradient(135deg, #d4af37, #f3d87a);
                    color:#111;
                    text-align:center;
                }
                .brand { font-size:20px; font-weight:700; letter-spacing:0.5px; margin:0; }
                .subtitle { margin:6px 0 0; font-size:13px; font-weight:500; opacity:0.9; }
                .body { padding:24px; color:#2a2a2a; font-size:15px; line-height:1.6; }
                .btn {
                    display:inline-block; background:#d4af37; color:#111; text-decoration:none;
                    padding:12px 22px; border-radius:999px; font-weight:700; letter-spacing:0.2px;
                    box-shadow:0 8px 20px rgba(212,175,55,0.35);
                }
                .btn:hover { filter:brightness(0.98); }
                .link { word-break:break-all; color:#555; font-size:13px; }
                .note { margin-top:18px; font-size:13px; color:#666; }
                .footer {
                    padding:18px 24px; background:#faf8f3; color:#777; font-size:12px; text-align:center;
                    border-top:1px solid #eee;
                }
                @media (max-width:480px){
                    .card{ border-radius:10px; }
                    .body{ padding:20px; font-size:14px; }
                    .btn{ width:100%; text-align:center; }
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="card">
                    <div class="header">
                        <p class="brand">Destello de Oro 18K</p>
                        <p class="subtitle">Restablecimiento de contraseña</p>
                    </div>
                    <div class="body">
                        <p>Hola <strong>{$user['name']}</strong>,</p>
                        <p>Recibimos tu solicitud para restablecer la contraseña. Usa el botón para continuar.</p>
                        <p style="text-align:center; margin:24px 0;">
                            <a class="btn" href="{$reset_link}">Restablecer contraseña</a>
                        </p>
                        <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                        <p class="link">{$reset_link}</p>
                        <p class="note">El enlace es válido por 1 hora. Si no solicitaste este cambio, ignora este correo.</p>
                    </div>
                    <div class="footer">
                        Correo automático de Destello de Oro 18K. No respondas a este mensaje.
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Se ha enviado un correo con las instrucciones para restablecer tu contraseÃ±a.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'El correo no pudo ser enviado. Error: ' . $mail->ErrorInfo]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>

