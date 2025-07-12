<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
// Utilisez votre SDK/API WhatsApp ici (exemple générique)
$data = json_decode(file_get_contents('php://input'), true);
$to = $data['to'] ?? null;
$message = $data['message'] ?? '';

function sendWhatsAppNotification($to, $message) {
    // TODO: Intégrer l'appel à l'API officielle WhatsApp Business
    // $response = ...
    return true;
}

if ($to && $message) {
    $success = sendWhatsAppNotification($to, $message);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
}
