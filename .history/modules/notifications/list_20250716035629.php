<?php
session_start();
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../../includes/auth.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Fonction pour marquer une notification comme lue
function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        error_log("Erreur markNotificationAsRead : " . $e->getMessage());
        return false;
    }
}

// Fonction pour marquer toutes les notifications comme lues
function markAllNotificationsAsRead($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Erreur markAllNotificationsAsRead : " . $e->getMessage());
        return false;
    }
}

// Traitement des actions
if (isset($_GET['mark_as_read']) && is_numeric($_GET['mark_as_read'])) {
    if (markNotificationAsRead($_GET['mark_as_read'], $_SESSION['user_id'])) {
        $_SESSION['success_message'] = "Notification marquée comme lue";
    }
    header("Location: list.php");
    exit;
}

if (isset($_GET['mark_all_read'])) {
    if (markAllNotificationsAsRead($_SESSION['user_id'])) {
        $_SESSION['success_message'] = "Toutes les notifications ont été marquées comme lues";
    }
    header("Location: list.php");
    exit;
}

// Récupération des notifications
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les notifications non lues
    $unreadCount = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) {
            $unreadCount++;
        }
    }
} catch (PDOException $e) {
    error_log("Erreur notifications : " . $e->getMessage());
    $notifications = [];
    $unreadCount = 0;
}

$pageTitle = "Notifications - MinSanté Dossiers";
include __DIR__ . '/../../includes/header.php';
?>

