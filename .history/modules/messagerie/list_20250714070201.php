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
        <?= $preferencesManager->generateThemeCSS() ?>
        
        /* === WHATSAPP-INSPIRED MESSAGERIE === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #111b21;
            color: #e9edef;
            overflow: hidden;
        }
        
        .whatsapp-container {
            height: 100vh;
            display: flex;
            background: #111b21;
        }
        
        /* === SIDEBAR STYLE WHATSAPP === */
        .sidebar-panel {
            width: 400px;
            background: #202c33;
            border-right: 1px solid #3b4a54;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 2;
        }
        
        .sidebar-header {
            background: #2a3942;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #3b4a54;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 70px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #25d366, #128c7e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .user-name {
            color: #e9edef;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .header-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: transparent;
            color: #8696a0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.125rem;
        }
        
        .header-btn:hover {
            background: #3b4a54;
            color: #e9edef;
        }
        
        .header-btn.active {
            background: #25d366;
            color: white;
        }
        
        /* === SEARCH BAR WHATSAPP STYLE === */
        .search-container {
            padding: 0.75rem 1rem;
            background: #202c33;
            border-bottom: 1px solid #3b4a54;
        }
        
        .search-wrapper {
            position: relative;
            background: #2a3942;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: none;
            background: transparent;
            color: #e9edef;
            font-size: 0.9rem;
            outline: none;
        }
        
        .search-input::placeholder {
            color: #8696a0;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8696a0;
            font-size: 0.9rem;
        }
        
        /* === FILTERS BAR === */
        .filters-bar {
            padding: 1rem;
            background: #202c33;
            border-bottom: 1px solid #3b4a54;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }
        
        .filter-chip {
            background: #2a3942;
            color: #8696a0;
            padding: 0.5rem 1rem;
            border-radius: 16px;
            font-size: 0.8rem;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #3b4a54;
        }
        
        .filter-chip.active {
            background: #25d366;
            color: white;
            border-color: #25d366;
        }
        
        .filter-chip:hover:not(.active) {
            background: #3b4a54;
            color: #e9edef;
        }
        
        /* === CONVERSATIONS LIST === */
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            background: #111b21;
        }
        
        .conversation-item {
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid rgba(134, 150, 160, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #202c33;
        }
        
        .conversation-item.active {
            background: #2a3942;
        }
        
        .conversation-item.unread {
            background: rgba(37, 211, 102, 0.05);
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .conversation-name {
            color: #e9edef;
            font-weight: 500;
            font-size: 1rem;
            truncate: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .conversation-time {
            color: #8696a0;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        
        .conversation-preview {
            color: #8696a0;
            font-size: 0.875rem;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .conversation-meta {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }
        
        .unread-count {
            background: #25d366;
            color: white;
            border-radius: 10px;
            padding: 0.125rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        .message-status {
            color: #8696a0;
            font-size: 0.8rem;
        }
        
        .message-status.sent {
            color: #8696a0;
        }
        
        .message-status.delivered {
            color: #8696a0;
        }
        
        .message-status.read {
            color: #53bdeb;
        }
        
        /* === MAIN CHAT AREA === */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0b141a;
            position: relative;
        }
        
        .chat-header {
            background: #202c33;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #3b4a54;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 70px;
        }
        
        .chat-contact-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chat-contact-name {
            color: #e9edef;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .chat-contact-status {
            color: #8696a0;
            font-size: 0.8rem;
        }
        
        .chat-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* === CHAT MESSAGES AREA === */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="chat-bg" x="0" y="0" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.02"/></pattern></defs><rect width="100" height="100" fill="url(%23chat-bg)"/></svg>');
            background-color: #0b141a;
        }
        
        .empty-chat {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #8696a0;
        }
        
        .empty-chat-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .empty-chat h3 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
            color: #e9edef;
        }
        
        .empty-chat p {
            font-size: 1rem;
            opacity: 0.7;
            max-width: 400px;
            line-height: 1.5;
        }
        
        /* === MESSAGE INPUT === */
        .message-input-container {
            background: #202c33;
            padding: 1rem 1.5rem;
            border-top: 1px solid #3b4a54;
        }
        
        .message-input-wrapper {
            display: flex;
            align-items: flex-end;
            gap: 0.75rem;
        }
        
        .message-input-box {
            flex: 1;
            background: #2a3942;
            border-radius: 24px;
            display: flex;
            align-items: flex-end;
            padding: 0.5rem 1rem;
            min-height: 48px;
        }
        
        .message-input {
            flex: 1;
            border: none;
            background: transparent;
            color: #e9edef;
            font-size: 0.95rem;
            resize: none;
            outline: none;
            padding: 0.5rem 0;
            max-height: 120px;
            min-height: 24px;
        }
        
        .message-input::placeholder {
            color: #8696a0;
        }
        
        .input-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .input-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: #8696a0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .input-btn:hover {
            background: rgba(134, 150, 160, 0.2);
            color: #e9edef;
        }
        
        .send-btn {
            width: 48px;
            height: 48px;
            border: none;
            background: #25d366;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.1rem;
        }
        
        .send-btn:hover {
            background: #1faa55;
            transform: scale(1.05);
        }
        
        .send-btn:disabled {
            background: #3b4a54;
            cursor: not-allowed;
            transform: none;
        }
        
        /* === NEW MESSAGE MODAL WHATSAPP STYLE === */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: #202c33;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        .modal-header {
            background: #2a3942;
            padding: 1.5rem;
            border-bottom: 1px solid #3b4a54;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            color: #e9edef;
            font-weight: 500;
            font-size: 1.2rem;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #8696a0;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #3b4a54;
            color: #e9edef;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            color: #e9edef;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-select,
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #3b4a54;
            border-radius: 8px;
            background: #2a3942;
            color: #e9edef;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .form-select:focus,
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #25d366;
            box-shadow: 0 0 0 2px rgba(37, 211, 102, 0.2);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #2a3942;
            border-top: 1px solid #3b4a54;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #25d366;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1faa55;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #3b4a54;
            color: #e9edef;
        }
        
        .btn-secondary:hover {
            background: #4a5c66;
        }
        
        /* === ANIMATIONS === */
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
        
        /* === SCROLLBAR STYLING === */
        .conversations-list::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .conversations-list::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .conversations-list::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb {
            background: #3b4a54;
            border-radius: 3px;
        }
        
        .conversations-list::-webkit-scrollbar-thumb:hover,
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #4a5c66;
        }
        
        /* === RESPONSIVE DESIGN === */
        @media (max-width: 768px) {
            .sidebar-panel {
                width: 100%;
                position: absolute;
                z-index: 10;
                height: 100%;
            }
            
            .sidebar-panel.hidden {
                transform: translateX(-100%);
            }
            
            .chat-panel {
                width: 100%;
            }
            
            .whatsapp-container {
                position: relative;
            }
        }
        
        /* === UTILITY CLASSES === */
        .hidden {
            display: none !important;
        }
        
        .text-green {
            color: #25d366 !important;
        }
        
        .text-muted {
            color: #8696a0 !important;
        }
        
        .text-white {
            color: #e9edef !important;
        }
        
        /* === NOTIFICATION STYLES === */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #25d366;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            z-index: 2000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 400px;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.error {
            background: #dc3545;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            opacity: 0.8;
            padding: 0.25rem;
            border-radius: 4px;
            transition: opacity 0.2s ease;
        }
        
        .notification-close:hover {
            opacity: 1;
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