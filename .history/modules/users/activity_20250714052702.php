<?php
/**
 * Page d'activité utilisateur avec système de préférences intégré
 */

require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/preferences.php';

// Vérifier l'authentification
requireAuth();

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);

// Traitement des préférences si soumission
if ($_POST && isset($_POST['action'])) {
    $success = false;
    $message = '';
    
    switch ($_POST['action']) {
        case 'update_theme':
            if (in_array($_POST['theme'], ['light'])) {
                $success = $preferencesManager->set('theme', $_POST['theme']);
                $message = $success ? 'Thème mis à jour avec succès' : 'Erreur lors de la mise à jour du thème';
            }
            break;
            
        case 'update_display':
            if (isset($_POST['items_per_page']) && in_array($_POST['items_per_page'], [10, 20, 50, 100])) {
                $success = $preferencesManager->set('items_per_page', $_POST['items_per_page']);
            }
            if (isset($_POST['dashboard_layout']) && in_array($_POST['dashboard_layout'], ['grid', 'list'])) {
                $success = $preferencesManager->set('dashboard_layout', $_POST['dashboard_layout']) || $success;
            }
            $message = $success ? 'Préférences d\'affichage mises à jour' : 'Erreur lors de la mise à jour';
            break;
            
        case 'update_notifications':
            $notifTypes = ['email_notifications', 'browser_notifications', 'activity_summary'];
            foreach ($notifTypes as $type) {
                $value = isset($_POST[$type]) ? '1' : '0';
                $preferencesManager->set($type, $value);
            }
            $success = true;
            $message = 'Paramètres de notification mis à jour';
            break;
    }
    
    if ($success) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = $message;
    }
    
    // Redirection pour éviter resoumission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Récupérer les données d'activité de l'utilisateur
try {
    // Activité récente (connexions, actions importantes)
    $stmt = $pdo->prepare("
        SELECT 'login' as type, created_at, CONCAT('Connexion depuis ', ip_address) as description 
        FROM user_sessions 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 'dossier' as type, created_at, CONCAT('Dossier créé: ', numero_dossier) as description
        FROM dossiers 
        WHERE created_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY created_at DESC 
        LIMIT " . $preferencesManager->getItemsPerPage()
    );
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    $activities = [];
    error_log("Erreur récupération activité: " . $e->getMessage());
}

// Récupérer les préférences actuelles
$currentPrefs = $preferencesManager->getAll();
$themeVars = $preferencesManager->getThemeVariables();

$pageTitle = "Mon Activité";
require_once '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables couleurs modernes */
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --shadow-soft: 0 10px 40px rgba(0,0,0,0.1);
    --shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    color: var(--text-primary);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    transition: var(--transition);
}

.activity-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.activity-header {
    background: var(--gradient-primary);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.activity-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
}

.activity-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.activity-header p {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
    position: relative;
    z-index: 1;
}

.badge {
    background: rgba(255,255,255,0.2) !important;
    backdrop-filter: blur(10px);
    padding: 0.75rem 1.5rem !important;
    border-radius: 25px !important;
    border: 1px solid rgba(255,255,255,0.3);
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow-soft);
    transition: var(--transition);
    border: 1px solid rgba(255,255,255,0.8);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
}

