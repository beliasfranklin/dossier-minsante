<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Récupération des messages
$messages = fetchAll("
    SELECT m.*, u.name as sender_name 
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.recipient_id = ?
    ORDER BY m.created_at DESC
", [$_SESSION['user_id']]);

// Marquer comme lus
executeQuery("
    UPDATE messages SET is_read = 1 
    WHERE recipient_id = ? AND is_read = 0
", [$_SESSION['user_id']]);

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="container messagerie-section" style="max-width:1000px;margin:auto;">
    <div class="messagerie-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="messagerie-icon"><i class="fas fa-envelope" style="font-size:48px;color:#2980b9;"></i></div>
        <h1 class="section-title" style="color:#2980b9;">Messagerie Interne</h1>
    </div>
    <div class="row" style="display:flex;gap:32px;flex-wrap:wrap;">
        <div class="col-md-4" style="flex:1 1 320px;min-width:320px;">
            <div class="card" style="background:#f4f8fb;border-radius:12px;box-shadow:0 2px 8px #2980b922;">
                <div class="card-header" style="font-weight:600;color:#2980b9;background:#eaf6fb;border-radius:12px 12px 0 0;">Nouveau message</div>
                <div class="card-body">
                    <form action="send.php" method="post">
                        <div class="form-group">
                            <label>Destinataire</label>
                            <select name="recipient_id" class="form-control" required>
                                <?php 
                                $users = fetchAll("SELECT id, name FROM users WHERE id != ?", [$_SESSION['user_id']]);
                                foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sujet</label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="content" rows="5" class="form-control" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8" style="flex:2 1 480px;min-width:320px;">
            <div class="card" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px #2980b922;">
                <div class="card-header" style="font-weight:600;color:#2980b9;background:#eaf6fb;border-radius:12px 12px 0 0;">Messages reçus</div>
                <div class="card-body">
                    <?php if ($messages): ?>
                        <div class="list-group">
                            <?php foreach ($messages as $m): ?>
                            <a href="view.php?id=<?= $m['id'] ?>" class="list-group-item list-group-item-action <?= !$m['is_read'] ? 'unread' : '' ?>" style="border-radius:8px;margin-bottom:8px;padding:16px 18px;background:#f4f8fb;box-shadow:0 1px 4px #e0eafc22;display:block;">
                                <div class="d-flex w-100 justify-content-between" style="display:flex;justify-content:space-between;align-items:center;">
                                    <h5 class="mb-1" style="margin:0;font-size:1.1em;font-weight:600;color:#2980b9;"><?= htmlspecialchars($m['subject']) ?></h5>
                                    <small style="color:#636e72;"><?= formatDate($m['created_at']) ?></small>
                                </div>
                                <p class="mb-1" style="margin:0;color:#34495e;">De: <?= htmlspecialchars($m['sender_name']) ?></p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Aucun message</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.unread {
    background-color: #f8f9fa;
    font-weight: bold;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>