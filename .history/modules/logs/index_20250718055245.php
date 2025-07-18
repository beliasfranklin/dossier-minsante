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

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'Audit - Système de Gestion des Dossiers</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: rgba(0, 0, 0, 0.1);
            --shadow-medium: rgba(0, 0, 0, 0.15);
            --shadow-heavy: rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #ffffff;
            line-height: 1.6;
        }

        .logs-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px var(--shadow-light);
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px var(--shadow-medium);
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .page-title i {
            margin-right: 15px;
            color: #ffd700;
        }

        /* Cartes de statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px var(--shadow-medium);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }

        .stat-card.primary::before {
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px var(--shadow-medium);
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 5px;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            color: rgba(255, 255, 255, 0.9);
        }

        /* Carte de filtres */
        .filter-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px var(--shadow-light);
            margin-bottom: 30px;
        }

        .filter-header {
            padding: 20px 25px;
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .filter-title i {
            color: #667eea;
        }

        .filter-content {
            padding: 25px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            color: white;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #a8d8ff;
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 0 0 3px rgba(168, 216, 255, 0.2);
            transform: translateY(-1px);
        }

        .form-select option {
            background: #2d3748;
            color: white;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Boutons modernes */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 16px rgba(79, 172, 254, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 172, 254, 0.4);
        }

        /* Carte de résultats */
        .results-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px var(--shadow-light);
        }

        .results-header {
            padding: 20px 25px;
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .results-title i {
            color: #667eea;
        }

        .results-count {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .pagination-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        /* Table moderne */
        .table-container {
            overflow-x: auto;
            max-height: 70vh;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }

        .modern-table th {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-weight: 600;
            padding: 15px 20px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .modern-table td {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            color: #ffffff;
            font-size: 0.9rem;
        }

        .modern-table tr {
            transition: all 0.3s ease;
        }

        .modern-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(2px);
        }

        /* Badges modernes */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .badge-success {
            background: var(--success-gradient);
            color: white;
        }

        .badge-warning {
            background: var(--warning-gradient);
            color: white;
        }

        .badge-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .badge-info {
            background: var(--info-gradient);
            color: white;
        }

        .badge-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .badge-light {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
        }

        .badge-dark {
            background: rgba(0, 0, 0, 0.3);
            color: white;
        }

        /* Avatar utilisateur */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Liens de dossier */
        .dossier-link {
            color: #a8d8ff;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .dossier-link:hover {
            color: #ffffff;
            transform: translateX(2px);
        }

        .dossier-ref {
            font-weight: 600;
            color: #ffffff;
        }

        .dossier-title {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }

        /* Pagination moderne */
        .pagination-container {
            padding: 20px 25px;
            background: rgba(255, 255, 255, 0.05);
            border-top: 1px solid var(--glass-border);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin: 0;
        }

        .pagination .page-item {
            list-style: none;
        }

        .pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .pagination .page-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .logs-container {
                padding: 10px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .results-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card,
        .filter-card,
        .results-card {
            animation: fadeIn 0.6s ease-out;
        }

        /* Scrollbar personnalisée */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>

<div class="logs-container">
    <!-- En-tête moderne -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-history"></i>
            Journal d'Audit
        </h1>
        <p class="page-subtitle">Suivi et traçabilité des actions système</p>
    </div>
    <!-- Statistiques rapides -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-content">
                <div>
                    <div class="stat-number"><?= number_format($stats['total_logs']) ?></div>
                    <div class="stat-label">📊 Total des logs</div>
                </div>
                <i class="fas fa-list stat-icon"></i>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-content">
                <div>
                    <div class="stat-number"><?= $stats['users_actifs'] ?></div>
                    <div class="stat-label">👥 Utilisateurs actifs</div>
                </div>
                <i class="fas fa-users stat-icon"></i>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-content">
                <div>
                    <div class="stat-number"><?= $stats['jours_actifs'] ?></div>
                    <div class="stat-label">📅 Jours avec activité</div>
                </div>
                <i class="fas fa-calendar stat-icon"></i>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-content">
                <div>
                    <div class="stat-number"><?= $stats['logs_24h'] ?></div>
                    <div class="stat-label">⏰ Dernières 24h</div>
                </div>
                <i class="fas fa-clock stat-icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filter-card">
        <div class="filter-header">
            <h5 class="filter-title">
                <i class="fas fa-filter"></i>
                Filtres de Recherche
            </h5>
        </div>
        
        <div class="filter-content">
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="user_id" class="form-label">👤 Utilisateur</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">-- Tous les utilisateurs --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="action_type" class="form-label">⚡ Type d'action</label>
                        <select class="form-select" id="action_type" name="action_type">
                            <option value="">-- Toutes les actions --</option>
                            <?php foreach ($action_types as $action): ?>
                                <option value="<?= $action ?>" <?= $filters['action_type'] == $action ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($action) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="dossier_id" class="form-label">📁 ID Dossier</label>
                        <input type="number" class="form-input" id="dossier_id" 
                               name="dossier_id" value="<?= htmlspecialchars($filters['dossier_id']) ?>" 
                               placeholder="Numéro du dossier">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from" class="form-label">📅 Date de début</label>
                        <input type="date" class="form-input" id="date_from" 
                               name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to" class="form-label">📅 Date de fin</label>
                        <input type="date" class="form-input" id="date_to" 
                               name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="search" class="form-label">🔍 Recherche</label>
                        <input type="text" class="form-input" id="search" 
                               name="search" value="<?= htmlspecialchars($filters['search']) ?>" 
                               placeholder="Détails, référence, titre...">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Appliquer les filtres
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                    <a href="export.php?<?= http_build_query($filters) ?>" class="btn btn-success">
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
                <span class="results-count">(<?= number_format($total_logs) ?> entrées)</span>
            </h5>
            
            <!-- Pagination info -->
            <span class="pagination-info">
                Page <?= $page ?> sur <?= $total_pages ?> 
                (<?= $offset + 1 ?>-<?= min($offset + $per_page, $total_logs) ?> sur <?= number_format($total_logs) ?>)
            </span>
        </div>
        
        <div class="table-container">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>📅 Date/Heure</th>
                        <th>👤 Utilisateur</th>
                        <th>⚡ Action</th>
                        <th>📁 Dossier</th>
                        <th>📝 Détails</th>
                        <th>🌐 IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">
                                    <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($log['prenom'], 0, 1) . substr($log['nom'], 0, 1)) ?>
                                    </div>
                                    <div class="user-name">
                                        <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= getActionColor($log['action_type']) ?>">
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
                                    <span style="color: rgba(255, 255, 255, 0.5);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                    <?= htmlspecialchars($log['details']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: rgba(255, 255, 255, 0.7); font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav aria-label="Pagination logs">
                    <ul class="pagination">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des éléments au scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observer les cartes
            document.querySelectorAll('.stat-card, .filter-card, .results-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Auto-complétion pour la recherche de dossiers
            const dossierInput = document.getElementById('dossier_id');
            if (dossierInput) {
                dossierInput.addEventListener('input', function() {
                    // Optionnel : ajouter une auto-complétion AJAX
                    if (this.value.length > 2) {
                        // Implémenter la logique d'auto-complétion ici
                    }
                });
            }
            
            // Raccourcis clavier
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    document.getElementById('search').focus();
                }
                
                // Raccourci pour exporter (Ctrl+E)
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    const exportBtn = document.querySelector('a[href*="export.php"]');
                    if (exportBtn) {
                        exportBtn.click();
                    }
                }
            });

            // Effet de hover sur les lignes du tableau
            const tableRows = document.querySelectorAll('.modern-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.background = 'rgba(255, 255, 255, 0.08)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.background = 'transparent';
                });
            });

            // Smooth scroll pour les liens de pagination
            document.querySelectorAll('.pagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Effet de loading lors du changement de page
                    const resultsCard = document.querySelector('.results-card');
                    resultsCard.style.opacity = '0.7';
                    resultsCard.style.transform = 'scale(0.98)';
                });
            });

            // Validation des formulaires
            const form = document.querySelector('form[method="GET"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtrage...';
                        submitBtn.disabled = true;
                        
                        // Réactiver après un délai pour permettre la soumission
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 1000);
                    }
                });
            }

            // Gestion des tooltips pour les actions
            const badges = document.querySelectorAll('.badge');
            badges.forEach(badge => {
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
                });
                
                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // Animation des statistiques au chargement
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                if (!isNaN(finalValue)) {
                    animateNumber(stat, finalValue);
                }
            });

            // Fonction pour animer les nombres
            function animateNumber(element, target) {
                const duration = 1000;
                const start = 0;
                const increment = target / (duration / 16);
                let current = start;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current).toLocaleString();
                }, 16);
            }

            // Gestion des filtres avancés
            const filterInputs = document.querySelectorAll('.form-input, .form-select');
            filterInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.2), 0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });

            // Notification pour les actions d'export
            const exportBtn = document.querySelector('a[href*="export.php"]');
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    // Afficher une notification
                    showNotification('Export en cours...', 'info');
                    
                    // Changer le style du bouton temporairement
                    const originalBg = this.style.background;
                    this.style.background = 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Export...';
                    
                    setTimeout(() => {
                        this.style.background = originalBg;
                        this.innerHTML = '<i class="fas fa-download"></i> Exporter CSV';
                    }, 2000);
                });
            }

            // Fonction pour afficher des notifications
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 12px;
                    padding: 15px 20px;
                    color: white;
                    z-index: 1000;
                    opacity: 0;
                    transform: translateX(100px);
                    transition: all 0.3s ease;
                `;
                
                notification.innerHTML = `
                    <i class="fas fa-${type === 'info' ? 'info-circle' : 'check-circle'}"></i>
                    ${message}
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '1';
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100px)';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }

            // Gestion responsive des filtres
            function handleResponsiveFilters() {
                const filterGrid = document.querySelector('.filter-grid');
                if (window.innerWidth <= 768) {
                    filterGrid.style.gridTemplateColumns = '1fr';
                } else if (window.innerWidth <= 1024) {
                    filterGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                } else {
                    filterGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(180px, 1fr))';
                }
            }
            
            handleResponsiveFilters();
            window.addEventListener('resize', handleResponsiveFilters);
        });

        // Fonction utilitaire pour les couleurs des actions
        <?php
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
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
