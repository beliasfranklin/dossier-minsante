<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

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
    /* === PAGE MESSAGERIE - STYLE ADMIN MODERNE === */
    .messagerie-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .messagerie-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        color: var(--gray-600);
        font-size: 0.875rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.3s ease-out;
    }
    
    .breadcrumb a {
        color: var(--primary-600);
        text-decoration: none;
        transition: var(--transition-all);
    }
    
    .breadcrumb a:hover {
        color: var(--primary-800);
    }
    
    .page-header {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .header-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .header-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
        animation: iconFloat 3s ease-in-out infinite;
        position: relative;
    }
    
    .header-icon .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--danger-500);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
        animation: pulse 2s infinite;
    }
    
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-6px) rotate(2deg); }
    }
    
    .header-details h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .header-details .subtitle {
        color: var(--gray-600);
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .main-content {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 2rem;
    }
    
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .sidebar-card {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInLeft 0.6s ease-out;
        position: relative;
        overflow: hidden;
        transition: var(--transition-all);
    }
    
    .sidebar-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .sidebar-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .sidebar-card:hover::before {
        transform: scaleX(1);
    }
    
    .sidebar-card h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .sidebar-card h3 i {
        color: var(--primary-500);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: var(--gray-50);
        padding: 1rem;
        border-radius: var(--radius-lg);
        text-align: center;
        border: 1px solid var(--gray-200);
        transition: var(--transition-all);
    }
    
    .stat-card:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-600);
        display: block;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    
    .messages-container {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInRight 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .messages-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .messages-container:hover::before {
        transform: scaleX(1);
    }
    
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }
    
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .section-title i {
        color: var(--primary-500);
        font-size: 1.25rem;
    }
    
    .filters-container {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-group {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .form-input, .form-select {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: white;
        font-size: 0.875rem;
        transition: var(--transition-all);
        min-width: 120px;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .search-input {
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    
    .search-input input {
        width: 100%;
        padding-left: 2.5rem;
    }
    
    .search-input i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
    }
    
    .messages-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .message-item {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        border: 2px solid var(--gray-200);
        transition: var(--transition-all);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .message-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gray-300);
        transition: all 0.3s ease;
    }
    
    .message-item.unread {
        background: var(--primary-50);
        border-color: var(--primary-200);
    }
    
    .message-item.unread::before {
        background: var(--primary-500);
    }
    
    .message-item:hover {
        background: white;
        border-color: var(--primary-300);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        gap: 1rem;
    }
    
    .message-subject {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0;
        flex: 1;
    }
    
    .message-date {
        font-size: 0.875rem;
        color: var(--gray-500);
        white-space: nowrap;
    }
    
    .message-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    
    .message-sender {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray-600);
        font-size: 0.875rem;
    }
    
    .message-actions {
        display: flex;
        gap: 0.5rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .message-item:hover .message-actions {
        opacity: 1;
    }
    
    .message-preview {
        color: var(--gray-600);
        font-size: 0.875rem;
        line-height: 1.5;
        margin-top: 0.5rem;
        max-height: 3em;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    
    .unread-indicator {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 12px;
        height: 12px;
        background: var(--primary-500);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--gray-200);
    }
    
    .pagination-btn {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        background: white;
        color: var(--gray-700);
        border-radius: var(--radius-lg);
        text-decoration: none;
        transition: var(--transition-all);
        font-size: 0.875rem;
    }
    
    .pagination-btn:hover {
        border-color: var(--primary-300);
        background: var(--primary-50);
        color: var(--primary-700);
    }
    
    .pagination-btn.active {
        background: var(--primary-500);
        color: white;
        border-color: var(--primary-500);
    }
    
    .form-container {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        border: 1px solid var(--gray-200);
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group:last-child {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--primary-500);
        width: 16px;
    }
    
    .form-label .required {
        color: var(--danger-500);
    }
    
    .form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
        resize: vertical;
        min-height: 100px;
        box-sizing: border-box;
    }
    
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-lg);
        font-weight: 500;
        transition: var(--transition-all);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
    }
    
    .btn-secondary:hover {
        background: var(--gray-200);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .btn-danger {
        background: var(--danger-500);
        color: white;
    }
    
    .btn-danger:hover {
        background: var(--danger-600);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        animation: slideInDown 0.5s ease-out;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-700);
        border: 1px solid var(--success-200);
        border-left: 4px solid var(--success-500);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
        border-left: 4px solid var(--danger-500);
    }
    
    .alert i {
        font-size: 1.125rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray-500);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background: white;
        margin: 5% auto;
        padding: 2rem;
        border-radius: var(--radius-2xl);
        width: 90%;
        max-width: 600px;
        box-shadow: var(--shadow-2xl);
        animation: modalSlideIn 0.3s ease-out;
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
    
    /* Animations */
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .main-content {
            grid-template-columns: 1fr;
        }
        
        .sidebar {
            order: -1;
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
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .header-info {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .header-details h1 {
            font-size: 1.5rem;
        }
        
        .message-header {
            flex-direction: column;
            align-items: start;
            gap: 0.5rem;
        }
        
        .message-meta {
            flex-direction: column;
            align-items: start;
            gap: 0.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .pagination {
            flex-wrap: wrap;
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
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            animateOnLoad();
        });
        
        // Animation des éléments au chargement
        function animateOnLoad() {
            const messages = document.querySelectorAll('.message-item');
            messages.forEach((message, index) => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    message.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    message.style.opacity = '1';
                    message.style.transform = 'translateY(0)';
                }, index * 50);
            });
        }
        
        // Initialiser les filtres
        function initializeFilters() {
            const searchInput = document.getElementById('searchInput');
            const senderFilter = document.getElementById('senderFilter');
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 500);
            });
            
            senderFilter.addEventListener('change', applyFilters);
        }
        
        // Appliquer les filtres
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const sender = document.getElementById('senderFilter').value;
            const status = '<?= $filterStatus ?>';
            
            const params = new URLSearchParams();
            if (search) params.set('search', search);
            if (sender) params.set('sender', sender);
            if (status && status !== 'all') params.set('status', status);
            
            window.location.href = '?' + params.toString();
        }
        
        // Afficher la modal nouveau message
        function showNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'block';
            document.getElementById('recipientId').focus();
        }
        
        // Fermer une modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'newMessageModal') {
                document.getElementById('newMessageForm').reset();
            }
        }
        
        // Voir un message
        function viewMessage(messageId) {
            window.location.href = 'view.php?id=' + messageId;
        }
        
        // Marquer un message comme lu
        function markAsRead(messageId) {
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
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Supprimer un message
        function deleteMessage(messageId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) {
                return;
            }
            
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
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Marquer tous les messages comme lus
        function markAllAsRead() {
            if (!confirm('Marquer tous les messages comme lus ?')) {
                return;
            }
            
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
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Gestionnaire de soumission du formulaire nouveau message
        document.getElementById('newMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax_action', 'send_message');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            submitBtn.disabled = true;
            
            fetch('list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Envoyé !';
                    submitBtn.style.background = 'var(--success-500)';
                    
                    setTimeout(() => {
                        closeModal('newMessageModal');
                        location.reload();
                    }, 1000);
                } else {
                    alert('Erreur : ' + data.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                alert('Erreur de connexion : ' + error.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    closeModal(openModal.id);
                }
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showNewMessageModal();
            }
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>