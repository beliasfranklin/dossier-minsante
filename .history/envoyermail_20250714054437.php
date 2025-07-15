<?php
require 'PHPMailer-6.10.0/src/PHPMailer.php';
require 'PHPMailer-6.10.0/src/SMTP.php';
require 'PHPMailer-6.10.0/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Activer le débogage SMTP
    $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
    $mail->Debugoutput = 'html'; // Format de sortie du débogage
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'beliasfranklin@gmail.com';
    $mail->Password   = 'gyuj nudi jaof wqtr'; // REMPLACEZ par un mot de passe d'application Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Options SMTP supplémentaires
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom('beliasfranklin@gmail.com', 'black star');
    $mail->addAddress('beliasfranklin@gmail.com', 'chilly porcupine');

    $mail->isHTML(true);
    $mail->Subject = 'Test sans Composer';
    $mail->Body    = '<b>Email envoyé sans Composer</b>';

    $mail->send();
    echo '✅ Message envoyé';
} catch (Exception $e) {
    echo "❌ Erreur : {$mail->ErrorInfo}";
}
?>
