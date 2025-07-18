<?php
// Configuration pour la gestion d'erreur
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../../includes/config.php';
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    
    // Vérifier l'authentification
    requireAuth();
    
    // Vérifier que la base de données est disponible
    global $pdo;
    if (!$pdo) {
        throw new Exception("Connexion à la base de données non disponible");
    }
    
    // Vérifier que les tables existent
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'dossiers'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        throw new Exception("Table 'dossiers' non trouvée. Veuillez exécuter setup_database.php d'abord.");
    }
    
    require_once 'analytics_manager.php';
    $analytics = new AnalyticsManager();
    
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Erreur Analytics</title></head><body>";
    echo "<h1>Erreur du module Analytics</h1>";
    echo "<p>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='setup_database.php'>Configurer la base de données</a></p>";
    echo "</body></html>";
    exit;
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_timeseries':
            $metric = $_POST['metric'] ?? 'created';
            $period = $_POST['period'] ?? '30d';
            $groupBy = $_POST['groupBy'] ?? 'day';
            echo json_encode($analytics->getTimeSeriesData($metric, $period, $groupBy));
            break;
            
        case 'get_realtime':
            echo json_encode($analytics->getRealtimeData());
            break;
            
        case 'export_report':
            $filters = json_decode($_POST['filters'] ?? '{}', true);
            $report = $analytics->generateCustomReport($filters);
            $analytics->exportToCsv($report['dossiers'], 'rapport_' . date('Y-m-d') . '.csv');
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
    exit;
}

// Données initiales
$kpis = $analytics->getMainKPIs();
$statusData = $analytics->getStatusDistribution();
$priorityData = $analytics->getPriorityDistribution();
$userPerformance = $analytics->getUserPerformance();
$servicePerformance = $analytics->getServicePerformance();
$deadlineAnalysis = $analytics->getDeadlineAnalysis();
$monthlyData = $analytics->getMonthlyPerformance();

include '../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.0/index.min.js"></script>

<style>
.analytics-container {
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
}

.analytics-header h1 {
    margin: 0;
    font-size: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.analytics-header p {
    margin: 1rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-left: 5px solid #667eea;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-radius: 50%;
    transform: translate(30px, -30px);
}

.kpi-value {
    font-size: 3rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
    position: relative;
    z-index: 1;
}

.kpi-label {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin: 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.kpi-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    margin-top: 1rem;
}

.trend-positive {
    color: #27ae60;
}

.trend-negative {
    color: #e74c3c;
}

.trend-neutral {
    color: #95a5a6;
}

.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.chart-card h3 {
    margin: 0 0 1.5rem 0;
    color: #2c3e50;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chart-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.chart-controls select,
.chart-controls button {
    padding: 0.5rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    background: white;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
}

.chart-controls select:focus,
.chart-controls button:hover {
    border-color: #667eea;
    outline: none;
}

.chart-controls button.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.chart-container {
    position: relative;
    height: 400px;
    margin-top: 1rem;
}

.chart-small {
    height: 300px;
}

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.table-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.performance-table th,
.performance-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.performance-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.performance-table tr:hover {
    background: #f8f9fa;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-en_cours {
    background: #3498db;
    color: white;
}

.status-valide {
    background: #27ae60;
    color: white;
}

.status-rejete {
    background: #e74c3c;
    color: white;
}

.status-archive {
    background: #95a5a6;
    color: white;
}

.priority-urgent {
    color: #e74c3c;
    font-weight: 700;
}

.priority-high {
    color: #f39c12;
    font-weight: 600;
}

.priority-medium {
    color: #3498db;
}

.priority-low {
    color: #95a5a6;
}

.filters-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.filter-group input,
.filter-group select {
    padding: 0.75rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: border-color 0.3s;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #667eea;
    outline: none;
}

.export-section {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.btn-export {
    background: #27ae60;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-export:hover {
    background: #219a52;
    transform: translateY(-2px);
}

.realtime-indicator {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    z-index: 1000;
    transition: all 0.3s;
}

.realtime-indicator.updating {
    background: #667eea;
    color: white;
}

.pulse {
    width: 12px;
    height: 12px;
    background: #27ae60;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7);
    }
    
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 10px rgba(39, 174, 96, 0);
    }
    
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(39, 174, 96, 0);
    }
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .tables-grid {
        grid-template-columns: 1fr;
    }
    
    .analytics-header h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .realtime-indicator {
        position: relative;
        top: auto;
        right: auto;
        margin-bottom: 1rem;
    }
}
</style>

