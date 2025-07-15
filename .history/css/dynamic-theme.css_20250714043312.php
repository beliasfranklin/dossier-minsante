<?php
/**
 * Générateur de CSS dynamique basé sur les préférences utilisateur
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/preferences.php';

// Définir le type de contenu
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo);
$theme = $preferencesManager->getTheme();
$themeCSS = $preferencesManager->getThemeCSS();

?>
/* CSS Dynamique généré selon les préférences utilisateur */
/* Thème actuel: <?= $theme ?> */

:root {
<?php foreach ($themeCSS as $property => $value): ?>
    <?= $property ?>: <?= $value ?>;
<?php endforeach; ?>
}

/* Styles de base pour tous les thèmes */
body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

/* Header et navigation */
.navbar, .page-header {
    background: var(--gradient-primary) !important;
    color: var(--text-primary);
}

/* Cards et conteneurs */
.card, .preference-card, .module-card {
    background: var(--bg-card) !important;
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
}

.card-header {
    background: var(--bg-secondary) !important;
    border-bottom: 1px solid var(--border-color);
}

/* Formulaires */
input, select, textarea {
    background: var(--bg-card) !important;
    color: var(--text-primary) !important;
    border: 2px solid var(--border-color) !important;
}

input:focus, select:focus, textarea:focus {
    border-color: var(--accent-color) !important;
    box-shadow: 0 0 0 3px rgba(22, 160, 133, 0.1) !important;
}

/* Boutons */
.btn-primary, button[type="submit"] {
    background: var(--gradient-accent) !important;
    border: none !important;
    color: white !important;
}

.btn-primary:hover, button[type="submit"]:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Tableaux */
table {
    background: var(--bg-card);
    color: var(--text-primary);
}

th {
    background: var(--bg-secondary) !important;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-color);
}

td {
    border-bottom: 1px solid var(--border-color);
}

/* Alertes */
.alert-success {
    background: rgba(40, 167, 69, 0.1) !important;
    color: var(--success-color) !important;
    border-left: 4px solid var(--success-color) !important;
}

.alert-error, .alert-danger {
    background: rgba(220, 53, 69, 0.1) !important;
    color: var(--danger-color) !important;
    border-left: 4px solid var(--danger-color) !important;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1) !important;
    color: var(--warning-color) !important;
    border-left: 4px solid var(--warning-color) !important;
}

/* Sidebar */
.sidebar {
    background: var(--bg-secondary) !important;
    border-right: 1px solid var(--border-color);
}

.sidebar a {
    color: var(--text-secondary) !important;
}

.sidebar a:hover, .sidebar a.active {
    background: var(--accent-color) !important;
    color: white !important;
}

/* Container principal */
.container {
    background: var(--bg-primary);
}

/* Styles spécifiques au thème sombre */
<?php if ($theme === 'dark'): ?>
/* Mode sombre activé */
body {
    background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
}

.page-header {
    background: linear-gradient(135deg, #2c3e50, #34495e) !important;
}

/* Scrollbars pour le mode sombre */
::-webkit-scrollbar {
    width: 8px;
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--accent-color);
}

/* Liens en mode sombre */
a {
    color: var(--accent-color) !important;
}

a:hover {
    color: #6bb6ff !important;
}

<?php else: ?>
/* Mode clair activé */
body {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
}

/* Scrollbars pour le mode clair */
::-webkit-scrollbar {
    width: 8px;
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--accent-color);
}

<?php endif; ?>

/* Animations et transitions */
.preference-card, .module-card {
    transition: all 0.3s ease;
}

.preference-card:hover, .module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,<?= $theme === 'dark' ? '0.4' : '0.15' ?>);
}

/* Responsive et layout selon les préférences */
<?php if ($preferencesManager->getDashboardLayout() === 'list'): ?>
/* Layout en liste */
.dashboard-grid {
    display: flex !important;
    flex-direction: column !important;
}

.dashboard-item {
    width: 100% !important;
    margin-bottom: 1rem !important;
}

<?php else: ?>
/* Layout en grille */
.dashboard-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)) !important;
    gap: 2rem !important;
}

<?php endif; ?>

/* Pagination selon les préférences */
.pagination-info::after {
    content: " (<?= $preferencesManager->getItemsPerPage() ?> par page)";
    color: var(--text-muted);
    font-size: 0.9em;
}

/* Indicateur de thème */
.theme-indicator {
    position: fixed;
    top: 10px;
    right: 10px;
    background: var(--accent-color);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    z-index: 1000;
    opacity: 0.7;
}

.theme-indicator::before {
    content: "<?= $theme === 'dark' ? '🌙' : '🌞' ?> <?= ucfirst($theme) ?>";
}

/* Media queries pour le responsive */
@media (max-width: 768px) {
    .preferences-container {
        grid-template-columns: 1fr !important;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
}

/* Animations de chargement */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Personnalisation des éléments selon la langue */
<?php if ($preferencesManager->getLanguage() === 'en'): ?>
/* Styles spécifiques à l'anglais */
body {
    font-family: 'Arial', sans-serif;
}

<?php else: ?>
/* Styles spécifiques au français */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

<?php endif; ?>

/* Classes utilitaires dynamiques */
.text-theme-primary { color: var(--text-primary) !important; }
.text-theme-secondary { color: var(--text-secondary) !important; }
.text-theme-muted { color: var(--text-muted) !important; }
.bg-theme-primary { background: var(--bg-primary) !important; }
.bg-theme-secondary { background: var(--bg-secondary) !important; }
.bg-theme-card { background: var(--bg-card) !important; }
.border-theme { border-color: var(--border-color) !important; }
.gradient-theme-primary { background: var(--gradient-primary) !important; }
.gradient-theme-accent { background: var(--gradient-accent) !important; }

/* Debug info (visible uniquement en mode debug) */
<?php if (isset($_GET['debug'])): ?>
.debug-info {
    position: fixed;
    bottom: 10px;
    left: 10px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 1000;
}

.debug-info::before {
    content: "Debug: Thème=<?= $theme ?>, Lang=<?= $preferencesManager->getLanguage() ?>, Layout=<?= $preferencesManager->getDashboardLayout() ?>";
}

<?php endif; ?>
