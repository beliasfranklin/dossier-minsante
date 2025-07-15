<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_ADMIN);

// Statistiques des dossiers par statut
$stats_dossiers = $pdo->query("SELECT status, COUNT(*) as count FROM dossiers GROUP BY status")->fetchAll();

// Statistiques des utilisateurs par rôle
$stats_users = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();

// Timeline des créations (30 derniers jours)
$stats_timeline = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM dossiers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Statistiques avancées
$advanced_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_dossiers,
        COUNT(DISTINCT created_by) as users_actifs,
        AVG(DATEDIFF(updated_at, created_at)) as duree_moyenne,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as dossiers_valides,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as nouveaux_7j,
        COUNT(CASE WHEN deadline < NOW() AND status != 'archive' THEN 1 END) as en_retard
    FROM dossiers
")->fetch();

// Activité récente par mois
$monthly_activity = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as nouveaux,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as valides
    FROM dossiers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mois DESC
    LIMIT 12
")->fetchAll();

// Statistiques par service (si la colonne service existe)
$stats_services = [];
try {
    $stats_services = $pdo->query("
        SELECT 
            u.service,
            COUNT(d.id) as count,
            COUNT(DISTINCT u.id) as users
        FROM dossiers d 
        LEFT JOIN users u ON d.created_by = u.id 
        GROUP BY u.service
        ORDER BY count DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Service column might not exist
}

// Statistiques par type (si la colonne type existe)
$stats_types = [];
try {
    $stats_types = $pdo->query("
        SELECT 
            type,
            COUNT(*) as count,
            (SELECT status FROM dossiers d2 WHERE d2.type = d1.type GROUP BY status ORDER BY COUNT(*) DESC LIMIT 1) as dominant_status
        FROM dossiers d1 
        GROUP BY type
        ORDER BY count DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Type column might not exist
}

// Utilisateurs les plus actifs
$stats_users_active = [];
try {
    $stats_users_active = $pdo->query("
        SELECT 
            u.id, u.nom, u.prenom, u.email, u.service, u.role,
            COUNT(d.id) as count,
            MAX(d.created_at) as derniere_activite
        FROM users u
        LEFT JOIN dossiers d ON u.id = d.created_by
        WHERE d.id IS NOT NULL
        GROUP BY u.id
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    // Handle missing columns
}

// Évolution temporelle (12 derniers mois)
$stats_evolution = [];
try {
    $stats_evolution = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mois,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'valide' THEN 1 END) as valides
        FROM dossiers 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mois ASC
    ")->fetchAll();
} catch (Exception $e) {
    // Handle errors
}

// Organiser les données dans un tableau pour compatibilité
$stats = [
    'dossiers' => $stats_dossiers,
    'users' => $stats_users,
    'timeline' => $stats_timeline
];

$page_title = "Statistiques et Reporting";
include __DIR__ . '/../../includes/header.php';
?>
<style>
:root {
    --stats-primary: #667eea;
    --stats-secondary: #764ba2;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-bg: #f8fafc;
    --white: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.stats-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    animation: fadeIn 0.8s ease-out;
}

.page-header {
    background: linear-gradient(135deg, var(--stats-primary) 0%, var(--stats-secondary) 100%);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow);
    animation: slideInDown 0.8s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    pointer-events: none;
}

.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 24px;
}

.header-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    color: var(--white);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}

.header-text h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 8px 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.header-text p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    font-size: 1.1rem;
    font-weight: 500;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.overview-card {
    background: var(--white);
    border-radius: 20px;
    padding: 28px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
}

.overview-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-hover);
}

.overview-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--stats-primary), var(--stats-secondary));
}

.card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: var(--white);
    background: linear-gradient(135deg, var(--stats-primary), var(--stats-secondary));
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.big-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--stats-primary);
    margin: 0 0 8px 0;
    line-height: 1;
}

.metric-label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.status-card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
}

.status-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(102, 126, 234, 0.15);
}

.status-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.status-card.en_attente::before { background: var(--warning-color); }
.status-card.en_cours::before { background: var(--info-color); }
.status-card.valide::before { background: var(--success-color); }
.status-card.rejete::before { background: var(--danger-color); }
.status-card.archive::before { background: var(--text-secondary); }

