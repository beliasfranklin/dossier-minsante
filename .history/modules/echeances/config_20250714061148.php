<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireRole(ROLE_GESTIONNAIRE);

// Actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_config':
                $type_dossier = $_POST['type_dossier'];
                $service = $_POST['service'];
                $delai_jours = (int)$_POST['delai_jours'];
                $alertes = json_decode($_POST['alertes']);
                
                if (empty($type_dossier) || empty($service) || $delai_jours <= 0) {
                    throw new Exception("Tous les champs sont obligatoires");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO echeances_config (type_dossier, service, delai_jours, alertes) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE delai_jours = ?, alertes = ?
                ");
                $stmt->execute([
                    $type_dossier, $service, $delai_jours, json_encode($alertes),
                    $delai_jours, json_encode($alertes)
                ]);
                
                logAction($_SESSION['user_id'], 'CREATE_ECHEANCE_CONFIG', null, 
                    "Configuration échéance: $type_dossier/$service - $delai_jours jours");
                
                echo json_encode(['success' => true, 'message' => 'Configuration sauvegardée']);
                break;
                
            case 'delete_config':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM echeances_config WHERE id = ?");
                $stmt->execute([$id]);
                
                logAction($_SESSION['user_id'], 'DELETE_ECHEANCE_CONFIG', null, "Suppression config échéance ID: $id");
                echo json_encode(['success' => true, 'message' => 'Configuration supprimée']);
                break;
                
            case 'toggle_config':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE echeances_config SET actif = NOT actif WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Statut modifié']);
                break;
                
            case 'recalculate_deadlines':
                // Recalculer toutes les échéances en fonction des nouvelles configs
                $updated = recalculateAllDeadlines();
                echo json_encode(['success' => true, 'message' => "$updated dossiers mis à jour"]);
                break;
                
            default:
                throw new Exception("Action non reconnue");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fonction pour recalculer les échéances
function recalculateAllDeadlines() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT d.id, d.type, d.service, d.created_at,
               ec.delai_jours
        FROM dossiers d
        LEFT JOIN echeances_config ec ON d.type = ec.type_dossier AND d.service = ec.service
        WHERE d.status != 'archive' AND ec.delai_jours IS NOT NULL
    ");
    
    $dossiers = $stmt->fetchAll();
    $updated = 0;
    
    foreach ($dossiers as $dossier) {
        $nouvelle_echeance = date('Y-m-d', strtotime($dossier['created_at'] . ' + ' . $dossier['delai_jours'] . ' days'));
        
        $update = $pdo->prepare("UPDATE dossiers SET deadline = ? WHERE id = ?");
        $update->execute([$nouvelle_echeance, $dossier['id']]);
        
        $updated++;
    }
    
    return $updated;
}

// Récupération des configurations existantes
$stmt = $pdo->query("
    SELECT ec.*, 
           COUNT(d.id) as nb_dossiers_associes
    FROM echeances_config ec
    LEFT JOIN dossiers d ON ec.type_dossier = d.type AND ec.service = d.service
    GROUP BY ec.id
    ORDER BY ec.type_dossier, ec.service
");
$configs = $stmt->fetchAll();

// Types et services disponibles
$types = ['Etude', 'Projet', 'Administratif', 'Autre'];
$services = ['DEP', 'Finance', 'RH', 'Logistique'];

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);
$themeVars = $preferencesManager->getThemeVariables();

$pageTitle = "Configuration des Échéances";
include '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables modernes pour configuration */
    --config-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #2ed573 0%, #1e90ff 100%);
    --warning-gradient: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);
    --danger-gradient: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
    --info-gradient: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
    
    --shadow-soft: 0 10px 40px rgba(0,0,0,0.1);
    --shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.config-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.config-header {
    background: var(--config-gradient);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.config-header::before {
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
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
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

.content-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.config-table-card, .config-form-card, .config-stats-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.8);
    transition: var(--transition);
}

.config-table-card:hover, .config-form-card:hover, .config-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
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

