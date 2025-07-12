<?php
if (!defined('NOTIFICATION_EMAIL_ENABLED')) {
    define('NOTIFICATION_EMAIL_ENABLED', false);
}

function createNotification($userId, $title, $message, $module = null, $itemId = null) {
    $sql = "INSERT INTO notifications (user_id, title, message, related_module, related_id) 
            VALUES (?, ?, ?, ?, ?)";
    executeQuery($sql, [$userId, $title, $message, $module, $itemId]);
    
    // Envoi immédiat par email si configuré
    if (NOTIFICATION_EMAIL_ENABLED) {
        sendEmailNotification($userId, $title, $message);
    }
}

function getUnreadNotifications($userId) {
    return fetchAll("SELECT * FROM notifications 
                    WHERE user_id = ? AND is_read = FALSE 
                    ORDER BY created_at DESC LIMIT 10", [$userId]);
}

function markAsRead($notificationId) {
    executeQuery("UPDATE notifications SET is_read = TRUE WHERE id = ?", [$notificationId]);
}

function sendEmailNotification($userId, $title, $message) {
    $user = fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
    if (!$user) return false;

    $to = $user['email'];
    $subject = "[MINSANTE] $title";
    $headers = "From: " . APP_NAME . " <notifications@minsante.cm>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $htmlMessage = "
    <html>
    <body>
        <h2>$title</h2>
        <p>$message</p>
        <p><small>" . APP_NAME . " - " . date('Y-m-d H:i') . "</small></p>
    </body>
    </html>
    ";

    return mail($to, $subject, $htmlMessage, $headers);
}
?>