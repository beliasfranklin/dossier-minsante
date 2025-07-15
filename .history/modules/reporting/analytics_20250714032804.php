<?php
/**
 * Module d'analyse et intelligence d'affaires pour MINSANTE
 * Tableaux de bord avancés, KPI, prédictions et recommandations
 */

require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_dashboard_data':
            $period = $_POST['period'] ?? '30days';
            $result = getDashboardData($period);
            echo json_encode($result);
            exit;
            
        case 'get_predictions':
            $type = $_POST['type'] ?? 'workload';
            $result = getPredictions($type);
            echo json_encode($result);
            exit;
            
        case 'export_analytics':
            $format = $_POST['format'] ?? 'excel';
            $data = $_POST['data'] ?? [];
            $result = exportAnalytics($format, $data);
            echo json_encode($result);
            exit;
            
        case 'save_dashboard_config':
            $config = $_POST['config'] ?? '{}';
            $result = saveDashboardConfig($_SESSION['user_id'], $config);
            echo json_encode($result);
            exit;
    }
}

/**
 * Obtenir les données du tableau de bord
 */
function getDashboardData($period) {
    global $pdo;
    
    $dateFilter = getDateFilter($period);
    
    try {
        $data = [];
        
        // 1. KPI Principaux
        $data['kpis'] = [
            'total_dossiers' => getTotalDossiers($dateFilter),
            'dossiers_en_cours' => getDossiersEnCours($dateFilter),
            'dossiers_termines' => getDossiersTermines($dateFilter),
            'delai_moyen' => getDelaiMoyen($dateFilter),
            'taux_respect_delais' => getTauxRespectDelais($dateFilter),
            'satisfaction_client' => getSatisfactionClient($dateFilter)
        ];
        
        // 2. Données temporelles
        $data['temporal'] = [
            'dossiers_par_jour' => getDossiersParJour($dateFilter),
            'tendance_delais' => getTendanceDelais($dateFilter),
            'charge_travail' => getChargeTravail($dateFilter)
        ];
        
        // 3. Analyses par catégorie
        $data['categories'] = [
            'par_type' => getDossiersParType($dateFilter),
            'par_service' => getDossiersParService($dateFilter),
            'par_priorite' => getDossiersParPriorite($dateFilter),
            'par_responsable' => getDossiersParResponsable($dateFilter)
        ];
        
        // 4. Performance des workflows
        $data['workflows'] = [
            'etapes_lentes' => getEtapesLentes($dateFilter),
            'goulots_etranglement' => getGoulotsEtranglement($dateFilter),
            'taux_approbation' => getTauxApprobation($dateFilter)
        ];
        
        // 5. Alertes et recommandations
        $data['alerts'] = getActiveAlerts();
        $data['recommendations'] = getRecommendations();
        
        return ['success' => true, 'data' => $data];
        
    } catch (Exception $e) {
        logError("Erreur tableau de bord: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors du chargement des données'];
    }
}

/**
 * Obtenir des prédictions
 */
function getPredictions($type) {
    global $pdo;
    
    try {
        $predictions = [];
        
        switch ($type) {
            case 'workload':
                $predictions = predictWorkload();
                break;
            case 'delays':
                $predictions = predictDelays();
                break;
            case 'resources':
                $predictions = predictResourceNeeds();
                break;
            case 'trends':
                $predictions = predictTrends();
                break;
        }
        
        return ['success' => true, 'predictions' => $predictions];
        
    } catch (Exception $e) {
        logError("Erreur prédictions: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la génération des prédictions'];
    }
}

/**
 * Prédire la charge de travail
 */
function predictWorkload() {
    global $pdo;
    
    // Analyse des tendances historiques
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            DAYOFWEEK(created_at) as day_of_week,
            WEEK(created_at) as week_num
        FROM dossiers 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    $historical = $stmt->fetchAll();
    
    // Calcul des moyennes par jour de la semaine
    $dayAverages = array_fill(1, 7, 0);
    $dayCounts = array_fill(1, 7, 0);
    
    foreach ($historical as $day) {
        $dayAverages[$day['day_of_week']] += $day['count'];
        $dayCounts[$day['day_of_week']]++;
    }
    
    for ($i = 1; $i <= 7; $i++) {
        if ($dayCounts[$i] > 0) {
            $dayAverages[$i] = round($dayAverages[$i] / $dayCounts[$i], 2);
        }
    }
    
    // Prédiction pour les 7 prochains jours
    $predictions = [];
    for ($i = 1; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $dayOfWeek = date('N', strtotime($date));
        
        $predicted = $dayAverages[$dayOfWeek];
        
        // Ajustement saisonnier
        $seasonal = getSeasonalAdjustment(date('n'), date('j'));
        $predicted = round($predicted * $seasonal, 0);
        
        $predictions[] = [
            'date' => $date,
            'predicted_count' => max(0, $predicted),
            'confidence' => calculateConfidence($dayOfWeek, $dayCounts[$dayOfWeek])
        ];
    }
    
    return $predictions;
}

/**
 * Prédire les retards
 */
function predictDelays() {
    global $pdo;
    
    try {
        // Vérifier si les tables et colonnes existent
        $startedAtExists = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'started_at'")->rowCount() > 0;
        $workflowDeadlinesExists = $pdo->query("SHOW TABLES LIKE 'workflow_deadlines'")->rowCount() > 0;
        
        if ($startedAtExists && $workflowDeadlinesExists) {
            // Analyser les dossiers en cours avec workflow complet
            $stmt = $pdo->query("
                SELECT 
                    d.*,
                    COALESCE(wi.started_at, wi.created_at) as started_at,
                    COALESCE(wd.deadline_hours, 72) as deadline_hours,
                    TIMESTAMPDIFF(HOUR, COALESCE(wi.started_at, wi.created_at), NOW()) as elapsed_hours,
                    (COALESCE(wd.deadline_hours, 72) - TIMESTAMPDIFF(HOUR, COALESCE(wi.started_at, wi.created_at), NOW())) as remaining_hours
                FROM dossiers d
                LEFT JOIN workflow_instances wi ON d.id = wi.dossier_id
                LEFT JOIN workflow_deadlines wd ON wi.workflow_step_id = wd.workflow_step_id
                WHERE d.status IN ('en_cours', 'en_attente')
                AND (wi.status IN ('active', 'pending') OR wi.status IS NULL)
            ");
        } else {
            // Version simplifiée sans workflow
            $stmt = $pdo->query("
                SELECT 
                    d.*,
                    d.created_at as started_at,
                    72 as deadline_hours,
                    TIMESTAMPDIFF(HOUR, d.created_at, NOW()) as elapsed_hours,
                    (72 - TIMESTAMPDIFF(HOUR, d.created_at, NOW())) as remaining_hours
                FROM dossiers d
                WHERE d.status IN ('en_cours', 'en_attente')
            ");
        }
        
        $dossiers = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur dans predictDelays: " . $e->getMessage());
        $dossiers = [];
    }
    $riskyDossiers = [];
    
    foreach ($dossiers as $dossier) {
        $riskScore = calculateDelayRisk($dossier);
        
        if ($riskScore > 0.5) { // Seuil de risque
            $riskyDossiers[] = [
                'dossier_id' => $dossier['id'],
                'reference' => $dossier['reference'],
                'risk_score' => $riskScore,
                'remaining_hours' => $dossier['remaining_hours'],
                'predicted_delay' => predictSpecificDelay($dossier)
            ];
        }
    }
    
    return $riskyDossiers;
}

/**
 * Calculer le risque de retard
 */
function calculateDelayRisk($dossier) {
    $factors = [];
    
    // Facteur temps
    $timeRatio = $dossier['elapsed_hours'] / $dossier['deadline_hours'];
    $factors['time'] = min(1, $timeRatio);
    
    // Facteur complexité (basé sur le type et le service)
    $factors['complexity'] = getComplexityFactor($dossier['type'], $dossier['service']);
    
    // Facteur charge de travail actuelle
    $factors['workload'] = getCurrentWorkloadFactor($dossier['created_by']);
    
    // Facteur historique (performance passée du responsable)
    $factors['history'] = getHistoricalPerformance($dossier['created_by']);
    
    // Calcul pondéré
    $weights = ['time' => 0.4, 'complexity' => 0.2, 'workload' => 0.2, 'history' => 0.2];
    $riskScore = 0;
    
    foreach ($factors as $factor => $value) {
        $riskScore += $value * $weights[$factor];
    }
    
    return min(1, max(0, $riskScore));
}

/**
 * Obtenir les KPI principaux
 */
function getTotalDossiers($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE created_at $dateFilter");
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

function getDossiersEnCours($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM dossiers 
        WHERE status IN ('en_cours', 'en_attente') 
        AND created_at $dateFilter
    ");
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

function getDossiersTermines($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM dossiers 
        WHERE status IN ('approuve', 'termine', 'archive') 
        AND created_at $dateFilter
    ");
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

function getDelaiMoyen($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(DAY, created_at, 
            COALESCE(workflow_completed_at, NOW()))) as avg_delay
        FROM dossiers 
        WHERE created_at $dateFilter
    ");
    $stmt->execute();
    
    return round($stmt->fetchColumn(), 1);
}

function getTauxRespectDelais($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN TIMESTAMPDIFF(DAY, created_at, workflow_completed_at) <= 7 THEN 1 END) * 100.0 / COUNT(*) as rate
        FROM dossiers 
        WHERE workflow_completed = 1 
        AND created_at $dateFilter
    ");
    $stmt->execute();
    
    return round($stmt->fetchColumn(), 1);
}

/**
 * Obtenir des analyses temporelles
 */
function getDossiersParJour($dateFilter) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM dossiers 
        WHERE created_at $dateFilter
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Générer des recommandations intelligentes
 */
function getRecommendations() {
    global $pdo;
    
    $recommendations = [];
    
    // 1. Recommandations sur les goulots d'étranglement
    $bottlenecks = getGoulotsEtranglement("AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    foreach ($bottlenecks as $bottleneck) {
        if ($bottleneck['avg_duration'] > 2) { // Plus de 2 jours
            $recommendations[] = [
                'type' => 'bottleneck',
                'priority' => 'high',
                'title' => 'Goulot d\'étranglement détecté',
                'message' => "L'étape '{$bottleneck['step_name']}' prend en moyenne {$bottleneck['avg_duration']} jours",
                'action' => 'Considérer l\'ajout de ressources ou la simplification du processus',
                'impact' => 'Réduction potentielle de 20-30% des délais'
            ];
        }
    }
    
    // 2. Recommandations sur la charge de travail
    $workloadPrediction = predictWorkload();
    $avgWorkload = array_sum(array_column($workloadPrediction, 'predicted_count')) / count($workloadPrediction);
    
    if ($avgWorkload > 50) { // Seuil élevé
        $recommendations[] = [
            'type' => 'workload',
            'priority' => 'medium',
            'title' => 'Charge de travail élevée prévue',
            'message' => "Une charge moyenne de {$avgWorkload} dossiers/jour est prévue",
            'action' => 'Planifier des ressources supplémentaires ou prioriser les tâches',
            'impact' => 'Maintien des délais de traitement'
        ];
    }
    
    // 3. Recommandations d'automatisation
    $autoOpportunities = findAutomationOpportunities();
    foreach ($autoOpportunities as $opportunity) {
        $recommendations[] = [
            'type' => 'automation',
            'priority' => 'medium',
            'title' => 'Opportunité d\'automatisation',
            'message' => $opportunity['description'],
            'action' => $opportunity['action'],
            'impact' => $opportunity['impact']
        ];
    }
    
    return $recommendations;
}

/**
 * Trouver des opportunités d'automatisation
 */
function findAutomationOpportunities() {
    global $pdo;
    
    $opportunities = [];
    
    // Rechercher les étapes répétitives avec peu de rejets
    $stmt = $pdo->query("
        SELECT 
            w.nom as step_name,
            COUNT(*) as total_instances,
            COUNT(CASE WHEN wi.status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN wi.status = 'rejected' THEN 1 END) as rejected,
            (COUNT(CASE WHEN wi.status = 'approved' THEN 1 END) * 100.0 / COUNT(*)) as approval_rate
        FROM workflow_instances wi
        JOIN workflows w ON wi.workflow_step_id = w.id
        WHERE wi.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY w.id
        HAVING total_instances > 20 AND approval_rate > 90
    ");
    
    $steps = $stmt->fetchAll();
    
    foreach ($steps as $step) {
        $opportunities[] = [
            'description' => "L'étape '{$step['step_name']}' a un taux d'approbation de {$step['approval_rate']}%",
            'action' => 'Considérer l\'approbation automatique avec conditions',
            'impact' => 'Réduction de 60-80% du temps de traitement pour cette étape'
        ];
    }
    
    return $opportunities;
}

/**
 * Utilitaires
 */
function getDateFilter($period) {
    switch ($period) {
        case '7days': return ">= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case '30days': return ">= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        case '90days': return ">= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        case '6months': return ">= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        case '1year': return ">= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        default: return ">= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

function getSeasonalAdjustment($month, $day) {
    // Ajustements saisonniers basiques
    if ($month == 12 && $day > 20) return 0.5; // Période de fin d'année
    if ($month == 1 && $day < 10) return 0.7; // Début d'année
    if ($month >= 6 && $month <= 8) return 0.8; // Période estivale
    return 1.0; // Normal
}

function calculateConfidence($dayOfWeek, $sampleSize) {
    $baseConfidence = min(0.9, $sampleSize / 10); // Max 90% de confiance
    
    // Les jours de semaine sont plus prévisibles
    if ($dayOfWeek >= 2 && $dayOfWeek <= 6) {
        $baseConfidence *= 1.1;
    }
    
    return min(1.0, $baseConfidence);
}

// Interface utilisateur
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Intelligence - MINSANTE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .recommendation-card {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .prediction-high { color: #dc3545; }
        .prediction-medium { color: #ffc107; }
        .prediction-low { color: #28a745; }
        .analytics-chart {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line"></i> Analytics & Intelligence d'Affaires</h2>
                    <div class="btn-group">
                        <select id="periodSelect" class="form-select">
                            <option value="7days">7 derniers jours</option>
                            <option value="30days" selected>30 derniers jours</option>
                            <option value="90days">90 derniers jours</option>
                            <option value="6months">6 derniers mois</option>
                            <option value="1year">1 an</option>
                        </select>
                        <button class="btn btn-primary" onclick="refreshDashboard()">
                            <i class="fas fa-sync"></i> Actualiser
                        </button>
                        <button class="btn btn-success" onclick="exportAnalytics()">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="row mb-4" id="kpiCards">
                    <!-- Sera rempli par JavaScript -->
                </div>

                <!-- Graphiques principaux -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="analytics-chart">
                            <h5><i class="fas fa-chart-area"></i> Évolution des Dossiers</h5>
                            <canvas id="dossiersChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="analytics-chart">
                            <h5><i class="fas fa-chart-pie"></i> Répartition par Type</h5>
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Prédictions et recommandations -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-crystal-ball"></i> Prédictions de Charge</h5>
                            </div>
                            <div class="card-body" id="predictionsContainer">
                                <!-- Sera rempli par JavaScript -->
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-lightbulb"></i> Recommandations Intelligentes</h5>
                            </div>
                            <div class="card-body" id="recommendationsContainer">
                                <!-- Sera rempli par JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analyses détaillées -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="analytics-chart">
                            <h5><i class="fas fa-users"></i> Performance par Responsable</h5>
                            <canvas id="responsableChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="analytics-chart">
                            <h5><i class="fas fa-clock"></i> Analyse des Délais</h5>
                            <canvas id="delaiChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Alertes -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5><i class="fas fa-exclamation-triangle"></i> Alertes Actives</h5>
                            </div>
                            <div class="card-body" id="alertsContainer">
                                <!-- Sera rempli par JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let dashboardData = {};
        let charts = {};

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            refreshDashboard();
            
            // Actualisation automatique toutes les 5 minutes
            setInterval(refreshDashboard, 5 * 60 * 1000);
        });

        // Actualiser le tableau de bord
        async function refreshDashboard() {
            const period = document.getElementById('periodSelect').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_dashboard_data');
                formData.append('period', period);
                
                const response = await fetch('analytics.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    dashboardData = result.data;
                    updateKPIs();
                    updateCharts();
                    updatePredictions();
                    updateRecommendations();
                    updateAlerts();
                } else {
                    console.error('Erreur:', result.message);
                }
            } catch (error) {
                console.error('Erreur chargement:', error);
            }
        }

        // Mettre à jour les KPI
        function updateKPIs() {
            const kpis = dashboardData.kpis;
            const container = document.getElementById('kpiCards');
            
            const kpiConfigs = [
                { key: 'total_dossiers', label: 'Total Dossiers', icon: 'fas fa-folder', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
                { key: 'dossiers_en_cours', label: 'En Cours', icon: 'fas fa-clock', color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
                { key: 'dossiers_termines', label: 'Terminés', icon: 'fas fa-check-circle', color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
                { key: 'delai_moyen', label: 'Délai Moyen (jours)', icon: 'fas fa-calendar', color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
                { key: 'taux_respect_delais', label: 'Respect Délais (%)', icon: 'fas fa-target', color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
                { key: 'satisfaction_client', label: 'Satisfaction (%)', icon: 'fas fa-heart', color: 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)' }
            ];
            
            let html = '';
            kpiConfigs.forEach(config => {
                const value = kpis[config.key] || 0;
                html += `
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="kpi-card" style="background: ${config.color}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="kpi-value">${value}</div>
                                    <div class="kpi-label">${config.label}</div>
                                </div>
                                <div style="font-size: 2rem; opacity: 0.7;">
                                    <i class="${config.icon}"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Mettre à jour les graphiques
        function updateCharts() {
            updateDossiersChart();
            updateTypeChart();
            updateResponsableChart();
            updateDelaiChart();
        }

        function updateDossiersChart() {
            const ctx = document.getElementById('dossiersChart').getContext('2d');
            const data = dashboardData.temporal.dossiers_par_jour || [];
            
            if (charts.dossiers) {
                charts.dossiers.destroy();
            }
            
            charts.dossiers = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [{
                        label: 'Nouveaux Dossiers',
                        data: data.map(d => d.count),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateTypeChart() {
            const ctx = document.getElementById('typeChart').getContext('2d');
            const data = dashboardData.categories.par_type || [];
            
            if (charts.type) {
                charts.type.destroy();
            }
            
            charts.type = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.type),
                    datasets: [{
                        data: data.map(d => d.count),
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Mettre à jour les prédictions
        async function updatePredictions() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_predictions');
                formData.append('type', 'workload');
                
                const response = await fetch('analytics.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const container = document.getElementById('predictionsContainer');
                    let html = '<div class="table-responsive"><table class="table table-sm">';
                    html += '<thead><tr><th>Date</th><th>Charge Prévue</th><th>Confiance</th></tr></thead><tbody>';
                    
                    result.predictions.forEach(pred => {
                        const confidenceClass = pred.confidence > 0.8 ? 'prediction-low' : 
                                              pred.confidence > 0.6 ? 'prediction-medium' : 'prediction-high';
                        
                        html += `
                            <tr>
                                <td>${pred.date}</td>
                                <td><strong>${pred.predicted_count}</strong> dossiers</td>
                                <td><span class="${confidenceClass}">${Math.round(pred.confidence * 100)}%</span></td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                }
            } catch (error) {
                console.error('Erreur prédictions:', error);
            }
        }

        // Mettre à jour les recommandations
        function updateRecommendations() {
            const recommendations = dashboardData.recommendations || [];
            const container = document.getElementById('recommendationsContainer');
            
            if (recommendations.length === 0) {
                container.innerHTML = '<p class="text-muted">Aucune recommandation pour le moment</p>';
                return;
            }
            
            let html = '';
            recommendations.forEach(rec => {
                const priorityClass = rec.priority === 'high' ? 'border-danger' : 
                                    rec.priority === 'medium' ? 'border-warning' : 'border-info';
                
                html += `
                    <div class="recommendation-card card mb-3 ${priorityClass}">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-lightbulb text-warning"></i>
                                ${rec.title}
                            </h6>
                            <p class="card-text">${rec.message}</p>
                            <p class="card-text"><strong>Action:</strong> ${rec.action}</p>
                            <p class="card-text"><small class="text-success">Impact: ${rec.impact}</small></p>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Mettre à jour les alertes
        function updateAlerts() {
            const alerts = dashboardData.alerts || [];
            const container = document.getElementById('alertsContainer');
            
            if (alerts.length === 0) {
                container.innerHTML = '<p class="text-success"><i class="fas fa-check"></i> Aucune alerte active</p>';
                return;
            }
            
            let html = '';
            alerts.forEach(alert => {
                html += `
                    <div class="alert alert-warning mb-2">
                        <strong>${alert.title}</strong> - ${alert.message}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Exporter les analytics
        async function exportAnalytics() {
            try {
                const formData = new FormData();
                formData.append('action', 'export_analytics');
                formData.append('format', 'excel');
                formData.append('data', JSON.stringify(dashboardData));
                
                const response = await fetch('analytics.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.open(result.download_url);
                } else {
                    alert('Erreur lors de l\'export: ' + result.message);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }

        // Changement de période
        document.getElementById('periodSelect').addEventListener('change', refreshDashboard);
    </script>
</body>
</html>