.card-content {
    padding: 0;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modern-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-table td {
    padding: 1.5rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
    transition: var(--transition);
}

.modern-table tbody tr {
    transition: var(--transition);
}

.modern-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(8px);
}

.modern-table tbody tr.row-inactive {
    opacity: 0.6;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary {
    background: var(--config-gradient);
    color: white;
}

.badge-info {
    background: var(--info-gradient);
    color: white;
}

.badge-warning {
    background: var(--warning-gradient);
    color: white;
}

.badge-secondary {
    background: #e2e8f0;
    color: #4a5568;
}

.badge-success {
    background: var(--success-gradient);
    color: white;
}

.badge-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
}

.status-active {
    background: var(--success-gradient);
    color: white;
}

.status-inactive {
    background: #e2e8f0;
    color: #6c757d;
}

.metric-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2d3748;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    font-size: 1rem;
}

.btn-edit {
    background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);
    color: white;
}

.btn-edit:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(255, 165, 2, 0.4);
}

.btn-toggle {
    background: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
    color: white;
}

.btn-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(55, 66, 250, 0.4);
}

.btn-delete {
    background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
    color: white;
}

.btn-delete:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(255, 71, 87, 0.4);
}

.form-container {
    padding: 2rem;
}

.modern-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    font-size: 1rem;
}

.form-input, .form-select {
    padding: 1rem 1.5rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: var(--transition);
    background: white;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.form-help {
    font-size: 0.9rem;
    color: #6c757d;
    font-style: italic;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: var(--transition);
    padding: 0.5rem;
    border-radius: 8px;
}

.checkbox-item:hover {
    background: white;
    transform: scale(1.05);
}

.checkbox-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #667eea;
    cursor: pointer;
}

.btn-block {
    width: 100%;
    justify-content: center;
    padding: 1.2rem 2rem;
    font-size: 1.1rem;
}

.btn-outline {
    background: transparent;
    border: 2px solid #6c757d;
    color: #6c757d;
}

.btn-outline:hover {
    background: #6c757d;
    color: white;
}

