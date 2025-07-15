<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérification des permissions
if (!hasPermission(ROLE_GESTIONNAIRE)) {
    die('Accès refusé');
}

// Traitement des filtres
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Début du mois
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Aujourd'hui
$service = $_GET['service'] ?? '';
$type = $_GET['type'] ?? '';
$responsable = $_GET['responsable_id'] ?? '';

// Statistiques générales avec délais
$statsQuery = "
    SELECT 
        COUNT(*) as total_dossiers,
        AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as delai_moyen_jours,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as dossiers_valides,
        COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as dossiers_en_cours,
        COUNT(CASE WHEN status = 'rejete' THEN 1 END) as dossiers_rejetes,
        COUNT(CASE WHEN deadline IS NOT NULL AND deadline < NOW() AND status != 'valide' THEN 1 END) as dossiers_retard,
        AVG(CASE WHEN status = 'valide' THEN DATEDIFF(updated_at, created_at) END) as delai_moyen_validation
    FROM dossiers 
    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";

$params = [$dateFrom, $dateTo];
$whereConditions = [];

if ($service) {
    $whereConditions[] = "service = ?";
    $params[] = $service;
}
if ($type) {
    $whereConditions[] = "type = ?";
    $params[] = $type;
}
if ($responsable) {
    $whereConditions[] = "responsable_id = ?";
    $params[] = $responsable;
}

if (!empty($whereConditions)) {
    $statsQuery .= " AND " . implode(" AND ", $whereConditions);
}

$stats = fetchOne($statsQuery, $params);

// Performance par responsable
$performanceQuery = "
    SELECT 
        u.name as responsable_name,
        u.id as responsable_id,
        COUNT(d.id) as total_dossiers,
        COUNT(CASE WHEN d.status = 'valide' THEN 1 END) as dossiers_valides,
        COUNT(CASE WHEN d.status = 'rejete' THEN 1 END) as dossiers_rejetes,
        AVG(CASE WHEN d.status = 'valide' THEN DATEDIFF(d.updated_at, d.created_at) END) as delai_moyen_traitement,
        COUNT(CASE WHEN d.deadline IS NOT NULL AND d.deadline < NOW() AND d.status != 'valide' THEN 1 END) as dossiers_retard,
        ROUND((COUNT(CASE WHEN d.status = 'valide' THEN 1 END) / COUNT(d.id)) * 100, 2) as taux_validation
    FROM users u
    LEFT JOIN dossiers d ON u.id = d.responsable_id 
        AND d.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";

$performanceParams = [$dateFrom, $dateTo];
if (!empty($whereConditions)) {
    $performanceQuery .= " AND " . implode(" AND ", array_map(function($cond) {
        return "d." . $cond;
    }, $whereConditions));
    $performanceParams = array_merge($performanceParams, array_slice($params, 2));
}

$performanceQuery .= " GROUP BY u.id, u.name HAVING total_dossiers > 0 ORDER BY taux_validation DESC";
$performance = fetchAll($performanceQuery, $performanceParams);

