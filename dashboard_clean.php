<?php
session_start();
require_once 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupération des statistiques
try {
    // Compter les dossiers par statut
    $stats = [
        'total' => 0,
        'en_cours' => 0,
        'valides' => 0,
        'rejetes' => 0,
        'retard' => 0
    ];
    
    $query = "SELECT status, COUNT(*) as count FROM dossiers GROUP BY status";
    $stmt = $pdo->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = (int)$row['count'];
        $stats['total'] += (int)$row['count'];
    }
    
    // Compter les dossiers en retard
    $retardQuery = "SELECT COUNT(*) as count FROM dossiers WHERE deadline < NOW() AND status = 'en_cours'";
    $stmt = $pdo->query($retardQuery);
    $stats['retard'] = (int)$stmt->fetchColumn();
    
    // Récupération des dossiers récents (derniers 5)
    $recentQuery = "SELECT d.*, u.nom as responsable_name, u.prenom as responsable_prenom 
                    FROM dossiers d 
                    LEFT JOIN users u ON d.responsable_id = u.id 
                    ORDER BY d.created_at DESC 
                    LIMIT 5";
    $stmt = $pdo->query($recentQuery);
    $recentDossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur dashboard : " . $e->getMessage());
    $stats = ['total' => 0, 'en_cours' => 0, 'valides' => 0, 'rejetes' => 0, 'retard' => 0];
    $recentDossiers = [];
}

// Récupération des informations utilisateur
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT nom, prenom, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur utilisateur : " . $e->getMessage());
    }
}

