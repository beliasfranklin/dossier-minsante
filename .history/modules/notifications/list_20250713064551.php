<?php
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/notifications.php';
requireAuth();

// Marquer comme lues si demandé
if (isset($_GET['mark_as_read'])) {
    markAsRead($_GET['mark_as_read']);
    header("Location: list.php");
    exit;
}

$notifications = fetchAll("SELECT * FROM notifications 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC", [$_SESSION['user_id']]);

include __DIR__.'/../../includes/header.php';
?>

<div class="module-card" style="background:#fff;border-radius:16px;box-shadow:0 2px 16px #2980b91a;padding:32px 24px;max-width:700px;margin:32px auto;">
    <h2 style="color:#2980b9;font-weight:700;margin-bottom:24px;display:flex;align-items:center;gap:10px;"><i class="fa fa-bell"></i> Mes Notifications</h2>
    <div class="notification-full-list" style="display:flex;flex-direction:column;gap:18px;">
        <?php if (empty($notifications)): ?>
            <div class="notification-item" style="text-align:center;color:#888;">Aucune notification pour le moment.</div>
        <?php endif; ?>
        <?php foreach ($notifications as $notif) : ?>
        <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" style="background:<?= $notif['is_read'] ? '#f8fafc' : '#eaf6fb' ?>;border-radius:12px;box-shadow:0 1px 6px #2980b91a;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:18px;">
            <div class="notification-content" style="flex:1;">
                <h4 style="margin:0 0 6px 0;font-size:1.1em;color:#2980b9;display:flex;align-items:center;gap:8px;">
                    <?php if (!$notif['is_read']): ?><span style="display:inline-block;width:10px;height:10px;background:#e74c3c;border-radius:50%;"></span><?php endif; ?>
                    <?= htmlspecialchars($notif['title']) ?>
                </h4>
                <p style="margin:0 0 4px 0;color:#34495e;"> <?= htmlspecialchars($notif['message']) ?> </p>
                <small style="color:#636e72;"> <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?> </small>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                <?php if (!$notif['is_read']) : ?>
                <a href="list.php?mark_as_read=<?= $notif['id'] ?>" class="btn btn-success" style="border-radius:8px;padding:8px 16px;font-size:0.98em;background:linear-gradient(90deg,#27ae60 80%,#2980b9 100%);color:#fff;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-check"></i> Marquer comme lu</a>
                <?php endif; ?>
                <?php if ($notif['related_module']) : ?>
                <a href="../<?= $notif['related_module'] ?>/view.php?id=<?= $notif['related_id'] ?>" class="btn btn-info" style="border-radius:8px;padding:8px 16px;font-size:0.98em;background:linear-gradient(90deg,#2980b9 80%,#6dd5fa 100%);color:#fff;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-eye"></i> Voir</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>