.stats-container {
    padding: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.stat-item {
    text-align: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    transition: var(--transition);
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-soft);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: var(--config-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    color: #6c757d;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.text-primary { color: #667eea !important; }
.text-success { color: #2ed573 !important; }
.text-info { color: #3742fa !important; }

/* Scrollbar personnalisée */
.config-table-card::-webkit-scrollbar {
    width: 8px;
}

.config-table-card::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.config-table-card::-webkit-scrollbar-thumb {
    background: var(--config-gradient);
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

.config-table-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.1s;
}

.config-form-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.2s;
}

.config-stats-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.3s;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .config-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .config-header {
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
    
    .modern-table {
        font-size: 0.9rem;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 1rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .checkbox-group {
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
    
    .badge-group {
        justify-content: center;
    }
}

/* États de formulaire */
.form-input-error {
    border-color: #ff4757;
    box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
}

.form-input-success {
    border-color: #2ed573;
    box-shadow: 0 0 0 3px rgba(46, 213, 115, 0.1);
}

/* Loading states */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Print styles */
@media print {
    .header-actions,
    .action-buttons {
        display: none;
    }
    
    .config-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
    
    .config-table-card,
    .config-form-card,
    .config-stats-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<div class="config-container">
    <!-- En-tête moderne -->
    <div class="config-header">
        <div class="header-content">
            <div class="header-main">
                <div class="header-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="header-text">
                    <h1>Configuration des Échéances</h1>
                    <p>Gérez intelligemment les délais et alertes pour chaque type de dossier</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <button type="button" class="btn btn-primary" id="recalculateBtn">
                    <i class="fas fa-sync-alt"></i> Recalculer
                </button>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-main">
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-settings"></i>
                        Configurations Actives
                    </h3>
                    <button type="button" class="btn btn-secondary btn-sm" id="recalculateBtn">
                        <i class="icon-refresh"></i>
                        Recalculer toutes les échéances
                    </button>
                </div>
                
                <div class="card-content">
                    <div class="table-container">
                        <table class="data-table" id="configsTable">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Service</th>
                                    <th>Délai (jours)</th>
                                    <th>Alertes</th>
                                    <th>Dossiers</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($configs as $config): ?>
                                    <tr class="<?= $config['actif'] ? 'row-active' : 'row-inactive' ?>" data-config-id="<?= $config['id'] ?>">
                                        <td>
                                            <span class="badge badge-primary">
                                                <?= htmlspecialchars($config['type_dossier']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($config['service']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="metric-value">
                                                <strong><?= $config['delai_jours'] ?></strong> jours
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge-group">
                                                <?php 
                                                $alertes = json_decode($config['alertes'], true);
                                                foreach ($alertes as $alerte): ?>
                                                    <span class="badge badge-warning">
                                                        <?= $alerte === 0 ? 'J' : "-$alerte j" ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?= $config['nb_dossiers_associes'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $config['actif'] ? 'active' : 'inactive' ?>">
                                                <?= $config['actif'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-edit edit-config" 
                                                        data-id="<?= $config['id'] ?>"
                                                        data-type="<?= htmlspecialchars($config['type_dossier']) ?>"
                                                        data-service="<?= htmlspecialchars($config['service']) ?>"
                                                        data-delai="<?= $config['delai_jours'] ?>"
                                                        data-alertes="<?= htmlspecialchars($config['alertes']) ?>"
                                                        title="Modifier">
                                                    <i class="icon-edit"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-toggle toggle-config" 
                                                        data-id="<?= $config['id'] ?>" 
                                                        title="<?= $config['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                    <i class="icon-<?= $config['actif'] ? 'eye-off' : 'eye' ?>"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-delete delete-config" 
                                                        data-id="<?= $config['id'] ?>" 
                                                        title="Supprimer">
                                                    <i class="icon-trash"></i>
                                                </button>
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
        
        <div class="content-sidebar">
            <div class="form-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-plus"></i> 
                        <span id="formTitle">Nouvelle Configuration</span>
                    </h3>
                </div>
                
                <div class="card-content">
                    <form id="configForm" class="modern-form">
                        <input type="hidden" id="config_id" name="config_id">
                        
                        <div class="form-group">
                            <label for="type_dossier" class="form-label">Type de dossier</label>
                            <select class="form-input" id="type_dossier" name="type_dossier" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="service" class="form-label">Service</label>
                            <select class="form-input" id="service" name="service" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service ?>"><?= $service ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="delai_jours" class="form-label">Délai en jours</label>
                            <input type="number" class="form-input" id="delai_jours" name="delai_jours" 
                                   min="1" max="365" required>
                            <div class="form-help">
                                Nombre de jours depuis la création du dossier
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alertes (jours avant échéance)</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" id="alert_14" value="14">
                                    <span class="checkmark"></span>
                                    14 jours
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" id="alert_7" value="7">
                                    <span class="checkmark"></span>
                                    7 jours
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" id="alert_3" value="3">
                                    <span class="checkmark"></span>
                                    3 jours
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" id="alert_1" value="1">
                                    <span class="checkmark"></span>
                                    1 jour
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" id="alert_0" value="0">
                                    <span class="checkmark"></span>
                                    Le jour J
                                </label>
                            </div>
                            <div class="form-help">
                                Cochez les moments où envoyer des alertes
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="icon-save"></i> 
                            <span id="submitText">Créer la configuration</span>
                        </button>
                        
                        <button type="button" class="btn btn-secondary btn-outline" id="cancelEdit" style="display: none;">
                            <i class="icon-x"></i> Annuler
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="stats-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-chart"></i> Statistiques
                    </h3>
                </div>
                <div class="card-content">
                    <?php
                    $stmt = $pdo->query("
                        SELECT 
                            COUNT(*) as total_configs,
                            COUNT(CASE WHEN actif = 1 THEN 1 END) as configs_actives,
                            AVG(delai_jours) as delai_moyen
                        FROM echeances_config
                    ");
                    $stats = $stmt->fetch();
                    ?>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value text-primary"><?= $stats['total_configs'] ?></div>
                            <div class="stat-label">Configurations</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value text-success"><?= $stats['configs_actives'] ?></div>
                            <div class="stat-label">Actives</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value text-info"><?= round($stats['delai_moyen'], 1) ?></div>
                            <div class="stat-label">Jours moy.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation de la table avec le design moderne
    initializeDataTable('configsTable', {
        language: {
            url: '../../assets/js/datatables-fr.json'
        },
        order: [[0, 'asc'], [1, 'asc']],
        columnDefs: [
            { targets: [6], orderable: false }
        ]
    });
    
    // Formulaire configuration
    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Récupérer les alertes cochées
        const alertes = [];
        document.querySelectorAll('.checkbox-group input:checked').forEach(cb => {
            alertes.push(parseInt(cb.value));
        });
        
        if (alertes.length === 0) {
            showNotification('Veuillez sélectionner au moins une alerte', 'warning');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'create_config');
        formData.append('alertes', JSON.stringify(alertes.sort((a, b) => b - a)));
        
        // Animation de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="icon-loading animate-spin"></i> Enregistrement...';
        submitBtn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Erreur: ' + data.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            showNotification('Erreur de communication', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            console.error(error);
        });
    });
    
    // Édition configuration
    document.querySelectorAll('.edit-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = this.dataset;
            
            document.getElementById('config_id').value = data.id;
            document.getElementById('type_dossier').value = data.type;
            document.getElementById('service').value = data.service;
            document.getElementById('delai_jours').value = data.delai;
            
            // Cocher les alertes
            document.querySelectorAll('.checkbox-group input').forEach(cb => cb.checked = false);
            const alertes = JSON.parse(data.alertes);
            alertes.forEach(alerte => {
                const checkbox = document.getElementById('alert_' + alerte);
                if (checkbox) checkbox.checked = true;
            });
            
            document.getElementById('formTitle').textContent = 'Modifier Configuration';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('cancelEdit').style.display = 'block';
            
            // Animation de focus sur le formulaire
            document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Annuler édition
    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('configForm').reset();
        document.getElementById('config_id').value = '';
        document.getElementById('formTitle').textContent = 'Nouvelle Configuration';
        document.getElementById('submitText').textContent = 'Créer la configuration';
        this.style.display = 'none';
    });
    
    // Toggle config
    document.querySelectorAll('.toggle-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const formData = new FormData();
            formData.append('action', 'toggle_config');
            formData.append('id', id);
            
            // Animation du bouton
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="icon-loading animate-spin"></i>';
            this.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Erreur: ' + data.message, 'error');
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }
            })
            .catch(error => {
                showNotification('Erreur de communication', 'error');
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    });
    
    // Delete config
    document.querySelectorAll('.delete-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            
            showConfirmDialog(
                'Supprimer la configuration',
                'Êtes-vous sûr de vouloir supprimer cette configuration ?',
                () => {
                    const formData = new FormData();
                    formData.append('action', 'delete_config');
                    formData.append('id', id);
                
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification('Erreur: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Erreur de communication', 'error');
                    });
                }
            );
        });
    });
    
    // Recalculer échéances
    document.getElementById('recalculateBtn').addEventListener('click', function() {
        showConfirmDialog(
            'Recalculer les échéances',
            'Recalculer toutes les échéances selon les nouvelles configurations ?',
            () => {
                const formData = new FormData();
                formData.append('action', 'recalculate_deadlines');
                
                const originalHtml = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="icon-loading animate-spin"></i> Calcul...';
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Erreur: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de communication', 'error');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                });
            }
        );
    });
    
    // Animation d'apparition pour les lignes du tableau
    animateElementsOnScroll('.data-table tbody tr');
    
    // Validation en temps réel
    const delaiInput = document.getElementById('delai_jours');
    if (delaiInput) {
        delaiInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 1 || value > 365) {
                this.classList.add('form-input-error');
            } else {
                this.classList.remove('form-input-error');
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
                this.innerHTML = '<i class="fas fa-sync"></i> Recalculer toutes les échéances';
            });
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