$pageTitle = "Tableau de bord - MinSanté Dossiers";
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="container">
        <!-- En-tête du dashboard -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <div class="dashboard-title-section">
                    <div class="dashboard-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div>
                        <h1 class="dashboard-title">Tableau de bord</h1>
                        <p class="dashboard-subtitle">
                            Bienvenue <?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? 'Utilisateur')) ?>
                        </p>
                    </div>
                </div>
                <div class="dashboard-actions">
                    <button onclick="refreshDashboard()" class="btn-outline" title="Actualiser">
                        <i class="fas fa-sync-alt"></i>
                        Actualiser
                    </button>
                    <a href="modules/dossiers/create.php" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Nouveau dossier
                    </a>
                </div>
            </div>
        </div>

        <!-- Grille des statistiques -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Total des dossiers</h3>
                    <p class="stat-value" data-target="<?= $stats['total'] ?>">0</p>
                    <div class="stat-trend trend-neutral">
                        <i class="fas fa-equals"></i>
                        <span>Tous les dossiers</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-info">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>En cours</h3>
                    <p class="stat-value" data-target="<?= $stats['en_cours'] ?>">0</p>
                    <div class="stat-trend trend-positive">
                        <i class="fas fa-arrow-up"></i>
                        <span><?= $stats['total'] > 0 ? round(($stats['en_cours'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Validés</h3>
                    <p class="stat-value" data-target="<?= $stats['valides'] ?>">0</p>
                    <div class="stat-trend trend-positive">
                        <i class="fas fa-arrow-up"></i>
                        <span><?= $stats['total'] > 0 ? round(($stats['valides'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Rejetés</h3>
                    <p class="stat-value" data-target="<?= $stats['rejetes'] ?>">0</p>
                    <div class="stat-trend trend-negative">
                        <i class="fas fa-arrow-down"></i>
                        <span><?= $stats['total'] > 0 ? round(($stats['rejetes'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>En retard</h3>
                    <p class="stat-value" data-target="<?= $stats['retard'] ?>">0</p>
                    <div class="stat-trend trend-negative">
                        <i class="fas fa-clock"></i>
                        <span>Échéances dépassées</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Actions rapides
            </h2>
            <div class="actions-grid">
                <a href="modules/dossiers/list.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="action-content">
                        <h3>Voir tous les dossiers</h3>
                        <p>Gérer la liste complète</p>
                    </div>
                </a>

                <a href="modules/dossiers/create.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="action-content">
                        <h3>Nouveau dossier</h3>
                        <p>Créer un nouveau dossier</p>
                    </div>
                </a>

                <a href="modules/reporting/stats.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-content">
                        <h3>Rapports</h3>
                        <p>Statistiques et analyses</p>
                    </div>
                </a>

                <a href="modules/export/export.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="action-content">
                        <h3>Exporter</h3>
                        <p>Exporter les données</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Dossiers récents -->
        <div class="recent-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Dossiers récents
                </h2>
                <a href="modules/dossiers/list.php" class="btn-outline">
                    <i class="fas fa-arrow-right"></i>
                    Voir tous
                </a>
            </div>

            <?php if (count($recentDossiers) > 0): ?>
                <div class="dossier-grid">
                    <?php foreach ($recentDossiers as $dossier): ?>
                        <div class="dossier-card">
                            <div class="dossier-header">
                                <div class="dossier-ref">
                                    <i class="fas fa-file-alt"></i>
                                    <?= htmlspecialchars($dossier['reference']) ?>
                                </div>
                                <div class="dossier-status">
                                    <?php
                                    $statusClasses = [
                                        'en_cours' => 'status-info',
                                        'valide' => 'status-success',
                                        'rejete' => 'status-danger',
                                        'archive' => 'status-secondary'
                                    ];
                                    $statusTexts = [
                                        'en_cours' => 'En cours',
                                        'valide' => 'Validé',
                                        'rejete' => 'Rejeté',
                                        'archive' => 'Archivé'
                                    ];
                                    $statusClass = $statusClasses[$dossier['status']] ?? 'status-secondary';
                                    $statusText = $statusTexts[$dossier['status']] ?? 'Inconnu';
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </div>
                            </div>
                            <div class="dossier-content">
                                <h3 class="dossier-title"><?= htmlspecialchars($dossier['titre']) ?></h3>
                                <div class="dossier-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?= htmlspecialchars(($dossier['responsable_prenom'] ?? '') . ' ' . ($dossier['responsable_name'] ?? 'Non assigné')) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('d/m/Y', strtotime($dossier['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dossier-actions">
                                <a href="modules/dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i>
                                    Voir
                                </a>
                                <a href="modules/dossiers/edit.php?id=<?= $dossier['id'] ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i>
                                    Modifier
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>Aucun dossier récent</h3>
                    <p>Les dossiers récemment créés apparaîtront ici.</p>
                    <a href="modules/dossiers/create.php" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Créer le premier dossier
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Variables CSS */
:root {
    --primary-50: #eff6ff;
    --primary-100: #dbeafe;
    --primary-500: #3b82f6;
    --primary-600: #2563eb;
    --primary-700: #1d4ed8;
    
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

/* Dashboard principal */
.dashboard-container {
    min-height: calc(100vh - 70px);
    background: var(--gray-50);
    padding: 2rem 0;
}

/* En-tête du dashboard */
.dashboard-header {
    background: white;
    border-radius: var(--radius-2xl);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
}

.dashboard-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dashboard-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dashboard-icon {
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

.dashboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
}

.dashboard-subtitle {
    color: var(--gray-600);
    font-size: 1.1rem;
    margin: 0.5rem 0 0 0;
    font-weight: 400;
}

.dashboard-actions {
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

/* Grille des statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius-2xl);
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
    transition: var(--transition-all);
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transform: scaleX(0);
    transition: transform 0.5s ease;
    transform-origin: left;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card.stat-primary::before {
    background: var(--gradient-primary);
}

.stat-card.stat-info::before {
    background: var(--gradient-info);
}

.stat-card.stat-success::before {
    background: var(--gradient-success);
}

.stat-card.stat-danger::before {
    background: var(--gradient-danger);
}

.stat-card.stat-warning::before {
    background: var(--gradient-warning);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: var(--shadow-lg);
}

.stat-primary .stat-icon {
    background: var(--gradient-primary);
}

.stat-info .stat-icon {
    background: var(--gradient-info);
}

.stat-success .stat-icon {
    background: var(--gradient-success);
}

.stat-danger .stat-icon {
    background: var(--gradient-danger);
}

.stat-warning .stat-icon {
    background: var(--gradient-warning);
}

.stat-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-700);
    margin: 0 0 0.5rem 0;
}

.stat-value {
    font-size: 3rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1;
    margin-bottom: 1rem;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.trend-positive {
    color: var(--success-600);
}

.trend-negative {
    color: var(--danger-600);
}

.trend-neutral {
    color: var(--gray-500);
}

/* Actions rapides */
.quick-actions {
    margin-bottom: 3rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 1.5rem 0;
}

.section-title i {
    color: var(--primary-500);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    color: inherit;
    transition: var(--transition-all);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-500);
}

.action-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient-primary);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: var(--shadow-md);
}

.action-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 0.25rem 0;
}

.action-content p {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin: 0;
}

/* Section dossiers récents */
.recent-section {
    background: white;
    border-radius: var(--radius-2xl);
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-100);
}

/* Grille des dossiers */
.dossier-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

.dossier-card {
    background: var(--gray-50);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    transition: var(--transition-all);
}

.dossier-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-500);
}

.dossier-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.dossier-ref {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--gray-700);
}

.dossier-ref i {
    color: var(--primary-500);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-info {
    background: var(--info-100);
    color: var(--info-600);
}

.status-success {
    background: var(--success-100);
    color: var(--success-600);
}

.status-danger {
    background: var(--danger-100);
    color: var(--danger-600);
}

.status-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}

.dossier-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 1rem 0;
    line-height: 1.4;
}

.dossier-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--gray-600);
}

.meta-item i {
    color: var(--gray-400);
    width: 14px;
}

.dossier-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-view,
.btn-edit {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-lg);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition-all);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    background: var(--gradient-primary);
    color: white;
}

.btn-edit {
    background: var(--success-100);
    color: var(--success-600);
    border: 1px solid var(--success-200);
}

.btn-view:hover,
.btn-edit:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: var(--gray-100);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    font-size: 2rem;
    color: var(--gray-400);
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-700);
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    color: var(--gray-500);
    margin: 0 0 1.5rem 0;
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

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card {
    animation: fadeInUp 0.6s ease-out forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
.stat-card:nth-child(5) { animation-delay: 0.5s; }

.recent-section {
    animation: slideInUp 0.8s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem 0;
    }
    
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .dashboard-header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .dossier-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .dossier-actions {
        justify-content: center;
    }
}
</style>

<script>
// Animation des compteurs
function animateStats() {
    const statValues = document.querySelectorAll('.stat-value');
    
    statValues.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-target'));
        const duration = 2000;
        const startTime = Date.now();
        
        function updateStat() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(progress * target);
            
            stat.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(updateStat);
            }
        }
        
        updateStat();
    });
}

// Fonction de rafraîchissement
function refreshDashboard() {
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

// Démarrer les animations au chargement
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(animateStats, 500);
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

<?php include __DIR__ . '/includes/footer.php'; ?>
