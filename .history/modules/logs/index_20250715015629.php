<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireRole(ROLE_GESTIONNAIRE);

// Paramètres de filtrage
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action_type' => $_GET['action_type'] ?? '',
    'dossier_id' => $_GET['dossier_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($filters['user_id'])) {
    $where_conditions[] = "l.user_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['action_type'])) {
    $where_conditions[] = "l.action_type = ?";
    $params[] = $filters['action_type'];
}

if (!empty($filters['dossier_id'])) {
    $where_conditions[] = "l.dossier_id = ?";
    $params[] = $filters['dossier_id'];
}

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(l.details LIKE ? OR d.reference LIKE ? OR d.titre LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_sql = "
    SELECT COUNT(*) 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN dossiers d ON l.dossier_id = d.id
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Récupérer les logs avec pagination
$sql = "
    SELECT 
        l.*,
        u.prenom, u.nom, u.email,
        d.reference, d.titre
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN dossiers d ON l.dossier_id = d.id
    $where_clause
    ORDER BY l.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Récupérer les données pour les filtres
$users_stmt = $pdo->query("SELECT id, prenom, nom FROM users ORDER BY prenom, nom");
$users = $users_stmt->fetchAll();

$actions_stmt = $pdo->query("SELECT DISTINCT action_type FROM logs ORDER BY action_type");
$action_types = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Statistiques rapides
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as users_actifs,
        COUNT(DISTINCT DATE(created_at)) as jours_actifs,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as logs_24h
    FROM logs
");
$stats = $stats_stmt->fetch();

$page_title = "Journal d'Audit";
include '../../includes/header.php';
?>

<style>
:root {
    --logs-primary: #667eea;
    --logs-secondary: #764ba2;
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

.logs-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    animation: fadeIn 0.8s ease-out;
}

.page-header {
    background: linear-gradient(135deg, var(--logs-primary) 0%, var(--logs-secondary) 100%);
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--white);
    border-radius: 20px;
    padding: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-hover);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
}

