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

// Récupération des utilisateurs pour les statistiques
$users = fetchAll("SELECT id, name FROM users WHERE id != ?", [$_SESSION['user_id']]);
$totalUsers = count($users);
$unreadCount = count(array_filter($messages, fn($m) => !$m['is_read']));

include __DIR__ . '/../../includes/header.php';
?>

<style>
:root {
    --whatsapp-green: #25D366;
    --whatsapp-green-light: #dcf8c6;
    --whatsapp-blue: #128C7E;
    --whatsapp-dark: #075E54;
    --text-primary: #2C3E50;
    --text-secondary: #7F8C8D;
    --background-light: #F8F9FA;
    --border-light: #E1E8ED;
    --success-color: #27AE60;
    --warning-color: #F39C12;
}

.messaging-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 120px);
    padding: 2rem;
    position: relative;
}

.messaging-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/><circle cx="30" cy="30" r="1" fill="white" opacity="0.1"/><circle cx="70" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="90" cy="80" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    z-index: 0;
}

.messaging-header {
    background: white;
    padding: 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    box-shadow: 0 15px 50px rgba(0,0,0,0.15);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-blue));
    color: white;
}

.messaging-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.messaging-header p {
    font-size: 1.1rem;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    z-index: 1;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(0,0,0,0.15);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--whatsapp-green);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
}

.main-grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 2rem;
    position: relative;
    z-index: 1;
}

.send-panel, .messages-panel {
    background: white;
    border-radius: 24px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.15);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.panel-header {
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-blue));
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.panel-header h2, .panel-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-select, .form-input {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid var(--border-light);
    border-radius: 16px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #FAFBFC;
}

.form-select:focus, .form-input:focus {
    outline: none;
    border-color: var(--whatsapp-green);
    background: white;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
}

.form-select-wrapper {
    position: relative;
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
}

.send-btn {
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-blue));
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
}

.messages-list {
    max-height: 600px;
    overflow-y: auto;
    padding: 0;
}

.message-item {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.message-item:hover {
    background: var(--whatsapp-green-light);
    transform: translateX(5px);
}

.message-item.unread {
    background: linear-gradient(90deg, rgba(37, 211, 102, 0.1), transparent);
    border-left: 4px solid var(--whatsapp-green);
}

.message-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.message-subject {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
}

.message-date {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.unread-indicator {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--whatsapp-green);
    font-size: 0.8rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 1rem 0 0.5rem 0;
    color: var(--text-primary);
}

@media (max-width: 1024px) {
    .main-grid {
        grid-template-columns: 1fr;
    }
    
    .messaging-container {
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .messaging-header h1 {
        font-size: 2rem;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.send-panel, .messages-panel, .stat-card {
    animation: fadeInUp 0.6s ease forwards;
}

/* Scrollbar personnalisée */
.messages-list::-webkit-scrollbar {
    width: 6px;
}

.messages-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.messages-list::-webkit-scrollbar-thumb {
    background: var(--whatsapp-green);
    border-radius: 10px;
}
</style>

<div class="messaging-container">
    <!-- En-tête Messagerie -->
    <div class="messaging-header">
        <h1>
            <i class="fas fa-comments"></i>
            Messagerie Interne
        </h1>
        <p>Communications et échanges entre collaborateurs du MinSanté</p>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($messages) ?></div>
            <div class="stat-label">Messages reçus</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $unreadCount ?></div>
            <div class="stat-label">Non lus</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Collaborateurs</div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-grid">
    <!-- Contenu principal -->
    <div class="main-grid">
        <!-- Panel d'envoi -->
        <div class="send-panel">
            <div class="panel-header">
                <i class="fas fa-paper-plane" style="font-size: 1.5rem;"></i>
                <h2>Nouveau Message</h2>
            </div>
            
            <div style="padding: 2rem;">
                <form action="send.php" method="post" class="message-form">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Destinataire
                        </label>
                        <div class="form-select-wrapper">
                            <select name="recipient_id" class="form-select" required>
                                <option value="">Choisir un destinataire</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i> Sujet
                        </label>
                        <input type="text" name="subject" class="form-input" placeholder="Objet du message" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment"></i> Message
                        </label>
                        <textarea name="content" rows="6" class="form-input" placeholder="Votre message..." required></textarea>
                    </div>
                    
                    <button type="submit" class="send-btn">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer le message
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Panel des messages -->
        <div class="messages-panel">
            <div class="panel-header">
                <i class="fas fa-inbox" style="font-size: 1.5rem;"></i>
                <h3>Messages reçus</h3>
            </div>
            
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

<script>
// Animation d'apparition au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des statistiques
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((stat, index) => {
        setTimeout(() => {
            stat.style.opacity = '0';
            stat.style.transform = 'scale(0.5)';
            stat.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                stat.style.opacity = '1';
                stat.style.transform = 'scale(1)';
            }, 100);
        }, index * 200);
    });
    
    // Animation des panneaux
    const panels = document.querySelectorAll('.send-panel, .messages-panel');
    panels.forEach((panel, index) => {
        panel.style.opacity = '0';
        panel.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            panel.style.transition = 'all 0.6s ease';
            panel.style.opacity = '1';
            panel.style.transform = 'translateY(0)';
        }, 300 + index * 200);
    });
    
    // Focus automatique sur le premier champ
    setTimeout(() => {
        const firstSelect = document.querySelector('.form-select');
        if (firstSelect) firstSelect.focus();
    }, 800);
});

// Animation de soumission du formulaire
document.querySelector('.message-form').addEventListener('submit', function(e) {
    const btn = document.querySelector('.send-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
});

console.log('📱 Messagerie avec design WhatsApp chargée avec succès');
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>