<div class="notifications-container">
    <div class="container">
        <!-- En-tête de la page -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-title-section">
                    <div class="page-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Centre de notifications</h1>
                        <p class="page-subtitle">
                            Gérez vos alertes et notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="unread-badge"><?= $unreadCount ?> non lue<?= $unreadCount > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="page-actions">
                    <?php if ($unreadCount > 0): ?>
                        <a href="?mark_all_read=1" class="btn-outline" title="Marquer toutes comme lues">
                            <i class="fas fa-check-double"></i>
                            Tout marquer comme lu
                        </a>
                    <?php endif; ?>
                    <button onclick="refreshNotifications()" class="btn-outline" title="Actualiser">
                        <i class="fas fa-sync-alt"></i>
                        Actualiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres et statistiques -->
        <?php if (!empty($notifications)): ?>
        <div class="notifications-stats">
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-icon total">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?= count($notifications) ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon unread">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?= $unreadCount ?></span>
                        <span class="stat-label">Non lues</span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon read">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?= count($notifications) - $unreadCount ?></span>
                        <span class="stat-label">Lues</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des notifications -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>Aucune notification</h3>
                <p>Vous n'avez pas encore reçu de notifications. Les alertes et messages importants apparaîtront ici.</p>
                <a href="../../dashboard.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Retour au tableau de bord
                </a>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $index => $notif): ?>
                <div class="notification-card <?= $notif['is_read'] ? 'read' : 'unread' ?>" style="animation-delay: <?= $index * 0.1 ?>s;">
                    <div class="notification-status">
                        <?php if (!$notif['is_read']): ?>
                            <div class="unread-indicator"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-icon">
                        <?php
                        $iconConfig = [
                            'dossiers' => ['icon' => 'fas fa-folder', 'color' => 'primary'],
                            'users' => ['icon' => 'fas fa-user', 'color' => 'info'],
                            'workflow' => ['icon' => 'fas fa-cogs', 'color' => 'warning'],
                            'system' => ['icon' => 'fas fa-info-circle', 'color' => 'secondary'],
                            'success' => ['icon' => 'fas fa-check-circle', 'color' => 'success'],
                            'error' => ['icon' => 'fas fa-exclamation-triangle', 'color' => 'danger'],
                            'reminder' => ['icon' => 'fas fa-clock', 'color' => 'warning']
                        ];
                        $config = $iconConfig[$notif['related_module'] ?? 'system'] ?? $iconConfig['system'];
                        ?>
                        <i class="<?= $config['icon'] ?> <?= $config['color'] ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-header">
                            <h4 class="notification-title"><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></h4>
                            <div class="notification-meta">
                                <span class="notification-date">
                                    <i class="fas fa-clock"></i>
                                    <?php
                                    $date = new DateTime($notif['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($date);
                                    
                                    if ($diff->days == 0) {
                                        if ($diff->h == 0) {
                                            echo $diff->i == 0 ? "À l'instant" : "Il y a " . $diff->i . " min";
                                        } else {
                                            echo "Il y a " . $diff->h . "h" . ($diff->i > 0 ? $diff->i . "min" : "");
                                        }
                                    } elseif ($diff->days == 1) {
                                        echo "Hier à " . $date->format('H:i');
                                    } elseif ($diff->days <= 7) {
                                        echo "Il y a " . $diff->days . " jour" . ($diff->days > 1 ? 's' : '');
                                    } else {
                                        echo $date->format('d/m/Y à H:i');
                                    }
                                    ?>
                                </span>
                                <?php if (!empty($notif['related_module'])): ?>
                                    <span class="notification-category">
                                        <i class="fas fa-tag"></i>
                                        <?= ucfirst($notif['related_module']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?= htmlspecialchars($notif['message'] ?? '') ?>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (!$notif['is_read']): ?>
                                <a href="list.php?mark_as_read=<?= $notif['id'] ?>" class="action-btn read-btn">
                                    <i class="fas fa-check"></i>
                                    Marquer comme lu
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($notif['related_module']) && !empty($notif['related_id'])): ?>
                                <a href="../<?= $notif['related_module'] ?>/view.php?id=<?= $notif['related_id'] ?>" class="action-btn view-btn">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir le détail
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination si nécessaire -->
            <?php if (count($notifications) > 20): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Affichage de <?= min(20, count($notifications)) ?> sur <?= count($notifications) ?> notifications
                </div>
                <div class="pagination-actions">
                    <button class="btn-outline" disabled>
                        <i class="fas fa-chevron-left"></i>
                        Précédent
                    </button>
                    <button class="btn-outline">
                        Suivant
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Variables CSS pour les notifications */
:root {
    --primary-50: #eff6ff;
    --primary-100: #dbeafe;
    --primary-500: #3b82f6;
    --primary-600: #2563eb;
    
    --success-50: #ecfdf5;
    --success-100: #d1fae5;
    --success-500: #10b981;
    --success-600: #059669;
    
    --danger-50: #fef2f2;
    --danger-100: #fee2e2;
    --danger-500: #ef4444;
    --danger-600: #dc2626;
    
    --warning-50: #fffbeb;
    --warning-100: #fef3c7;
    --warning-500: #f59e0b;
    --warning-600: #d97706;
    
    --info-50: #f0f9ff;
    --info-100: #e0f2fe;
    --info-500: #06b6d4;
    --info-600: #0891b2;
    
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-2xl: 20px;
    
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    
    --transition-fast: all 0.15s ease;
    --transition-all: all 0.3s ease;
}

/* Container principal */
.notifications-container {
    min-height: calc(100vh - 70px);
    background: var(--gray-50);
    padding: 2rem 0;
}

/* En-tête de la page */
.page-header {
    background: white;
    border-radius: var(--radius-2xl);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
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
    background: var(--gradient-primary);
}

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient-primary);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: var(--shadow-lg);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
}

.page-subtitle {
    color: var(--gray-600);
    font-size: 1.1rem;
    margin: 0.5rem 0 0 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.unread-badge {
    background: var(--gradient-danger);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    animation: pulse 2s infinite;
}

.page-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Boutons */
.btn-primary {
    background: var(--gradient-primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-lg);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition-all);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    box-shadow: var(--shadow-md);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--gray-300);
    color: var(--gray-700);
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-lg);
    font-weight: 500;
    transition: var(--transition-all);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    cursor: pointer;
}

.btn-outline:hover {
    border-color: var(--primary-500);
    color: var(--primary-600);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Statistiques des notifications */
.notifications-stats {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius-lg);
    background: var(--gray-50);
    transition: var(--transition-all);
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: var(--shadow-md);
}

