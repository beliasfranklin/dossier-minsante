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
}

body {
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.activity-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.activity-header {
    background: linear-gradient(135deg, var(--accent-color), #667eea);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.activity-section, .preferences-section {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
}

.section-header {
    background: var(--bg-secondary);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
}

.activity-list {
    padding: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.2s ease;
}

.activity-item:hover {
    background: var(--bg-secondary);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    color: white;
}

.activity-login { background: var(--success-color); }
.activity-dossier { background: var(--accent-color); }
.activity-default { background: var(--text-secondary); }

.preference-card {
    margin: 1rem;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-secondary);
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: border-color 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: var(--accent-color);
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-1px);
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--accent-color);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    transition: all 0.3s ease;
}

.theme-toggle:hover {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-container {
        padding: 1rem;
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
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
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
                    <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>Aucune activité récente trouvée</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-<?= $activity['type'] ?>">
                                <i class="fas fa-<?= $activity['type'] === 'login' ? 'sign-in-alt' : 'folder' ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 500;"><?= htmlspecialchars($activity['description']) ?></div>
                                <div style="color: var(--text-secondary); font-size: 0.9em;">
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
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="email_notifications" 
                                   <?= $preferencesManager->get('email_notifications', '1') === '1' ? 'checked' : '' ?>>
                            Notifications email
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="browser_notifications"
                                   <?= $preferencesManager->get('browser_notifications', '0') === '1' ? 'checked' : '' ?>>
                            Notifications navigateur
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="activity_summary"
                                   <?= $preferencesManager->get('activity_summary', '1') === '1' ? 'checked' : '' ?>>
                            Résumé d'activité
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

<!-- Bouton de changement de thème rapide -->
<button class="theme-toggle" onclick="toggleTheme()" title="Changer de thème">
    <i class="fas fa-<?= $preferencesManager->getTheme() === 'dark' ? 'sun' : 'moon' ?>"></i>
</button>

<script>
// Changement de thème rapide
function toggleTheme() {
    const currentTheme = '<?= $preferencesManager->getTheme() ?>';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Créer un formulaire invisible pour soumettre le changement
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    form.innerHTML = `
        <input type="hidden" name="action" value="update_theme">
        <input type="hidden" name="theme" value="${newTheme}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}

// Animation des stats au chargement
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Sauvegarde automatique pour certains champs
document.addEventListener('change', function(e) {
    if (e.target.name === 'theme') {
        e.target.form.submit();
    }
});

console.log('🎨 Page d\'activité chargée avec préférences');
console.log('Thème actuel:', '<?= $preferencesManager->getTheme() ?>');
</script>

<?php require_once '../../includes/footer.php'; ?>