.stat-value {
    font-size: 3rem;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.activity-section, .preferences-section {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(255,255,255,0.8);
}

.section-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-header i {
    font-size: 1.5rem;
    color: var(--accent-color);
}

.activity-list {
    padding: 1rem;
    max-height: 600px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.activity-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(8px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.activity-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.5rem;
    color: white;
    font-size: 1.3rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.activity-login { 
    background: var(--gradient-success);
}
.activity-dossier { 
    background: var(--gradient-primary);
}
.activity-default { 
    background: var(--gradient-warning);
}

.activity-content {
    flex: 1;
}

.activity-description {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.activity-time {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.preference-card {
    margin: 1.5rem;
    padding: 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    transition: var(--transition);
}

.preference-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.preference-card h4 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
}

.preference-card h4 i {
    color: var(--accent-color);
    font-size: 1.3rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    background: white;
    color: var(--text-primary);
    transition: var(--transition);
    font-size: 1rem;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    transform: scale(1.02);
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-primary:active {
    transform: translateY(-1px);
}

.alert {
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    border: none;
    box-shadow: var(--shadow-soft);
}

.alert i {
    font-size: 1.5rem;
}

.alert-success {
    background: var(--gradient-success);
    color: white;
}

.alert-error {
    background: var(--gradient-warning);
    color: white;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    transition: var(--transition);
    cursor: pointer;
}

.checkbox-wrapper:hover {
    background: #f8f9fa;
}

.checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
    color: var(--accent-color);
}

.empty-state p {
    font-size: 1.2rem;
    font-weight: 500;
}

/* Scrollbar personnalisée */
.activity-list::-webkit-scrollbar {
    width: 8px;
}

.activity-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.activity-list::-webkit-scrollbar-thumb {
    background: var(--gradient-primary);
    border-radius: 10px;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.stat-card {
    animation: fadeInUp 0.6s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

/* Responsive Design */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-container {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    .activity-header {
        padding: 2rem 1.5rem;
    }
    
    .activity-header h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-value {
        font-size: 2.5rem;
    }
    
    .preference-card {
        margin: 1rem;
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .activity-icon {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2d2d2d;
        --text-primary: #ffffff;
        --text-secondary: #b0b0b0;
        --border-color: #404040;
    }
    
    body {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    }
    
    .stat-card,
    .activity-section,
    .preferences-section {
        background: var(--bg-primary);
        border-color: var(--border-color);
    }
    
    .form-control {
        background: var(--bg-secondary);
        border-color: var(--border-color);
        color: var(--text-primary);
    }
}

/* Print styles */
@media print {
    .preference-card,
    .btn {
        display: none;
    }
    
    .activity-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
}
</style>

<div class="activity-container">
    <!-- En-tête -->
    <div class="activity-header">
        <h1><i class="fas fa-chart-line"></i> Mon Activité</h1>
        <p>Consultez votre activité récente et gérez vos préférences</p>
        <div style="margin-top: 1rem;">
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 20px;">
                Thème actuel: <?= ucfirst($preferencesManager->getTheme()) ?>
            </span>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($activities) ?></div>
            <div>Activités récentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $preferencesManager->getItemsPerPage() ?></div>
            <div>Éléments par page</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= ucfirst($preferencesManager->getDashboardLayout()) ?></div>
            <div>Mode d'affichage</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= strtoupper($preferencesManager->getLanguage()) ?></div>
            <div>Langue</div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="content-grid">
        <!-- Liste d'activités -->
        <div class="activity-section">
            <div class="section-header">
                <i class="fas fa-history"></i> Activité Récente
            </div>
            <div class="activity-list">
                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>Aucune activité récente trouvée</p>
                        <small>Vos prochaines actions apparaîtront ici</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-<?= $activity['type'] ?>">
                                <i class="fas fa-<?= $activity['type'] === 'login' ? 'sign-in-alt' : 'folder' ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-description">
                                    <?= htmlspecialchars($activity['description']) ?>
                                </div>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i>
                                    <?= date('d/m/Y à H:i', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Préférences -->
        <div class="preferences-section">
            <div class="section-header">
                <i class="fas fa-cog"></i> Préférences Rapides
            </div>

            <!-- Thème -->
            <div class="preference-card">
                <h4><i class="fas fa-palette"></i> Thème</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="update_theme">
                    <div class="form-group">
                        <select name="theme" class="form-control" onchange="this.form.submit()">
                            <option value="light" <?= $preferencesManager->getTheme() === 'light' ? 'selected' : '' ?>>🌞 Clair</option>
                            <option value="dark" <?= $preferencesManager->getTheme() === 'dark' ? 'selected' : '' ?>>🌙 Sombre</option>
                            <option value="auto" <?= $preferencesManager->getTheme() === 'auto' ? 'selected' : '' ?>>🔄 Automatique</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Affichage -->
            <div class="preference-card">
                <h4><i class="fas fa-list"></i> Affichage</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="update_display">
                    <div class="form-group">
                        <label class="form-label">Éléments par page</label>
                        <select name="items_per_page" class="form-control">
                            <option value="10" <?= $preferencesManager->getItemsPerPage() == 10 ? 'selected' : '' ?>>10</option>
                            <option value="20" <?= $preferencesManager->getItemsPerPage() == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $preferencesManager->getItemsPerPage() == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $preferencesManager->getItemsPerPage() == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Layout Dashboard</label>
                        <select name="dashboard_layout" class="form-control">
                            <option value="grid" <?= $preferencesManager->getDashboardLayout() === 'grid' ? 'selected' : '' ?>>Grille</option>
                            <option value="list" <?= $preferencesManager->getDashboardLayout() === 'list' ? 'selected' : '' ?>>Liste</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Sauvegarder
                    </button>
                </form>
            </div>

            <!-- Notifications -->
            <div class="preference-card">
                <h4><i class="fas fa-bell"></i> Notifications</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="email_notifications" 
                                   <?= $preferencesManager->get('email_notifications', '1') === '1' ? 'checked' : '' ?>>
                            <span>
                                <i class="fas fa-envelope"></i>
                                Notifications email
                            </span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="browser_notifications"
                                   <?= $preferencesManager->get('browser_notifications', '0') === '1' ? 'checked' : '' ?>>
                            <span>
                                <i class="fas fa-desktop"></i>
                                Notifications navigateur
                            </span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="activity_summary"
                                   <?= $preferencesManager->get('activity_summary', '1') === '1' ? 'checked' : '' ?>>
                            <span>
                                <i class="fas fa-chart-line"></i>
                                Résumé d'activité
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Sauvegarder
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Animation des statistiques au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes de statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Animation des éléments d'activité
    const activityItems = document.querySelectorAll('.activity-item');
    activityItems.forEach((item, index) => {
        setTimeout(() => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-30px)';
            item.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 50);
        }, index * 100);
    });
    
    // Améliorer l'expérience des formulaires
    enhanceFormExperience();
    
    // Initialiser les tooltips si disponibles
    initTooltips();
});

// Amélioration de l'expérience des formulaires
function enhanceFormExperience() {
    // Auto-submit pour le changement de thème
    const themeSelect = document.querySelector('select[name="theme"]');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            // Ajouter un feedback visuel
            this.style.borderColor = 'var(--accent-color)';
            this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.1)';
            
            // Soumettre après un court délai pour le feedback
            setTimeout(() => {
                this.form.submit();
            }, 300);
        });
    }
    
    // Améliorer les checkboxes
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const wrapper = this.closest('.checkbox-wrapper');
            if (wrapper) {
                wrapper.style.background = this.checked ? 
                    'linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1))' : 
                    '';
            }
        });
    });
    
    // Validation en temps réel
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
                
                // Réactiver après 3 secondes au cas où
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Sauvegarder';
                }, 3000);
            }
        });
    });
}