.status-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.status-card.en_attente .status-number { color: var(--warning-color); }
.status-card.en_cours .status-number { color: var(--info-color); }
.status-card.valide .status-number { color: var(--success-color); }
.status-card.rejete .status-number { color: var(--danger-color); }
.status-card.archive .status-number { color: var(--text-secondary); }

.status-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
    text-transform: capitalize;
}

.data-section {
    background: var(--white);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    animation: fadeInUp 0.6s ease-out;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--light-bg);
}

.section-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--stats-primary), var(--stats-secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.3rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 16px;
}

.data-table thead th {
    background: linear-gradient(135deg, var(--light-bg), var(--white));
    color: var(--text-primary);
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 20px;
    border: none;
    border-bottom: 2px solid var(--border-color);
}

.data-table thead th:first-child {
    border-top-left-radius: 12px;
}

.data-table thead th:last-child {
    border-top-right-radius: 12px;
}

.data-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid var(--border-color);
}

.data-table tbody tr:hover {
    background: var(--light-bg);
    transform: scale(1.01);
}

.data-table tbody tr:last-child td:first-child {
    border-bottom-left-radius: 12px;
}

.data-table tbody tr:last-child td:last-child {
    border-bottom-right-radius: 12px;
}

.data-table tbody td {
    padding: 16px 20px;
    border: none;
    vertical-align: middle;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-admin {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
}

.role-gestionnaire {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info-color);
}

