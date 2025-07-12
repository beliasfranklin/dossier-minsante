<?php
/**
 * API AJAX pour le dashboard personnalisable
 * Gère les requêtes temps réel et les opérations sur les widgets
 */

require_once '../../includes/config.php';
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Action non spécifiée'];

require_once 'dashboard_manager.php';
$dashboardManager = new DashboardManager($_SESSION['user_id']);

try {
    switch ($action) {
        case 'get_dashboard':
            $dashboardId = $_GET['dashboard_id'] ?? null;
            $dashboard = $dashboardManager->getUserDashboard($dashboardId);
            if ($dashboard) {
                $response = ['success' => true, 'data' => $dashboard];
            } else {
                $response = ['success' => false, 'error' => 'Dashboard non trouvé'];
            }
            break;
            
        case 'get_widget_data':
            $widgetType = $_GET['widget_type'] ?? '';
            $config = json_decode($_GET['config'] ?? '{}', true);
            
            if (empty($widgetType)) {
                $response = ['success' => false, 'error' => 'Type de widget requis'];
                break;
            }
            
            $data = $dashboardManager->getWidgetData($widgetType, $config);
            $response = ['success' => true, 'data' => $data];
            break;
            
        case 'update_widget_layout':
            $widgetId = $_POST['widget_id'] ?? 0;
            $x = $_POST['x'] ?? 0;
            $y = $_POST['y'] ?? 0;
            $width = $_POST['width'] ?? 4;
            $height = $_POST['height'] ?? 3;
            
            if ($dashboardManager->updateWidgetLayout($widgetId, $x, $y, $width, $height)) {
                $response = ['success' => true, 'message' => 'Layout mis à jour'];
            } else {
                $response = ['success' => false, 'error' => 'Erreur lors de la mise à jour'];
            }
            break;
            
        case 'add_widget':
            $dashboardId = $_POST['dashboard_id'] ?? 0;
            $widgetType = $_POST['widget_type'] ?? '';
            $x = $_POST['x'] ?? 0;
            $y = $_POST['y'] ?? 0;
            $width = $_POST['width'] ?? 4;
            $height = $_POST['height'] ?? 3;
            $config = json_decode($_POST['config'] ?? '{}', true);
            
            if ($dashboardManager->addWidget($dashboardId, $widgetType, $x, $y, $width, $height, $config)) {
                $response = ['success' => true, 'message' => 'Widget ajouté'];
            } else {
                $response = ['success' => false, 'error' => 'Erreur lors de l\'ajout'];
            }
            break;
            
        case 'remove_widget':
            $widgetId = $_POST['widget_id'] ?? 0;
            
            if ($dashboardManager->removeWidget($widgetId)) {
                $response = ['success' => true, 'message' => 'Widget supprimé'];
            } else {
                $response = ['success' => false, 'error' => 'Erreur lors de la suppression'];
            }
            break;
            
        case 'get_available_widgets':
            $widgets = $dashboardManager->getAvailableWidgets();
            $response = ['success' => true, 'data' => $widgets];
            break;
            
        case 'get_preferences':
            $preferences = $dashboardManager->getUserPreferences();
            $response = ['success' => true, 'data' => $preferences];
            break;
            
        case 'update_preferences':
            $preferences = $_POST;
            unset($preferences['action']);
            
            if ($dashboardManager->updatePreferences($preferences)) {
                $response = ['success' => true, 'message' => 'Préférences mises à jour'];
            } else {
                $response = ['success' => false, 'error' => 'Erreur lors de la mise à jour'];
            }
            break;
            
        case 'get_notifications':
            // Récupération des notifications en temps réel
            $notifications = getRealtimeNotifications();
            $response = ['success' => true, 'data' => $notifications];
            break;
            
        case 'mark_notification_read':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (markNotificationAsRead($notificationId, $_SESSION['user_id'])) {
                $response = ['success' => true, 'message' => 'Notification marquée comme lue'];
            } else {
                $response = ['success' => false, 'error' => 'Erreur lors de la mise à jour'];
            }
            break;
            
        case 'get_system_stats':
            // Statistiques système pour le monitoring
            $stats = getSystemStats();
            $response = ['success' => true, 'data' => $stats];
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Action non reconnue'];
            break;
    }
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Erreur serveur'];
}

echo json_encode($response);

/**
 * Récupère les notifications en temps réel
 */
function getRealtimeNotifications() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT n.*, d.reference as dossier_reference, d.titre as dossier_titre
        FROM notifications n
        LEFT JOIN dossiers d ON n.related_id = d.id AND n.type = 'dossier'
        WHERE n.user_id = ? AND n.is_read = 0
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque une notification comme lue
 */
function markNotificationAsRead($notificationId, $userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notificationId, $userId]);
}

/**
 * Récupère les statistiques système
 */
function getSystemStats() {
    global $conn;
    
    $stats = [];
    
    // Statistiques générales
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_dossiers,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as dossiers_today,
            COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as dossiers_actifs,
            COUNT(CASE WHEN deadline < NOW() AND status != 'valide' THEN 1 END) as dossiers_retard
        FROM dossiers
    ");
    $stmt->execute();
    $stats['general'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Utilisateurs connectés (simulation - nécessiterait une table de sessions)
    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $stmt->execute();
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Performance base de données (dernières requêtes)
    $stats['performance'] = [
        'response_time' => round(microtime(true) * 1000, 2) . ' ms',
        'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
    ];
    
    return $stats;
}
?>
