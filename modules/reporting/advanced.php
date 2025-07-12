<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();
requirePermission(ROLE_ADMIN);

// Filtres
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$service = $_GET['service'] ?? null;

// Requêtes analytiques avec gestion d'erreurs
$stats = [
    'creation_trend' => [],
    'by_service' => [],
    'processing_time' => ['avg_hours' => 0, 'min_hours' => 0, 'max_hours' => 0]
];

try {
    $stats['creation_trend'] = fetchAll("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM dossiers 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$dateFrom, $dateTo]);
    
    $stats['by_service'] = fetchAll("
        SELECT service, COUNT(*) as total,
               SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
               SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
               SUM(CASE WHEN status = 'rejete' THEN 1 ELSE 0 END) as rejetes
        FROM dossiers
        WHERE created_at BETWEEN ? AND ?
        GROUP BY service
    ", [$dateFrom, $dateTo]);
    
    $processingResult = fetchOne("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours,
               MIN(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as min_hours,
               MAX(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as max_hours
        FROM dossiers
        WHERE status = 'valide' AND created_at BETWEEN ? AND ? AND updated_at IS NOT NULL
    ", [$dateFrom, $dateTo]);
    
    if ($processingResult) {
        $stats['processing_time'] = $processingResult;
    }
    
} catch (Exception $e) {
    error_log("Erreur dans advanced.php: " . $e->getMessage());
    // Données par défaut si erreur
    $stats['creation_trend'] = [
        ['date' => date('Y-m-d'), 'count' => 0]
    ];
    $stats['by_service'] = [
        ['service' => 'Aucune donnée', 'total' => 0, 'en_cours' => 0, 'valides' => 0, 'rejetes' => 0]
    ];
}

// Debug pour vérifier les données (à supprimer en production)
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Date From: $dateFrom\n";
    echo "Date To: $dateTo\n";
    echo "Creation Trend: " . print_r($stats['creation_trend'], true) . "\n";
    echo "By Service: " . print_r($stats['by_service'], true) . "\n";
    echo "Processing Time: " . print_r($stats['processing_time'], true) . "\n";
    echo "</pre>";
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.analytics-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    color: white;
    text-align: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.page-header h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.page-header p {
    margin: 8px 0 0 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.filters-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #f0f4f8;
}

.filters-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    color: #2d3748;
    font-size: 1.2rem;
    font-weight: 600;
}

.filters-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #4a5568;
    font-size: 0.9rem;
}

.form-control {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: #fff;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-apply {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-apply:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.chart-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #f0f4f8;
    transition: all 0.3s ease;
}

.chart-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.chart-header {
    margin-bottom: 20px;
}

.chart-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-subtitle {
    color: #718096;
    font-size: 0.9rem;
    margin-top: 4px;
}

.chart-body {
    position: relative;
}

.processing-stats {
    text-align: center;
    padding: 20px 0;
}

.main-stat {
    margin-bottom: 24px;
}

.main-stat .stat-value {
    font-size: 3rem;
    font-weight: 700;
    color: #667eea;
    line-height: 1;
}

.main-stat .stat-label {
    font-size: 1.1rem;
    color: #4a5568;
    margin-top: 8px;
}

.stat-row {
    display: flex;
    justify-content: space-around;
    gap: 20px;
}

.stat-item .stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
}

.stat-item .stat-label {
    font-size: 0.9rem;
    color: #718096;
    margin-top: 4px;
}

.data-table-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #f0f4f8;
}

.table-header {
    margin-bottom: 24px;
}

.table-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-subtitle {
    color: #718096;
    font-size: 0.9rem;
    margin-top: 4px;
}

.table-responsive {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
}

.modern-table thead th {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    color: #2d3748;
    font-weight: 600;
    padding: 16px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.9rem;
}

.modern-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f7fafc;
}

.modern-table tbody tr:hover {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    transform: translateX(4px);
}

.modern-table td {
    padding: 16px;
    vertical-align: middle;
}

.service-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.stat-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
}

.stat-badge.total {
    background: #e6fffa;
    color: #234e52;
}

.stat-badge.en-cours {
    background: #e3f2fd;
    color: #0d47a1;
}

.stat-badge.valide {
    background: #e8f5e8;
    color: #2e7d32;
}

.stat-badge.rejete {
    background: #ffebee;
    color: #c62828;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    border-radius: 10px;
    transition: width 0.5s ease;
}

.progress-text {
    font-weight: 600;
    color: #2d3748;
    min-width: 40px;
}

@media (max-width: 768px) {
    .analytics-container {
        padding: 16px;
    }
    
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr !important;
    }
    
    .stat-row {
        flex-direction: column;
        gap: 16px;
    }
    
    .modern-table {
        font-size: 0.9rem;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 12px 8px;
    }
}
</style>

