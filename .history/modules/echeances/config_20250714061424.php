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

    <div class="content-layout">
        <!-- Table des configurations -->
        <div class="config-table-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Configurations Actives
                </h3>
                <span class="badge badge-info"><?= count($configs) ?> configs</span>
            </div>
            
            <div class="card-content">
                <div style="overflow-x: auto; max-height: 600px;">
                    <table class="modern-table" id="configsTable">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Service</th>
                                <th>Délai</th>
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
                                            <?= $config['delai_jours'] ?> jours
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge-group">
                                            <?php 
                                            $alertes = json_decode($config['alertes'], true);
                                            foreach ($alertes as $alerte): ?>
                                                <span class="badge badge-warning">
                                                    <?= $alerte === 0 ? 'J' : "-{$alerte}j" ?>
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
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn-action btn-toggle toggle-config" 
                                                    data-id="<?= $config['id'] ?>" 
                                                    title="<?= $config['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                <i class="fas fa-<?= $config['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                            <button type="button" class="btn-action btn-delete delete-config" 
                                                    data-id="<?= $config['id'] ?>" 
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
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
        
        <!-- Sidebar avec formulaire et stats -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Formulaire de configuration -->
            <div class="config-form-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus-circle"></i> 
                        <span id="formTitle">Nouvelle Configuration</span>
                    </h3>
                </div>
                
                <div class="card-content">
                    <div class="form-container">
                        <form id="configForm" class="modern-form">
                            <input type="hidden" id="config_id" name="config_id">
                            
                            <div class="form-group">
                                <label for="type_dossier" class="form-label">
                                    <i class="fas fa-folder"></i> Type de dossier
                                </label>
                                <select class="form-select" id="type_dossier" name="type_dossier" required>
                                    <option value="">-- Sélectionnez un type --</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="service" class="form-label">
                                    <i class="fas fa-building"></i> Service
                                </label>
                                <select class="form-select" id="service" name="service" required>
                                    <option value="">-- Sélectionnez un service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service ?>"><?= $service ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="delai_jours" class="form-label">
                                    <i class="fas fa-clock"></i> Délai en jours
                                </label>
                                <input type="number" class="form-input" id="delai_jours" name="delai_jours" 
                                       min="1" max="365" required placeholder="Ex: 30">
                                <div class="form-help">
                                    <i class="fas fa-info-circle"></i> Durée depuis la création du dossier
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-bell"></i> Alertes automatiques
                                </label>
                                <div class="checkbox-group">
                                    <label class="checkbox-item">
                                        <input type="checkbox" id="alert_14" value="14">
                                        14 jours avant
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" id="alert_7" value="7">
                                        7 jours avant
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" id="alert_3" value="3">
                                        3 jours avant
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" id="alert_1" value="1">
                                        1 jour avant
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" id="alert_0" value="0">
                                        Le jour J
                                    </label>
                                </div>
                                <div class="form-help">
                                    <i class="fas fa-lightbulb"></i> Choisissez quand envoyer les notifications
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> 
                                <span id="submitText">Créer la configuration</span>
                            </button>
                            
                            <button type="button" class="btn btn-secondary btn-outline btn-block" id="cancelEdit" style="display: none;">
                                <i class="fas fa-times"></i> Annuler la modification
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="config-stats-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-chart-bar"></i> Statistiques
                    </h3>
                </div>
                <div class="card-content">
                    <div class="stats-container">
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
                                <div class="stat-label">
                                    <i class="fas fa-cogs"></i> Configurations
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value text-success"><?= $stats['configs_actives'] ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-check-circle"></i> Actives
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value text-info"><?= round($stats['delai_moyen'], 1) ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-calendar-alt"></i> Jours moy.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎛️ Configuration des Échéances initialisée');
    
    // Animation d'apparition des éléments
    const animateElements = () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.config-table-card, .config-form-card, .config-stats-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(el);
        });
    };
    
    // Animation des lignes du tableau
    const animateTableRows = () => {
        document.querySelectorAll('.modern-table tbody tr').forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            row.style.transition = 'all 0.3s ease';
            
            setTimeout(() => {
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 100);
        });
    };
    
    // Initialisation des animations
    setTimeout(animateElements, 100);
    setTimeout(animateTableRows, 500);
    
    // Gestion du formulaire de configuration
    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validation et collecte des alertes
        const alertes = [];
        document.querySelectorAll('.checkbox-group input:checked').forEach(cb => {
            alertes.push(parseInt(cb.value));
        });
        
        if (alertes.length === 0) {
            showNotification('⚠️ Veuillez sélectionner au moins une alerte', 'warning');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'create_config');
        formData.append('alertes', JSON.stringify(alertes.sort((a, b) => b - a)));
        
        // Animation du bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Traitement...';
        submitBtn.disabled = true;
        
        // Envoi AJAX
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
                
                // Animation de succès
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Enregistré !';
                submitBtn.style.background = 'var(--success-gradient)';
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('❌ Erreur: ' + data.message, 'error');
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
                
                // Animation d'erreur
                submitBtn.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => submitBtn.style.animation = '', 500);
            }
        })
        .catch(error => {
            showNotification('🔌 Erreur de communication', 'error');
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
            console.error('Erreur:', error);
        });
    });
    
    // Édition d'une configuration
    document.querySelectorAll('.edit-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = this.dataset;
            
            // Remplir le formulaire
            document.getElementById('config_id').value = data.id;
            document.getElementById('type_dossier').value = data.type;
            document.getElementById('service').value = data.service;
            document.getElementById('delai_jours').value = data.delai;
            
            // Cocher les alertes appropriées
            document.querySelectorAll('.checkbox-group input').forEach(cb => cb.checked = false);
            const alertes = JSON.parse(data.alertes);
            alertes.forEach(alerte => {
                const checkbox = document.getElementById('alert_' + alerte);
                if (checkbox) checkbox.checked = true;
            });
            
            // Changer l'interface en mode édition
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier Configuration';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('cancelEdit').style.display = 'block';
            
            // Animation de focus sur le formulaire
            document.querySelector('.config-form-card').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
            
            // Effet de highlight
            const formCard = document.querySelector('.config-form-card');
            formCard.style.boxShadow = '0 0 30px rgba(102, 126, 234, 0.3)';
            setTimeout(() => {
                formCard.style.boxShadow = 'var(--shadow-soft)';
            }, 2000);
        });
    });
    
    // Annuler l'édition
    document.getElementById('cancelEdit').addEventListener('click', function() {
        // Reset du formulaire
        document.getElementById('configForm').reset();
        document.getElementById('config_id').value = '';
        
        // Reset de l'interface
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nouvelle Configuration';
        document.getElementById('submitText').textContent = 'Créer la configuration';
        this.style.display = 'none';
        
        // Animation de reset
        const formCard = document.querySelector('.config-form-card');
        formCard.style.transform = 'scale(0.95)';
        setTimeout(() => {
            formCard.style.transform = 'scale(1)';
        }, 200);
    });
    
    // Toggle activation/désactivation
    document.querySelectorAll('.toggle-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            
            const formData = new FormData();
            formData.append('action', 'toggle_config');
            formData.append('id', id);
            
            // Animation du bouton
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner animate-spin"></i>';
            this.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('🔄 ' + data.message, 'success');
                    
                    // Animation de la ligne
                    row.style.opacity = '0.5';
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('❌ Erreur: ' + data.message, 'error');
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }
            })
            .catch(error => {
                showNotification('🔌 Erreur de communication', 'error');
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    });
    
    // Suppression d'une configuration
    document.querySelectorAll('.delete-config').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            
            // Dialog de confirmation moderne
            const confirmDelete = () => {
                return new Promise((resolve) => {
                    const overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(0,0,0,0.5); z-index: 10000;
                        display: flex; align-items: center; justify-content: center;
                        animation: fadeIn 0.3s ease;
                    `;
                    
                    const modal = document.createElement('div');
                    modal.style.cssText = `
                        background: white; padding: 2rem; border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center; max-width: 400px;
                        animation: slideIn 0.3s ease;
                    `;
                    
                    modal.innerHTML = `
                        <div style="color: #ff4757; font-size: 3rem; margin-bottom: 1rem;">
                            <i class="fas fa-trash"></i>
                        </div>
                        <h3 style="color: #2d3748; margin-bottom: 1rem;">Supprimer la configuration</h3>
                        <p style="color: #6c757d; margin-bottom: 2rem;">
                            Cette action est irréversible. Êtes-vous sûr ?
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button id="confirmYes" style="
                                background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
                                color: white; padding: 0.75rem 1.5rem; border: none;
                                border-radius: 8px; cursor: pointer; font-weight: 600;
                            ">Supprimer</button>
                            <button id="confirmNo" style="
                                background: #e2e8f0; color: #6c757d; padding: 0.75rem 1.5rem;
                                border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
                            ">Annuler</button>
                        </div>
                    `;
                    
                    overlay.appendChild(modal);
                    document.body.appendChild(overlay);
                    
                    modal.querySelector('#confirmYes').onclick = () => {
                        document.body.removeChild(overlay);
                        resolve(true);
                    };
                    
                    modal.querySelector('#confirmNo').onclick = () => {
                        document.body.removeChild(overlay);
                        resolve(false);
                    };
                    
                    overlay.onclick = (e) => {
                        if (e.target === overlay) {
                            document.body.removeChild(overlay);
                            resolve(false);
                        }
                    };
                });
            };
            
            confirmDelete().then(confirmed => {
                if (!confirmed) return;
                
                const formData = new FormData();
                formData.append('action', 'delete_config');
                formData.append('id', id);
                
                // Animation de suppression
                row.style.transition = 'all 0.5s ease';
                row.style.transform = 'translateX(-100%)';
                row.style.opacity = '0';
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('🗑️ ' + data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('❌ Erreur: ' + data.message, 'error');
                        // Annuler l'animation
                        row.style.transform = 'translateX(0)';
                        row.style.opacity = '1';
                    }
                })
                .catch(error => {
                    showNotification('🔌 Erreur de communication', 'error');
                    row.style.transform = 'translateX(0)';
                    row.style.opacity = '1';
                });
            });
        });
    });
    
    // Recalcul des échéances
    document.getElementById('recalculateBtn').addEventListener('click', function() {
        const confirmRecalc = () => {
            return new Promise((resolve) => {
                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5); z-index: 10000;
                    display: flex; align-items: center; justify-content: center;
                    animation: fadeIn 0.3s ease;
                `;
                
                const modal = document.createElement('div');
                modal.style.cssText = `
                    background: white; padding: 2rem; border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center; max-width: 450px;
                    animation: slideIn 0.3s ease;
                `;
                
                modal.innerHTML = `
                    <div style="color: #667eea; font-size: 3rem; margin-bottom: 1rem;">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3 style="color: #2d3748; margin-bottom: 1rem;">Recalculer les échéances</h3>
                    <p style="color: #6c757d; margin-bottom: 2rem;">
                        Toutes les échéances seront recalculées selon les nouvelles configurations. Continuer ?
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button id="confirmYes" style="
                            background: var(--config-gradient);
                            color: white; padding: 0.75rem 1.5rem; border: none;
                            border-radius: 8px; cursor: pointer; font-weight: 600;
                        ">Recalculer</button>
                        <button id="confirmNo" style="
                            background: #e2e8f0; color: #6c757d; padding: 0.75rem 1.5rem;
                            border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
                        ">Annuler</button>
                    </div>
                `;
                
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                
                modal.querySelector('#confirmYes').onclick = () => {
                    document.body.removeChild(overlay);
                    resolve(true);
                };
                
                modal.querySelector('#confirmNo').onclick = () => {
                    document.body.removeChild(overlay);
                    resolve(false);
                };
                
                overlay.onclick = (e) => {
                    if (e.target === overlay) {
                        document.body.removeChild(overlay);
                        resolve(false);
                    }
                };
            });
        };
        
        confirmRecalc().then(confirmed => {
            if (!confirmed) return;
            
            const formData = new FormData();
            formData.append('action', 'recalculate_deadlines');
            
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Calcul en cours...';
            this.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('🎯 ' + data.message, 'success');
                    this.innerHTML = '<i class="fas fa-check"></i> Terminé !';
                    this.style.background = 'var(--success-gradient)';
                    
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Erreur: ' + data.message, 'error');
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }
            })
            .catch(error => {
                showNotification('🔌 Erreur de communication', 'error');
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    });
    
    // Validation en temps réel du délai
    const delaiInput = document.getElementById('delai_jours');
    if (delaiInput) {
        delaiInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            
            if (value < 1 || value > 365 || isNaN(value)) {
                this.classList.add('form-input-error');
                this.style.borderColor = '#ff4757';
            } else {
                this.classList.remove('form-input-error');
                this.style.borderColor = '#2ed573';
                
                // Animation de succès
                this.style.transform = 'scale(1.02)';
                setTimeout(() => this.style.transform = 'scale(1)', 200);
            }
        });
    }
    
    // Fonction de notification moderne
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        const colors = {
            success: 'var(--success-gradient)',
            error: 'var(--danger-gradient)',
            warning: 'var(--warning-gradient)',
            info: 'var(--info-gradient)'
        };
        
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10001;
            background: ${colors[type]}; color: white;
            padding: 1rem 1.5rem; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transform: translateX(100%); transition: all 0.3s ease;
            max-width: 400px; font-weight: 600;
        `;
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => notification.style.transform = 'translateX(0)', 100);
        
        // Auto-suppression
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => document.body.removeChild(notification), 300);
        }, 4000);
    };
    
    // Ajout des styles d'animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);
    
    console.log('✅ Configuration des Échéances prête !');
});
</script>

<?php include '../../includes/footer.php'; ?>