.stat-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-info h3 {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.stat-info p {
    color: var(--text-secondary);
    margin: 0;
    font-weight: 600;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: var(--white);
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.filters-card {
    background: var(--white);
    border-radius: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    margin-bottom: 32px;
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

.card-header-custom {
    background: linear-gradient(135deg, var(--light-bg), var(--white));
    padding: 24px;
    border-bottom: 2px solid var(--border-color);
}

.card-header-custom h5 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-form {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control-modern {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-control-modern:focus {
    outline: none;
    border-color: var(--logs-primary);
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.filter-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 2px solid var(--light-bg);
}

.btn-modern {
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary-modern {
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
    color: var(--white);
    border-color: var(--logs-primary);
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: var(--white);
}

.btn-secondary-modern {
    background: var(--white);
    color: var(--text-secondary);
    border-color: var(--border-color);
}

.btn-secondary-modern:hover {
    background: var(--light-bg);
    color: var(--text-primary);
    border-color: var(--text-secondary);
}

.btn-success-modern {
    background: var(--success-color);
    color: var(--white);
    border-color: var(--success-color);
}

.btn-success-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    color: var(--white);
}

.results-card {
    background: var(--white);
    border-radius: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

.results-header {
    background: linear-gradient(135deg, var(--light-bg), var(--white));
    padding: 24px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.results-title {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.results-info {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.table-container {
    overflow-x: auto;
    max-height: 600px;
}

.table-modern {
    width: 100%;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table-modern thead th {
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
    color: var(--white);
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 12px;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-modern thead th:first-child {
    border-top-left-radius: 0;
}

.table-modern thead th:last-child {
    border-top-right-radius: 0;
}

.table-modern tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid var(--border-color);
}

.table-modern tbody tr:hover {
    background: var(--light-bg);
    transform: scale(1.01);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
}

.table-modern tbody td {
    padding: 16px 12px;
    border: none;
    vertical-align: middle;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.user-details h6 {
    margin: 0;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.action-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.dossier-link {
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: block;
}

.dossier-link:hover {
    background: var(--light-bg);
    transform: translateX(4px);
}

.dossier-ref {
    font-weight: 700;
    color: var(--logs-primary);
    font-size: 0.85rem;
}

.dossier-title {
    color: var(--text-secondary);
    font-size: 0.8rem;
    margin-top: 2px;
}

.ip-address {
    font-family: 'Courier New', monospace;
    background: var(--light-bg);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.pagination-modern {
    padding: 24px;
    background: var(--light-bg);
    display: flex;
    justify-content: center;
}

.pagination-modern .pagination {
    margin: 0;
}

.pagination-modern .page-item .page-link {
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 16px;
    margin: 0 4px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-modern .page-item:hover .page-link {
    background: var(--logs-primary);
    color: var(--white);
    border-color: var(--logs-primary);
    transform: translateY(-2px);
}

.pagination-modern .page-item.active .page-link {
    background: linear-gradient(135deg, var(--logs-primary), var(--logs-secondary));
    border-color: var(--logs-primary);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
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

@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    .logs-container {
        padding: 16px;
    }
    
    .page-header {
        padding: 24px;
    }
    
    .filter-form {
        padding: 16px;
    }
    
    .filter-actions {
        justify-content: center;
    }
    
    .results-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    
    .table-container {
        max-height: 500px;
    }
    
    .table-modern tbody td {
        padding: 12px 8px;
        font-size: 0.85rem;
    }
}
</style>

<div class="logs-container">
    <!-- En-tête de la page -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="header-text">
                <h1>Journal d'Audit</h1>
                <p>Surveillance et traçabilité des actions système</p>
            </div>
        </div>
    </div>
    <!-- Statistiques rapides -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= number_format($stats['total_logs']) ?></h3>
                    <p>Total des logs</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['users_actifs'] ?></h3>
                    <p>Utilisateurs actifs</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['jours_actifs'] ?></h3>
                    <p>Jours avec activité</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['logs_24h'] ?></h3>
                    <p>Dernières 24h</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filters-card">
        <div class="card-header-custom">
            <h5><i class="fas fa-filter"></i> Filtres de Recherche</h5>
        </div>
        
        <div class="filter-form">
            <form method="GET">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="user_id" class="form-label">Utilisateur</label>
                            <select class="form-control-modern" id="user_id" name="user_id">
                                <option value="">-- Tous --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="action_type" class="form-label">Type d'action</label>
                            <select class="form-control-modern" id="action_type" name="action_type">
                                <option value="">-- Toutes --</option>
                                <?php foreach ($action_types as $action): ?>
                                    <option value="<?= $action ?>" <?= $filters['action_type'] == $action ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($action) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="dossier_id" class="form-label">ID Dossier</label>
                            <input type="number" class="form-control-modern" id="dossier_id" 
                                   name="dossier_id" value="<?= htmlspecialchars($filters['dossier_id']) ?>" 
                                   placeholder="ID du dossier">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_from" class="form-label">Date début</label>
                            <input type="date" class="form-control-modern" id="date_from" 
                                   name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_to" class="form-label">Date fin</label>
                            <input type="date" class="form-control-modern" id="date_to" 
                                   name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control-modern" id="search" 
                                   name="search" value="<?= htmlspecialchars($filters['search']) ?>" 
                                   placeholder="Détails, référence...">
                        </div>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-modern btn-secondary-modern">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                    <a href="export.php?<?= http_build_query($filters) ?>" class="btn-modern btn-success-modern">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Résultats -->
    <div class="results-card">
        <div class="results-header">
            <h5 class="results-title">
                <i class="fas fa-history"></i> 
                Journal d'Audit
                <small class="text-muted">(<?= number_format($total_logs) ?> entrées)</small>
            </h5>
            
            <div class="results-info">
                Page <?= $page ?> sur <?= $total_pages ?> 
                (<?= $offset + 1 ?>-<?= min($offset + $per_page, $total_logs) ?> sur <?= number_format($total_logs) ?>)
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h4>Aucun log trouvé</h4>
                    <p>Aucune entrée ne correspond aux critères de recherche.</p>
                </div>
            <?php else: ?>
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Dossier</th>
                            <th>Détails</th>
                            <th>Adresse IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">
                                        <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($log['prenom'], 0, 1) . substr($log['nom'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h6><?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="action-badge" style="background: <?= getActionBadgeColor($log['action_type']) ?>; color: white;">
                                        <?= htmlspecialchars($log['action_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['dossier_id']): ?>
                                        <a href="../../modules/dossiers/view.php?id=<?= $log['dossier_id'] ?>" 
                                           class="dossier-link">
                                            <div class="dossier-ref"><?= htmlspecialchars($log['reference']) ?></div>
                                            <div class="dossier-title">
                                                <?= htmlspecialchars(substr($log['titre'] ?? 'Sans titre', 0, 30)) ?>...
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; color: var(--text-secondary); font-size: 0.9rem;">
                                        <?= htmlspecialchars($log['details']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="ip-address">
                                        <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-modern">
                <nav aria-label="Pagination logs">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $total_pages])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des sections
    const sections = document.querySelectorAll('.stat-card, .filters-card, .results-card');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Auto-complétion pour la recherche de dossiers
    const dossierInput = document.getElementById('dossier_id');
    if (dossierInput) {
        dossierInput.addEventListener('input', function() {
            // Optionnel : ajouter une auto-complétion AJAX
        });
    }
    
    // Animation des lignes du tableau au survol
    const tableRows = document.querySelectorAll('.table-modern tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Effet de ripple sur les boutons
    const buttons = document.querySelectorAll('.btn-modern');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            createRippleEffect(this, e);
        });
    });

    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
    });
    
    // Animation des statistiques au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '50px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in-view');
                animateNumber(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.stat-card').forEach(card => {
        observer.observe(card);
    });

    console.log('📊 Page de logs d\'audit initialisée avec succès');
});

// Fonction pour créer un effet de ripple
function createRippleEffect(element, event) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple-effect 0.6s ease-out;
        pointer-events: none;
        z-index: 100;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Animation des nombres dans les statistiques
function animateNumber(card) {
    const numberElement = card.querySelector('h3');
    if (!numberElement) return;
    
    const finalNumber = parseInt(numberElement.textContent.replace(/[^\d]/g, ''));
    const duration = 1000;
    const steps = 30;
    const increment = finalNumber / steps;
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= finalNumber) {
            current = finalNumber;
            clearInterval(timer);
        }
        numberElement.textContent = Math.floor(current).toLocaleString();
    }, duration / steps);
}

// Animation CSS pour l'effet ripple
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple-effect {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .animate-in-view {
        animation: slideInUp 0.6s ease-out;
    }
    
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
`;
document.head.appendChild(rippleStyle);
</script>

<?php 
// Fonction pour les couleurs des badges d'action
function getActionBadgeColor($action) {
    $colors = [
        'LOGIN' => '#10b981',
        'LOGOUT' => '#6b7280',
        'CREATE' => '#3b82f6',
        'UPDATE' => '#f59e0b',
        'DELETE' => '#ef4444',
        'CHANGE_STATUS' => '#8b5cf6',
        'UPLOAD' => '#06b6d4',
        'DOWNLOAD' => '#14b8a6',
        'EXPORT' => '#10b981',
        'ARCHIVE' => '#374151',
        'ADD_COMMENT' => '#6366f1'
    ];
    
    foreach ($colors as $pattern => $color) {
        if (strpos($action, $pattern) !== false) {
            return $color;
        }
    }
    
    return '#9ca3af';
}

// Fonction utilitaire pour les couleurs des actions (conservée pour compatibilité)
function getActionColor($action) {
    $colors = [
        'LOGIN' => 'success',
        'LOGOUT' => 'secondary',
        'CREATE' => 'primary',
        'UPDATE' => 'warning',
        'DELETE' => 'danger',
        'CHANGE_STATUS' => 'info',
        'UPLOAD' => 'primary',
        'DOWNLOAD' => 'info',
        'EXPORT' => 'success',
        'ARCHIVE' => 'dark'
    ];
    
    foreach ($colors as $pattern => $color) {
        if (strpos($action, $pattern) !== false) {
            return $color;
        }
    }
    
    return 'light';
}
?>

<?php include '../../includes/footer.php'; ?>
