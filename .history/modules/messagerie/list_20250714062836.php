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

<div class="main-content">
    <div class="content-header">
        <div class="header-icon">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="header-text">
            <h1>Messagerie Interne</h1>
            <p>Communications et échanges entre collaborateurs</p>
        </div>
    </div>
    <div class="content-grid">
        <div class="grid-sidebar">
            <div class="info-card animate-fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Nouveau message</h3>
                </div>
                <div class="card-body">
                    <form action="send.php" method="post" class="message-form">
                        <div class="form-group">
                            <label class="form-label">Destinataire</label>
                            <div class="form-select-wrapper">
                                <select name="recipient_id" class="form-select" required>
                                    <option value="">Choisir un destinataire</option>
                                    <?php 
                                    $users = fetchAll("SELECT id, name FROM users WHERE id != ?", [$_SESSION['user_id']]);
                                    foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down select-arrow"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sujet</label>
                            <input type="text" name="subject" class="form-input" placeholder="Objet du message" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="content" rows="5" class="form-input" placeholder="Votre message..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="grid-main">
            <div class="main-card animate-fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-inbox"></i> Messages reçus</h3>
                </div>
                <div class="card-body">
                    <?php if ($messages): ?>
                        <div class="messages-list">
                            <?php foreach ($messages as $m): ?>
                            <div class="message-item <?= !$m['is_read'] ? 'unread' : '' ?>" onclick="window.location.href='view.php?id=<?= $m['id'] ?>'">
                                <div class="message-header">
                                    <h4 class="message-subject"><?= htmlspecialchars($m['subject']) ?></h4>
                                    <span class="message-date"><?= formatDate($m['created_at']) ?></span>
                                </div>
                                <div class="message-sender">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($m['sender_name']) ?>
                                </div>
                                <?php if (!$m['is_read']): ?>
                                    <div class="unread-indicator">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>Aucun message</h3>
                            <p>Vous n'avez pas encore reçu de messages</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Animation d'apparition au chargement
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-fade-in');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 200);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>