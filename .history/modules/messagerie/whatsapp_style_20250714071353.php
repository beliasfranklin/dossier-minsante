<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/PreferencesManager.php';

// Vérifier l'authentification
requireAuth();

// Initialisation du gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);

// Traitement AJAX
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'send_message':
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content, created_at) VALUES (?, ?, ?, ?, NOW())");
                $result = $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['recipient_id'],
                    $_POST['subject'],
                    $_POST['content']
                ]);
                
                echo json_encode(['success' => $result, 'message' => $result ? 'Message envoyé' : 'Erreur d\'envoi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'mark_read':
            try {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
                $result = $stmt->execute([$_POST['message_id'], $_SESSION['user_id']]);
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_message':
            try {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND recipient_id = ?");
                $result = $stmt->execute([$_POST['message_id'], $_SESSION['user_id']]);
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Récupération des messages
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.nom as sender_name, u.prenom as sender_firstname
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.recipient_id = ?
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $messages = $stmt->fetchAll();
    
    // Récupération des utilisateurs pour nouveau message
    $stmt = $pdo->prepare("SELECT id, nom, prenom FROM users WHERE id != ? ORDER BY nom");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $messages = [];
    $users = [];
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie WhatsApp Style - MINSANTE</title>
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
        
        /* === MESSAGE BUBBLES === */
        .message-bubble {
            max-width: 65%;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .message-bubble.sent {
            margin-left: auto;
            margin-right: 0;
        }
        
        .message-bubble.received {
            margin-left: 0;
            margin-right: auto;
        }
        
        .message-content {
            background: #202c33;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            position: relative;
        }
        
        .message-bubble.sent .message-content {
            background: #005c4b;
            color: white;
            border-bottom-right-radius: 3px;
        }
        
        .message-bubble.received .message-content {
            background: #202c33;
            color: #e9edef;
            border-bottom-left-radius: 3px;
        }
        
        .message-subject {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #25d366;
        }
        
        .message-text {
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
            text-align: right;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.25rem;
        }
        
        .message-bubble.received .message-time {
            color: #8696a0;
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
    <!-- WHATSAPP-STYLE CONTAINER -->
    <div class="whatsapp-container">
        <!-- SIDEBAR PANEL -->
        <div class="sidebar-panel">
            <!-- SIDEBAR HEADER -->
            <div class="sidebar-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['nom'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-name">
                        <?= htmlspecialchars($_SESSION['nom'] ?? 'Utilisateur') ?>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="header-btn" id="newMessageBtn" title="Nouveau message">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="header-btn" id="refreshBtn" title="Actualiser">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button class="header-btn" id="settingsBtn" title="Paramètres">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
            
            <!-- SEARCH BAR -->
            <div class="search-container">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Rechercher une conversation..." id="conversationSearch">
                </div>
            </div>
            
            <!-- FILTERS BAR -->
            <div class="filters-bar">
                <div class="filter-chip active" data-filter="all">
                    <i class="fas fa-comments"></i> Toutes
                </div>
                <div class="filter-chip" data-filter="unread">
                    <i class="fas fa-circle"></i> Non lues
                </div>
                <div class="filter-chip" data-filter="sent">
                    <i class="fas fa-paper-plane"></i> Envoyées
                </div>
                <div class="filter-chip" data-filter="received">
                    <i class="fas fa-inbox"></i> Reçues
                </div>
            </div>
            
            <!-- CONVERSATIONS LIST -->
            <div class="conversations-list" id="conversationsList">
                <?php if (empty($messages)): ?>
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <h3>Aucune conversation</h3>
                        <p>Commencez une nouvelle conversation en cliquant sur le bouton "Nouveau message"</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $index => $message): ?>
                        <div class="conversation-item <?= !$message['is_read'] ? 'unread' : '' ?>" 
                             data-message-id="<?= $message['id'] ?>"
                             data-sender="<?= htmlspecialchars($message['sender_name']) ?>"
                             data-filter="received">
                            
                            <div class="conversation-avatar">
                                <?= strtoupper(substr($message['sender_name'], 0, 1)) ?>
                            </div>
                            
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($message['sender_name'] . ' ' . $message['sender_firstname']) ?>
                                    </div>
                                    <div class="conversation-time">
                                        <?= date('H:i', strtotime($message['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="conversation-preview">
                                    <strong><?= htmlspecialchars($message['subject']) ?></strong>
                                    <?php if (strlen($message['content']) > 50): ?>
                                        - <?= htmlspecialchars(substr(strip_tags($message['content']), 0, 50)) ?>...
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!$message['is_read']): ?>
                                <div class="unread-count">●</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CHAT PANEL -->
        <div class="chat-panel">
            <!-- CHAT HEADER -->
            <div class="chat-header">
                <div class="chat-contact-info">
                    <div class="conversation-avatar">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <div class="chat-contact-name">Messagerie Interne</div>
                        <div class="chat-contact-status">Système de communication MINSANTE</div>
                    </div>
                </div>
                <div class="chat-actions">
                    <button class="header-btn" title="Rechercher dans les messages">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="header-btn" title="Plus d'options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            
            <!-- CHAT MESSAGES -->
            <div class="chat-messages" id="chatMessages">
                <div class="empty-chat">
                    <div class="empty-chat-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <h3>Messagerie Interne MINSANTE</h3>
                    <p>Sélectionnez une conversation dans la liste pour afficher les messages, ou créez un nouveau message pour commencer une discussion.</p>
                </div>
            </div>
            
            <!-- MESSAGE INPUT -->
            <div class="message-input-container">
                <div class="message-input-wrapper">
                    <div class="message-input-box">
                        <textarea class="message-input" placeholder="Tapez votre message..." rows="1" id="messageInput" disabled></textarea>
                        <div class="input-actions">
                            <button class="input-btn" title="Joindre un fichier">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button class="input-btn" title="Emoji">
                                <i class="fas fa-smile"></i>
                            </button>
                        </div>
                    </div>
                    <button class="send-btn" id="sendBtn" disabled title="Envoyer le message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NEW MESSAGE MODAL -->
    <div id="newMessageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Nouveau Message</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="newMessageForm">
                    <div class="form-group">
                        <label class="form-label" for="recipientId">
                            <i class="fas fa-user"></i> Destinataire <span style="color: #25d366;">*</span>
                        </label>
                        <select name="recipient_id" id="recipientId" class="form-select" required>
                            <option value="">Sélectionnez un destinataire</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="messageSubject">
                            <i class="fas fa-tag"></i> Sujet <span style="color: #25d366;">*</span>
                        </label>
                        <input type="text" name="subject" id="messageSubject" class="form-input" placeholder="Entrez le sujet du message" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="messageContent">
                            <i class="fas fa-comment"></i> Message <span style="color: #25d366;">*</span>
                        </label>
                        <textarea name="content" id="messageContent" class="form-textarea" placeholder="Rédigez votre message..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelBtn">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" form="newMessageForm" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </div>
        </div>
    </div>
    
    <!-- NOTIFICATIONS -->
    <div id="notification" class="notification"></div>

    <script>
        // === VARIABLES GLOBALES ===
        let currentConversation = null;
        let selectedMessage = null;
        
        // === GESTION DES MODALES ===
        const newMessageModal = document.getElementById('newMessageModal');
        const newMessageBtn = document.getElementById('newMessageBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        
        newMessageBtn?.addEventListener('click', () => {
            newMessageModal.style.display = 'block';
            setTimeout(() => newMessageModal.classList.add('show'), 10);
        });
        
        [closeModal, cancelBtn].forEach(btn => {
            btn?.addEventListener('click', () => {
                newMessageModal.style.display = 'none';
                newMessageModal.classList.remove('show');
            });
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === newMessageModal) {
                newMessageModal.style.display = 'none';
                newMessageModal.classList.remove('show');
            }
        });
        
        // === ENVOI DE NOUVEAU MESSAGE ===
        document.getElementById('newMessageForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('ajax_action', 'send_message');
            
            try {
                const response = await fetch('whatsapp_style.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Message envoyé avec succès !', 'success');
                    newMessageModal.style.display = 'none';
                    e.target.reset();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('Erreur lors de l\'envoi du message: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Erreur de connexion', 'error');
            }
        });
        
        // === GESTION DES CONVERSATIONS ===
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                // Marquer comme actif
                document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                
                // Supprimer l'indicateur de non-lu
                if (item.classList.contains('unread')) {
                    item.classList.remove('unread');
                    const unreadCount = item.querySelector('.unread-count');
                    if (unreadCount) unreadCount.remove();
                    
                    // Marquer comme lu via AJAX
                    const messageId = item.dataset.messageId;
                    markAsRead(messageId);
                }
                
                // Charger la conversation
                loadConversation(item.dataset.messageId, item.dataset.sender);
            });
        });
        
        // === MARQUER COMME LU ===
        async function markAsRead(messageId) {
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'mark_read');
                formData.append('message_id', messageId);
                
                await fetch('whatsapp_style.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Erreur lors du marquage comme lu:', error);
            }
        }
        
        // === CHARGER CONVERSATION ===
        function loadConversation(messageId, senderName) {
            const chatMessages = document.getElementById('chatMessages');
            const chatContactName = document.querySelector('.chat-contact-name');
            const chatContactStatus = document.querySelector('.chat-contact-status');
            
            selectedMessage = messageId;
            
            // Mettre à jour l'en-tête
            chatContactName.textContent = senderName;
            chatContactStatus.textContent = 'En ligne';
            
            // Trouver le message dans la liste
            const conversations = document.querySelectorAll('.conversation-item');
            let messageData = null;
            
            conversations.forEach(conv => {
                if (conv.dataset.messageId === messageId) {
                    const subject = conv.querySelector('strong').textContent;
                    const preview = conv.querySelector('.conversation-preview').textContent;
                    const time = conv.querySelector('.conversation-time').textContent;
                    
                    messageData = {
                        subject: subject,
                        content: preview.replace(subject + ' - ', ''),
                        time: time
                    };
                }
            });
            
            if (messageData) {
                chatMessages.innerHTML = `
                    <div class="message-bubble received">
                        <div class="message-content">
                            <div class="message-subject">${messageData.subject}</div>
                            <div class="message-text">${messageData.content}</div>
                            <div class="message-time">
                                ${messageData.time}
                                <i class="fas fa-check-double"></i>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Activer l'input de message
            document.getElementById('messageInput').disabled = false;
            updateSendButton();
        }
        
        // === RECHERCHE DE CONVERSATIONS ===
        document.getElementById('conversationSearch')?.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const conversations = document.querySelectorAll('.conversation-item');
            
            conversations.forEach(conv => {
                const name = conv.dataset.sender.toLowerCase();
                const preview = conv.querySelector('.conversation-preview').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    conv.style.display = 'flex';
                } else {
                    conv.style.display = 'none';
                }
            });
        });
        
        // === FILTRAGE DES CONVERSATIONS ===
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                // Mettre à jour l'état actif
                document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                
                const filter = chip.dataset.filter;
                const conversations = document.querySelectorAll('.conversation-item');
                
                conversations.forEach(conv => {
                    if (filter === 'all') {
                        conv.style.display = 'flex';
                    } else if (filter === 'unread' && conv.classList.contains('unread')) {
                        conv.style.display = 'flex';
                    } else if (conv.dataset.filter === filter) {
                        conv.style.display = 'flex';
                    } else {
                        conv.style.display = 'none';
                    }
                });
            });
        });
        
        // === GESTION DE L'INPUT MESSAGE ===
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        
        function updateSendButton() {
            const hasContent = messageInput.value.trim().length > 0;
            const hasConversation = selectedMessage !== null;
            sendBtn.disabled = !(hasContent && hasConversation);
        }
        
        messageInput?.addEventListener('input', () => {
            updateSendButton();
            
            // Auto-resize textarea
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
        });
        
        messageInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) {
                    sendMessage();
                }
            }
        });
        
        // === ENVOYER MESSAGE ===
        function sendMessage() {
            const content = messageInput.value.trim();
            if (!content || !selectedMessage) return;
            
            // Ajouter le message à la conversation actuelle
            const chatMessages = document.getElementById('chatMessages');
            const newMessage = document.createElement('div');
            newMessage.className = 'message-bubble sent';
            newMessage.innerHTML = `
                <div class="message-content">
                    <div class="message-text">${content}</div>
                    <div class="message-time">
                        ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            `;
            
            chatMessages.appendChild(newMessage);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            messageInput.value = '';
            messageInput.style.height = 'auto';
            updateSendButton();
            
            showNotification('Message envoyé', 'success');
        }
        
        sendBtn?.addEventListener('click', sendMessage);
        
        // === NOTIFICATIONS ===
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.classList.remove('show')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }
        
        // === ACTUALISATION ===
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            const icon = document.querySelector('#refreshBtn i');
            icon.classList.add('fa-spin');
            
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
        
        // === RESPONSIVE DESIGN ===
        function handleResize() {
            const sidebar = document.querySelector('.sidebar-panel');
            const chatPanel = document.querySelector('.chat-panel');
            
            if (window.innerWidth <= 768) {
                // Mobile: masquer le chat si aucune conversation n'est sélectionnée
                if (!currentConversation) {
                    chatPanel.style.display = 'none';
                    sidebar.style.display = 'flex';
                } else {
                    chatPanel.style.display = 'flex';
                    sidebar.style.display = 'none';
                }
            } else {
                // Desktop: afficher les deux panneaux
                chatPanel.style.display = 'flex';
                sidebar.style.display = 'flex';
            }
        }
        
        window.addEventListener('resize', handleResize);
        handleResize(); // Appel initial
        
        // === ANIMATIONS D'ENTRÉE ===
        function animateConversations() {
            const conversations = document.querySelectorAll('.conversation-item');
            conversations.forEach((conv, index) => {
                conv.style.animationDelay = `${index * 0.1}s`;
            });
        }
        
        // === INITIALISATION ===
        document.addEventListener('DOMContentLoaded', () => {
            animateConversations();
            
            // Auto-focus sur la recherche
            const searchInput = document.getElementById('conversationSearch');
            if (searchInput && window.innerWidth > 768) {
                searchInput.focus();
            }
            
            // Sélectionner automatiquement la première conversation s'il y en a une
            const firstConversation = document.querySelector('.conversation-item');
            if (firstConversation) {
                firstConversation.click();
            }
        });
    </script>
</body>
</html>
