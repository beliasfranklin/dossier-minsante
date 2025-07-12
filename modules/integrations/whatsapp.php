<?php
/**
 * Module d'intégration WhatsApp Business API
 * Gère l'envoi de notifications, la configuration et les webhooks
 */

class WhatsAppIntegration {
    private $accessToken;
    private $phoneNumberId;
    private $webhookVerifyToken;
    private $apiVersion = 'v18.0';
    private $baseUrl = 'https://graph.facebook.com';
    
    public function __construct() {
        $this->loadConfig();
    }
    
    /**
     * Charge la configuration WhatsApp depuis la base de données
     */
    private function loadConfig() {
        global $conn;
        
        $stmt = $conn->prepare("SELECT * FROM integrations_config WHERE service = 'whatsapp' AND is_active = 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $configData = json_decode($config['config_data'], true);
            $this->accessToken = $configData['access_token'] ?? '';
            $this->phoneNumberId = $configData['phone_number_id'] ?? '';
            $this->webhookVerifyToken = $configData['webhook_verify_token'] ?? '';
        }
    }
    
    /**
     * Configure l'intégration WhatsApp
     */
    public function configure($config) {
        global $conn;
        
        $this->accessToken = $config['access_token'];
        $this->phoneNumberId = $config['phone_number_id'];
        $this->webhookVerifyToken = $config['webhook_verify_token'];
        
        // Sauvegarder la configuration
        $configData = json_encode([
            'access_token' => $this->accessToken,
            'phone_number_id' => $this->phoneNumberId,
            'webhook_verify_token' => $this->webhookVerifyToken,
            'webhook_url' => $config['webhook_url'] ?? '',
            'business_account_id' => $config['business_account_id'] ?? ''
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO integrations_config (service, config_data, is_active, created_at) 
            VALUES ('whatsapp', ?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            config_data = VALUES(config_data),
            updated_at = NOW()
        ");
        
        return $stmt->execute([$configData]);
    }
    
    /**
     * Teste la connexion à l'API WhatsApp
     */
    public function testConnection() {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            return ['success' => false, 'error' => 'Configuration manquante'];
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}";
        
        $response = $this->makeApiCall($url, 'GET');
        
        if ($response && isset($response['id'])) {
            return ['success' => true, 'data' => $response];
        }
        
        return ['success' => false, 'error' => 'Connexion échouée'];
    }
    
    /**
     * Envoie un message texte via WhatsApp
     */
    public function sendTextMessage($to, $message) {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            return ['success' => false, 'error' => 'Configuration WhatsApp manquante'];
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        $response = $this->makeApiCall($url, 'POST', $data);
        
        if ($response && isset($response['messages'])) {
            $this->logMessage($to, 'text', $message, 'sent', $response['messages'][0]['id'] ?? null);
            return ['success' => true, 'message_id' => $response['messages'][0]['id']];
        }
        
        return ['success' => false, 'error' => $response['error']['message'] ?? 'Erreur inconnue'];
    }
    
    /**
     * Envoie un message template via WhatsApp
     */
    public function sendTemplateMessage($to, $templateName, $parameters = []) {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            return ['success' => false, 'error' => 'Configuration WhatsApp manquante'];
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'fr'],
                'components' => $components
            ]
        ];
        
        $response = $this->makeApiCall($url, 'POST', $data);
        
        if ($response && isset($response['messages'])) {
            $this->logMessage($to, 'template', $templateName, 'sent', $response['messages'][0]['id'] ?? null);
            return ['success' => true, 'message_id' => $response['messages'][0]['id']];
        }
        