.role-consultant {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.chart-container {
    margin-top: 24px;
    padding: 20px;
    background: var(--light-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.timeline-chart {
    display: flex;
    align-items: end;
    gap: 8px;
    height: 200px;
    padding: 20px 0;
}

.timeline-bar {
    flex: 1;
    background: linear-gradient(135deg, var(--stats-primary), var(--stats-secondary));
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
}

.timeline-bar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.timeline-bar::after {
    content: attr(data-count);
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.timeline-bar:hover::after {
    opacity: 1;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: var(--light-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 2rem;
    color: var(--text-secondary);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1024px) {
    .overview-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .status-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .header-text h1 {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .stats-container {
        padding: 16px;
    }
    
    .page-header {
        padding: 24px;
    }
    
    .data-section {
        padding: 20px;
    }
    
    .data-table {
        font-size: 0.9rem;
    }
    
    .data-table thead th,
    .data-table tbody td {
        padding: 12px 16px;
    }
    
    .big-number {
        font-size: 2.5rem;
    }
    
    .status-number {
        font-size: 2rem;
    }
}
</style>

<div class="stats-container">
    <!-- En-tête de la page -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="header-text">
                <h1>Statistiques & Reporting</h1>
                <p>Tableau de bord analytique et métriques de performance</p>
            </div>
        </div>
    </div>

    <!-- Vue d'ensemble -->
    <div class="overview-grid">
        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <h3 class="card-title">Total Dossiers</h3>
            </div>
            <div class="big-number" data-count="<?= $advanced_stats['total_dossiers'] ?>"><?= $advanced_stats['total_dossiers'] ?></div>
            <div class="metric-label">Dossiers créés</div>
        </div>

        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="card-title">Dossiers Validés</h3>
            </div>
            <div class="big-number" data-count="<?= $advanced_stats['dossiers_valides'] ?>"><?= $advanced_stats['dossiers_valides'] ?></div>
            <div class="metric-label">Traitement terminé</div>
        </div>

        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="card-title">Utilisateurs Actifs</h3>
            </div>
            <div class="big-number" data-count="<?= $advanced_stats['users_actifs'] ?>"><?= $advanced_stats['users_actifs'] ?></div>
            <div class="metric-label">Créateurs de dossiers</div>
        </div>

        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="card-title">Durée Moyenne</h3>
            </div>
            <div class="big-number" data-count="<?= round($advanced_stats['duree_moyenne']) ?>"><?= round($advanced_stats['duree_moyenne']) ?></div>
            <div class="metric-label">Jours de traitement</div>
        </div>

        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3 class="card-title">Nouveaux (7j)</h3>
            </div>
            <div class="big-number" data-count="<?= $advanced_stats['nouveaux_7j'] ?>"><?= $advanced_stats['nouveaux_7j'] ?></div>
            <div class="metric-label">Cette semaine</div>
        </div>

        <div class="overview-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="card-title">En Retard</h3>
            </div>
            <div class="big-number" data-count="<?= $advanced_stats['en_retard'] ?>"><?= $advanced_stats['en_retard'] ?></div>
            <div class="metric-label">Échéances dépassées</div>
        </div>
    </div>

    <!-- Répartition par statut -->
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h2 class="section-title">Répartition par Statut</h2>
        </div>
        
        <div class="status-grid">
            <?php foreach ($stats_dossiers as $stat): ?>
                <div class="status-card <?= strtolower($stat['status']) ?>">
                    <div class="status-number" data-count="<?= $stat['count'] ?>"><?= $stat['count'] ?></div>
                    <div class="status-label"><?= ucfirst(str_replace('_', ' ', $stat['status'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Répartition par service -->
    <?php if (!empty($stats_services)): ?>
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-building"></i>
            </div>
            <h2 class="section-title">Répartition par Service</h2>
        </div>
        
        <div class="chart-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Nombre de Dossiers</th>
                        <th>Pourcentage</th>
                        <th>Moyenne par Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_services as $service): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($service['service'] ?: 'Non défini') ?>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary);">
                                <?= $service['count'] ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-secondary);">
                                <?= round(($service['count'] / $advanced_stats['total_dossiers']) * 100, 1) ?>%
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--success-color);">
                                <?= round($service['count'] / max($service['users'], 1), 1) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Répartition par type -->
    <?php if (!empty($stats_types)): ?>
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-tags"></i>
            </div>
            <h2 class="section-title">Répartition par Type</h2>
        </div>
        
        <div class="chart-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type de Dossier</th>
                        <th>Nombre</th>
                        <th>Pourcentage</th>
                        <th>Statut Dominant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_types as $type): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($type['type'] ?: 'Non défini') ?>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary);">
                                <?= $type['count'] ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-secondary);">
                                <?= round(($type['count'] / $advanced_stats['total_dossiers']) * 100, 1) ?>%
                            </span>
                        </td>
                        <td>
                            <span class="status-label" style="font-weight: 600;">
                                <?= $type['dominant_status'] ?: 'Mixte' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Utilisateurs les plus actifs -->
    <?php if (!empty($stats_users)): ?>
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-star"></i>
            </div>
            <h2 class="section-title">Utilisateurs les Plus Actifs</h2>
        </div>
        
        <div class="chart-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Service</th>
                        <th>Rôle</th>
                        <th>Dossiers Créés</th>
                        <th>Dernière Activité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--stats-primary), var(--stats-secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.9rem;">
                                    <?= strtoupper(substr($user['nom'], 0, 1) . substr($user['prenom'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--text-secondary);">
                            <?= htmlspecialchars($user['service'] ?: 'Non défini') ?>
                        </td>
                        <td>
                            <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                <i class="fas fa-<?= $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'gestionnaire' ? 'user-tie' : 'eye') ?>"></i>
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary); font-size: 1.1rem;">
                                <?= $user['count'] ?>
                            </span>
                        </td>
                        <td style="color: var(--text-secondary);">
                            <?= date('d/m/Y H:i', strtotime($user['derniere_activite'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Évolution temporelle -->
    <?php if (!empty($stats_evolution)): ?>
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h2 class="section-title">Évolution Temporelle (12 derniers mois)</h2>
        </div>
        
        <div class="chart-container">
            <div class="timeline-chart">
                <?php foreach ($stats_evolution as $month): ?>
                    <div class="timeline-bar" 
                         style="height: <?= ($month['count'] / max(array_column($stats_evolution, 'count'))) * 160 ?>px;"
                         data-count="<?= $month['count'] ?>"
                         title="<?= $month['mois'] ?> : <?= $month['count'] ?> dossiers">
                    </div>
                <?php endforeach; ?>
            </div>
            
            <table class="data-table" style="margin-top: 24px;">
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Dossiers Créés</th>
                        <th>Dossiers Validés</th>
                        <th>Taux de Validation</th>
                        <th>Évolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prev_count = null;
                    foreach ($stats_evolution as $month): 
                        $evolution = $prev_count ? (($month['count'] - $prev_count) / $prev_count) * 100 : 0;
                        $prev_count = $month['count'];
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-primary);">
                            <?= $month['mois'] ?>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary);">
                                <?= $month['count'] ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--success-color);">
                                <?= $month['valides'] ?: 0 ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-secondary);">
                                <?= $month['count'] > 0 ? round(($month['valides'] / $month['count']) * 100, 1) : 0 ?>%
                            </span>
                        </td>
                        <td>
                            <?php if ($evolution > 0): ?>
                                <span style="color: var(--success-color); font-weight: 600;">
                                    <i class="fas fa-arrow-up"></i> +<?= round($evolution, 1) ?>%
                                </span>
                            <?php elseif ($evolution < 0): ?>
                                <span style="color: var(--danger-color); font-weight: 600;">
                                    <i class="fas fa-arrow-down"></i> <?= round($evolution, 1) ?>%
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-weight: 600;">
                                    <i class="fas fa-minus"></i> Stable
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Utilisateurs par rôle -->
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <h2 class="section-title">Utilisateurs par Rôle</h2>
        </div>
        
        <div class="chart-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Nombre d'Utilisateurs</th>
                        <th>Pourcentage</th>
                        <th>Permissions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['users'] as $row): ?>
                    <tr>
                        <td>
                            <span class="role-badge role-<?= strtolower($row['role']) ?>">
                                <i class="fas fa-<?= $row['role'] === 'admin' ? 'crown' : ($row['role'] === 'gestionnaire' ? 'user-tie' : 'eye') ?>"></i>
                                <?= getRoleName($row['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary); font-size: 1.1rem;">
                                <?= $row['count'] ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-secondary);">
                                <?= round(($row['count'] / array_sum(array_column($stats['users'], 'count'))) * 100, 1) ?>%
                            </span>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.9rem;">
                            <?php 
                            switch($row['role']) {
                                case 'admin': echo 'Toutes les permissions'; break;
                                case 'gestionnaire': echo 'Gestion des dossiers'; break;
                                case 'consultant': echo 'Consultation uniquement'; break;
                                default: echo 'Permissions limitées';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Activité récente -->
    <div class="data-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h2 class="section-title">Créations de Dossiers (30 derniers jours)</h2>
        </div>
        
        <div class="chart-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Nombre de Créations</th>
                        <th>Jour de la Semaine</th>
                        <th>Évolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prev_count = null;
                    foreach ($stats['timeline'] as $row): 
                        $evolution = $prev_count ? (($row['count'] - $prev_count) / max($prev_count, 1)) * 100 : 0;
                        $prev_count = $row['count'];
                        $day_name = date('l', strtotime($row['date']));
                        $day_names = [
                            'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
                            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi',
                            'Sunday' => 'Dimanche'
                        ];
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-primary);">
                            <?= date('d/m/Y', strtotime($row['date'])) ?>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--stats-primary); font-size: 1.1rem;">
                                <?= $row['count'] ?>
                            </span>
                        </td>
                        <td style="color: var(--text-secondary);">
                            <?= $day_names[$day_name] ?>
                        </td>
                        <td>
                            <?php if ($evolution > 0): ?>
                                <span style="color: var(--success-color); font-weight: 600;">
                                    <i class="fas fa-arrow-up"></i> +<?= round($evolution, 1) ?>%
                                </span>
                            <?php elseif ($evolution < 0): ?>
                                <span style="color: var(--danger-color); font-weight: 600;">
                                    <i class="fas fa-arrow-down"></i> <?= round($evolution, 1) ?>%
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-weight: 600;">
                                    <i class="fas fa-minus"></i> Stable
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- État si pas de données -->
    <?php if (empty($stats_dossiers) && empty($stats_services) && empty($stats_types) && empty($stats_users)): ?>
    <div class="data-section">
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <h3 style="color: var(--text-secondary); margin: 0 0 8px 0;">Aucune donnée disponible</h3>
            <p style="color: var(--text-secondary); margin: 0;">Aucun dossier n'a été créé pour le moment.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'entrée progressive pour les cartes
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Initialiser l'observation des éléments animés
    document.querySelectorAll('.overview-card, .status-card, .data-section').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });

    // Animation des nombres avec compteur
    function animateCounter(element, target, duration = 2000) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 16);
    }

    // Animer les gros nombres
    setTimeout(() => {
        document.querySelectorAll('.big-number, .status-number').forEach(el => {
            const target = parseInt(el.dataset.count || el.textContent);
            if (!isNaN(target)) {
                animateCounter(el, target, 1500);
            }
        });
    }, 500);

    // Effet de ripple pour les cartes
    function createRipple(event) {
        const card = event.currentTarget;
        const ripple = document.createElement('span');
        const rect = card.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        // Styles pour l'effet ripple
        ripple.style.position = 'absolute';
        ripple.style.borderRadius = '50%';
        ripple.style.background = 'rgba(102, 126, 234, 0.2)';
        ripple.style.transform = 'scale(0)';
        ripple.style.animation = 'ripple-animation 0.6s linear';
        ripple.style.pointerEvents = 'none';
        
        card.style.position = 'relative';
        card.style.overflow = 'hidden';
        card.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Ajouter l'effet ripple aux cartes interactives
    document.querySelectorAll('.overview-card, .status-card').forEach(card => {
        card.addEventListener('click', createRipple);
        card.style.cursor = 'pointer';
    });

    // Animation des barres de timeline
    setTimeout(() => {
        document.querySelectorAll('.timeline-bar').forEach((bar, index) => {
            setTimeout(() => {
                bar.style.transform = 'scaleY(1)';
                bar.style.opacity = '1';
            }, index * 100);
        });
    }, 1000);

    // Initialiser les barres avec animation
    document.querySelectorAll('.timeline-bar').forEach(bar => {
        bar.style.transform = 'scaleY(0)';
        bar.style.transformOrigin = 'bottom';
        bar.style.opacity = '0';
        bar.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
    });

    // Hover effects pour les lignes de tableau
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--light-bg)';
            this.style.transform = 'scale(1.01)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = 'scale(1)';
        });
    });

    // Système de notifications pour les interactions
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'info' ? 'var(--info-color)' : 'var(--success-color)'};
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Interactions avec les cartes de statut
    document.querySelectorAll('.status-card').forEach(card => {
        card.addEventListener('click', function() {
            const status = this.querySelector('.status-label').textContent;
            const count = this.querySelector('.status-number').textContent;
            showNotification(`${count} dossiers avec le statut "${status}"`, 'info');
        });
    });

    // Smooth scroll pour la navigation
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Lazy loading pour les images et contenus lourds
    const lazyElements = document.querySelectorAll('[data-lazy]');
    const lazyObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                element.classList.add('loaded');
                lazyObserver.unobserve(element);
            }
        });
    });

    lazyElements.forEach(el => lazyObserver.observe(el));

    // Gestion du thème sombre (préparation)
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    function handleThemeChange(e) {
        document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
    }
    
    prefersDark.addEventListener('change', handleThemeChange);
    handleThemeChange(prefersDark);

    // Performance optimization: Debounce des événements
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Optimisation du redimensionnement
    const handleResize = debounce(() => {
        // Recalculer les layouts si nécessaire
        document.querySelectorAll('.timeline-chart').forEach(chart => {
            // Re-trigger animations si visible
            const rect = chart.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                chart.style.animation = 'none';
                chart.offsetHeight; // Trigger reflow
                chart.style.animation = null;
            }
        });
    }, 250);

    window.addEventListener('resize', handleResize);

    console.log('📊 Statistiques dashboard initialized with advanced interactions');
});

// CSS pour l'animation ripple
const rippleStyles = document.createElement('style');
rippleStyles.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    [data-theme="dark"] {
        --stats-primary: #818cf8;
        --stats-secondary: #a855f7;
        --light-bg: #1e293b;
        --white: #0f172a;
        --text-primary: #f1f5f9;
        --text-secondary: #94a3b8;
        --border-color: #334155;
    }
    
    .loaded {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;
document.head.appendChild(rippleStyles);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>