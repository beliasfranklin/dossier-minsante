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
    
    // Animation des statistiques
    setTimeout(animateStats, 500);
});
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
                                            <td>
                                                <small><?= htmlspecialchars($dossier['type_dossier']) ?></small>
                                            </td>
                                            <td>
                                                <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-3"></i>
                            <p>Aucun dossier à surveiller dans les prochains jours.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-outline-dark {
    color: #495057;
    border: 1px solid #495057;
    background: transparent;
}

.table-responsive {
    max-height: 400px;
    overflow-y: auto;
}

.card-header h5 {
    margin: 0;
}

.table th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
}
</style>

<?php include '../../includes/footer.php'; ?>

$page_title = "Dashboard des Échéances";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Alertes critiques -->
    <?php if (count($dossiers_critiques) > 0): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <h5><i class="fas fa-exclamation-triangle"></i> Attention !</h5>
            <p><strong><?= count($dossiers_critiques) ?> dossier(s)</strong> nécessitent une action immédiate (échéance dépassée, aujourd'hui ou demain).</p>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Cartes de statistiques -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats_echeances['depassee'] ?? 0 ?></h4>
                            <small>Dépassées</small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats_echeances['aujourdhui'] ?? 0 ?></h4>
                            <small>Aujourd'hui</small>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats_echeances['demain'] ?? 0 ?></h4>
                            <small>Demain</small>
                        </div>
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= ($stats_echeances['dans_3_jours'] ?? 0) + ($stats_echeances['dans_7_jours'] ?? 0) ?></h4>
                            <small>Cette semaine</small>
                        </div>
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats_echeances['plus_tard'] ?? 0 ?></h4>
                            <small>Plus tard</small>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats_echeances['sans_echeance'] ?? 0 ?></h4>
                            <small>Sans échéance</small>
                        </div>
                        <i class="fas fa-question fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Dossiers critiques -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-fire"></i> Dossiers Critiques 
                        <span class="badge badge-light"><?= count($dossiers_critiques) ?></span>
                    </h5>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($dossiers_critiques)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p>Aucun dossier critique !</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Dossier</th>
                                        <th>Responsable</th>
                                        <th>Échéance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers_critiques as $dossier): ?>
                                        <tr class="<?= $dossier['jours_retard'] > 0 ? 'table-danger' : ($dossier['jours_retard'] == 0 ? 'table-warning' : '') ?>">
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($dossier['reference']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(substr($dossier['titre'], 0, 30)) ?>...
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars($dossier['name'] ?? 'Non assigné') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                                    <?php if ($dossier['jours_retard'] > 0): ?>
                                                        <br><span class="text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> 
                                                            +<?= $dossier['jours_retard'] ?>j
                                                        </span>
                                                    <?php elseif ($dossier['jours_retard'] == 0): ?>
                                                        <br><span class="text-warning">
                                                            <i class="fas fa-clock"></i> Aujourd'hui
                                                        </span>
                                                    <?php else: ?>
                                                        <br><span class="text-info">
                                                            <i class="fas fa-calendar"></i> Demain
                                                        </span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../../modules/dossiers/view.php?id=<?= $dossier['id'] ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (canEditDossier($_SESSION['user_id'], $_SESSION['role'], $dossier['responsable_id'])): ?>
                                                        <a href="../../modules/dossiers/edit.php?id=<?= $dossier['id'] ?>" 
                                                           class="btn btn-outline-warning btn-sm" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Dossiers à surveiller -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-eye"></i> À Surveiller (3-7 jours)
                        <span class="badge badge-dark"><?= count($dossiers_a_surveiller) ?></span>
                    </h5>
                </div>
                
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($dossiers_a_surveiller)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-3"></i>
                            <p>Aucun dossier à surveiller dans les 7 jours</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Dossier</th>
                                        <th>Responsable</th>
                                        <th>Dans</th>
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
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques par service -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Répartition par Service et Type</h5>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="statsTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Service</th>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>En retard</th>
                                    <th>Urgents (7j)</th>
                                    <th>% En retard</th>
                                    <th>Graphique</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_services as $stat): ?>
                                    <?php
                                    $pct_retard = $stat['total'] > 0 ? round(($stat['en_retard'] / $stat['total']) * 100, 1) : 0;
                                    $pct_urgents = $stat['total'] > 0 ? round(($stat['urgents'] / $stat['total']) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?= htmlspecialchars($stat['service']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($stat['type']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= $stat['total'] ?></strong></td>
                                        <td>
                                            <span class="badge badge-<?= $stat['en_retard'] > 0 ? 'danger' : 'success' ?>">
                                                <?= $stat['en_retard'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $stat['urgents'] > 0 ? 'warning' : 'success' ?>">
                                                <?= $stat['urgents'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-<?= $pct_retard > 20 ? 'danger' : ($pct_retard > 10 ? 'warning' : 'success') ?>">
                                                <?= $pct_retard ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-danger" style="width: <?= $pct_retard ?>%"></div>
                                                <div class="progress-bar bg-warning" style="width: <?= $pct_urgents - $pct_retard ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable pour les statistiques
    $('#statsTable').DataTable({
        language: {
            url: '../../assets/js/datatables-fr.json'
        },
        order: [[5, 'desc']], // Trier par % en retard
        columnDefs: [
            { targets: [6], orderable: false } // Graphique non triable
        ]
    });
    
    // Auto-refresh toutes les 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
    
    // Notification son pour dossiers critiques
    <?php if (count($dossiers_critiques) > 0): ?>
        // Jouer un son d'alerte (optionnel)
        console.log('⚠️  <?= count($dossiers_critiques) ?> dossier(s) critique(s) détecté(s)');
    <?php endif; ?>
});

// Fonction utilitaire pour vérifier les permissions
<?php
function canEditDossier($user_id, $user_role, $responsable_id) {
    return $user_role <= ROLE_GESTIONNAIRE || $user_id == $responsable_id;
}
?>
</script>

<?php include '../../includes/footer.php'; ?>