<div class="analytics-container">
    <!-- Indicateur temps réel -->
    <div class="realtime-indicator" id="realtimeIndicator">
        <div class="pulse"></div>
        <span>Données temps réel</span>
        <small id="lastUpdate">Maintenant</small>
    </div>

    <!-- En-tête -->
    <div class="analytics-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            Analytics Avancées
        </h1>
        <p>Tableau de bord analytique avec KPI et graphiques temps réel</p>
    </div>

    <!-- KPI Principal -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value"><?= number_format($kpis['total_dossiers']) ?></div>
            <div class="kpi-label">Total Dossiers</div>
            <div class="kpi-trend trend-<?= $kpis['tendances']['dossiers'] >= 0 ? 'positive' : 'negative' ?>">
                <i class="fas fa-arrow-<?= $kpis['tendances']['dossiers'] >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($kpis['tendances']['dossiers']) ?>% vs période précédente
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-value"><?= $kpis['taux_validation'] ?>%</div>
            <div class="kpi-label">Taux de Validation</div>
            <div class="kpi-trend trend-<?= $kpis['tendances']['validations'] >= 0 ? 'positive' : 'negative' ?>">
                <i class="fas fa-arrow-<?= $kpis['tendances']['validations'] >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($kpis['tendances']['validations']) ?>% vs période précédente
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-value"><?= number_format($kpis['temps_moyen_traitement'], 1) ?></div>
            <div class="kpi-label">Jours Moyens Traitement</div>
            <div class="kpi-trend trend-neutral">
                <i class="fas fa-clock"></i>
                Temps de traitement moyen
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-value"><?= $kpis['dossiers_urgent'] ?></div>
            <div class="kpi-label">Dossiers Urgents</div>
            <div class="kpi-trend trend-<?= $kpis['dossiers_urgent'] > 0 ? 'negative' : 'positive' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $kpis['dossiers_urgent'] > 0 ? 'Attention requise' : 'Situation normale' ?>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-value"><?= $kpis['dossiers_retard'] ?></div>
            <div class="kpi-label">Dossiers en Retard</div>
            <div class="kpi-trend trend-<?= $kpis['dossiers_retard'] > 0 ? 'negative' : 'positive' ?>">
                <i class="fas fa-calendar-times"></i>
                <?= $kpis['taux_retard'] ?>% du total
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-value"><?= $kpis['utilisateurs_actifs'] ?></div>
            <div class="kpi-label">Utilisateurs Actifs</div>
            <div class="kpi-trend trend-positive">
                <i class="fas fa-users"></i>
                Avec dossiers assignés
            </div>
        </div>
    </div>

    <!-- Graphiques principaux -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>
                <i class="fas fa-chart-area"></i>
                Évolution Temporelle
            </h3>
            <div class="chart-controls">
                <select id="timeMetric">
                    <option value="created">Dossiers créés</option>
                    <option value="updated">Dossiers mis à jour</option>
                    <option value="validated">Dossiers validés</option>
                </select>
                <select id="timePeriod">
                    <option value="7d">7 derniers jours</option>
                    <option value="30d" selected>30 derniers jours</option>
                    <option value="90d">90 derniers jours</option>
                    <option value="365d">1 année</option>
                </select>
                <button id="refreshChart" class="btn-chart">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
            </div>
            <div class="chart-container">
                <canvas id="timeSeriesChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <h3>
                <i class="fas fa-chart-pie"></i>
                Répartition par Statut
            </h3>
            <div class="chart-container chart-small">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphiques secondaires -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>
                <i class="fas fa-chart-bar"></i>
                Performance Mensuelle
            </h3>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <h3>
                <i class="fas fa-exclamation-circle"></i>
                Répartition par Priorité
            </h3>
            <div class="chart-container chart-small">
                <canvas id="priorityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tableaux de performance -->
    <div class="tables-grid">
        <div class="table-card">
            <h3>
                <i class="fas fa-user-chart"></i>
                Performance par Utilisateur
            </h3>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Total</th>
                        <th>Validés</th>
                        <th>Taux</th>
                        <th>Temps Moyen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userPerformance as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <div style="font-size: 0.8em; color: #666;">
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                        </td>
                        <td><?= $user['total_dossiers'] ?></td>
                        <td><?= $user['dossiers_valides'] ?></td>
                        <td>
                            <div><?= $user['taux_validation'] ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $user['taux_validation'] ?>%"></div>
                            </div>
                        </td>
                        <td><?= round($user['temps_moyen'] ?? 0, 1) ?> j</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-card">
            <h3>
                <i class="fas fa-building"></i>
                Performance par Service
            </h3>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Total</th>
                        <th>Taux Validation</th>
                        <th>Temps Moyen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicePerformance as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['service']) ?></td>
                        <td><?= $service['total_dossiers'] ?></td>
                        <td>
                            <div><?= $service['taux_validation'] ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $service['taux_validation'] ?>%"></div>
                            </div>
                        </td>
                        <td><?= round($service['temps_moyen'] ?? 0, 1) ?> j</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analyse des échéances -->
    <div class="chart-card">
        <h3>
            <i class="fas fa-calendar-check"></i>
            Analyse des Échéances
        </h3>
        <div class="kpi-grid" style="margin-bottom: 0;">
            <div class="kpi-card">
                <div class="kpi-value" style="color: #e74c3c;"><?= $deadlineAnalysis['en_retard'] ?></div>
                <div class="kpi-label">En Retard</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #f39c12;"><?= $deadlineAnalysis['echeance_3j'] ?></div>
                <div class="kpi-label">Échéance 3 jours</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #3498db;"><?= $deadlineAnalysis['echeance_7j'] ?></div>
                <div class="kpi-label">Échéance 7 jours</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #27ae60;"><?= round($deadlineAnalysis['marge_moyenne_respectee'] ?? 0, 1) ?></div>
                <div class="kpi-label">Jours d'avance (moyenne)</div>
            </div>
        </div>
    </div>

    <!-- Filtres et export -->
    <div class="filters-section">
        <h3>
            <i class="fas fa-filter"></i>
            Rapport Personnalisé
        </h3>
        <div class="filters-grid">
            <div class="filter-group">
                <label>Date de début</label>
                <input type="date" id="filterDateStart">
            </div>
            <div class="filter-group">
                <label>Date de fin</label>
                <input type="date" id="filterDateEnd">
            </div>
            <div class="filter-group">
                <label>Statut</label>
                <select id="filterStatus" multiple>
                    <option value="en_cours">En cours</option>
                    <option value="valide">Validé</option>
                    <option value="rejete">Rejeté</option>
                    <option value="archive">Archivé</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Priorité</label>
                <select id="filterPriority" multiple>
                    <option value="urgent">Urgent</option>
                    <option value="high">Élevée</option>
                    <option value="medium">Moyenne</option>
                    <option value="low">Faible</option>
                </select>
            </div>
        </div>
        <div class="export-section">
            <button class="btn-export" onclick="exportReport()">
                <i class="fas fa-download"></i>
                Exporter CSV
            </button>
            <button class="btn-export" onclick="refreshAllCharts()" style="background: #667eea;">
                <i class="fas fa-sync-alt"></i>
                Actualiser Tout
            </button>
        </div>
    </div>