.stat-icon.total {
    background: var(--gradient-primary);
}

.stat-icon.unread {
    background: var(--gradient-danger);
}

.stat-icon.read {
    background: var(--gradient-success);
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-500);
    font-weight: 500;
}

/* Liste des notifications */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notification-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    transition: var(--transition-all);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    opacity: 0;
    transform: translateY(20px);
    animation: slideInUp 0.6s ease-out forwards;
}

.notification-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-500);
}

.notification-card.unread {
    border-left: 4px solid var(--primary-500);
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.notification-card.read {
    opacity: 0.8;
}

.notification-status {
    position: relative;
    width: 8px;
    flex-shrink: 0;
}

.unread-indicator {
    width: 8px;
    height: 8px;
    background: var(--primary-500);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.notification-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: var(--shadow-md);
    flex-shrink: 0;
}

.notification-icon .primary {
    background: var(--gradient-primary);
    color: white;
}

.notification-icon .success {
    background: var(--gradient-success);
    color: white;
}

.notification-icon .danger {
    background: var(--gradient-danger);
    color: white;
}

.notification-icon .warning {
    background: var(--gradient-warning);
    color: white;
}

.notification-icon .info {
    background: var(--gradient-info);
    color: white;
}

.notification-icon .secondary {
    background: var(--gray-500);
    color: white;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    gap: 1rem;
}

.notification-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    line-height: 1.4;
}

.notification-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
    flex-shrink: 0;
}

.notification-date,
.notification-category {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--gray-500);
}

.notification-category {
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius-lg);
    font-weight: 500;
}

.notification-message {
    color: var(--gray-700);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.notification-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-lg);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition-all);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
}

.read-btn {
    background: var(--success-100);
    color: var(--success-600);
    border: 1px solid var(--success-200);
}

.read-btn:hover {
    background: var(--success-200);
    transform: translateY(-1px);
}

.view-btn {
    background: var(--primary-100);
    color: var(--primary-600);
    border: 1px solid var(--primary-200);
}

.view-btn:hover {
    background: var(--primary-200);
    transform: translateY(-1px);
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: var(--radius-2xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.empty-icon {
    width: 120px;
    height: 120px;
    background: var(--gray-100);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    font-size: 3rem;
    color: var(--gray-400);
    animation: float 3s ease-in-out infinite;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gray-700);
    margin: 0 0 1rem 0;
}

.empty-state p {
    color: var(--gray-500);
    margin: 0 0 2rem 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.pagination-info {
    color: var(--gray-600);
    font-size: 0.9rem;
}

.pagination-actions {
    display: flex;
    gap: 0.75rem;
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.1);
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .notifications-container {
        padding: 1rem 0;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .page-title-section {
        width: 100%;
    }
    
    .page-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .notification-card {
        padding: 1rem;
        flex-direction: column;
        align-items: stretch;
    }
    
    .notification-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .notification-meta {
        align-items: flex-start;
        flex-direction: row;
        justify-content: space-between;
    }
    
    .notification-actions {
        justify-content: center;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.5rem;
    }
    
    .notification-actions {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
    }
}
</style>

<script>
// Fonction de rafraîchissement
function refreshNotifications() {
    const button = event.target.closest('.btn-outline');
    const icon = button.querySelector('i');
    
    // Animation du bouton
    icon.style.animation = 'spin 1s linear infinite';
    button.disabled = true;
    
    // Simulation du rechargement
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Animation d'apparition des notifications
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes de notification
    const cards = document.querySelectorAll('.notification-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Gestion des clics sur les notifications non lues
    document.querySelectorAll('.notification-card.unread').forEach(card => {
        card.addEventListener('click', function(e) {
            // Si ce n'est pas un clic sur un bouton d'action
            if (!e.target.closest('.action-btn')) {
                // Ajouter un effet visuel pour indiquer que la notification a été "vue"
                this.style.background = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';
            }
        });
    });
});

// Style pour l'animation de rotation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>