<div class="analytics-container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Reporting Avancé</h1>
        <p>Analyses détaillées et tendances des dossiers</p>
    </div>
    
    <!-- Filtres -->
    <div class="filters-card">
        <div class="filters-header">
            <i class="fas fa-filter"></i>
            Filtres d'analyse
        </div>
        <form method="get" class="filters-form">
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date de début</label>
                <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date de fin</label>
                <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control">
            </div>
            <div class="form-group">
                <label><i class="fas fa-building"></i> Service</label>
                <select name="service" class="form-control">
                    <option value="">Tous les services</option>
                    <?php foreach (['DEP', 'Finance', 'RH', 'Logistique'] as $s): ?>
                        <option value="<?= $s ?>" <?= $service === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-search"></i> Appliquer les filtres
                </button>
            </div>
        </form>
    </div>

    <!-- Graphiques -->
    <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Tendance des créations
                </div>
                <div class="chart-subtitle">Évolution quotidienne</div>
            </div>
            <div class="chart-body">
                <?php if (!empty($stats['creation_trend'])): ?>
                    <canvas id="creationChart" height="200"></canvas>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#718096;">
                        <i class="fas fa-chart-line" style="font-size:48px; margin-bottom:16px; opacity:0.5;"></i>
                        <p style="margin:0; font-size:16px;">Aucune donnée disponible pour cette période</p>
                        <p style="margin:8px 0 0 0; font-size:14px; opacity:0.7;">Essayez de modifier les filtres ou la période</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-clock"></i>
                    Temps de traitement
                </div>
                <div class="chart-subtitle">Performances moyennes</div>
            </div>
            <div class="chart-body">
                <div class="processing-stats">
                    <div class="stat-item main-stat">
                        <div class="stat-value"><?= isset($stats['processing_time']['avg_hours']) && $stats['processing_time']['avg_hours'] ? round($stats['processing_time']['avg_hours']/24, 1) : '0' ?></div>
                        <div class="stat-label">jours en moyenne</div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-item">
                            <div class="stat-value"><?= isset($stats['processing_time']['min_hours']) && $stats['processing_time']['min_hours'] ? round($stats['processing_time']['min_hours']/24, 1) : '0' ?></div>
                            <div class="stat-label">minimum</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= isset($stats['processing_time']['max_hours']) && $stats['processing_time']['max_hours'] ? round($stats['processing_time']['max_hours']/24, 1) : '0' ?></div>
                            <div class="stat-label">maximum</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau détaillé -->
    <div class="data-table-card">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-table"></i>
                Statistiques par service
            </div>
            <div class="table-subtitle">Répartition détaillée des dossiers</div>
        </div>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-building"></i> Service</th>
                        <th><i class="fas fa-folder"></i> Total</th>
                        <th><i class="fas fa-clock"></i> En cours</th>
                        <th><i class="fas fa-check-circle"></i> Validés</th>
                        <th><i class="fas fa-times-circle"></i> Rejetés</th>
                        <th><i class="fas fa-percentage"></i> Taux validation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['by_service'] as $row): ?>
                    <tr>
                        <td class="service-cell">
                            <span class="service-badge"><?= $row['service'] ?></span>
                        </td>
                        <td><span class="stat-badge total"><?= $row['total'] ?></span></td>
                        <td><span class="stat-badge en-cours"><?= $row['en_cours'] ?></span></td>
                        <td><span class="stat-badge valide"><?= $row['valides'] ?></span></td>
                        <td><span class="stat-badge rejete"><?= $row['rejetes'] ?></span></td>
                        <td>
                            <div class="progress-container">
                                <?php $rate = $row['total'] > 0 ? round(($row['valides']/$row['total'])*100) : 0; ?>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $rate ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Vérifier que Chart.js est chargé
console.log('Chart.js version:', Chart.version);

// Données pour le graphique
const chartData = {
    labels: [<?php 
        if (!empty($stats['creation_trend'])) {
            echo implode(',', array_map(function($d) { 
                return "'" . date('d/m', strtotime($d['date'])) . "'"; 
            }, $stats['creation_trend']));
        } else {
            echo "'Aucune donnée'";
        }
    ?>],
    data: [<?php 
        if (!empty($stats['creation_trend'])) {
            echo implode(',', array_column($stats['creation_trend'], 'count'));
        } else {
            echo '0';
        }
    ?>]
};

console.log('Données du graphique:', chartData);

// Graphique des créations avec style moderne
const canvas = document.getElementById('creationChart');
if (canvas) {
    const ctx = canvas.getContext('2d');
    
    // Gradient pour la ligne
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
    gradient.addColorStop(1, 'rgba(102, 126, 234, 0.0)');
    
    try {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Dossiers créés',
                    data: chartData.data,
                    borderColor: '#667eea',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#5a67d8',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { 
                        display: false 
                    },
                    tooltip: {
                        backgroundColor: 'rgba(45, 55, 72, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return 'Date: ' + context[0].label;
                            },
                            label: function(context) {
                                return context.parsed.y + ' dossier(s) créé(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#718096',
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(226, 232, 240, 0.5)',
                            borderDash: [2, 2]
                        },
                        ticks: {
                            color: '#718096',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            beginAtZero: true
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });
        console.log('Graphique créé avec succès');
    } catch (error) {
        console.error('Erreur lors de la création du graphique:', error);
        // Afficher un message d'erreur dans le canvas
        const errorDiv = document.createElement('div');
        errorDiv.innerHTML = '<p style="text-align:center;color:#e74c3c;padding:20px;">Erreur lors du chargement du graphique</p>';
        canvas.parentNode.replaceChild(errorDiv, canvas);
    }
} else {
    console.error('Canvas creationChart non trouvé');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>