</div>

<script>
// Configuration des graphiques
const chartColors = {
    primary: '#667eea',
    secondary: '#764ba2',
    success: '#27ae60',
    warning: '#f39c12',
    danger: '#e74c3c',
    info: '#3498db'
};

let timeSeriesChart, statusChart, priorityChart, monthlyChart;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    startRealtimeUpdates();
    setupEventListeners();
});

function initializeCharts() {
    // Graphique série temporelle
    const timeCtx = document.getElementById('timeSeriesChart').getContext('2d');
    timeSeriesChart = new Chart(timeCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Créés',
                data: [],
                borderColor: chartColors.primary,
                backgroundColor: chartColors.primary + '20',
                tension: 0.4,
                fill: true
            }, {
                label: 'Validés',
                data: [],
                borderColor: chartColors.success,
                backgroundColor: chartColors.success + '20',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Graphique statuts
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($statusData, 'status')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($statusData, 'count')) ?>,
                backgroundColor: [
                    chartColors.info,
                    chartColors.success,
                    chartColors.danger,
                    '#95a5a6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Graphique priorités
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    priorityChart = new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($priorityData, 'priority')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($priorityData, 'count')) ?>,
                backgroundColor: [
                    chartColors.danger,
                    chartColors.warning,
                    chartColors.info,
                    '#95a5a6'
                ]
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
    
    // Graphique mensuel
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(function($m) { return $m['month_name'] . ' ' . $m['year']; }, $monthlyData)) ?>,
            datasets: [{
                label: 'Créés',
                data: <?= json_encode(array_column($monthlyData, 'total_created')) ?>,
                backgroundColor: chartColors.primary + '80'
            }, {
                label: 'Validés',
                data: <?= json_encode(array_column($monthlyData, 'total_validated')) ?>,
                backgroundColor: chartColors.success + '80'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Charger les données initiales pour la série temporelle
    loadTimeSeriesData();
}

function setupEventListeners() {
    document.getElementById('refreshChart').addEventListener('click', loadTimeSeriesData);
    document.getElementById('timeMetric').addEventListener('change', loadTimeSeriesData);
    document.getElementById('timePeriod').addEventListener('change', loadTimeSeriesData);
}

function loadTimeSeriesData() {
    const metric = document.getElementById('timeMetric').value;
    const period = document.getElementById('timePeriod').value;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_timeseries&metric=${metric}&period=${period}&groupBy=day`
    })
    .then(response => response.json())
    .then(data => {
        timeSeriesChart.data.labels = data.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
        });
        
        timeSeriesChart.data.datasets[0].data = data.map(item => item.count);
        timeSeriesChart.data.datasets[1].data = data.map(item => item.validated);
        
        timeSeriesChart.update();
    })
    .catch(error => console.error('Erreur lors du chargement des données:', error));
}

function startRealtimeUpdates() {
    updateRealtimeData();
    setInterval(updateRealtimeData, 30000); // Mise à jour toutes les 30 secondes
}

function updateRealtimeData() {
    const indicator = document.getElementById('realtimeIndicator');
    indicator.classList.add('updating');
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_realtime'
    })
    .then(response => response.json())
    .then(data => {
        // Mettre à jour l'indicateur
        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('fr-FR');
        
        // Ici vous pouvez mettre à jour les KPI en temps réel
        // updateKPIs(data.kpis);
        
        indicator.classList.remove('updating');
    })
    .catch(error => {
        console.error('Erreur mise à jour temps réel:', error);
        indicator.classList.remove('updating');
    });
}

function refreshAllCharts() {
    loadTimeSeriesData();
    // Recharger la page pour mettre à jour tous les graphiques
    location.reload();
}

function exportReport() {
    const filters = {
        date_start: document.getElementById('filterDateStart').value,
        date_end: document.getElementById('filterDateEnd').value,
        status: Array.from(document.getElementById('filterStatus').selectedOptions).map(opt => opt.value),
        priority: Array.from(document.getElementById('filterPriority').selectedOptions).map(opt => opt.value)
    };
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.name = 'action';
    actionInput.value = 'export_report';
    form.appendChild(actionInput);
    
    const filtersInput = document.createElement('input');
    filtersInput.name = 'filters';
    filtersInput.value = JSON.stringify(filters);
    form.appendChild(filtersInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../../includes/footer.php'; ?>
