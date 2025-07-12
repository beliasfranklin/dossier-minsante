<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$to = $data['to'] ?? null;
$subject = $data['subject'] ?? '';
$body = $data['body'] ?? '';

function sendNotificationEmail($to, $subject, $body) {
    // Version sans PHPMailer - utilise la fonction mail() de PHP
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@minsante.local'),
        'Reply-To: ' . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@minsante.local'),
        'X-Mailer: PHP/' . phpversion()
    ];
    
    try {
        return mail($to, $subject, $body, implode("\r\n", $headers));
    } catch (Exception $e) {
        error_log("Erreur envoi email: " . $e->getMessage());
        return false;
    }
}

// Version PHPMailer (si disponible)
function sendNotificationEmailWithPHPMailer($to, $subject, $body) {
    // Vérifier si PHPMailer est disponible
    if (file_exists('../../vendor/autoload.php')) {
        require_once '../../vendor/autoload.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            use PHPMailer\PHPMailer\PHPMailer;
            use PHPMailer\PHPMailer\Exception;
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
                $mail->SMTPAuth = true;
                $mail->Username = defined('SMTP_USER') ? SMTP_USER : '';
                $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
                $mail->setFrom(defined('SMTP_FROM') ? SMTP_FROM : 'noreply@minsante.local', defined('APP_NAME') ? APP_NAME : 'MINSANTE');
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log("Erreur PHPMailer: " . $e->getMessage());
                return false;
            }
        }
    }
    
    // PHPMailer n'est pas disponible, utiliser la fonction mail() classique
    return sendNotificationEmail($to, $subject, $body);
}

if ($to && $subject && $body) {
    // Essayer PHPMailer en premier, puis fallback vers mail()
    $success = sendNotificationEmailWithPHPMailer($to, $subject, $body);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
}