// Délais par type de dossier
$delaisParType = fetchAll("
    SELECT 
        type,
        COUNT(*) as nombre,
        AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as delai_moyen,
        MIN(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as delai_min,
        MAX(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as delai_max,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as valides,
        AVG(CASE WHEN status = 'valide' THEN DATEDIFF(updated_at, created_at) END) as delai_validation_moyen
    FROM dossiers 
    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY type
    ORDER BY delai_moyen DESC
", [$dateFrom, $dateTo]);

// Évolution temporelle (par semaine)
$evolution = fetchAll("
    SELECT 
        YEARWEEK(created_at) as semaine,
        DATE(created_at - INTERVAL WEEKDAY(created_at) DAY) as debut_semaine,
        COUNT(*) as nombre_crees,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as nombre_valides,
        AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as delai_moyen_semaine
    FROM dossiers 
    WHERE created_at BETWEEN DATE_SUB(?, INTERVAL 12 WEEK) AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY YEARWEEK(created_at)
    ORDER BY semaine DESC
    LIMIT 12
", [$dateFrom, $dateTo]);

// Goulots d'étranglement (workflow) - Requête sécurisée
try {
    // Vérifier si les tables et colonnes existent
    $tablesExist = true;
    $columnsExist = true;
    
    // Vérifier l'existence des tables
    $workflowTableExists = $pdo->query("SHOW TABLES LIKE 'workflows'")->rowCount() > 0;
    $workflowInstancesTableExists = $pdo->query("SHOW TABLES LIKE 'workflow_instances'")->rowCount() > 0;
    
    if (!$workflowTableExists || !$workflowInstancesTableExists) {
        $tablesExist = false;
    }
    
    // Vérifier l'existence des colonnes critiques
    if ($tablesExist) {
        $startedAtExists = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'started_at'")->rowCount() > 0;
        $approvedAtExists = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'approved_at'")->rowCount() > 0;
        $rejectedAtExists = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'rejected_at'")->rowCount() > 0;
        $workflowStepIdExists = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'workflow_step_id'")->rowCount() > 0;
        
        if (!$startedAtExists || !$approvedAtExists || !$rejectedAtExists || !$workflowStepIdExists) {
            $columnsExist = false;
        }
    }
    
    if ($tablesExist && $columnsExist) {
        // Requête complète si tout existe
        $goulots = fetchAll("
            SELECT 
                COALESCE(w.etape, 'Étape inconnue') as etape,
                COALESCE(w.type_dossier, 'Type non défini') as type_dossier,
                COUNT(wi.id) as nombre_instances,
                AVG(TIMESTAMPDIFF(HOUR, 
                    COALESCE(wi.started_at, wi.created_at), 
                    COALESCE(wi.approved_at, wi.rejected_at, wi.completed_at, NOW())
                )) as duree_moyenne_heures,
                COUNT(CASE WHEN wi.status IN ('active', 'pending') THEN 1 END) as en_attente,
                COUNT(CASE WHEN wi.status = 'approved' THEN 1 END) as approuvees,
                COUNT(CASE WHEN wi.status = 'rejected' THEN 1 END) as rejetees
            FROM workflow_instances wi
            LEFT JOIN workflows w ON wi.workflow_step_id = w.id
            JOIN dossiers d ON wi.dossier_id = d.id
            WHERE d.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY COALESCE(w.id, 0), COALESCE(w.etape, 'Étape inconnue'), COALESCE(w.type_dossier, 'Type non défini')
            ORDER BY duree_moyenne_heures DESC
        ", [$dateFrom, $dateTo]);
    } else {
        // Requête alternative simplifiée si les tables/colonnes n'existent pas
        $goulots = fetchAll("
            SELECT 
                'Workflow non configuré' as etape,
                'Standard' as type_dossier,
                COUNT(d.id) as nombre_instances,
                AVG(TIMESTAMPDIFF(HOUR, d.created_at, COALESCE(d.updated_at, NOW()))) as duree_moyenne_heures,
                COUNT(CASE WHEN d.status = 'en_cours' THEN 1 END) as en_attente,
                COUNT(CASE WHEN d.status = 'valide' THEN 1 END) as approuvees,
                COUNT(CASE WHEN d.status = 'rejete' THEN 1 END) as rejetees
            FROM dossiers d
            WHERE d.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY d.status
            ORDER BY duree_moyenne_heures DESC
        ", [$dateFrom, $dateTo]);
    }
    
} catch (Exception $e) {
    // En cas d'erreur, créer un tableau vide
    $goulots = [];
    error_log("Erreur dans la requête des goulots d'étranglement: " . $e->getMessage());
}

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporting Avancé - MINSANTE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reporting-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .reporting-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            margin: 15px 0;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .performance-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2d3748;
        }
        
        .performance-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e1e8ed;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .text-success { color: #27ae60 !important; }
        .text-warning { color: #f39c12 !important; }
        .text-danger { color: #e74c3c !important; }
        .text-primary { color: #3498db !important; }
    </style>
</head>
<body>
    <div class="reporting-container">
        <!-- En-tête -->
        <div class="reporting-header">
            <h1><i class="fas fa-chart-line"></i> Reporting Avancé & Analyse de Performance</h1>
            <p>Analyse détaillée des délais, performance et goulots d'étranglement</p>
        </div>
        
        <!-- Filtres -->
        <div class="filters-section">
            <h3><i class="fas fa-filter"></i> Filtres d'analyse</h3>
            <form method="get" class="filters-grid">
                <div>
                    <label>Date de début :</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control" required>
                </div>
                <div>
                    <label>Date de fin :</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control" required>
                </div>
                <div>
                    <label>Service :</label>
                    <select name="service" class="form-control">
                        <option value="">Tous les services</option>
                        <option value="DEP" <?= $service === 'DEP' ? 'selected' : '' ?>>DEP</option>
                        <option value="Finance" <?= $service === 'Finance' ? 'selected' : '' ?>>Finance</option>
                        <option value="RH" <?= $service === 'RH' ? 'selected' : '' ?>>RH</option>
                        <option value="Logistique" <?= $service === 'Logistique' ? 'selected' : '' ?>>Logistique</option>
                    </select>
                </div>
                <div>
                    <label>Type :</label>
                    <select name="type" class="form-control">
                        <option value="">Tous les types</option>
                        <option value="Etude" <?= $type === 'Etude' ? 'selected' : '' ?>>Étude</option>
                        <option value="Projet" <?= $type === 'Projet' ? 'selected' : '' ?>>Projet</option>
                        <option value="Administratif" <?= $type === 'Administratif' ? 'selected' : '' ?>>Administratif</option>
                        <option value="Autre" <?= $type === 'Autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div>
                    <label>Responsable :</label>
                    <select name="responsable_id" class="form-control">
                        <option value="">Tous les responsables</option>
                        <?php
                        $responsables = fetchAll("SELECT id, name FROM users WHERE role >= ? ORDER BY name", [ROLE_GESTIONNAIRE]);
                        foreach ($responsables as $resp):
                        ?>
                            <option value="<?= $resp['id'] ?>" <?= $responsable == $resp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($resp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Analyser
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Statistiques principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon text-primary">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="stat-value text-primary"><?= number_format($stats['total_dossiers']) ?></div>
                <div class="stat-label">Total Dossiers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value text-warning"><?= number_format($stats['delai_moyen_jours'], 1) ?></div>
                <div class="stat-label">Délai Moyen (jours)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value text-success">
                    <?= $stats['total_dossiers'] > 0 ? number_format(($stats['dossiers_valides'] / $stats['total_dossiers']) * 100, 1) : 0 ?>%
                </div>
                <div class="stat-label">Taux de Validation</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value text-danger"><?= number_format($stats['dossiers_retard']) ?></div>
                <div class="stat-label">Dossiers en Retard</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon text-success">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="stat-value text-success"><?= number_format($stats['delai_moyen_validation'], 1) ?></div>
                <div class="stat-label">Délai Validation (jours)</div>
            </div>
        </div>
        
        <!-- Performance par responsable -->
        <div class="chart-container">
            <h3><i class="fas fa-users"></i> Performance par Responsable</h3>
            <?php if (!empty($performance)): ?>
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Responsable</th>
                            <th>Total</th>
                            <th>Validés</th>
                            <th>Rejetés</th>
                            <th>Taux Validation</th>
                            <th>Délai Moyen</th>
                            <th>En Retard</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance as $perf): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($perf['responsable_name']) ?></strong></td>
                                <td><?= $perf['total_dossiers'] ?></td>
                                <td><span class="badge badge-success"><?= $perf['dossiers_valides'] ?></span></td>
                                <td><span class="badge badge-danger"><?= $perf['dossiers_rejetes'] ?></span></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $perf['taux_validation'] ?>%; background: <?= $perf['taux_validation'] >= 80 ? '#27ae60' : ($perf['taux_validation'] >= 60 ? '#f39c12' : '#e74c3c') ?>;"></div>
                                    </div>
                                    <?= $perf['taux_validation'] ?>%
                                </td>
                                <td><?= number_format($perf['delai_moyen_traitement'], 1) ?> jours</td>
                                <td>
                                    <?php if ($perf['dossiers_retard'] > 0): ?>
                                        <span class="badge badge-danger"><?= $perf['dossiers_retard'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $score = $perf['taux_validation'] - ($perf['dossiers_retard'] * 5);
                                    if ($score >= 90) echo '<span class="text-success"><i class="fas fa-star"></i> Excellent</span>';
                                    elseif ($score >= 70) echo '<span class="text-primary"><i class="fas fa-thumbs-up"></i> Bon</span>';
                                    elseif ($score >= 50) echo '<span class="text-warning"><i class="fas fa-minus-circle"></i> Moyen</span>';
                                    else echo '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Faible</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #64748b; padding: 40px;">Aucune donnée de performance pour la période sélectionnée</p>
            <?php endif; ?>
        </div>
        
        <!-- Délais par type -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-bar"></i> Analyse des Délais par Type</h3>
            <div style="position: relative; height: 400px; margin-bottom: 20px;">
                <canvas id="delaisChart"></canvas>
            </div>
            
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Type de Dossier</th>
                        <th>Nombre</th>
                        <th>Délai Moyen</th>
                        <th>Délai Min</th>
                        <th>Délai Max</th>
                        <th>Délai Validation</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delaisParType as $delai): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($delai['type']) ?></strong></td>
                            <td><?= $delai['nombre'] ?></td>
                            <td><?= number_format($delai['delai_moyen'], 1) ?> jours</td>
                            <td><?= $delai['delai_min'] ?> jours</td>
                            <td><?= $delai['delai_max'] ?> jours</td>
                            <td><?= number_format($delai['delai_validation_moyen'], 1) ?> jours</td>
                            <td>
                                <?php
                                $efficacite = $delai['delai_validation_moyen'] < 7 ? 'Excellent' : 
                                           ($delai['delai_validation_moyen'] < 14 ? 'Bon' : 
                                           ($delai['delai_validation_moyen'] < 30 ? 'Moyen' : 'Lent'));
                                $color = $efficacite === 'Excellent' ? 'success' : 
                                        ($efficacite === 'Bon' ? 'primary' : 
                                        ($efficacite === 'Moyen' ? 'warning' : 'danger'));
                                ?>
                                <span class="text-<?= $color ?>"><?= $efficacite ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Évolution temporelle -->
        <div class="chart-container">
            <h3><i class="fas fa-line-chart"></i> Évolution des Délais (12 dernières semaines)</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>
        
        <!-- Goulots d'étranglement -->
        <?php if (!empty($goulots)): ?>
            <div class="chart-container">
                <h3><i class="fas fa-bottleneck"></i> Analyse des Goulots d'Étranglement (Workflow)</h3>
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Étape</th>
                            <th>Type Dossier</th>
                            <th>Instances</th>
                            <th>Durée Moyenne (h)</th>
                            <th>En Attente</th>
                            <th>Approuvées</th>
                            <th>Rejetées</th>
                            <th>Efficacité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goulots as $goulot): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($goulot['etape']) ?></strong></td>
                                <td><?= htmlspecialchars($goulot['type_dossier']) ?></td>
                                <td><?= $goulot['nombre_instances'] ?></td>
                                <td>
                                    <span class="<?= $goulot['duree_moyenne_heures'] > 48 ? 'text-danger' : ($goulot['duree_moyenne_heures'] > 24 ? 'text-warning' : 'text-success') ?>">
                                        <?= number_format($goulot['duree_moyenne_heures'], 1) ?>h
                                    </span>
                                </td>
                                <td>
                                    <?php if ($goulot['en_attente'] > 0): ?>
                                        <span class="badge badge-warning"><?= $goulot['en_attente'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-success"><?= $goulot['approuvees'] ?></span></td>
                                <td><span class="badge badge-danger"><?= $goulot['rejetees'] ?></span></td>
                                <td>
                                    <?php
                                    $tauxApprob = $goulot['nombre_instances'] > 0 ? ($goulot['approuvees'] / $goulot['nombre_instances']) * 100 : 0;
                                    if ($tauxApprob >= 90 && $goulot['duree_moyenne_heures'] <= 24) {
                                        echo '<span class="text-success"><i class="fas fa-rocket"></i> Fluide</span>';
                                    } elseif ($tauxApprob >= 70 && $goulot['duree_moyenne_heures'] <= 48) {
                                        echo '<span class="text-primary"><i class="fas fa-check"></i> Normal</span>';
                                    } elseif ($goulot['duree_moyenne_heures'] > 48 || $goulot['en_attente'] > 5) {
                                        echo '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Goulot</span>';
                                    } else {
                                        echo '<span class="text-warning"><i class="fas fa-clock"></i> Lent</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Actions recommandées -->
        <div class="chart-container">
            <h3><i class="fas fa-lightbulb"></i> Recommandations d'Amélioration</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <?php
                $recommendations = [];
                
                if ($stats['dossiers_retard'] > 0) {
                    $recommendations[] = [
                        'icon' => 'fas fa-exclamation-triangle',
                        'title' => 'Dossiers en retard',
                        'description' => $stats['dossiers_retard'] . ' dossiers dépassent leur échéance. Priorité à donner.',
                        'color' => 'danger'
                    ];
                }
                
                if ($stats['delai_moyen_jours'] > 15) {
                    $recommendations[] = [
                        'icon' => 'fas fa-clock',
                        'title' => 'Délais trop longs',
                        'description' => 'Le délai moyen de ' . number_format($stats['delai_moyen_jours'], 1) . ' jours peut être optimisé.',
                        'color' => 'warning'
                    ];
                }
                
                if (!empty($goulots) && $goulots[0]['duree_moyenne_heures'] > 48) {
                    $recommendations[] = [
                        'icon' => 'fas fa-bottleneck',
                        'title' => 'Goulot d\'étranglement détecté',
                        'description' => 'L\'étape "' . $goulots[0]['etape'] . '" prend ' . number_format($goulots[0]['duree_moyenne_heures'], 1) . 'h en moyenne.',
                        'color' => 'danger'
                    ];
                }
                
                $tauxValidation = $stats['total_dossiers'] > 0 ? ($stats['dossiers_valides'] / $stats['total_dossiers']) * 100 : 0;
                if ($tauxValidation >= 90) {
                    $recommendations[] = [
                        'icon' => 'fas fa-trophy',
                        'title' => 'Excellente performance',
                        'description' => 'Taux de validation de ' . number_format($tauxValidation, 1) . '%. Maintenez cette qualité !',
                        'color' => 'success'
                    ];
                }
                
                if (empty($recommendations)) {
                    $recommendations[] = [
                        'icon' => 'fas fa-check-circle',
                        'title' => 'Performance satisfaisante',
                        'description' => 'Aucun problème majeur détecté dans la période analysée.',
                        'color' => 'success'
                    ];
                }
                
                foreach ($recommendations as $rec):
                ?>
                    <div style="padding: 20px; border-left: 4px solid; border-color: var(--bs-<?= $rec['color'] ?>); background: rgba(var(--bs-<?= $rec['color'] ?>-rgb), 0.1); border-radius: 8px;">
                        <h4 style="color: var(--bs-<?= $rec['color'] ?>); margin-bottom: 10px;">
                            <i class="<?= $rec['icon'] ?>"></i> <?= $rec['title'] ?>
                        </h4>
                        <p style="margin: 0; color: #64748b;"><?= $rec['description'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Graphique des délais par type
        const delaisCtx = document.getElementById('delaisChart').getContext('2d');
        new Chart(delaisCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($delaisParType, 'type')) ?>,
                datasets: [
                    {
                        label: 'Délai Moyen (jours)',
                        data: <?= json_encode(array_column($delaisParType, 'delai_moyen')) ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Délai Validation (jours)',
                        data: <?= json_encode(array_column($delaisParType, 'delai_validation_moyen')) ?>,
                        backgroundColor: 'rgba(39, 174, 96, 0.7)',
                        borderColor: 'rgba(39, 174, 96, 1)',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jours'
                        }
                    }
                }
            }
        });
        
        // Graphique d'évolution
        const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
        new Chart(evolutionCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($e) { return date('d/m', strtotime($e['debut_semaine'])); }, array_reverse($evolution))) ?>,
                datasets: [
                    {
                        label: 'Dossiers créés',
                        data: <?= json_encode(array_column(array_reverse($evolution), 'nombre_crees')) ?>,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.3
                    },
                    {
                        label: 'Dossiers validés',
                        data: <?= json_encode(array_column(array_reverse($evolution), 'nombre_valides')) ?>,
                        borderColor: 'rgba(39, 174, 96, 1)',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre de dossiers'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Semaines'
                        }
                    }
                }
            }
        });
    </script>
    
    <style>
        :root {
            --bs-success-rgb: 39, 174, 96;
            --bs-warning-rgb: 243, 156, 18;
            --bs-danger-rgb: 231, 76, 60;
            --bs-primary-rgb: 52, 152, 219;
            --bs-success: #27ae60;
            --bs-warning: #f39c12;
            --bs-danger: #e74c3c;
            --bs-primary: #3498db;
        }
    </style>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
