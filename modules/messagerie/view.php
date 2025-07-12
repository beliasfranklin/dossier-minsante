<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(404);
    echo '<h2>Message introuvable</h2>';
    exit;
}

$message = fetchOne("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ? AND m.recipient_id = ?", [$_GET['id'], $_SESSION['user_id']]);

if (!$message) {
    http_response_code(404);
    echo '<h2>Message introuvable ou accès refusé</h2>';
    exit;
}

// Marquer comme lu si non lu
if (!$message['is_read']) {
    executeQuery("UPDATE messages SET is_read = 1 WHERE id = ?", [$_GET['id']]);
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="max-width:600px;margin:auto;">
    <div class="card" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px #2980b922;margin-top:32px;">
        <div class="card-header" style="font-weight:600;color:#2980b9;background:#eaf6fb;border-radius:12px 12px 0 0;">
            Message reçu
        </div>
        <div class="card-body" style="padding:24px;">
            <h3 style="color:#2980b9;">Sujet : <?= htmlspecialchars($message['subject']) ?></h3>
            <p style="color:#34495e;">De : <strong><?= htmlspecialchars($message['sender_name']) ?></strong></p>
            <p style="color:#636e72;">Reçu le : <?= formatDate($message['created_at']) ?></p>
            <hr>
            <div style="font-size:1.1em;line-height:1.7;"> <?= nl2br(htmlspecialchars($message['content'])) ?> </div>
        </div>
    </div>
    <div style="margin-top:18px;text-align:right;">
        <a href="list.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Retour à la messagerie</a>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
