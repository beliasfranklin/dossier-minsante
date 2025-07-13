<?php
/**
 * SYSTÈME DE NOTIFICATIONS WHATSAPP - MINSANTE
 * Intégration WhatsApp Business API pour notifications automatiques
 */

class WhatsAppNotificationService {
    private $config;
    private $db;
    private $logger;
    
    public function __construct($pdo) {
        $this->db = $pdo;
        $this->config = $this->loadConfiguration();
        $this->logger = new NotificationLogger($pdo);
    }
    
    private function loadConfiguration() {
        return [
            'api_url' => 'https://graph.facebook.com/v17.0/',
            'phone_number_id' => $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '',
            'access_token' => $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '',
            'webhook_verify_token' => $_ENV['WHATSAPP_WEBHOOK_TOKEN'] ?? '',
            'business_account_id' => $_ENV['WHATSAPP_BUSINESS_ACCOUNT_ID'] ?? '',
            'enabled' => filter_var($_ENV['WHATSAPP_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'rate_limit' => 80, // Messages par minute
            'retry_attempts' => 3,
            'timeout' => 30
        ];
    }
    
    /**
     * Envoyer une notification WhatsApp
     */
    public function sendNotification($phoneNumber, $message, $type = 'text', $template = null, $data = []) {
        try {
            if (!$this->config['enabled'] || !$this->isValidConfiguration()) {
                throw new Exception("Configuration WhatsApp invalide ou désactivée");
            }
            
            // Valider et formater le numéro
            $formattedNumber = $this->formatPhoneNumber($phoneNumber);
            if (!$formattedNumber) {
                throw new Exception("Numéro de téléphone invalide: $phoneNumber");
            }
            
            // Vérifier les limites de taux
            if (!$this->checkRateLimit()) {
                throw new Exception("Limite de taux dépassée");
            }
            
            $payload = $this->buildPayload($formattedNumber, $message, $type, $template, $data);
            $response = $this->makeApiCall($payload);
            
            // Enregistrer le succès
            $this->logger->logNotification('whatsapp', $phoneNumber, $message, 'sent', $response['message_id'] ?? null);
            
            return [
                'success' => true,
                'message_id' => $response['message_id'] ?? null,
                'status' => 'sent'
            ];
            
        } catch (Exception $e) {
            $this->logger->logNotification('whatsapp', $phoneNumber, $message, 'failed', null, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envoyer une notification de nouveau dossier
     */
    public function sendNewDossierNotification($dossier, $recipient) {
        $template = 'nouveau_dossier';
        $data = [
            'recipient_name' => $recipient['name'],
            'dossier_number' => $dossier['numero_dossier'],
            'dossier_title' => $dossier['titre'],
            'created_date' => date('d/m/Y', strtotime($dossier['created_at'])),
            'deadline' => $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : 'Non définie',
            'priority' => $this->getPriorityLabel($dossier['priority']),
            'dashboard_link' => BASE_URL . 'dashboard.php'
        ];
        
        return $this->sendTemplateMessage($recipient['phone'], $template, $data);
    }
    
    /**
     * Envoyer une notification d'échéance
     */
    public function sendDeadlineNotification($dossier, $recipient, $daysUntilDeadline) {
        $template = $daysUntilDeadline <= 0 ? 'echeance_depassee' : 'echeance_proche';
        $data = [
            'recipient_name' => $recipient['name'],
            'dossier_number' => $dossier['numero_dossier'],
            'dossier_title' => $dossier['titre'],
            'deadline_date' => date('d/m/Y', strtotime($dossier['deadline'])),
            'days_remaining' => abs($daysUntilDeadline),
            'status' => $dossier['status'],
            'urgency_level' => $this->getUrgencyLevel($daysUntilDeadline),
            'action_link' => BASE_URL . 'modules/dossiers/view.php?id=' . $dossier['id']
        ];
        
        return $this->sendTemplateMessage($recipient['phone'], $template, $data);
    }
    
    /**
     * Envoyer une notification de changement de statut
     */
    public function sendStatusChangeNotification($dossier, $recipient, $oldStatus, $newStatus) {
        $template = 'changement_statut';
        $data = [
            'recipient_name' => $recipient['name'],
            'dossier_number' => $dossier['numero_dossier'],
            'dossier_title' => $dossier['titre'],
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'changed_by' => $_SESSION['user_name'] ?? 'Système',
            'change_date' => date('d/m/Y H:i'),
            'dossier_link' => BASE_URL . 'modules/dossiers/view.php?id=' . $dossier['id']
        ];
        
        return $this->sendTemplateMessage($recipient['phone'], $template, $data);
    }
    
    /**
     * Notification de validation requise
     */
    public function sendValidationRequiredNotification($dossier, $validator) {
        $template = 'validation_requise';
        $data = [
            'validator_name' => $validator['name'],
            'dossier_number' => $dossier['numero_dossier'],
            'dossier_title' => $dossier['titre'],
            'requester_name' => $_SESSION['user_name'] ?? 'Système',
            'request_date' => date('d/m/Y H:i'),
            'validation_link' => BASE_URL . 'modules/dossiers/validate.php?id=' . $dossier['id'],
            'deadline' => $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : 'Non définie'
        ];
        
        return $this->sendTemplateMessage($validator['phone'], $template, $data);
    }
    
    /**
     * Notification résumé hebdomadaire
     */
    public function sendWeeklySummary($recipient, $stats) {
        $template = 'resume_hebdomadaire';
        $data = [
            'recipient_name' => $recipient['name'],
            'week_period' => date('d/m/Y', strtotime('-7 days')) . ' - ' . date('d/m/Y'),
            'new_dossiers' => $stats['new_dossiers'],
            'completed_dossiers' => $stats['completed_dossiers'],
            'overdue_dossiers' => $stats['overdue_dossiers'],
            'pending_validations' => $stats['pending_validations'],
            'dashboard_link' => BASE_URL . 'dashboard.php'
        ];
        
        return $this->sendTemplateMessage($recipient['phone'], $template, $data);
    }
    
    /**
     * Envoyer un message avec template
     */
    private function sendTemplateMessage($phoneNumber, $template, $data) {
        $message = $this->buildMessageFromTemplate($template, $data);
        return $this->sendNotification($phoneNumber, $message, 'text');
    }
    
    /**
     * Construire le message à partir du template
     */
    private function buildMessageFromTemplate($template, $data) {
        $templates = [
            'nouveau_dossier' => "🆕 *Nouveau dossier assigné*\n\nBonjour {recipient_name},\n\nUn nouveau dossier vous a été assigné :\n\n📋 *Dossier :* {dossier_number}\n📝 *Titre :* {dossier_title}\n📅 *Créé le :* {created_date}\n⏰ *Échéance :* {deadline}\n🔥 *Priorité :* {priority}\n\n👆 Consultez votre tableau de bord : {dashboard_link}\n\n_MINSANTE - Système de gestion des dossiers_",
            
            'echeance_proche' => "⚠️ *Échéance proche*\n\nBonjour {recipient_name},\n\nUn dossier nécessite votre attention :\n\n📋 *Dossier :* {dossier_number}\n📝 *Titre :* {dossier_title}\n📅 *Échéance :* {deadline_date}\n⏳ *Dans {days_remaining} jour(s)*\n📊 *Statut :* {status}\n🚨 *Urgence :* {urgency_level}\n\n👆 Traiter maintenant : {action_link}\n\n_MINSANTE - Système de gestion des dossiers_",
            
            'echeance_depassee' => "🚨 *ÉCHÉANCE DÉPASSÉE*\n\nBonjour {recipient_name},\n\n⚠️ Un dossier a dépassé son échéance :\n\n📋 *Dossier :* {dossier_number}\n📝 *Titre :* {dossier_title}\n📅 *Échéance était le :* {deadline_date}\n⏰ *Retard :* {days_remaining} jour(s)\n📊 *Statut actuel :* {status}\n\n🔥 *ACTION REQUISE IMMÉDIATEMENT*\n👆 Traiter le dossier : {action_link}\n\n_MINSANTE - Système de gestion des dossiers_",
            
            'changement_statut' => "🔄 *Statut modifié*\n\nBonjour {recipient_name},\n\nLe statut d'un dossier a été modifié :\n\n📋 *Dossier :* {dossier_number}\n📝 *Titre :* {dossier_title}\n📊 *Ancien statut :* {old_status}\n✅ *Nouveau statut :* {new_status}\n👤 *Modifié par :* {changed_by}\n📅 *Le :* {change_date}\n\n👆 Voir les détails : {dossier_link}\n\n_MINSANTE - Système de gestion des dossiers_",
            
            'validation_requise' => "✋ *Validation requise*\n\nBonjour {validator_name},\n\nUne validation est requise pour :\n\n📋 *Dossier :* {dossier_number}\n📝 *Titre :* {dossier_title}\n👤 *Demandé par :* {requester_name}\n📅 *Le :* {request_date}\n⏰ *Échéance :* {deadline}\n\n🔍 *Votre approbation est nécessaire*\n👆 Valider maintenant : {validation_link}\n\n_MINSANTE - Système de gestion des dossiers_",
            
            'resume_hebdomadaire' => "📊 *Résumé hebdomadaire*\n\nBonjour {recipient_name},\n\nRésumé de la semaine ({week_period}) :\n\n🆕 *Nouveaux dossiers :* {new_dossiers}\n✅ *Dossiers terminés :* {completed_dossiers}\n⚠️ *Dossiers en retard :* {overdue_dossiers}\n⏳ *Validations en attente :* {pending_validations}\n\n👆 Tableau de bord : {dashboard_link}\n\n_MINSANTE - Système de gestion des dossiers_"
        ];
        
        $message = $templates[$template] ?? "Message de notification MINSANTE";
        
        // Remplacer les variables
        foreach ($data as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Construire le payload pour l'API
     */
    private function buildPayload($phoneNumber, $message, $type, $template, $data) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => $type
        ];
        
        if ($type === 'text') {
            $payload['text'] = [
                'body' => $message,
                'preview_url' => true
            ];
        } elseif ($type === 'template' && $template) {
            $payload['template'] = [
                'name' => $template,
                'language' => ['code' => 'fr'],
                'components' => $this->buildTemplateComponents($data)
            ];
        }
        
        return $payload;
    }
    
    /**
     * Effectuer l'appel API
     */
    private function makeApiCall($payload) {
        $url = $this->config['api_url'] . $this->config['phone_number_id'] . '/messages';
        
        $headers = [
            'Authorization: Bearer ' . $this->config['access_token'],
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erreur cURL: $error");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMessage = $decodedResponse['error']['message'] ?? "Erreur HTTP $httpCode";
            throw new Exception("Erreur API WhatsApp: $errorMessage");
        }
        
        if (!isset($decodedResponse['messages'][0]['id'])) {
            throw new Exception("Réponse API invalide");
        }
        
        return [
            'message_id' => $decodedResponse['messages'][0]['id'],
            'status' => 'sent'
        ];
    }
    
    /**
     * Valider et formater le numéro de téléphone
     */
    private function formatPhoneNumber($phoneNumber) {
        // Nettoyer le numéro
        $clean = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ajouter l'indicatif pays si manquant (supposant Cameroun +237)
        if (strlen($clean) === 9 && !str_starts_with($clean, '237')) {
            $clean = '237' . $clean;
        }
        
        // Valider le format
        if (strlen($clean) >= 10 && strlen($clean) <= 15) {
            return $clean;
        }
        
        return false;
    }
    
    /**
     * Vérifier les limites de taux
     */
    private function checkRateLimit() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as sent_count 
            FROM notification_logs 
            WHERE channel = 'whatsapp' 
            AND status = 'sent' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return ($result['sent_count'] ?? 0) < $this->config['rate_limit'];
    }
    
    /**
     * Vérifier la configuration
     */
    private function isValidConfiguration() {
        return !empty($this->config['phone_number_id']) &&
               !empty($this->config['access_token']) &&
               !empty($this->config['api_url']);
    }
    
    /**
     * Obtenir le libellé de priorité
     */
    private function getPriorityLabel($priority) {
        $labels = [
            'low' => '🟢 Faible',
            'medium' => '🟡 Moyenne',
            'high' => '🟠 Élevée',
            'urgent' => '🔴 Urgente'
        ];
        
        return $labels[$priority] ?? '⚪ Non définie';
    }
    
    /**
     * Obtenir le niveau d'urgence
     */
    private function getUrgencyLevel($daysUntilDeadline) {
        if ($daysUntilDeadline <= 0) return '🚨 CRITIQUE';
        if ($daysUntilDeadline == 1) return '🔴 URGENT';
        if ($daysUntilDeadline <= 3) return '🟠 ÉLEVÉ';
        return '🟡 NORMAL';
    }
    
    /**
     * Obtenir le libellé de statut
     */
    private function getStatusLabel($status) {
        $labels = [
            'nouveau' => '🆕 Nouveau',
            'en_cours' => '⏳ En cours',
            'en_attente' => '⏸️ En attente',
            'valide' => '✅ Validé',
            'archive' => '📁 Archivé',
            'rejete' => '❌ Rejeté'
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Gérer les webhooks WhatsApp
     */
    public function handleWebhook($data) {
        try {
            if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
                foreach ($data['entry'][0]['changes'][0]['value']['messages'] as $message) {
                    $this->processIncomingMessage($message);
                }
            }
            
            if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                foreach ($data['entry'][0]['changes'][0]['value']['statuses'] as $status) {
                    $this->processMessageStatus($status);
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Erreur webhook WhatsApp: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Traiter un message entrant
     */
    private function processIncomingMessage($message) {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];
        
        if (isset($message['text'])) {
            $text = $message['text']['body'];
            
            // Traiter les commandes simples
            $response = $this->processCommand($text, $from);
            if ($response) {
                $this->sendNotification($from, $response);
            }
        }
        
        // Enregistrer le message entrant
        $this->logger->logIncomingMessage('whatsapp', $from, json_encode($message));
    }
    
    /**
     * Traiter les commandes simples
     */
    private function processCommand($text, $from) {
        $text = strtolower(trim($text));
        
        switch ($text) {
            case 'help':
            case 'aide':
                return "🤖 *Commandes disponibles:*\n\n• *aide* - Afficher cette aide\n• *statut* - Voir vos dossiers\n• *stop* - Arrêter les notifications\n\n_MINSANTE - Système de gestion des dossiers_";
                
            case 'statut':
            case 'status':
                return $this->getUserStatus($from);
                
            case 'stop':
            case 'arrêt':
                $this->disableNotifications($from);
                return "✅ Notifications WhatsApp désactivées.\n\nPour les réactiver, connectez-vous à votre tableau de bord.\n\n_MINSANTE - Système de gestion des dossiers_";
                
            default:
                return null;
        }
    }
    
    /**
     * Obtenir le statut utilisateur
     */
    private function getUserStatus($phoneNumber) {
        $stmt = $this->db->prepare("
            SELECT u.*, 
                   COUNT(CASE WHEN d.status NOT IN ('archive', 'valide') THEN 1 END) as active_dossiers,
                   COUNT(CASE WHEN d.deadline <= CURDATE() AND d.status NOT IN ('archive', 'valide') THEN 1 END) as overdue_dossiers
            FROM users u 
            LEFT JOIN dossiers d ON d.responsable_id = u.id OR d.created_by = u.id
            WHERE u.phone = ?
            GROUP BY u.id
        ");
        $stmt->execute([$phoneNumber]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return "❌ Numéro non reconnu. Veuillez contacter l'administrateur.";
        }
        
        return "👋 Bonjour {$user['name']} !\n\n📊 *Votre statut :*\n\n📋 Dossiers actifs : {$user['active_dossiers']}\n⚠️ Dossiers en retard : {$user['overdue_dossiers']}\n\n👆 Tableau de bord : " . BASE_URL . "dashboard.php\n\n_MINSANTE - Système de gestion des dossiers_";
    }
    
    /**
     * Désactiver les notifications pour un numéro
     */
    private function disableNotifications($phoneNumber) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET whatsapp_notifications = 0 
            WHERE phone = ?
        ");
        $stmt->execute([$phoneNumber]);
    }
    
    /**
     * Traiter le statut d'un message
     */
    private function processMessageStatus($status) {
        $messageId = $status['id'];
        $recipientId = $status['recipient_id'];
        $statusValue = $status['status'];
        $timestamp = $status['timestamp'];
        
        // Mettre à jour le statut dans les logs
        $stmt = $this->db->prepare("
            UPDATE notification_logs 
            SET status = ?, 
                updated_at = FROM_UNIXTIME(?)
            WHERE external_id = ? AND channel = 'whatsapp'
        ");
        $stmt->execute([$statusValue, $timestamp, $messageId]);
    }
}

/**
 * Logger pour les notifications
 */
class NotificationLogger {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    public function logNotification($channel, $recipient, $message, $status, $externalId = null, $error = null) {
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs 
            (channel, recipient, message, status, external_id, error_message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$channel, $recipient, $message, $status, $externalId, $error]);
    }
    
    public function logIncomingMessage($channel, $sender, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO incoming_messages 
            (channel, sender, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$channel, $sender, $message]);
    }
}
?>
