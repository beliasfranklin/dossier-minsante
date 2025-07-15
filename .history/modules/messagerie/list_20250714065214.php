<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/PreferencesManager.php';
requireAuth();

$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);
$currentTheme = $preferencesManager->getCurrentTheme();
$themeVariables = $preferencesManager->getThemeVariables();

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'send_message':
            $result = sendMessage($_POST);
            echo json_encode($result);
            exit;
            
        case 'mark_read':
            $messageId = (int)$_POST['message_id'];
            $result = markMessageAsRead($messageId);
            echo json_encode($result);
            exit;
            
        case 'delete_message':
            $messageId = (int)$_POST['message_id'];
            $result = deleteMessage($messageId);
            echo json_encode($result);
            exit;
            
        case 'mark_all_read':
            $result = markAllMessagesAsRead();
            echo json_encode($result);
            exit;
    }
}

/**
 * Envoyer un nouveau message
 */
function sendMessage($data) {
    try {
        $recipientId = (int)($data['recipient_id'] ?? 0);
        $subject = cleanInput($data['subject'] ?? '');
        $content = cleanInput($data['content'] ?? '');
        $senderId = $_SESSION['user_id'];
        
        if (empty($subject) || empty($content) || $recipientId <= 0) {
            return ['success' => false, 'message' => 'Tous les champs sont requis'];
        }
        
        // Vérifier que le destinataire existe
        $recipient = fetchOne("SELECT id, name FROM users WHERE id = ?", [$recipientId]);
        if (!$recipient) {
            return ['success' => false, 'message' => 'Destinataire introuvable'];
        }
        
        executeQuery(
            "INSERT INTO messages (sender_id, recipient_id, subject, content, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$senderId, $recipientId, $subject, $content]
        );
        
        $messageId = getLastInsertId();
        
        // Créer une notification
        createNotification(
            $recipientId,
            "Nouveau message : $subject",
            "Vous avez reçu un nouveau message de " . $_SESSION['user_name'],
            'message',
            $messageId
        );
        
        logAction($senderId, 'message_sent', $messageId, "Message envoyé à {$recipient['name']}");
        
        return ['success' => true, 'message' => 'Message envoyé avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Marquer un message comme lu
 */
function markMessageAsRead($messageId) {
    try {
        executeQuery(
            "UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?",
            [$messageId, $_SESSION['user_id']]
        );
        
        return ['success' => true, 'message' => 'Message marqué comme lu'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Supprimer un message
 */
function deleteMessage($messageId) {
    try {
        $message = fetchOne(
            "SELECT * FROM messages WHERE id = ? AND recipient_id = ?",
            [$messageId, $_SESSION['user_id']]
        );
        
        if (!$message) {
            return ['success' => false, 'message' => 'Message introuvable'];
        }
        
        executeQuery(
            "UPDATE messages SET deleted_by_recipient = 1 WHERE id = ?",
            [$messageId]
        );
        
        logAction($_SESSION['user_id'], 'message_deleted', $messageId, "Message supprimé");
        
        return ['success' => true, 'message' => 'Message supprimé'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Marquer tous les messages comme lus
 */
function markAllMessagesAsRead() {
    try {
        executeQuery(
            "UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND is_read = 0",
            [$_SESSION['user_id']]
        );
        
        return ['success' => true, 'message' => 'Tous les messages marqués comme lus'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filtres
$filterStatus = $_GET['status'] ?? 'all';
$filterSender = $_GET['sender'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Construction de la requête
$whereConditions = ["m.recipient_id = ?", "m.deleted_by_recipient = 0"];
$params = [$_SESSION['user_id']];

if ($filterStatus === 'unread') {
    $whereConditions[] = "m.is_read = 0";
} elseif ($filterStatus === 'read') {
    $whereConditions[] = "m.is_read = 1";
}

if (!empty($filterSender)) {
    $whereConditions[] = "m.sender_id = ?";
    $params[] = $filterSender;
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.subject LIKE ? OR m.content LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

// Récupération des messages avec pagination
$messages = fetchAll("
    SELECT m.*, u.name as sender_name, u.email as sender_email
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE $whereClause
    ORDER BY m.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Compter le total pour la pagination
$totalMessages = fetchOne("
    SELECT COUNT(*) as count
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE $whereClause
", $params)['count'];

$totalPages = ceil($totalMessages / $perPage);

// Statistiques
$stats = [
    'total' => fetchOne("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND deleted_by_recipient = 0", [$_SESSION['user_id']])['count'],
    'unread' => fetchOne("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0 AND deleted_by_recipient = 0", [$_SESSION['user_id']])['count'],
    'read' => fetchOne("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 1 AND deleted_by_recipient = 0", [$_SESSION['user_id']])['count']
];

// Liste des expéditeurs pour le filtre
$senders = fetchAll("
    SELECT DISTINCT u.id, u.name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.recipient_id = ? AND m.deleted_by_recipient = 0
    ORDER BY u.name
", [$_SESSION['user_id']]);

// Liste des utilisateurs pour nouveau message
$users = fetchAll("SELECT id, name, role FROM users WHERE id != ? ORDER BY name", [$_SESSION['user_id']]);

// Messages de session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie Interne - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* === VARIABLES CSS DYNAMIQUES === */
        :root {
            <?= $preferencesManager->generateThemeCSS() ?>
        }
        
        /* === MESSAGERIE MODERNE - DESIGN SYSTEM === */
        .messagerie-page {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            min-height: calc(100vh - 70px);
            padding: 2rem 0;
            position: relative;
            overflow-x: hidden;
        }
        
        .messagerie-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(var(--primary-500-rgb), 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(var(--secondary-500-rgb), 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(var(--accent-500-rgb), 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }
        
        .messagerie-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
            position: relative;
            z-index: 2;
        }
        
        /* === BREADCRUMB MODERNE === */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            color: var(--gray-600);
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInDown 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .breadcrumb::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500), var(--accent-500));
            border-radius: var(--radius-2xl) var(--radius-2xl) 0 0;
        }
        
        .breadcrumb a {
            color: var(--primary-600);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
        }
        
        .breadcrumb a:hover {
            color: var(--primary-700);
            background: var(--primary-50);
            transform: translateY(-1px);
        }
        
        .breadcrumb i {
            opacity: 0.7;
        }
        
        /* === HEADER AVANCÉ === */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-3xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInDown 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500), var(--accent-500));
            border-radius: var(--radius-3xl) var(--radius-3xl) 0 0;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 0%, rgba(var(--primary-500-rgb), 0.05) 50%, transparent 100%);
            transform: rotate(45deg);
            pointer-events: none;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .header-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .header-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            border-radius: var(--radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.25rem;
            font-weight: 700;
            box-shadow: var(--shadow-2xl);
            position: relative;
            animation: iconPulse 4s ease-in-out infinite;
        }
        
        .header-icon::before {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            border-radius: var(--radius-2xl);
            z-index: -1;
            opacity: 0.3;
            animation: iconGlow 3s ease-in-out infinite alternate;
        }
        
        .header-icon .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, var(--danger-500), var(--warning-500));
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: var(--shadow-lg);
            animation: badgePulse 2s infinite;
            border: 3px solid white;
        }
        
        @keyframes iconPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.05) rotate(1deg); }
        }
        
        @keyframes iconGlow {
            0% { opacity: 0.3; transform: scale(1); }
            100% { opacity: 0.6; transform: scale(1.1); }
        }
        
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); box-shadow: var(--shadow-lg); }
            50% { transform: scale(1.1); box-shadow: var(--shadow-2xl); }
        }
        
        .header-details h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .header-details .subtitle {
            color: var(--gray-600);
            font-size: 1.125rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        /* === LAYOUT PRINCIPAL === */
        .main-content {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        /* === SIDEBAR MODERNE === */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: sticky;
            top: 2rem;
        }
        
        .sidebar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideInLeft 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .sidebar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            transform: scaleX(0);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: left;
        }
        
        .sidebar-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }
        
        .sidebar-card:hover::before {
            transform: scaleX(1);
        }
        
        .sidebar-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0 0 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-card h3 i {
            color: var(--primary-500);
            font-size: 1.125rem;
        }
        
        /* === STATISTIQUES GRID === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            padding: 1.5rem 1rem;
            border-radius: var(--radius-xl);
            text-align: center;
            border: 2px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .stat-card:hover {
            background: white;
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-300);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-number {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--primary-600);
            display: block;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        /* === CONTAINER MESSAGES === */
        .messages-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-3xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInRight 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .messages-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500), var(--accent-500));
            border-radius: var(--radius-3xl) var(--radius-3xl) 0 0;
        }
        
        /* === SECTION HEADER === */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--gray-100);
            position: relative;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            border-radius: 2px;
        }
        
        .section-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }
        
        .section-title i {
            color: var(--primary-500);
            font-size: 1.5rem;
        }
        
        /* === FILTRES AVANCÉS === */
        .filters-container {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-2xl);
            border: 1px solid var(--gray-200);
        }
        
        .filter-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .form-input, .form-select {
            padding: 0.75rem 1.25rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-xl);
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-width: 140px;
            box-shadow: var(--shadow-sm);
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(var(--primary-500-rgb), 0.1);
            transform: translateY(-2px);
        }
        
        .search-input {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        
        .search-input input {
            width: 100%;
            padding-left: 3rem;
            background: white;
            border-radius: var(--radius-xl);
        }
        
        .search-input i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.125rem;
        }
        
        /* === LISTE MESSAGES MODERNE === */
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .message-item {
            background: var(--gray-50);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            border: 2px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: messageSlideIn 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .message-item:nth-child(1) { animation-delay: 0.1s; }
        .message-item:nth-child(2) { animation-delay: 0.2s; }
        .message-item:nth-child(3) { animation-delay: 0.3s; }
        .message-item:nth-child(4) { animation-delay: 0.4s; }
        .message-item:nth-child(5) { animation-delay: 0.5s; }
        
        @keyframes messageSlideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: var(--gray-300);
            transition: all 0.4s ease;
        }
        
        .message-item.unread {
            background: linear-gradient(135deg, var(--primary-50), var(--secondary-50));
            border-color: var(--primary-200);
            box-shadow: var(--shadow-md);
        }
        
        .message-item.unread::before {
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            width: 6px;
        }
        
        .message-item:hover {
            background: white;
            border-color: var(--primary-300);
            transform: translateY(-6px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }
        
        .message-item:hover::before {
            width: 8px;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            gap: 1rem;
        }
        
        .message-subject {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            flex: 1;
            line-height: 1.3;
        }
        
        .message-date {
            font-size: 0.875rem;
            color: var(--gray-500);
            white-space: nowrap;
            font-weight: 500;
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .message-sender {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .message-sender i {
            color: var(--primary-500);
        }
        
        .message-actions {
            display: flex;
            gap: 0.75rem;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .message-item:hover .message-actions {
            opacity: 1;
        }
        
        .message-preview {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.6;
            margin-top: 1rem;
            max-height: 4em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .unread-indicator {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 14px;
            height: 14px;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            border-radius: 50%;
            animation: indicatorPulse 2s infinite;
            box-shadow: var(--shadow-md);
        }
        
        @keyframes indicatorPulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(var(--primary-500-rgb), 0.7);
            }
            50% { 
                opacity: 0.8; 
                transform: scale(1.1);
                box-shadow: 0 0 0 8px rgba(var(--primary-500-rgb), 0);
            }
        }
        
        /* === PAGINATION MODERNE === */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .pagination-btn {
            padding: 0.75rem 1.25rem;
            border: 2px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            border-radius: var(--radius-xl);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        
        .pagination-btn:hover {
            border-color: var(--primary-300);
            background: var(--primary-50);
            color: var(--primary-700);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            color: white;
            border-color: var(--primary-500);
            box-shadow: var(--shadow-lg);
        }
        
        /* === FORMULAIRES AVANCÉS === */
        .form-container {
            background: var(--gray-50);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            border: 1px solid var(--gray-200);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: var(--primary-500);
            width: 18px;
        }
        
        .form-label .required {
            color: var(--danger-500);
        }
        
        .form-textarea {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-xl);
            font-size: 0.875rem;
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            resize: vertical;
            min-height: 120px;
            box-sizing: border-box;
            font-family: inherit;
            line-height: 1.5;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(var(--primary-500-rgb), 0.1);
            transform: translateY(-2px);
        }
        
        /* === BOUTONS MODERNES === */
        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            color: white;
            border: 2px solid transparent;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-2xl);
        }
        
        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }
        
        .btn-secondary:hover {
            background: var(--gray-50);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gray-300);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-500), var(--warning-500));
            color: white;
            border: 2px solid transparent;
        }
        
        .btn-danger:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-2xl);
        }
        
        /* === ALERTES MODERNES === */
        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: var(--radius-2xl);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
            animation: alertSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
        }
        
        .alert-success {
            background: linear-gradient(135deg, var(--success-50), var(--success-100));
            color: var(--success-700);
            border-color: var(--success-200);
        }
        
        .alert-success::before {
            background: var(--success-500);
        }
        
        .alert-error {
            background: linear-gradient(135deg, var(--danger-50), var(--danger-100));
            color: var(--danger-700);
            border-color: var(--danger-200);
        }
        
        .alert-error::before {
            background: var(--danger-500);
        }
        
        @keyframes alertSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* === ÉTAT VIDE === */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--primary-400);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* === MODAL MODERNE === */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
        }
        
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 2.5rem;
            border-radius: var(--radius-3xl);
            width: 90%;
            max-width: 650px;
            box-shadow: var(--shadow-2xl);
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            border-radius: var(--radius-3xl) var(--radius-3xl) 0 0;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* === ANIMATIONS GÉNÉRALES === */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* === RESPONSIVE DESIGN === */
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 320px 1fr;
                gap: 1.5rem;
            }
            
            .header-icon {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .sidebar {
                order: -1;
                position: static;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
        }
        
        @media (max-width: 768px) {
            .messagerie-container {
                padding: 0 0.75rem;
            }
            
            .page-header {
                padding: 2rem;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .header-info {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .header-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .header-icon {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }
            
            .header-details h1 {
                font-size: 2rem;
            }
            
            .sidebar {
                grid-template-columns: 1fr;
            }
            
            .message-item {
                padding: 1.5rem;
            }
            
            .message-header {
                flex-direction: column;
                align-items: start;
                gap: 0.75rem;
            }
            
            .message-meta {
                flex-direction: column;
                align-items: start;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .pagination-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .messagerie-page {
                padding: 1rem 0;
            }
            
            .breadcrumb {
                padding: 0.75rem 1rem;
                margin-bottom: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .header-details h1 {
                font-size: 1.75rem;
            }
            
            .messages-container, .sidebar-card {
                padding: 1.5rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-input, .form-select, .search-input input {
                min-width: auto;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="messagerie-page">
        <div class="messagerie-container">
            <!-- Fil d'Ariane -->
            <nav class="breadcrumb">
                <a href="<?= BASE_URL ?>dashboard.php">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Messagerie</span>
            </nav>

            <!-- En-tête -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <div class="header-icon">
                            <i class="fas fa-envelope"></i>
                            <?php if ($stats['unread'] > 0): ?>
                                <span class="notification-badge"><?= $stats['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="header-details">
                            <h1>Messagerie Interne</h1>
                            <p class="subtitle">
                                <i class="fas fa-comments"></i>
                                Communications et échanges entre collaborateurs
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button onclick="showNewMessageModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouveau message
                        </button>
                        <?php if ($stats['unread'] > 0): ?>
                            <button onclick="markAllAsRead()" class="btn btn-secondary">
                                <i class="fas fa-check-double"></i>
                                Tout marquer lu
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages d'alerte -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Contenu principal -->
            <div class="main-content">
                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Statistiques -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-chart-bar"></i>
                            Statistiques
                        </h3>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <span class="stat-number"><?= $stats['total'] ?></span>
                                <span class="stat-label">Total</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?= $stats['unread'] ?></span>
                                <span class="stat-label">Non lus</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?= $stats['read'] ?></span>
                                <span class="stat-label">Lus</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filtres rapides -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-filter"></i>
                            Filtres rapides
                        </h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="?status=all" class="btn <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="justify-content: center;">
                                <i class="fas fa-list"></i>
                                Tous les messages
                            </a>
                            <a href="?status=unread" class="btn <?= $filterStatus === 'unread' ? 'btn-primary' : 'btn-secondary' ?>" style="justify-content: center;">
                                <i class="fas fa-envelope"></i>
                                Non lus (<?= $stats['unread'] ?>)
                            </a>
                            <a href="?status=read" class="btn <?= $filterStatus === 'read' ? 'btn-primary' : 'btn-secondary' ?>" style="justify-content: center;">
                                <i class="fas fa-envelope-open"></i>
                                Lus (<?= $stats['read'] ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-bolt"></i>
                            Actions rapides
                        </h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <button onclick="showNewMessageModal()" class="btn btn-primary" style="justify-content: center;">
                                <i class="fas fa-plus"></i>
                                Nouveau message
                            </button>
                            <a href="<?= BASE_URL ?>modules/messagerie/sent.php" class="btn btn-secondary" style="justify-content: center;">
                                <i class="fas fa-paper-plane"></i>
                                Messages envoyés
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Liste des messages -->
                <div class="messages-container">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-inbox"></i>
                            Messages reçus
                        </h2>
                    </div>

                    <!-- Filtres et recherche -->
                    <div class="filters-container">
                        <div class="search-input">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher dans les messages..." value="<?= htmlspecialchars($searchTerm) ?>" id="searchInput">
                        </div>
                        
                        <div class="filter-group">
                            <select id="senderFilter" class="form-select">
                                <option value="">Tous les expéditeurs</option>
                                <?php foreach ($senders as $sender): ?>
                                    <option value="<?= $sender['id'] ?>" <?= $filterSender == $sender['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sender['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Liste des messages -->
                    <?php if (!empty($messages)): ?>
                        <div class="messages-list">
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?= !$message['is_read'] ? 'unread' : '' ?>" onclick="viewMessage(<?= $message['id'] ?>)">
                                    <?php if (!$message['is_read']): ?>
                                        <div class="unread-indicator"></div>
                                    <?php endif; ?>
                                    
                                    <div class="message-header">
                                        <h4 class="message-subject"><?= htmlspecialchars($message['subject']) ?></h4>
                                        <span class="message-date"><?= formatDate($message['created_at']) ?></span>
                                    </div>
                                    
                                    <div class="message-meta">
                                        <div class="message-sender">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($message['sender_name']) ?>
                                        </div>
                                        <div class="message-actions" onclick="event.stopPropagation()">
                                            <?php if (!$message['is_read']): ?>
                                                <button onclick="markAsRead(<?= $message['id'] ?>)" class="btn btn-secondary btn-sm" title="Marquer comme lu">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteMessage(<?= $message['id'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="message-preview">
                                        <?= htmlspecialchars(substr($message['content'], 0, 150)) ?>
                                        <?= strlen($message['content']) > 150 ? '...' : '' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&sender=<?= $filterSender ?>&search=<?= urlencode($searchTerm) ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&sender=<?= $filterSender ?>&search=<?= urlencode($searchTerm) ?>" 
                                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&sender=<?= $filterSender ?>&search=<?= urlencode($searchTerm) ?>" class="pagination-btn">
                                        Suivant
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>Aucun message</h3>
                            <p>
                                <?php if (!empty($searchTerm) || !empty($filterSender) || $filterStatus !== 'all'): ?>
                                    Aucun message ne correspond à vos critères de recherche.
                                <?php else: ?>
                                    Vous n'avez pas encore reçu de messages.
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($searchTerm) || !empty($filterSender) || $filterStatus !== 'all'): ?>
                                <a href="?" class="btn btn-secondary" style="margin-top: 1rem;">
                                    <i class="fas fa-times"></i>
                                    Effacer les filtres
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal nouveau message -->
    <div id="newMessageModal" class="modal">
        <div class="modal-content">
            <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-plus-circle"></i>
                Nouveau message
            </h3>
            
            <form id="newMessageForm" class="form-container">
                <div class="form-group">
                    <label for="recipientId" class="form-label">
                        <i class="fas fa-user"></i>
                        Destinataire <span class="required">*</span>
                    </label>
                    <select id="recipientId" name="recipient_id" class="form-select" required>
                        <option value="">Sélectionner un destinataire...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>">
                                <?= htmlspecialchars($user['name']) ?> 
                                (<?= getRoleName($user['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="messageSubject" class="form-label">
                        <i class="fas fa-tag"></i>
                        Sujet <span class="required">*</span>
                    </label>
                    <input type="text" id="messageSubject" name="subject" class="form-input" required placeholder="Objet du message">
                </div>
                
                <div class="form-group">
                    <label for="messageContent" class="form-label">
                        <i class="fas fa-edit"></i>
                        Message <span class="required">*</span>
                    </label>
                    <textarea id="messageContent" name="content" class="form-textarea" required placeholder="Votre message..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('newMessageModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let searchTimeout;
        let currentPage = <?= $page ?>;
        let totalPages = <?= $totalPages ?>;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            initializeAnimations();
            initializeKeyboardShortcuts();
            animateOnLoad();
            updatePageStats();
        });
        
        // Initialiser les animations
        function initializeAnimations() {
            // Animation des cartes au survol
            const cards = document.querySelectorAll('.sidebar-card, .message-item');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = this.classList.contains('sidebar-card') 
                        ? 'translateY(-8px) scale(1.02)' 
                        : 'translateY(-6px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Animation des boutons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    if (!this.disabled) {
                        this.style.transform = 'translateY(-2px) scale(1.05)';
                    }
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        }
        
        // Animation des éléments au chargement
        function animateOnLoad() {
            const messages = document.querySelectorAll('.message-item');
            messages.forEach((message, index) => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(30px) scale(0.95)';
                setTimeout(() => {
                    message.style.transition = 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
                    message.style.opacity = '1';
                    message.style.transform = 'translateY(0) scale(1)';
                }, index * 100);
            });
            
            // Animation des stats
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((stat, index) => {
                const finalValue = parseInt(stat.textContent);
                stat.textContent = '0';
                setTimeout(() => {
                    animateNumber(stat, 0, finalValue, 1000);
                }, index * 200);
            });
        }
        
        // Animation des chiffres
        function animateNumber(element, start, end, duration) {
            const range = end - start;
            const startTime = Date.now();
            
            function updateNumber() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.round(start + (range * easeOutQuart));
                
                element.textContent = current;
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }
        
        // Mettre à jour les statistiques de la page
        function updatePageStats() {
            const totalMessages = <?= $stats['total'] ?>;
            const unreadMessages = <?= $stats['unread'] ?>;
            const readMessages = <?= $stats['read'] ?>;
            
            // Mettre à jour le titre de la page
            if (unreadMessages > 0) {
                document.title = `(${unreadMessages}) Messagerie - MINSANTE`;
            }
        }
        
        // Initialiser les filtres
        function initializeFilters() {
            const searchInput = document.getElementById('searchInput');
            const senderFilter = document.getElementById('senderFilter');
            
            // Recherche avec debounce amélioré
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value.trim();
                
                // Effet visuel de recherche
                this.style.background = searchValue ? 'linear-gradient(135deg, var(--primary-50), var(--secondary-50))' : 'white';
                this.style.borderColor = searchValue ? 'var(--primary-300)' : 'var(--gray-200)';
                
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 300);
            });
            
            senderFilter.addEventListener('change', applyFilters);
            
            // Focus automatique sur la recherche
            searchInput.addEventListener('focus', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 0 0 4px rgba(var(--primary-500-rgb), 0.1)';
            });
            
            searchInput.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
        }
        
        // Initialiser les raccourcis clavier
        function initializeKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Escape pour fermer les modals
                if (e.key === 'Escape') {
                    const openModal = document.querySelector('.modal[style*="block"]');
                    if (openModal) {
                        closeModal(openModal.id);
                    }
                }
                
                // Ctrl+N pour nouveau message
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    showNewMessageModal();
                }
                
                // Ctrl+A pour marquer tout comme lu
                if (e.ctrlKey && e.key === 'a' && e.shiftKey) {
                    e.preventDefault();
                    markAllAsRead();
                }
                
                // F pour focus sur recherche
                if (e.key === 'f' && !e.ctrlKey && !e.altKey) {
                    const searchInput = document.getElementById('searchInput');
                    if (document.activeElement !== searchInput) {
                        e.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                
                // Navigation avec flèches (gauche/droite pour pagination)
                if (e.key === 'ArrowLeft' && currentPage > 1) {
                    window.location.href = updateUrlParameter('page', currentPage - 1);
                }
                if (e.key === 'ArrowRight' && currentPage < totalPages) {
                    window.location.href = updateUrlParameter('page', currentPage + 1);
                }
            });
        }
        
        // Appliquer les filtres avec animation
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const sender = document.getElementById('senderFilter').value;
            const status = '<?= $filterStatus ?>';
            
            // Animation de chargement
            const messagesContainer = document.querySelector('.messages-list');
            messagesContainer.style.opacity = '0.6';
            messagesContainer.style.transform = 'translateY(10px)';
            
            const params = new URLSearchParams();
            if (search) params.set('search', search);
            if (sender) params.set('sender', sender);
            if (status && status !== 'all') params.set('status', status);
            
            setTimeout(() => {
                window.location.href = '?' + params.toString();
            }, 200);
        }
        
        // Mettre à jour les paramètres URL
        function updateUrlParameter(param, value) {
            const url = new URL(window.location);
            url.searchParams.set(param, value);
            return url.toString();
        }
        
        // Afficher la modal nouveau message avec animation
        function showNewMessageModal() {
            const modal = document.getElementById('newMessageModal');
            modal.style.display = 'block';
            
            // Animation d'entrée
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.transform = 'translateY(-50px) scale(0.9)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modalContent.style.transition = 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
                modalContent.style.transform = 'translateY(0) scale(1)';
                modalContent.style.opacity = '1';
            }, 10);
            
            // Focus sur le premier champ
            setTimeout(() => {
                document.getElementById('recipientId').focus();
            }, 100);
        }
        
        // Fermer une modal avec animation
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            const modalContent = modal.querySelector('.modal-content');
            
            modalContent.style.transition = 'all 0.3s ease';
            modalContent.style.transform = 'translateY(-30px) scale(0.95)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                if (modalId === 'newMessageModal') {
                    document.getElementById('newMessageForm').reset();
                }
            }, 300);
        }
        
        // Voir un message avec effet de transition
        function viewMessage(messageId) {
            // Animation de sélection
            const messageItem = event.currentTarget;
            messageItem.style.transform = 'scale(0.98)';
            messageItem.style.opacity = '0.8';
            
            setTimeout(() => {
                window.location.href = 'view.php?id=' + messageId;
            }, 150);
        }
        
        // Marquer un message comme lu avec animation
        function markAsRead(messageId) {
            const messageItem = event.target.closest('.message-item');
            
            // Animation de traitement
            messageItem.style.transition = 'all 0.4s ease';
            messageItem.style.opacity = '0.6';
            messageItem.style.transform = 'scale(0.98)';
            
            fetch('list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'mark_read',
                    message_id: messageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de succès
                    messageItem.classList.remove('unread');
                    messageItem.style.background = 'var(--success-50)';
                    messageItem.style.borderColor = 'var(--success-300)';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    showNotification('Erreur : ' + data.message, 'error');
                    messageItem.style.opacity = '1';
                    messageItem.style.transform = 'scale(1)';
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion : ' + error.message, 'error');
                messageItem.style.opacity = '1';
                messageItem.style.transform = 'scale(1)';
            });
        }
        
        // Supprimer un message avec confirmation
        function deleteMessage(messageId) {
            // Modal de confirmation moderne
            const confirmDelete = confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce message ?\n\nCette action est irréversible.');
            
            if (!confirmDelete) return;
            
            const messageItem = event.target.closest('.message-item');
            
            // Animation de suppression
            messageItem.style.transition = 'all 0.5s ease';
            messageItem.style.transform = 'translateX(-100%) scale(0.8)';
            messageItem.style.opacity = '0';
            
            fetch('list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'delete_message',
                    message_id: messageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        messageItem.remove();
                        showNotification('Message supprimé avec succès', 'success');
                        updateMessageCount();
                    }, 500);
                } else {
                    showNotification('Erreur : ' + data.message, 'error');
                    messageItem.style.transform = 'translateX(0) scale(1)';
                    messageItem.style.opacity = '1';
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion : ' + error.message, 'error');
                messageItem.style.transform = 'translateX(0) scale(1)';
                messageItem.style.opacity = '1';
            });
        }
        
        // Marquer tous les messages comme lus
        function markAllAsRead() {
            if (!confirm('🔄 Marquer tous les messages comme lus ?\n\nCela affectera tous vos messages non lus.')) {
                return;
            }
            
            // Animation globale
            const messagesContainer = document.querySelector('.messages-list');
            messagesContainer.style.transition = 'all 0.6s ease';
            messagesContainer.style.opacity = '0.6';
            messagesContainer.style.transform = 'scale(0.98)';
            
            fetch('list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'mark_all_read'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Tous les messages marqués comme lus', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Erreur : ' + data.message, 'error');
                    messagesContainer.style.opacity = '1';
                    messagesContainer.style.transform = 'scale(1)';
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion : ' + error.message, 'error');
                messagesContainer.style.opacity = '1';
                messagesContainer.style.transform = 'scale(1)';
            });
        }
        
        // Mettre à jour le compteur de messages
        function updateMessageCount() {
            const messageItems = document.querySelectorAll('.message-item').length;
            if (messageItems === 0) {
                const messagesContainer = document.querySelector('.messages-list');
                messagesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Aucun message</h3>
                        <p>Tous vos messages ont été traités.</p>
                    </div>
                `;
            }
        }
        
        // Système de notifications modernes
        function showNotification(message, type = 'info') {
            // Créer la notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Styles pour la notification
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? 'var(--success-500)' : type === 'error' ? 'var(--danger-500)' : 'var(--primary-500)'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-2xl);
                z-index: 3000;
                transform: translateX(400px);
                transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                display: flex;
                align-items: center;
                gap: 1rem;
                min-width: 300px;
                max-width: 500px;
            `;
            
            document.body.appendChild(notification);
            
            // Animation d'entrée
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Suppression automatique
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 400);
            }, 4000);
        }
        
        // Gestionnaire de soumission du formulaire nouveau message
        document.getElementById('newMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax_action', 'send_message');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Animation de chargement
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
            submitBtn.disabled = true;
            submitBtn.style.background = 'var(--gray-400)';
            
            fetch('list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Envoyé !';
                    submitBtn.style.background = 'var(--success-500)';
                    
                    showNotification('Message envoyé avec succès', 'success');
                    
                    setTimeout(() => {
                        closeModal('newMessageModal');
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('Erreur : ' + data.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.style.background = '';
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion : ' + error.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.style.background = '';
            });
        });
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
        
        // Gestion des liens de pagination avec animation
        document.querySelectorAll('.pagination-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    e.preventDefault();
                    
                    // Animation de transition
                    const messagesContainer = document.querySelector('.messages-list');
                    messagesContainer.style.transition = 'all 0.3s ease';
                    messagesContainer.style.opacity = '0';
                    messagesContainer.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 300);
                }
            });
        });
        
        // Auto-refresh des messages (optionnel)
        let autoRefreshInterval;
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                // Vérifier s'il y a de nouveaux messages
                fetch('list.php?ajax=check_new')
                .then(response => response.json())
                .then(data => {
                    if (data.hasNew) {
                        showNotification('Nouveaux messages disponibles', 'info');
                    }
                })
                .catch(() => {}); // Ignorer les erreurs de vérification
            }, 30000); // Vérifier toutes les 30 secondes
        }
        
        // Démarrer l'auto-refresh si l'utilisateur est actif
        let userActive = true;
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                userActive = false;
                clearInterval(autoRefreshInterval);
            } else {
                userActive = true;
                startAutoRefresh();
            }
        });
        
        // Démarrer l'auto-refresh
        startAutoRefresh();
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>