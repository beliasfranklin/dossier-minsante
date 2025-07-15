<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/preferences.php';

requireRole(ROLE_CONSULTANT);

// Calculer les statistiques des échéances
$today = date('Y-m-d');

// Dossiers par état d'échéance
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN deadline IS NULL THEN 'sans_echeance'
            WHEN deadline < ? THEN 'depassee'
            WHEN deadline = ? THEN 'aujourdhui'
            WHEN deadline <= DATE_ADD(?, INTERVAL 1 DAY) THEN 'demain'
            WHEN deadline <= DATE_ADD(?, INTERVAL 3 DAY) THEN 'dans_3_jours'
            WHEN deadline <= DATE_ADD(?, INTERVAL 7 DAY) THEN 'dans_7_jours'
            ELSE 'ulterieur'
        END as etat_echeance,
        COUNT(*) as nombre
    FROM dossiers 
    WHERE status NOT IN ('archive')
    GROUP BY etat_echeance
");
$stmt->execute([$today, $today, $today, $today, $today]);
$stats_echeances = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Dossiers critiques (dépassés + aujourd'hui + demain)
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email,
           DATEDIFF(?, d.deadline) as jours_retard
    FROM dossiers d
    LEFT JOIN users u ON d.responsable_id = u.id
    WHERE d.status NOT IN ('archive') 
    AND d.deadline IS NOT NULL
    AND d.deadline <= DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY d.deadline ASC, d.created_at ASC
");
$stmt->execute([$today, $today]);
$dossiers_critiques = $stmt->fetchAll();

// Dossiers à surveiller (3-7 jours)
$stmt = $pdo->prepare("
    SELECT d.*, u.name, u.email,
           DATEDIFF(d.deadline, ?) as jours_restants
    FROM dossiers d
    LEFT JOIN users u ON d.responsable_id = u.id
    WHERE d.status NOT IN ('archive') 
    AND d.deadline IS NOT NULL
    AND d.deadline > DATE_ADD(?, INTERVAL 1 DAY)
    AND d.deadline <= DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY d.deadline ASC
");
$stmt->execute([$today, $today, $today]);
$dossiers_a_surveiller = $stmt->fetchAll();

// Statistiques par service et type
$stmt = $pdo->prepare("
    SELECT service, COUNT(*) as total,
           COUNT(CASE WHEN deadline IS NOT NULL AND deadline < ? THEN 1 END) as en_retard,
           COUNT(CASE WHEN deadline IS NOT NULL AND deadline <= DATE_ADD(?, INTERVAL 7 DAY) THEN 1 END) as urgent
    FROM dossiers 
    WHERE status NOT IN ('archive')
    GROUP BY service
");
$stmt->execute([$today, $today]);
$stats_services = $stmt->fetchAll();

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);
$themeVars = $preferencesManager->getThemeVariables();

$pageTitle = "Dashboard des Échéances";
include '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables couleurs modernes pour échéances */
    --danger-gradient: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
    --warning-gradient: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);
    --info-gradient: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
    --success-gradient: linear-gradient(135deg, #2ed573 0%, #1e90ff 100%);
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f1f2f6 0%, #ddd 100%);
    
    /* Shadows et effets */
    --shadow-soft: 0 10px 40px rgba(0,0,0,0.1);
    --shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
    --shadow-danger: 0 8px 30px rgba(255, 71, 87, 0.3);
    --shadow-warning: 0 8px 30px rgba(255, 165, 2, 0.3);
    --shadow-info: 0 8px 30px rgba(55, 66, 250, 0.3);
    --shadow-success: 0 8px 30px rgba(46, 213, 115, 0.3);
    
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.echeances-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.echeances-header {
    background: var(--primary-gradient);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.echeances-header::before {
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

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.header-main {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
}

.header-text h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.header-text p {
    font-size: 1.2rem;
    opacity: 0.9;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
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
    background: rgba(255,255,255,0.2);
    color: white;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-primary:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255,255,255,0.2);
}

.btn-secondary {
    background: rgba(0,0,0,0.1);
    color: white;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-secondary:hover {
    background: rgba(0,0,0,0.2);
    transform: translateY(-3px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
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
}

.stat-card.danger::before { background: var(--danger-gradient); }
.stat-card.warning::before { background: var(--warning-gradient); }
.stat-card.info::before { background: var(--info-gradient); }
.stat-card.primary::before { background: var(--primary-gradient); }
.stat-card.secondary::before { background: var(--secondary-gradient); }
.stat-card.success::before { background: var(--success-gradient); }

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
}

.stat-card.danger:hover { box-shadow: var(--shadow-danger); }
.stat-card.warning:hover { box-shadow: var(--shadow-warning); }
.stat-card.info:hover { box-shadow: var(--shadow-info); }
.stat-card.success:hover { box-shadow: var(--shadow-success); }

.stat-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
}

.stat-card.danger .stat-icon { background: var(--danger-gradient); }
.stat-card.warning .stat-icon { background: var(--warning-gradient); }
.stat-card.info .stat-icon { background: var(--info-gradient); }
.stat-card.primary .stat-icon { background: var(--primary-gradient); }
.stat-card.secondary .stat-icon { background: var(--secondary-gradient); }
.stat-card.success .stat-icon { background: var(--success-gradient); }

.stat-details {
    flex: 1;
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    line-height: 1;
}

.stat-card.danger .stat-number { color: #ff4757; }
.stat-card.warning .stat-number { color: #ffa502; }
.stat-card.info .stat-number { color: #3742fa; }
.stat-card.primary .stat-number { color: #667eea; }
.stat-card.secondary .stat-number { color: #57606f; }
.stat-card.success .stat-number { color: #2ed573; }

.stat-label {
    color: #6c757d;
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.main-card, .info-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.8);
}

.card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.critical-header {
    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
    border-bottom-color: #feb2b2;
}

.card-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #2d3748;
}

.critical-header h3 {
    color: #c53030;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.badge-danger {
    background: var(--danger-gradient);
    color: white;
}

.card-body {
    padding: 0;
}

.critical-list {
    max-height: 600px;
    overflow-y: auto;
}

.critical-item {
    display: flex;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
    position: relative;
}

.critical-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(8px);
}

.critical-item.overdue {
    border-left: 4px solid #ff4757;
    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 30%, transparent);
}

.critical-item.today {
    border-left: 4px solid #ffa502;
    background: linear-gradient(135deg, #fffbf0 0%, #feebc8 30%, transparent);
}

.critical-item.tomorrow {
    border-left: 4px solid #3742fa;
    background: linear-gradient(135deg, #f0f4ff 0%, #c3dafe 30%, transparent);
}

.critical-info {
    flex: 1;
    margin-right: 1rem;
}

.dossier-ref {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.dossier-title {
    color: #4a5568;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.dossier-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.responsible {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.9rem;
}

.service-badge {
    background: #e2e8f0;
    color: #4a5568;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
}

.critical-deadline {
    text-align: center;
    min-width: 120px;
}

.deadline-date {
    font-weight: 700;
    font-size: 1rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.deadline-status {
    margin-bottom: 1rem;
}

.status-overdue {
    color: #c53030;
    font-weight: 600;
    font-size: 0.85rem;
}

.status-today {
    color: #d69e2e;
    font-weight: 600;
    font-size: 0.85rem;
}

.status-tomorrow {
    color: #3182ce;
    font-weight: 600;
    font-size: 0.85rem;
}

.action-btn {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    border-radius: 8px;
}

.service-stats {
    padding: 1rem;
}

.service-item {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
}

.service-item:hover {
    background: #f8f9fa;
    transform: translateX(4px);
}

.service-name {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.service-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.metric {
    text-align: center;
    padding: 0.75rem;
    border-radius: 8px;
}

.metric.total {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 100%);
}

.metric.urgent {
    background: linear-gradient(135deg, #feebc8 0%, #fbd38d 100%);
}

.metric.overdue {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.metric-label {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.watch-list {
    max-height: 500px;
    overflow-y: auto;
}

.watch-item {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
}

.watch-item:hover {
    background: #f8f9fa;
    transform: translateX(4px);
}

.watch-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.watch-ref {
    font-weight: 700;
    color: #2d3748;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.watch-title {
    color: #4a5568;
    font-size: 0.95rem;
}

.watch-days {
    background: var(--info-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.watch-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.deadline-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
    color: #2ed573;
}

.empty-state h3 {
    color: #2d3748;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.empty-state p {
    font-size: 1.1rem;
}

.card-subtitle {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    font-style: italic;
}

/* Scrollbar personnalisée */
.critical-list::-webkit-scrollbar,
.watch-list::-webkit-scrollbar,
.service-stats::-webkit-scrollbar {
    width: 8px;
}

.critical-list::-webkit-scrollbar-track,
.watch-list::-webkit-scrollbar-track,
.service-stats::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.critical-list::-webkit-scrollbar-thumb,
.watch-list::-webkit-scrollbar-thumb,
.service-stats::-webkit-scrollbar-thumb {
    background: var(--primary-gradient);
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
.stat-card:nth-child(5) { animation-delay: 0.5s; }
.stat-card:nth-child(6) { animation-delay: 0.6s; }

/* Responsive Design */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .echeances-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    .echeances-header {
        padding: 2rem 1.5rem;
    }
    
    .header-main {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .header-text h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
    }
    
    .critical-item {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .dossier-meta {
        justify-content: center;
    }
    
    .watch-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .watch-meta {
        justify-content: center;
    }
    
    .service-metrics {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Print styles */
@media print {
    .header-actions,
    .action-btn {
        display: none;
    }
    
    .echeances-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
    
    .stat-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<div class="echeances-container">
    <!-- En-tête moderne -->
    <div class="echeances-header">
        <div class="header-content">
            <div class="header-main">
                <div class="header-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="header-text">
                    <h1>Dashboard des Échéances</h1>
                    <p>Suivi et gestion des délais critiques en temps réel</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="config.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Configuration
                </a>
                <a href="../dossiers/" class="btn btn-primary">
                    <i class="fas fa-folder"></i> Tous les Dossiers
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiques globales modernes -->
    <div class="stats-grid">
        <div class="stat-card danger">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['depassee'] ?? 0 ?></h3>
                    <p class="stat-label">Dépassées</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['aujourdhui'] ?? 0 ?></h3>
                    <p class="stat-label">Aujourd'hui</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['demain'] ?? 0 ?></h3>
                    <p class="stat-label">Demain</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['dans_3_jours'] ?? 0 ?></h3>
                    <p class="stat-label">3 jours</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card secondary">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['dans_7_jours'] ?? 0 ?></h3>
                    <p class="stat-label">7 jours</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-number"><?= $stats_echeances['sans_echeance'] ?? 0 ?></h3>
                    <p class="stat-label">Sans échéance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Dossiers critiques -->
        <div class="grid-main">
            <div class="main-card">
                <div class="card-header critical-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Dossiers Critiques</h3>
                    <span class="badge badge-danger"><?= count($dossiers_critiques) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($dossiers_critiques)): ?>
                        <div class="critical-list">
                            <?php foreach ($dossiers_critiques as $dossier): ?>
                                <div class="critical-item <?= $dossier['jours_retard'] > 0 ? 'overdue' : ($dossier['jours_retard'] == 0 ? 'today' : 'tomorrow') ?>">
                                    <div class="critical-info">
                                        <div class="dossier-ref">
                                            <strong><?= htmlspecialchars($dossier['reference'] ?? $dossier['numero_dossier'] ?? 'N/A') ?></strong>
                                        </div>
                                        <div class="dossier-title">
                                            <?= htmlspecialchars(substr($dossier['titre'] ?? 'Sans titre', 0, 40)) ?>...
                                        </div>
                                        <div class="dossier-meta">
                                            <span class="responsible">
                                                <i class="fas fa-user"></i> 
                                                <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                            </span>
                                            <span class="service-badge">
                                                <?= htmlspecialchars($dossier['service'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="critical-deadline">
                                        <div class="deadline-date">
                                            <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                        </div>
                                        <div class="deadline-status">
                                            <?php if ($dossier['jours_retard'] > 0): ?>
                                                <span class="status-overdue">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    +<?= $dossier['jours_retard'] ?>j de retard
                                                </span>
                                            <?php elseif ($dossier['jours_retard'] == 0): ?>
                                                <span class="status-today">
                                                    <i class="fas fa-clock"></i> Aujourd'hui
                                                </span>
                                            <?php else: ?>
                                                <span class="status-tomorrow">
                                                    <i class="fas fa-arrow-right"></i> Demain
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary action-btn">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>Aucun dossier critique</h3>
                            <p>Toutes les échéances sont sous contrôle !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistiques par service -->
        <div class="grid-sidebar">
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Par Service</h3>
                </div>
                <div class="card-body">
                    <div class="service-stats">
                        <?php foreach ($stats_services as $service): ?>
                            <div class="service-item">
                                <div class="service-name">
                                    <?= htmlspecialchars($service['service']) ?>
                                </div>
                                <div class="service-metrics">
                                    <div class="metric total">
                                        <div class="metric-value"><?= $service['total'] ?></div>
                                        <div class="metric-label">Total</div>
                                    </div>
                                    <div class="metric urgent">
                                        <div class="metric-value"><?= $service['urgent'] ?></div>
                                        <div class="metric-label">Urgent</div>
                                    </div>
                                    <div class="metric overdue">
                                        <div class="metric-value"><?= $service['en_retard'] ?></div>
                                        <div class="metric-label">Retard</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dossiers à surveiller -->
    <div class="main-card">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> Dossiers à Surveiller</h3>
            <span class="card-subtitle">Échéances dans les 3 à 7 prochains jours</span>
        </div>
        <div class="card-body">
            <?php if (!empty($dossiers_a_surveiller)): ?>
                <div class="watch-list">
                    <?php foreach ($dossiers_a_surveiller as $dossier): ?>
                        <div class="watch-item">
                            <div class="watch-header">
                                <div>
                                    <div class="watch-ref"><?= htmlspecialchars($dossier['reference'] ?? $dossier['numero_dossier'] ?? 'N/A') ?></div>
                                    <div class="watch-title"><?= htmlspecialchars($dossier['titre'] ?? 'Sans titre') ?></div>
                                </div>
                                <div class="watch-days">
                                    Dans <?= $dossier['jours_restants'] ?> jour<?= $dossier['jours_restants'] > 1 ? 's' : '' ?>
                                </div>
                            </div>
                            <div class="watch-meta">
                                <span class="responsible">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                </span>
                                <span class="service-badge">
                                    <?= htmlspecialchars($dossier['service'] ?? 'N/A') ?>
                                </span>
                                <span class="deadline-info">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                </span>
                                <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary action-btn">
                                    <i class="fas fa-eye"></i> Consulter
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Aucun dossier à surveiller</h3>
                    <p>Pas d'échéances dans les 7 prochains jours</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Animation d'apparition au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8) translateY(30px)';
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'scale(1) translateY(0)';
            }, 100);
        }, index * 150);
    });
    
    // Animation des éléments critiques
    const criticalItems = document.querySelectorAll('.critical-item');
    criticalItems.forEach((item, index) => {
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
    
    // Mise à jour automatique toutes les 2 minutes
    setTimeout(() => {
        setInterval(() => {
            // Animation de "pulse" pour indiquer la mise à jour
            document.querySelector('.echeances-header').style.opacity = '0.7';
            setTimeout(() => {
                location.reload();
            }, 1000);
        }, 120000); // 2 minutes
    }, 5000);
    
    // Notification pour dossiers critiques
    const critiquesCount = <?= count($dossiers_critiques) ?>;
    if (critiquesCount > 0) {
        console.log(`⚠️ ${critiquesCount} dossier(s) critique(s) détecté(s)`);
        
        // Notification sonore discrète (optionnel)
        if (critiquesCount > 5) {
            setTimeout(() => {
                // Animation d'alerte pour les cas très critiques
                document.querySelector('.critical-header').style.animation = 'pulse 2s infinite';
            }, 2000);
        }
    }
    
    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // R pour refresh
        if (e.key === 'r' || e.key === 'R') {
            if (e.ctrlKey || e.metaKey) return; // Éviter le refresh normal
            location.reload();
            e.preventDefault();
        }
        
        // C pour aller aux critiques
        if (e.key === 'c' || e.key === 'C') {
            document.querySelector('.critical-header').scrollIntoView({ behavior: 'smooth' });
        }
        
        // S pour aller aux stats
        if (e.key === 's' || e.key === 'S') {
            document.querySelector('.stats-grid').scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Animation personnalisée pour le pulse
const pulseStyle = document.createElement('style');
pulseStyle.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(pulseStyle);

console.log('📊 Dashboard des Échéances chargé avec succès');
console.log('📈 Statistiques:', <?= json_encode($stats_echeances) ?>);
</script>

<?php include '../../includes/footer.php'; ?>
    WHERE d.status NOT IN ('archive') 
    AND d.deadline IS NOT NULL
    AND d.deadline > DATE_ADD(?, INTERVAL 1 DAY)
    AND d.deadline <= DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY d.deadline ASC
");
$stmt->execute([$today, $today, $today]);
$dossiers_a_surveiller = $stmt->fetchAll();

// Statistiques par service et type
$stmt = $pdo->prepare("
    SELECT service, COUNT(*) as total,
           COUNT(CASE WHEN deadline IS NOT NULL AND deadline < ? THEN 1 END) as en_retard,
           COUNT(CASE WHEN deadline IS NOT NULL AND deadline <= DATE_ADD(?, INTERVAL 7 DAY) THEN 1 END) as urgent
    FROM dossiers 
    WHERE status NOT IN ('archive')
    GROUP BY service
");
$stmt->execute([$today, $today]);
$stats_services = $stmt->fetchAll();

$pageTitle = "Dashboard des Échéances";
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="content-header">
        <div class="header-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="header-text">
            <h1>Dashboard des Échéances</h1>
            <p>Suivi et gestion des délais critiques</p>
        </div>
        <div class="header-actions">
            <a href="config.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-cog"></i> Configuration
            </a>
            <a href="../dossiers/" class="btn btn-primary btn-sm">
                <i class="fas fa-folder"></i> Tous les Dossiers
            </a>
        </div>
    </div>

    <!-- Statistiques globales -->
    <div class="stats-grid animate-fade-in">
        <div class="stat-card danger">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['depassee'] ?? 0 ?></h3>
                <p class="stat-label">Dépassées</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['aujourdhui'] ?? 0 ?></h3>
                <p class="stat-label">Aujourd'hui</p>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['demain'] ?? 0 ?></h3>
                <p class="stat-label">Demain</p>
            </div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['dans_3_jours'] ?? 0 ?></h3>
                <p class="stat-label">3 jours</p>
            </div>
        </div>
        
        <div class="stat-card secondary">
            <div class="stat-icon">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['dans_7_jours'] ?? 0 ?></h3>
                <p class="stat-label">7 jours</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-infinity"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?= $stats_echeances['sans_echeance'] ?? 0 ?></h3>
                <p class="stat-label">Sans échéance</p>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Dossiers critiques -->
        <div class="grid-main">
            <div class="main-card animate-fade-in">
                <div class="card-header critical-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Dossiers Critiques</h3>
                    <span class="badge badge-danger"><?= count($dossiers_critiques) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($dossiers_critiques)): ?>
                        <div class="critical-list">
                            <?php foreach ($dossiers_critiques as $dossier): ?>
                                <div class="critical-item <?= $dossier['jours_retard'] > 0 ? 'overdue' : ($dossier['jours_retard'] == 0 ? 'today' : 'tomorrow') ?>">
                                    <div class="critical-info">
                                        <div class="dossier-ref">
                                            <strong><?= htmlspecialchars($dossier['reference']) ?></strong>
                                        </div>
                                        <div class="dossier-title">
                                            <?= htmlspecialchars(substr($dossier['titre'], 0, 40)) ?>...
                                        </div>
                                        <div class="dossier-meta">
                                            <span class="responsible">
                                                <i class="fas fa-user"></i> 
                                                <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                            </span>
                                            <span class="service-badge">
                                                <?= htmlspecialchars($dossier['service']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="critical-deadline">
                                        <div class="deadline-date">
                                            <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                        </div>
                                        <div class="deadline-status">
                                            <?php if ($dossier['jours_retard'] > 0): ?>
                                                <span class="status-overdue">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    +<?= $dossier['jours_retard'] ?>j de retard
                                                </span>
                                            <?php elseif ($dossier['jours_retard'] == 0): ?>
                                                <span class="status-today">
                                                    <i class="fas fa-clock"></i> Aujourd'hui
                                                </span>
                                            <?php else: ?>
                                                <span class="status-tomorrow">
                                                    <i class="fas fa-arrow-right"></i> Demain
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary btn-sm action-btn">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>Aucun dossier critique</h3>
                            <p>Toutes les échéances sont sous contrôle !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistiques par service -->
        <div class="grid-sidebar">
            <div class="info-card animate-fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Par Service</h3>
                </div>
                <div class="card-body">
                    <div class="service-stats">
                        <?php foreach ($stats_services as $service): ?>
                            <div class="service-item">
                                <div class="service-name">
                                    <?= htmlspecialchars($service['service']) ?>
                                </div>
                                <div class="service-metrics">
                                    <div class="metric total">
                                        <div class="metric-value"><?= $service['total'] ?></div>
                                        <div class="metric-label">Total</div>
                                    </div>
                                    <div class="metric urgent">
                                        <div class="metric-value"><?= $service['urgent'] ?></div>
                                        <div class="metric-label">Urgent</div>
                                    </div>
                                    <div class="metric overdue">
                                        <div class="metric-value"><?= $service['en_retard'] ?></div>
                                        <div class="metric-label">Retard</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dossiers à surveiller -->
    <div class="main-card animate-fade-in">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> Dossiers à Surveiller</h3>
            <span class="card-subtitle">Échéances dans les 3 à 7 prochains jours</span>
        </div>
        <div class="card-body">
            <?php if (!empty($dossiers_a_surveiller)): ?>
                <div class="watch-list">
                    <?php foreach ($dossiers_a_surveiller as $dossier): ?>
                        <div class="watch-item">
                            <div class="watch-header">
                                <div>
                                    <div class="watch-ref"><?= htmlspecialchars($dossier['reference']) ?></div>
                                    <div class="watch-title"><?= htmlspecialchars($dossier['titre']) ?></div>
                                </div>
                                <div class="watch-days">
                                    Dans <?= $dossier['jours_restants'] ?> jour<?= $dossier['jours_restants'] > 1 ? 's' : '' ?>
                                </div>
                            </div>
                            <div class="watch-meta">
                                <span class="responsible">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                </span>
                                <span class="service-badge">
                                    <?= htmlspecialchars($dossier['service']) ?>
                                </span>
                                <span class="deadline-info">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                </span>
                                <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary btn-sm action-btn">
                                    <i class="fas fa-eye"></i> Consulter
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Aucun dossier à surveiller</h3>
                    <p>Pas d'échéances dans les 7 prochains jours</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dossier</th>
                                        <th>Responsable</th>
                                        <th>Échéance</th>
                                        <th>Service</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers_a_surveiller as $dossier): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($dossier['reference']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(substr($dossier['titre'], 0, 25)) ?>...
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $dossier['jours_restants'] <= 3 ? 'warning' : 'info' ?>">
                                                    <?= $dossier['jours_restants'] ?>j
                                                </span>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($dossier['service']) ?></small>
                                            </td>

