<?php
/**
 * Service d'automatisation pour le traitement des workflows
 * Ce script doit être exécuté via CRON toutes les heures
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

class WorkflowAutomationService {
    
    private $pdo;
    private $logFile;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->logFile = __DIR__ . '/../logs/workflow_automation.log';
        
        // Créer le répertoire logs s'il n'existe pas
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    /**
     * Exécuter toutes les tâches d'automatisation
     */
    public function run() {
        $this->log("=== Début du cycle d'automatisation ===");
        
        try {
            // 1. Démarrer automatiquement les workflows pour les nouveaux dossiers
            $this->autoStartWorkflows();
            
            // 2. Traiter les transitions automatiques
            $this->processAutomaticTransitions();
            
            // 3. Gérer les délais et escalades
            $this->processDeadlines();
            
            // 4. Envoyer les notifications de rappel
            $this->sendReminderNotifications();
            
            // 5. Traiter les conditions spéciales
            $this->processConditionalLogic();
            
            // 6. Nettoyer les données anciennes
            $this->cleanupOldData();
            
            $this->log("=== Cycle d'automatisation terminé avec succès ===");
            
        } catch (Exception $e) {
            $this->log("ERREUR: " . $e->getMessage());
            $this->notifyAdministrators($e);
        }
    }
    
    /**
     * Démarrer automatiquement les workflows pour les nouveaux dossiers
     */
    private function autoStartWorkflows() {
        $this->log("Démarrage automatique des workflows...");
        
        // Rechercher les dossiers sans workflow démarré
        $stmt = $this->pdo->query("
            SELECT d.*, dt.workflow_template_id 
            FROM dossiers d
            LEFT JOIN dossier_types dt ON d.type = dt.name
            WHERE d.workflow_started = 0 
            AND d.status != 'brouillon'
            AND dt.workflow_template_id IS NOT NULL
        ");
        
        $dossiers = $stmt->fetchAll();
        $count = 0;
        
        foreach ($dossiers as $dossier) {
            try {
                $this->startWorkflowForDossier($dossier['id'], $dossier['workflow_template_id']);
                $count++;
            } catch (Exception $e) {
                $this->log("Erreur démarrage workflow dossier {$dossier['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("$count workflows démarrés automatiquement");
    }
    
    /**
     * Traiter les transitions automatiques
     */
    private function processAutomaticTransitions() {
        $this->log("Traitement des transitions automatiques...");
        
        // Rechercher les étapes avec conditions d'automatisation
        $stmt = $this->pdo->query("
            SELECT wi.*, w.nom as step_name, w.auto_approve, w.conditions
            FROM workflow_instances wi
            JOIN workflows w ON wi.workflow_step_id = w.id
            WHERE wi.status = 'active'
            AND w.auto_approve = 1
        ");
        
        $instances = $stmt->fetchAll();
        $count = 0;
        
        foreach ($instances as $instance) {
            try {
                if ($this->evaluateAutoApprovalConditions($instance)) {
                    $this->autoApproveStep($instance['id']);
                    $count++;
                }
            } catch (Exception $e) {
                $this->log("Erreur transition auto instance {$instance['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("$count transitions automatiques traitées");
    }
    
    /**
     * Gérer les délais et escalades
     */
    private function processDeadlines() {
        $this->log("Traitement des délais et escalades...");
        
        // Rechercher les étapes en retard
        $stmt = $this->pdo->query("
            SELECT wi.*, wd.deadline_hours, wd.escalate_to_role, wd.auto_approve,
                   TIMESTAMPDIFF(HOUR, wi.started_at, NOW()) as hours_elapsed
            FROM workflow_instances wi
            JOIN workflow_deadlines wd ON wi.workflow_step_id = wd.workflow_step_id
            WHERE wi.status = 'active'
            AND wi.started_at IS NOT NULL
            AND TIMESTAMPDIFF(HOUR, wi.started_at, NOW()) >= wd.deadline_hours
        ");
        
        $overdueInstances = $stmt->fetchAll();
        $escalated = 0;
        $autoApproved = 0;
        
        foreach ($overdueInstances as $instance) {
            try {
                if ($instance['auto_approve']) {
                    $this->autoApproveStep($instance['id'], 'Approbation automatique après dépassement du délai');
                    $autoApproved++;
                } elseif ($instance['escalate_to_role']) {
                    $this->escalateToRole($instance['id'], $instance['escalate_to_role']);
                    $escalated++;
                }
            } catch (Exception $e) {
                $this->log("Erreur escalade instance {$instance['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("$escalated escalades, $autoApproved approbations automatiques");
    }
    
    /**
     * Envoyer les notifications de rappel
     */
    private function sendReminderNotifications() {
        $this->log("Envoi des notifications de rappel...");
        
        // Rechercher les instances nécessitant des rappels
        $stmt = $this->pdo->query("
            SELECT wi.*, wd.notification_hours, wd.deadline_hours,
                   TIMESTAMPDIFF(HOUR, wi.started_at, NOW()) as hours_elapsed,
                   u.email, u.nom as user_name, d.reference
            FROM workflow_instances wi
            JOIN workflow_deadlines wd ON wi.workflow_step_id = wd.workflow_step_id
            JOIN users u ON (wi.delegated_to IS NOT NULL AND u.id = wi.delegated_to) 
                         OR (wi.delegated_to IS NULL AND u.role = wi.role_requis)
            JOIN dossiers d ON wi.dossier_id = d.id
            WHERE wi.status = 'active'
            AND wi.started_at IS NOT NULL
            AND wd.notification_hours IS NOT NULL
        ");
        
        $instances = $stmt->fetchAll();
        $sent = 0;
        
        foreach ($instances as $instance) {
            $notificationHours = explode(',', $instance['notification_hours']);
            $hoursRemaining = $instance['deadline_hours'] - $instance['hours_elapsed'];
            
            foreach ($notificationHours as $notifyHour) {
                $notifyHour = (int)trim($notifyHour);
                
                if ($hoursRemaining <= $notifyHour && $hoursRemaining > ($notifyHour - 1)) {
                    // Vérifier si notification déjà envoyée
                    if (!$this->notificationAlreadySent($instance['id'], $notifyHour)) {
                        $this->sendDeadlineReminder($instance);
                        $this->markNotificationSent($instance['id'], $notifyHour);
                        $sent++;
                    }
                }
            }
        }
        
        $this->log("$sent notifications de rappel envoyées");
    }
    
    /**
     * Traiter la logique conditionnelle
     */
    private function processConditionalLogic() {
        $this->log("Traitement de la logique conditionnelle...");
        
        // Rechercher les instances avec conditions
        $stmt = $this->pdo->query("
            SELECT wi.*, wc.*
            FROM workflow_instances wi
            JOIN workflow_conditions wc ON wi.workflow_step_id = wc.workflow_step_id
            WHERE wi.status = 'pending'
        ");
        
        $instances = $stmt->fetchAll();
        $processed = 0;
        
        foreach ($instances as $instance) {
            try {
                if ($this->evaluateCondition($instance)) {
                    $this->applyConditionAction($instance);
                    $processed++;
                }
            } catch (Exception $e) {
                $this->log("Erreur condition instance {$instance['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("$processed conditions traitées");
    }
    
    /**
     * Nettoyer les données anciennes
     */
    private function cleanupOldData() {
        $this->log("Nettoyage des données anciennes...");
        
        // Supprimer les logs de notification anciens (> 30 jours)
        $stmt = $this->pdo->prepare("
            DELETE FROM workflow_notification_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        $this->log("$deleted anciens logs supprimés");
    }
    
    /**
     * Démarrer un workflow pour un dossier
     */
    private function startWorkflowForDossier($dossierId, $templateId) {
        // Marquer le dossier comme ayant un workflow démarré
        $stmt = $this->pdo->prepare("
            UPDATE dossiers 
            SET workflow_started = 1 
            WHERE id = ?
        ");
        $stmt->execute([$dossierId]);
        
        // Créer les instances pour toutes les étapes du template
        $stmt = $this->pdo->prepare("
            SELECT * FROM workflows 
            WHERE template_id = ? 
            ORDER BY ordre
        ");
        $stmt->execute([$templateId]);
        $steps = $stmt->fetchAll();
        
        foreach ($steps as $step) {
            $status = ($step['ordre'] == 1) ? 'active' : 'pending';
            $startedAt = ($step['ordre'] == 1) ? date('Y-m-d H:i:s') : null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO workflow_instances 
                (dossier_id, workflow_step_id, ordre, role_requis, status, started_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dossierId, 
                $step['id'], 
                $step['ordre'], 
                $step['role_requis'], 
                $status, 
                $startedAt
            ]);
        }
        
        $this->log("Workflow démarré pour dossier $dossierId");
        
        // Notifier les responsables
        $this->notifyWorkflowStart($dossierId);
    }
    
    /**
     * Évaluer les conditions d'approbation automatique
     */
    private function evaluateAutoApprovalConditions($instance) {
        // Logique d'évaluation des conditions
        // À implémenter selon les règles métier
        return true; // Placeholder
    }
    
    /**
     * Approuver automatiquement une étape
     */
    private function autoApproveStep($instanceId, $comments = 'Approbation automatique') {
        $stmt = $this->pdo->prepare("
            UPDATE workflow_instances 
            SET status = 'approved', 
                approved_at = NOW(),
                comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$comments, $instanceId]);
        
        // Démarrer l'étape suivante
        $this->startNextStep($instanceId);
    }
    
    /**
     * Escalader vers un rôle supérieur
     */
    private function escalateToRole($instanceId, $roleId) {
        $stmt = $this->pdo->prepare("
            UPDATE workflow_instances 
            SET role_requis = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$roleId, $instanceId]);
        
        $this->log("Escalade instance $instanceId vers rôle $roleId");
    }
    
    /**
     * Démarrer l'étape suivante du workflow
     */
    private function startNextStep($currentInstanceId) {
        // Récupérer l'instance actuelle
        $stmt = $this->pdo->prepare("
            SELECT * FROM workflow_instances WHERE id = ?
        ");
        $stmt->execute([$currentInstanceId]);
        $currentInstance = $stmt->fetch();
        
        if (!$currentInstance) return;
        
        // Rechercher l'étape suivante
        $nextOrder = $currentInstance['ordre'] + 1;
        $stmt = $this->pdo->prepare("
            UPDATE workflow_instances 
            SET status = 'active', started_at = NOW()
            WHERE dossier_id = ? AND ordre = ?
        ");
        $stmt->execute([$currentInstance['dossier_id'], $nextOrder]);
        
        if ($stmt->rowCount() == 0) {
            // Plus d'étapes, marquer le workflow comme terminé
            $this->completeWorkflow($currentInstance['dossier_id']);
        }
    }
    
    /**
     * Marquer un workflow comme terminé
     */
    private function completeWorkflow($dossierId) {
        $stmt = $this->pdo->prepare("
            UPDATE dossiers 
            SET workflow_completed = 1, 
                workflow_completed_at = NOW(),
                status = 'approuve'
            WHERE id = ?
        ");
        $stmt->execute([$dossierId]);
        
        $this->log("Workflow terminé pour dossier $dossierId");
        $this->notifyWorkflowCompletion($dossierId);
    }
    
    /**
     * Vérifier si une notification a déjà été envoyée
     */
    private function notificationAlreadySent($instanceId, $notifyHour) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM workflow_notification_log 
            WHERE instance_id = ? AND notification_type = 'deadline_reminder'
            AND notification_hour = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$instanceId, $notifyHour]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Marquer une notification comme envoyée
     */
    private function markNotificationSent($instanceId, $notifyHour) {
        $stmt = $this->pdo->prepare("
            INSERT INTO workflow_notification_log 
            (instance_id, notification_type, notification_hour, created_at)
            VALUES (?, 'deadline_reminder', ?, NOW())
        ");
        $stmt->execute([$instanceId, $notifyHour]);
    }
    
    /**
     * Envoyer un rappel de délai
     */
    private function sendDeadlineReminder($instance) {
        // Envoyer email
        $subject = "Rappel: Action requise sur dossier {$instance['reference']}";
        $message = "Une action est requise sur le dossier {$instance['reference']}. Délai restant: " . 
                  ($instance['deadline_hours'] - $instance['hours_elapsed']) . " heures.";
        
        sendEmail($instance['email'], $subject, $message);
    }
    
    /**
     * Évaluer une condition
     */
    private function evaluateCondition($instance) {
        // Récupérer les données du dossier
        $stmt = $this->pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
        $stmt->execute([$instance['dossier_id']]);
        $dossier = $stmt->fetch();
        
        if (!$dossier) return false;
        
        $value = null;
        switch ($instance['condition_type']) {
            case 'budget':
                $value = $dossier['budget'] ?? 0;
                break;
            case 'service':
                $value = $dossier['service'];
                break;
            case 'type':
                $value = $dossier['type'];
                break;
            case 'priority':
                $value = $dossier['priorite'] ?? 'normale';
                break;
        }
        
        return $this->compareValues($value, $instance['condition_operator'], $instance['condition_value']);
    }
    
    /**
     * Comparer des valeurs selon un opérateur
     */
    private function compareValues($value1, $operator, $value2) {
        switch ($operator) {
            case '=': return $value1 == $value2;
            case '!=': return $value1 != $value2;
            case '>': return $value1 > $value2;
            case '<': return $value1 < $value2;
            case '>=': return $value1 >= $value2;
            case '<=': return $value1 <= $value2;
            case 'LIKE': return strpos($value1, $value2) !== false;
            case 'IN': return in_array($value1, explode(',', $value2));
            default: return false;
        }
    }
    
    /**
     * Appliquer l'action d'une condition
     */
    private function applyConditionAction($instance) {
        switch ($instance['action_type']) {
            case 'skip':
                $this->autoApproveStep($instance['id'], 'Étape ignorée par condition');
                break;
            case 'branch':
                // Logique de branchement
                break;
            case 'require_additional':
                // Ajouter des étapes supplémentaires
                break;
        }
    }
    
    /**
     * Notifier le démarrage d'un workflow
     */
    private function notifyWorkflowStart($dossierId) {
        // Implémenter la notification
    }
    
    /**
     * Notifier la fin d'un workflow
     */
    private function notifyWorkflowCompletion($dossierId) {
        // Implémenter la notification
    }
    
    /**
     * Notifier les administrateurs d'une erreur
     */
    private function notifyAdministrators($exception) {
        $subject = "Erreur dans le service d'automatisation de workflow";
        $message = "Une erreur s'est produite:\n\n" . $exception->getMessage() . "\n\n" . $exception->getTraceAsString();
        
        // Récupérer les emails des administrateurs
        $stmt = $this->pdo->query("SELECT email FROM users WHERE role = 1");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $email) {
            sendEmail($email, $subject, $message);
        }
    }
    
    /**
     * Logger un message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
}

// Exécution du service
if (php_sapi_name() === 'cli' || !empty($_GET['manual'])) {
    $service = new WorkflowAutomationService();
    $service->run();
} else {
    echo "Ce script doit être exécuté en ligne de commande ou avec le paramètre ?manual=1";
}
?>
