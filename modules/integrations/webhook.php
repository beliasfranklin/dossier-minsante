<?php
/**
 * Webhook Handler pour WhatsApp Business API
 * Traite les messages entrants et les mises à jour de statut
 */

require_once '../../includes/config.php';
require_once 'whatsapp.php';

// Désactiver l'affichage des erreurs pour le webhook
error_reporting(0);
ini_set('display_errors', 0);

// Logs pour débogage
function logWebhook($message) {
    $logFile = '../../logs/whatsapp_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Vérification du webhook (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logWebhook("Webhook verification request: mode=$mode, token=$token");
    
    // Récupérer le token de vérification configuré
    try {
        $stmt = $conn->prepare("SELECT config_data FROM integrations_config WHERE service = 'whatsapp' AND is_active = 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $configData = json_decode($config['config_data'], true);
            $verifyToken = $configData['webhook_verify_token'] ?? '';
            
            if ($mode === 'subscribe' && $token === $verifyToken) {
                logWebhook("Webhook verification successful");
                echo $challenge;
                exit;
            }
        }
    } catch (Exception $e) {
        logWebhook("Database error during verification: " . $e->getMessage());
    }
    
    logWebhook("Webhook verification failed");
    http_response_code(403);
    exit;
}

// Traitement des webhooks (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    logWebhook("Webhook received: " . $input);
    
    if (!$data) {
        logWebhook("Invalid JSON data");
        http_response_code(400);
        exit;
    }
    
    try {
        $whatsapp = new WhatsAppIntegration();
        $result = $whatsapp->handleWebhook($data);
        
        if ($result['success']) {
            logWebhook("Webhook processed successfully");
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
        } else {
            logWebhook("Webhook processing failed: " . ($result['error'] ?? 'Unknown error'));
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
        }
    } catch (Exception $e) {
        logWebhook("Exception during webhook processing: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    }
    
    exit;
}

// Méthode non supportée
logWebhook("Unsupported method: " . $_SERVER['REQUEST_METHOD']);
http_response_code(405);
exit;
?>
