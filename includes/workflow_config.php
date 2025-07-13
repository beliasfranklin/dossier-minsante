<?php
/**
 * Configuration et fonctions pour le système de workflow
 * Consolidation intelligente des besoins du module workflow
 */

// Inclure les fichiers de base nécessaires
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language_manager.php';

// Fonction pour enregistrer les erreurs
if (!function_exists('logError')) {
    function logError($message) {
        $logFile = __DIR__ . '/../logs/error.log';
        $logDir = dirname($logFile);
        
        // Créer le répertoire logs s'il n'existe pas
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] ERROR: {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// Configuration du workflow
define('WORKFLOW_STATUS_PENDING', 'pending');
define('WORKFLOW_STATUS_IN_PROGRESS', 'in_progress');
define('WORKFLOW_STATUS_COMPLETED', 'completed');
define('WORKFLOW_STATUS_REJECTED', 'rejected');
define('WORKFLOW_STATUS_CANCELLED', 'cancelled');

// Configuration des signatures
define('SIGNATURE_TYPE_SIMPLE', 'simple');
define('SIGNATURE_TYPE_ADVANCED', 'advanced');
define('SIGNATURE_TYPE_QUALIFIED', 'qualified');

// Configuration des notifications workflow
define('WORKFLOW_NOTIFY_EMAIL', true);
define('WORKFLOW_NOTIFY_SMS', false);
define('WORKFLOW_NOTIFY_INTERNAL', true);

/**
 * Vérifier si un utilisateur peut signer une instance de workflow
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('canUserSignInstance')) {
    function canUserSignInstance($instanceId, $userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM workflow_instances wi 
                JOIN workflow_steps ws ON wi.current_step_id = ws.id 
                WHERE wi.id = ? AND ws.assigned_user_id = ? AND wi.status = 'in_progress'
            ");
            $stmt->execute([$instanceId, $userId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Erreur canUserSignInstance: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Vérifier le PIN utilisateur pour la signature
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('verifyUserPin')) {
    function verifyUserPin($userId, $pin) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT signature_pin 
                FROM users 
                WHERE id = ? AND signature_pin IS NOT NULL
            ");
            $stmt->execute([$userId]);
            $storedPin = $stmt->fetchColumn();
            
            if ($storedPin) {
                return password_verify($pin, $storedPin);
            }
            
            // Si pas de PIN configuré, on accepte (mode dégradé)
            return true;
        } catch (Exception $e) {
            error_log("Erreur verifyUserPin: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Générer le hash d'un document pour signature
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('generateDocumentHash')) {
    function generateDocumentHash($instance) {
        return hash('sha256', json_encode([
            'instance_id' => $instance['id'],
            'dossier_id' => $instance['dossier_id'],
            'reference' => $instance['reference'],
            'timestamp' => time()
        ]));
    }
}

/**
 * Générer le hash de signature
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('generateSignatureHash')) {
    function generateSignatureHash($userId, $documentHash, $signatureData) {
        return hash('sha256', $userId . $documentHash . $signatureData . time());
    }
}

/**
 * Approuver une étape de workflow avec signature
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('approveWorkflowStepWithSignature')) {
    function approveWorkflowStepWithSignature($instanceId, $userId) {
        global $pdo;
        
        try {
            // Marquer l'étape actuelle comme signée
            $stmt = $pdo->prepare("
                UPDATE workflow_instances 
                SET status = 'completed', 
                    completed_at = NOW(),
                    completed_by = ?
                WHERE id = ?
            ");
            return $stmt->execute([$userId, $instanceId]);
        } catch (Exception $e) {
            error_log("Erreur approveWorkflowStepWithSignature: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Obtenir les instances de workflow pour un utilisateur
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('getUserWorkflowInstances')) {
    function getUserWorkflowInstances($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT wi.*, d.reference, d.titre, d.description,
                       u.nom as assignee_nom, u.prenom as assignee_prenom
                FROM workflow_instances wi
                JOIN dossiers d ON wi.dossier_id = d.id
                LEFT JOIN users u ON wi.assigned_to = u.id
                WHERE wi.assigned_to = ? OR wi.created_by = ?
                ORDER BY wi.created_at DESC
            ");
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getUserWorkflowInstances: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Créer les tables nécessaires si elles n'existent pas
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('ensureWorkflowTables')) {
    function ensureWorkflowTables() {
        global $pdo;
        
        try {
            // Table des instances de workflow
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS workflow_instances (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    dossier_id INT NOT NULL,
                    workflow_name VARCHAR(255) NOT NULL,
                    status ENUM('pending', 'in_progress', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
                    assigned_to INT,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP NULL,
                    completed_by INT NULL,
                    data JSON,
                    FOREIGN KEY (dossier_id) REFERENCES dossiers(id),
                    FOREIGN KEY (assigned_to) REFERENCES users(id),
                    FOREIGN KEY (created_by) REFERENCES users(id),
                    FOREIGN KEY (completed_by) REFERENCES users(id)
                )
            ");
            
            // Table des signatures
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS workflow_signatures (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    workflow_instance_id INT NOT NULL,
                    user_id INT NOT NULL,
                    signature_hash VARCHAR(255) NOT NULL,
                    signature_data TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (workflow_instance_id) REFERENCES workflow_instances(id),
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    UNIQUE KEY unique_signature (workflow_instance_id, user_id)
                )
            ");
            
            // Table des intégrations (pour la configuration du système)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS integrations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    service_name VARCHAR(100) NOT NULL DEFAULT 'system',
                    config_key VARCHAR(100) NOT NULL,
                    config_value TEXT,
                    is_active BOOLEAN DEFAULT 1,
                    system_name VARCHAR(50),
                    api_key VARCHAR(255),
                    last_sync TIMESTAMP NULL,
                    status VARCHAR(20),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_service_config (service_name, config_key)
                )
            ");
            
            // Table des logs d'audit pour les workflows
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS workflow_audit_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    workflow_instance_id INT NOT NULL,
                    user_id INT NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (workflow_instance_id) REFERENCES workflow_instances(id),
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
            
            // Table des logs d'intégration (pour les statistiques et le monitoring)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS integration_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    service_name VARCHAR(100) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    records_processed INT DEFAULT 0,
                    errors INT DEFAULT 0,
                    details TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_service_action (service_name, action),
                    INDEX idx_created_at (created_at)
                )
            ");
            
            // Ajouter colonne signature_pin si elle n'existe pas
            $pdo->exec("
                ALTER TABLE users 
                ADD COLUMN IF NOT EXISTS signature_pin VARCHAR(255) NULL
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur ensureWorkflowTables: " . $e->getMessage());
            return false;
        }
    }
}

// Initialisation automatique des tables
ensureWorkflowTables();

/**
 * Fonctions utilitaires pour les workflows
 * (protégées contre la redéclaration)
 */

if (!function_exists('getWorkflowStatusText')) {
    function getWorkflowStatusText($status) {
        $statusMap = [
            'pending' => _t('En attente'),
            'in_progress' => _t('En cours'),
            'completed' => _t('Terminé'),
            'rejected' => _t('Rejeté'),
            'cancelled' => _t('Annulé')
        ];
        
        return $statusMap[$status] ?? $status;
    }
}

if (!function_exists('getWorkflowStatusClass')) {
    function getWorkflowStatusClass($status) {
        $classMap = [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary'
        ];
        
        return $classMap[$status] ?? 'secondary';
    }
}

/**
 * Logger pour les workflows
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('logWorkflowAction')) {
    function logWorkflowAction($action, $details = []) {
        error_log(sprintf(
            "[WORKFLOW] %s - User: %s - Details: %s",
            $action,
            $_SESSION['user_id'] ?? 'anonymous',
            json_encode($details)
        ));
    }
}

/**
 * Obtenir les informations de l'utilisateur courant
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        global $pdo;
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT id, nom, prenom, email, role, service, 
                       CONCAT(prenom, ' ', nom) as nom_complet,
                       signature_pin IS NOT NULL as has_signature_pin
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Ajouter des informations de session utiles
                $user['session_role'] = $_SESSION['role'] ?? $user['role'];
                $user['language'] = $_SESSION['language'] ?? 'fr';
                $user['is_admin'] = ($user['role'] ?? 99) <= 1; // ROLE_ADMIN = 1
                $user['is_gestionnaire'] = ($user['role'] ?? 99) <= 2; // ROLE_GESTIONNAIRE = 2
            }
            
            return $user;
        } catch (Exception $e) {
            error_log("Erreur getCurrentUser: " . $e->getMessage());
            return null;
        }
    }
}

// Fonction pour enregistrer les actions de signature
if (!function_exists('logSignatureAction')) {
    function logSignatureAction($instanceId, $userId, $action, $details = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO workflow_audit_logs 
                (workflow_instance_id, user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $instanceId,
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            return true;
        } catch (Exception $e) {
            logError("Erreur lors de l'enregistrement de l'action de signature: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Vérifier si l'utilisateur courant peut effectuer une action
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('canCurrentUserPerformAction')) {
    function canCurrentUserPerformAction($action, $resourceId = null) {
        $user = getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        switch ($action) {
            case 'sign_workflow':
                return $user['is_gestionnaire'] || canUserSignInstance($resourceId, $user['id']);
                
            case 'create_workflow':
                return $user['is_gestionnaire'];
                
            case 'approve_workflow':
                return $user['is_admin'] || $user['is_gestionnaire'];
                
            case 'view_all_workflows':
                return $user['is_admin'];
                
            default:
                return false;
        }
    }
}

/**
 * Obtenir le nom d'affichage de l'utilisateur courant
 * (fonction protégée contre la redéclaration)
 */
if (!function_exists('getCurrentUserDisplayName')) {
    function getCurrentUserDisplayName() {
        $user = getCurrentUser();
        
        if (!$user) {
            return _t('Utilisateur inconnu');
        }
        
        return $user['nom_complet'] ?? ($user['prenom'] . ' ' . $user['nom']) ?? $user['email'] ?? 'Utilisateur #' . $user['id'];
    }
}

// Fonctions de vérification pour les signatures
if (!function_exists('verifySignatureIntegrity')) {
    function verifySignatureIntegrity($signature) {
        try {
            // Vérifier l'intégrité de la signature
            $expectedHash = hash('sha256', $signature['user_id'] . $signature['document_hash'] . $signature['signature_data']);
            return $signature['signature_hash'] === $expectedHash;
        } catch (Exception $e) {
            logError("Erreur vérification intégrité signature: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('verifyUserCertificate')) {
    function verifyUserCertificate($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM user_certificates 
                WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
            ");
            $stmt->execute([$userId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            logError("Erreur vérification certificat utilisateur: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('verifyTimestamp')) {
    function verifyTimestamp($timestamp) {
        try {
            // Vérifier que le timestamp n'est pas trop ancien (ex: 30 jours)
            $maxAge = 30 * 24 * 3600; // 30 jours en secondes
            $timestampUnix = strtotime($timestamp);
            $currentTime = time();
            
            return ($currentTime - $timestampUnix) <= $maxAge;
        } catch (Exception $e) {
            logError("Erreur vérification timestamp: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('calculateIntegrityScore')) {
    function calculateIntegrityScore($signature) {
        $score = 0;
        
        // Vérification de l'intégrité de base (40 points)
        if (verifySignatureIntegrity($signature)) {
            $score += 40;
        }
        
        // Vérification du certificat (30 points)
        if (verifyUserCertificate($signature['user_id'])) {
            $score += 30;
        }
        
        // Vérification du timestamp (20 points)
        if (verifyTimestamp($signature['signed_at'])) {
            $score += 20;
        }
        
        // Vérification que la signature n'a pas été révoquée (10 points)
        if (!$signature['is_revoked']) {
            $score += 10;
        }
        
        return $score;
    }
}

// Fonction pour révoquer une signature
if (!function_exists('revokeSignature')) {
    function revokeSignature($signatureId, $userId, $reason = '') {
        global $pdo;
        
        try {
            // Vérifier que l'utilisateur a le droit de révoquer cette signature
            $stmt = $pdo->prepare("
                SELECT ws.*, wi.dossier_id
                FROM workflow_signatures ws
                JOIN workflow_instances wi ON ws.workflow_instance_id = wi.id
                WHERE ws.id = ?
            ");
            $stmt->execute([$signatureId]);
            $signature = $stmt->fetch();
            
            if (!$signature) {
                return ['success' => false, 'message' => 'Signature non trouvée'];
            }
            
            // Vérifier les permissions (seul le signataire ou un admin peut révoquer)
            $user = getCurrentUser();
            if (!$user || ($signature['user_id'] != $userId && $user['role'] !== 'admin')) {
                return ['success' => false, 'message' => 'Permission insuffisante pour révoquer cette signature'];
            }
            
            // Révoquer la signature
            $stmt = $pdo->prepare("
                UPDATE workflow_signatures 
                SET is_revoked = 1, revoked_at = NOW(), revocation_reason = ?
                WHERE id = ?
            ");
            $success = $stmt->execute([$reason, $signatureId]);
            
            if ($success) {
                // Enregistrer l'action
                logSignatureAction($signature['workflow_instance_id'], $userId, 'revoke', 
                    "Signature révoquée. Raison: " . $reason);
                
                return [
                    'success' => true, 
                    'message' => 'Signature révoquée avec succès'
                ];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de la révocation'];
            }
            
        } catch (Exception $e) {
            logError("Erreur révocation signature: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur système lors de la révocation'];
        }
    }
}

// Fonction pour enregistrer les logs d'intégration
if (!function_exists('logIntegrationAction')) {
    function logIntegrationAction($serviceName, $action, $recordsProcessed = 0, $errors = 0, $details = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO integration_logs 
                (service_name, action, records_processed, errors, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $serviceName,
                $action,
                $recordsProcessed,
                $errors,
                $details
            ]);
            
            return true;
        } catch (Exception $e) {
            logError("Erreur lors de l'enregistrement du log d'intégration: " . $e->getMessage());
            return false;
        }
    }
}

// Fonction pour obtenir les statistiques d'intégration
if (!function_exists('getIntegrationStats')) {
    function getIntegrationStats($days = 30) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    service_name,
                    action,
                    COUNT(*) as total_operations,
                    SUM(records_processed) as total_records,
                    SUM(errors) as total_errors,
                    MAX(created_at) as last_sync
                FROM integration_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY service_name, action
                ORDER BY last_sync DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Erreur lors de la récupération des statistiques d'intégration: " . $e->getMessage());
            return [];
        }
    }
}
?>
