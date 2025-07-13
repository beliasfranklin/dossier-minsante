<?php
/**
 * Téléchargement des logs système
 * Module de gestion des journaux d'activité
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/workflow_config.php';

requireAuth();
requirePermission(ROLE_ADMIN);

// Récupérer le type de log demandé
$logType = $_GET['type'] ?? 'all';
$format = $_GET['format'] ?? 'txt';
$days = (int)($_GET['days'] ?? 7);

try {
    // Fonction pour obtenir les logs d'erreur
    function getErrorLogs($days = 7) {
        $logFile = __DIR__ . '/../../logs/error.log';
        $logs = [];
        
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", $content);
            
            $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
            
            foreach ($lines as $line) {
                if (trim($line) && preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                    if ($matches[1] >= $cutoffDate) {
                        $logs[] = $line;
                    }
                }
            }
        }
        
        return $logs;
    }
    
    // Fonction pour obtenir les logs d'intégration
    function getIntegrationLogs($days = 7) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT 
                created_at,
                service_name,
                action,
                records_processed,
                errors,
                details
            FROM integration_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fonction pour obtenir les logs d'audit workflow
    function getWorkflowLogs($days = 7) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    wal.created_at,
                    wal.action,
                    wal.details,
                    wal.ip_address,
                    u.nom,
                    u.prenom,
                    d.reference as dossier_ref
                FROM workflow_audit_logs wal
                LEFT JOIN users u ON wal.user_id = u.id
                LEFT JOIN workflow_instances wi ON wal.workflow_instance_id = wi.id
                LEFT JOIN dossiers d ON wi.dossier_id = d.id
                WHERE wal.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY wal.created_at DESC
            ");
            $stmt->execute([$days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    // Fonction pour obtenir les logs de connexion
    function getLoginLogs($days = 7) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    created_at,
                    user_id,
                    ip_address,
                    user_agent,
                    action
                FROM auth_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Créer la table si elle n'existe pas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS auth_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    action VARCHAR(50) NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
            return [];
        }
    }
    
    // Collecter les logs selon le type demandé
    $allLogs = [];
    
    switch ($logType) {
        case 'error':
            $logs = getErrorLogs($days);
            foreach ($logs as $log) {
                $allLogs[] = ['type' => 'ERROR', 'message' => $log];
            }
            break;
            
        case 'integration':
            $logs = getIntegrationLogs($days);
            foreach ($logs as $log) {
                $message = sprintf("[%s] %s/%s - %d records, %d errors - %s",
                    $log['created_at'],
                    $log['service_name'],
                    $log['action'],
                    $log['records_processed'],
                    $log['errors'],
                    $log['details']
                );
                $allLogs[] = ['type' => 'INTEGRATION', 'message' => $message];
            }
            break;
            
        case 'workflow':
            $logs = getWorkflowLogs($days);
            foreach ($logs as $log) {
                $user = $log['nom'] ? $log['prenom'] . ' ' . $log['nom'] : 'Utilisateur inconnu';
                $dossier = $log['dossier_ref'] ? " (Dossier: {$log['dossier_ref']})" : '';
                $message = sprintf("[%s] %s - %s - IP: %s%s - %s",
                    $log['created_at'],
                    $log['action'],
                    $user,
                    $log['ip_address'],
                    $dossier,
                    $log['details']
                );
                $allLogs[] = ['type' => 'WORKFLOW', 'message' => $message];
            }
            break;
            
        case 'auth':
            $logs = getLoginLogs($days);
            foreach ($logs as $log) {
                $message = sprintf("[%s] User ID: %s - %s - IP: %s",
                    $log['created_at'],
                    $log['user_id'],
                    $log['action'],
                    $log['ip_address']
                );
                $allLogs[] = ['type' => 'AUTH', 'message' => $message];
            }
            break;
            
        default: // 'all'
            $errorLogs = getErrorLogs($days);
            foreach ($errorLogs as $log) {
                $allLogs[] = ['type' => 'ERROR', 'message' => $log];
            }
            
            $integrationLogs = getIntegrationLogs($days);
            foreach ($integrationLogs as $log) {
                $message = sprintf("[%s] INTEGRATION %s/%s - %d records, %d errors - %s",
                    $log['created_at'],
                    $log['service_name'],
                    $log['action'],
                    $log['records_processed'],
                    $log['errors'],
                    $log['details']
                );
                $allLogs[] = ['type' => 'INTEGRATION', 'message' => $message];
            }
            
            $workflowLogs = getWorkflowLogs($days);
            foreach ($workflowLogs as $log) {
                $user = $log['nom'] ? $log['prenom'] . ' ' . $log['nom'] : 'Utilisateur inconnu';
                $dossier = $log['dossier_ref'] ? " (Dossier: {$log['dossier_ref']})" : '';
                $message = sprintf("[%s] WORKFLOW %s - %s - IP: %s%s - %s",
                    $log['created_at'],
                    $log['action'],
                    $user,
                    $log['ip_address'],
                    $dossier,
                    $log['details']
                );
                $allLogs[] = ['type' => 'WORKFLOW', 'message' => $message];
            }
            break;
    }
    
    // Trier les logs par date (plus récents en premier)
    usort($allLogs, function($a, $b) {
        return strcmp($b['message'], $a['message']);
    });
    
    // Générer le nom du fichier
    $filename = sprintf('logs_%s_%s_%ddays_%s.%s',
        $logType,
        date('Y-m-d'),
        $days,
        date('His'),
        $format
    );
    
    // Définir les en-têtes selon le format
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Créer le CSV
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Type', 'Message']);
        
        foreach ($allLogs as $log) {
            fputcsv($output, [$log['type'], $log['message']]);
        }
        
        fclose($output);
    } else {
        // Format texte par défaut
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo "# Logs Système MINSANTE\n";
        echo "# Généré le: " . date('Y-m-d H:i:s') . "\n";
        echo "# Type: " . strtoupper($logType) . "\n";
        echo "# Période: " . $days . " derniers jours\n";
        echo "# Total d'entrées: " . count($allLogs) . "\n";
        echo str_repeat("=", 80) . "\n\n";
        
        foreach ($allLogs as $log) {
            echo "[{$log['type']}] {$log['message']}\n";
        }
    }
    
} catch (Exception $e) {
    // En cas d'erreur, retourner une réponse d'erreur
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors de la génération des logs: ' . $e->getMessage()
    ]);
}
?>