        return ['success' => false, 'error' => $response['error']['message'] ?? 'Erreur inconnue'];
    }
    
    /**
     * Envoie une notification de nouveau dossier
     */
    public function sendNewDossierNotification($dossier, $userPhone) {
        $message = "🔔 Nouveau dossier assigné\n\n";
        $message .= "📋 Référence: {$dossier['reference']}\n";
        $message .= "📝 Titre: {$dossier['titre']}\n";
        $message .= "⚡ Priorité: {$dossier['priority']}\n";
        $message .= "📅 Échéance: " . ($dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : 'Non définie') . "\n\n";
        $message .= "Connectez-vous au système pour plus de détails.";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Envoie une notification d'échéance proche
     */
    public function sendDeadlineNotification($dossier, $userPhone, $daysRemaining) {
        $urgencyEmoji = $daysRemaining <= 1 ? '🚨' : ($daysRemaining <= 3 ? '⚠️' : '📅');
        
        $message = "{$urgencyEmoji} Échéance proche - {$daysRemaining} jour(s) restant(s)\n\n";
        $message .= "📋 Dossier: {$dossier['reference']}\n";
        $message .= "📝 Titre: {$dossier['titre']}\n";
        $message .= "⏰ Échéance: " . date('d/m/Y', strtotime($dossier['deadline'])) . "\n";
        $message .= "📊 Statut: {$dossier['status']}\n\n";
        $message .= "Action requise rapidement.";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Envoie une notification de changement de statut
     */
    public function sendStatusChangeNotification($dossier, $oldStatus, $newStatus, $userPhone) {
        $statusEmoji = [
            'en_cours' => '⏳',
            'valide' => '✅',
            'rejete' => '❌',
            'archive' => '📦'
        ];
        
        $message = "📝 Changement de statut\n\n";
        $message .= "📋 Dossier: {$dossier['reference']}\n";
        $message .= "📝 Titre: {$dossier['titre']}\n";
        $message .= "🔄 Ancien statut: {$statusEmoji[$oldStatus]} {$oldStatus}\n";
        $message .= "🆕 Nouveau statut: {$statusEmoji[$newStatus]} {$newStatus}\n\n";
        $message .= "Consultez le dossier pour plus d'informations.";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Traite les webhooks entrants de WhatsApp
     */
    public function handleWebhook($data) {
        if (!$this->verifyWebhook($data)) {
            return ['success' => false, 'error' => 'Webhook non autorisé'];
        }
        
        foreach ($data['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'messages') {
                    $this->processIncomingMessage($change['value']);
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Traite les messages entrants
     */
    private function processIncomingMessage($messageData) {
        if (!isset($messageData['messages'])) {
            return;
        }
        
        foreach ($messageData['messages'] as $message) {
            $from = $message['from'];
            $messageId = $message['id'];
            $timestamp = $message['timestamp'];
            
            // Traiter selon le type de message
            if (isset($message['text'])) {
                $this->handleTextMessage($from, $message['text']['body'], $messageId, $timestamp);
            } elseif (isset($message['interactive'])) {
                $this->handleInteractiveMessage($from, $message['interactive'], $messageId, $timestamp);
            }
            
            // Marquer comme lu
            $this->markMessageAsRead($messageId);
        }
    }
    
    /**
     * Traite les messages texte entrants
     */
    private function handleTextMessage($from, $text, $messageId, $timestamp) {
        global $conn;
        
        // Log du message entrant
        $this->logMessage($from, 'text', $text, 'received', $messageId);
        
        // Analyse basique du contenu pour réponses automatiques
        $text = strtolower(trim($text));
        
        $responses = [
            'aide' => "🤖 Aide MINSANTE\n\n📋 Commandes disponibles:\n• 'mes dossiers' - Voir vos dossiers\n• 'urgent' - Dossiers prioritaires\n• 'échéances' - Prochaines échéances\n• 'statut [ref]' - Statut d'un dossier\n\nPour plus d'aide, contactez l'administrateur.",
            'help' => "🤖 MINSANTE Help\n\n📋 Available commands:\n• 'my files' - View your files\n• 'urgent' - Priority files\n• 'deadlines' - Upcoming deadlines\n• 'status [ref]' - File status\n\nFor more help, contact the administrator.",
            'mes dossiers' => $this->getUserDossiersSummary($from),
            'urgent' => $this->getUrgentDossiersSummary($from),
            'échéances' => $this->getUpcomingDeadlines($from)
        ];
        
        // Vérifier les commandes de statut
        if (preg_match('/statut\s+(\w+)/i', $text, $matches)) {
            $reference = $matches[1];
            $response = $this->getDossierStatus($reference, $from);
        } else {
            $response = $responses[$text] ?? null;
        }
        
        // Envoyer la réponse automatique si disponible
        if ($response) {
            $this->sendTextMessage($from, $response);
        } else {
            // Notifier l'équipe d'un message non traité
            $this->notifyUnhandledMessage($from, $text);
        }
    }
    
    /**
     * Obtient un résumé des dossiers de l'utilisateur
     */
    private function getUserDossiersSummary($phoneNumber) {
        global $conn;
        
        // Trouver l'utilisateur par numéro de téléphone
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE phone = ? OR whatsapp_phone = ?");
        $stmt->execute([$phoneNumber, $phoneNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return "❌ Numéro non reconnu. Contactez l'administrateur pour associer votre numéro.";
        }
        
        // Récupérer les statistiques des dossiers
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
            FROM dossiers 
            WHERE responsable_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $message = "📊 Vos dossiers - {$user['name']}\n\n";
        $message .= "📋 Total: {$stats['total']}\n";
        $message .= "⏳ En cours: {$stats['en_cours']}\n";
        $message .= "✅ Validés: {$stats['valides']}\n";
        $message .= "🚨 Urgents: {$stats['urgent']}\n\n";
        $message .= "Pour plus de détails, connectez-vous au système.";
        
        return $message;
    }
    
    /**
     * Obtient les dossiers urgents de l'utilisateur
     */
    private function getUrgentDossiersSummary($phoneNumber) {
        global $conn;
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? OR whatsapp_phone = ?");
        $stmt->execute([$phoneNumber, $phoneNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return "❌ Numéro non reconnu.";
        }
        
        $stmt = $conn->prepare("
            SELECT reference, titre, deadline
            FROM dossiers 
            WHERE responsable_id = ? AND priority = 'urgent' AND status != 'archive'
            ORDER BY deadline ASC
            LIMIT 5
        ");
        $stmt->execute([$user['id']]);
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($dossiers)) {
            return "✅ Aucun dossier urgent actuellement.";
        }
        
        $message = "🚨 Dossiers urgents:\n\n";
        foreach ($dossiers as $dossier) {
            $deadline = $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : 'Non définie';
            $message .= "📋 {$dossier['reference']}\n";
            $message .= "📝 {$dossier['titre']}\n";
            $message .= "📅 Échéance: {$deadline}\n\n";
        }
        
        return $message;
    }
    
    /**
     * Obtient les prochaines échéances
     */
    private function getUpcomingDeadlines($phoneNumber) {
        global $conn;
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? OR whatsapp_phone = ?");
        $stmt->execute([$phoneNumber, $phoneNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return "❌ Numéro non reconnu.";
        }
        
        $stmt = $conn->prepare("
            SELECT reference, titre, deadline,
                   DATEDIFF(deadline, NOW()) as days_remaining
            FROM dossiers 
            WHERE responsable_id = ? 
                  AND deadline IS NOT NULL 
                  AND deadline >= NOW() 
                  AND deadline <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                  AND status NOT IN ('valide', 'archive')
            ORDER BY deadline ASC
        ");
        $stmt->execute([$user['id']]);
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($dossiers)) {
            return "✅ Aucune échéance dans les 7 prochains jours.";
        }
        
        $message = "📅 Échéances prochaines (7 jours):\n\n";
        foreach ($dossiers as $dossier) {
            $urgencyEmoji = $dossier['days_remaining'] <= 1 ? '🚨' : ($dossier['days_remaining'] <= 3 ? '⚠️' : '📅');
            $message .= "{$urgencyEmoji} {$dossier['reference']}\n";
            $message .= "📝 {$dossier['titre']}\n";
            $message .= "⏰ {$dossier['days_remaining']} jour(s) restant(s)\n\n";
        }
        
        return $message;
    }
    
    /**
     * Obtient le statut d'un dossier spécifique
     */
    private function getDossierStatus($reference, $phoneNumber) {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT d.*, u.name as responsable_name
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            WHERE d.reference = ?
        ");
        $stmt->execute([$reference]);
        $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dossier) {
            return "❌ Dossier '{$reference}' non trouvé.";
        }
        
        $statusEmoji = [
            'en_cours' => '⏳',
            'valide' => '✅',
            'rejete' => '❌',
            'archive' => '📦'
        ];
        
        $message = "📋 Statut du dossier {$reference}\n\n";
        $message .= "📝 Titre: {$dossier['titre']}\n";
        $message .= "📊 Statut: {$statusEmoji[$dossier['status']]} {$dossier['status']}\n";
        $message .= "⚡ Priorité: {$dossier['priority']}\n";
        $message .= "👤 Responsable: {$dossier['responsable_name']}\n";
        
        if ($dossier['deadline']) {
            $deadline = date('d/m/Y', strtotime($dossier['deadline']));
            $daysRemaining = max(0, (strtotime($dossier['deadline']) - time()) / (24 * 3600));
            $message .= "📅 Échéance: {$deadline}";
            if ($daysRemaining > 0) {
                $message .= " (" . ceil($daysRemaining) . " jour(s) restant(s))";
            }
            $message .= "\n";
        }
        
        $message .= "📅 Créé le: " . date('d/m/Y', strtotime($dossier['created_at']));
        
        return $message;
    }
    
    /**
     * Marque un message comme lu
     */
    private function markMessageAsRead($messageId) {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];
        
        $this->makeApiCall($url, 'POST', $data);
    }
    
    /**
     * Notifie l'équipe d'un message non traité
     */
    private function notifyUnhandledMessage($from, $text) {
        global $conn;
        
        // Créer une notification pour les administrateurs
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            SELECT id, 'Message WhatsApp non traité', ?, 'whatsapp_message', NOW()
            FROM users WHERE role = 'admin'
        ");
        
        $message = "Message de {$from}: {$text}";
        $stmt->execute([$message]);
    }
    
    /**
     * Vérifie la validité du webhook
     */
    private function verifyWebhook($data) {
        // Vérification basique - à améliorer avec la signature
        return !empty($this->webhookVerifyToken);
    }
    
    /**
     * Log des messages envoyés/reçus
     */
    private function logMessage($phoneNumber, $messageType, $content, $direction, $messageId = null) {
        global $conn;
        
        $stmt = $conn->prepare("
            INSERT INTO whatsapp_messages (phone_number, message_type, content, direction, message_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$phoneNumber, $messageType, $content, $direction, $messageId]);
    }
    
    /**
     * Formate le numéro de téléphone pour WhatsApp
     */
    private function formatPhoneNumber($phoneNumber) {
        // Supprimer tous les caractères non numériques
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ajouter le code pays si nécessaire (supposons +33 pour la France)
        if (strlen($cleaned) === 10 && substr($cleaned, 0, 1) === '0') {
            $cleaned = '33' . substr($cleaned, 1);
        } elseif (strlen($cleaned) === 9) {
            $cleaned = '33' . $cleaned;
        }
        
        return $cleaned;
    }
    
    /**
     * Effectue un appel à l'API WhatsApp
     */
    private function makeApiCall($url, $method = 'GET', $data = null) {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            error_log("WhatsApp API Error: " . $error);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            error_log("WhatsApp API HTTP Error {$httpCode}: " . $response);
        }
        
        return $decoded;
    }
    
    /**
     * Obtient les templates disponibles
     */
    public function getMessageTemplates() {
        if (empty($this->accessToken)) {
            return ['success' => false, 'error' => 'Configuration manquante'];
        }
        
        // URL pour récupérer les templates (nécessite business_account_id)
        // $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessAccountId}/message_templates";
        
        // Pour l'instant, retourner des templates par défaut
        return [
            'success' => true,
            'templates' => [
                [
                    'name' => 'nouveau_dossier',
                    'category' => 'UTILITY',
                    'language' => 'fr',
                    'status' => 'APPROVED',
                    'components' => [
                        [
                            'type' => 'BODY',
                            'text' => 'Nouveau dossier assigné: {{1}}\nRéférence: {{2}}\nÉchéance: {{3}}'
                        ]
                    ]
                ],
                [
                    'name' => 'echeance_proche',
                    'category' => 'UTILITY',
                    'language' => 'fr',
                    'status' => 'APPROVED',
                    'components' => [
                        [
                            'type' => 'BODY',
                            'text' => 'Échéance proche pour le dossier {{1}}.\nIl reste {{2}} jour(s).\nAction requise.'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Crée les tables nécessaires pour WhatsApp
     */
    public static function createTables() {
        global $conn;
        
        $sql = "
        CREATE TABLE IF NOT EXISTS whatsapp_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            message_type VARCHAR(50) NOT NULL,
            content TEXT,
            direction ENUM('sent', 'received') NOT NULL,
            message_id VARCHAR(255),
            status VARCHAR(50) DEFAULT 'sent',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone_number),
            INDEX idx_direction (direction),
            INDEX idx_created (created_at)
        );
        
        CREATE TABLE IF NOT EXISTS integrations_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service VARCHAR(50) NOT NULL UNIQUE,
            config_data JSON,
            is_active BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ";
        
        return $conn->exec($sql);
    }
}