// Initialiser les tooltips
function initTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Gestion du scroll smooth pour la navigation
function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Fonction pour afficher des notifications toast
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'var(--gradient-success)' : 'var(--gradient-warning)'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Suppression automatique
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Gestion des erreurs de formulaire côté client
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger-color)';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    return isValid;
}

// Amélioration de l'accessibilité
function enhanceAccessibility() {
    // Ajouter des attributs ARIA
    const buttons = document.querySelectorAll('button');
    buttons.forEach(btn => {
        if (!btn.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', btn.textContent.trim());
        }
    });
    
    // Support des raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl + S pour sauvegarder le premier formulaire visible
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const firstForm = document.querySelector('form:not([hidden])');
            if (firstForm) {
                firstForm.submit();
            }
        }
    });
}

// Initialiser l'accessibilité
enhanceAccessibility();

// Feedback visuel pour les interactions
document.addEventListener('click', function(e) {
    if (e.target.matches('.btn, .stat-card, .activity-item')) {
        // Effet de ripple
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        const rect = e.target.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        
        e.target.style.position = 'relative';
        e.target.style.overflow = 'hidden';
        e.target.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
});

// Ajouter les styles pour l'animation ripple
const rippleStyles = document.createElement('style');
rippleStyles.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyles);

console.log('🎨 Page d\'activité moderne chargée avec succès');
console.log('📊 Statistiques animées activées');
console.log('🔧 Préférences utilisateur:', {
    theme: '<?= $preferencesManager->getTheme() ?>',
    itemsPerPage: <?= $preferencesManager->getItemsPerPage() ?>,
    layout: '<?= $preferencesManager->getDashboardLayout() ?>'
});
</script>

<?php require_once '../../includes/footer.php'; ?>
