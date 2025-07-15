<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/PreferencesManager.php';

requireRole(ROLE_ADMIN); // Seuls les admins peuvent gérer les transitions

// Actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_transition':
                $from_status = $_POST['from_status'];
                $to_status = $_POST['to_status'];
                $role_requis = (int)$_POST['role_requis'];
                
                if ($from_status === $to_status) {
                    throw new Exception("Les statuts source et destination doivent être différents");
                }
                
                $stmt = $pdo->prepare("INSERT INTO status_transitions (from_status, to_status, role_requis) VALUES (?, ?, ?)");
                $stmt->execute([$from_status, $to_status, $role_requis]);
                
                logAction($_SESSION['user_id'], 'CREATE_TRANSITION', null, "Création transition: $from_status -> $to_status");
                echo json_encode(['success' => true, 'message' => 'Transition créée']);
                break;
                
            case 'delete_transition':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM status_transitions WHERE id = ?");
                $stmt->execute([$id]);
                
                logAction($_SESSION['user_id'], 'DELETE_TRANSITION', null, "Suppression transition ID: $id");
                echo json_encode(['success' => true, 'message' => 'Transition supprimée']);
                break;
                
            case 'toggle_transition':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE status_transitions SET actif = NOT actif WHERE id = ?");
                $stmt->execute([$id]);
                
                logAction($_SESSION['user_id'], 'TOGGLE_TRANSITION', null, "Basculer transition ID: $id");
                echo json_encode(['success' => true, 'message' => 'Statut modifié']);
                break;
                
            default:
                throw new Exception("Action non reconnue");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Récupération des transitions existantes
$stmt = $pdo->query("
    SELECT st.*, 
           CASE st.role_requis 
               WHEN 1 THEN 'Administrateur'
               WHEN 2 THEN 'Gestionnaire'
               WHEN 3 THEN 'Consultant'
               ELSE 'Inconnu'
           END as role_nom
    FROM status_transitions st 
    ORDER BY st.from_status, st.to_status
");
$transitions = $stmt->fetchAll();

// Définition des statuts disponibles
$statuts = [
    'en_cours' => 'En cours',
    'valide' => 'Validé',
    'rejete' => 'Rejeté',
    'archive' => 'Archivé'
];

$roles = [
    ROLE_ADMIN => 'Administrateur',
    ROLE_GESTIONNAIRE => 'Gestionnaire', 
    ROLE_CONSULTANT => 'Consultant'
];

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);
$themeVars = $preferencesManager->getThemeVariables();

$pageTitle = "Gestion des Transitions de Statuts";
include '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables modernes pour transitions */
    --transitions-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.transitions-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.transitions-header {
    background: var(--transitions-gradient);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.transitions-header::before {
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

.header-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 100px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    display: block;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.content-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.transitions-table-card, .transitions-form-card, .matrix-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.8);
    transition: var(--transition);
}

.transitions-table-card:hover, .transitions-form-card:hover, .matrix-card:hover {
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
    background: var(--transitions-gradient);
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

.transition-flow {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-en_cours {
    background: var(--warning-gradient);
    color: white;
}

.status-valide {
    background: var(--success-gradient);
    color: white;
}

.status-rejete {
    background: var(--danger-gradient);
    color: white;
}

.status-archive {
    background: #6c757d;
    color: white;
}

.transition-arrow {
    font-size: 1.5rem;
    color: #667eea;
    animation: pulse 2s infinite;
}

.role-badge {
    background: var(--info-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
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
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    background: var(--transitions-gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-block {
    width: 100%;
    justify-content: center;
}

.matrix-container {
    padding: 1.5rem;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.matrix-table th,
.matrix-table td {
    padding: 0.75rem;
    text-align: center;
    border: 1px solid #e2e8f0;
}

.matrix-table th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    font-weight: 600;
    color: #2d3748;
}

.matrix-table .status-label {
    font-weight: 600;
    color: #2d3748;
    text-align: left;
    padding-left: 1rem;
}

.matrix-cell {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    border-radius: 8px;
    transition: var(--transition);
}

.matrix-allowed {
    background: var(--success-gradient);
    color: white;
}

.matrix-denied {
    background: var(--danger-gradient);
    color: white;
}

.matrix-self {
    background: #e2e8f0;
    color: #6c757d;
}

.matrix-cell:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.sidebar-layout {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: var(--success-gradient);
    color: white;
}

.alert-danger {
    background: var(--danger-gradient);
    color: white;
}

.alert-warning {
    background: var(--warning-gradient);
    color: white;
}

.alert-info {
    background: var(--info-gradient);
    color: white;
}

/* Scrollbar personnalisée */
.transitions-table-card::-webkit-scrollbar {
    width: 8px;
}

.transitions-table-card::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.transitions-table-card::-webkit-scrollbar-thumb {
    background: var(--transitions-gradient);
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

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.transitions-table-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.1s;
}

.transitions-form-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.2s;
}

.matrix-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.3s;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .transitions-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }
    
    .header-stats {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .transitions-header {
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
    
    .transition-flow {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .header-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .status-badge,
    .role-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
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
    .action-buttons {
        display: none;
    }
    
    .transitions-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
    
    .transitions-table-card,
    .transitions-form-card,
    .matrix-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<div class="transitions-container">
    <!-- En-tête moderne -->
    <div class="transitions-header">
        <div class="header-content">
            <div class="header-main">
                <div class="header-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="header-text">
                    <h1>Gestion des Transitions</h1>
                    <p>Configurez les règles de transition entre les statuts de dossiers</p>
                </div>
            </div>
            <div class="header-stats">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transitions,
                        COUNT(CASE WHEN actif = 1 THEN 1 END) as transitions_actives,
                        COUNT(DISTINCT from_status) as statuts_source
                    FROM status_transitions
                ");
                $stats = $stmt->fetch();
                ?>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['total_transitions'] ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['transitions_actives'] ?></span>
                    <span class="stat-label">Actives</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['statuts_source'] ?></span>
                    <span class="stat-label">Statuts</span>
                </div>
            </div>
        </div>
    </div>

    <div class="content-layout">
        <!-- Table des transitions -->
        <div class="transitions-table-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-route"></i>
                    Transitions configurées
                </h3>
            </div>
            
            <div class="card-content">
                <div style="overflow-x: auto; max-height: 600px;">
                    <table class="modern-table" id="transitionsTable">
                        <thead>
                            <tr>
                                <th>Transition</th>
                                <th>Rôle requis</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transitions as $transition): ?>
                                <tr class="<?= $transition['actif'] ? 'row-active' : 'row-inactive' ?>" data-transition-id="<?= $transition['id'] ?>">
                                    <td>
                                        <div class="transition-flow">
                                            <span class="status-badge status-<?= $transition['from_status'] ?>">
                                                <i class="fas fa-<?= getStatusIcon($transition['from_status']) ?>"></i>
                                                <?= $statuts[$transition['from_status']] ?>
                                            </span>
                                            <i class="fas fa-arrow-right transition-arrow"></i>
                                            <span class="status-badge status-<?= $transition['to_status'] ?>">
                                                <i class="fas fa-<?= getStatusIcon($transition['to_status']) ?>"></i>
                                                <?= $statuts[$transition['to_status']] ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge">
                                            <i class="fas fa-<?= getRoleIcon($transition['role_requis']) ?>"></i>
                                            <?= $transition['role_nom'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $transition['actif'] ? 'status-valide' : 'status-archive' ?>">
                                            <i class="fas fa-<?= $transition['actif'] ? 'check-circle' : 'pause-circle' ?>"></i>
                                            <?= $transition['actif'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-action btn-toggle toggle-transition" 
                                                    data-id="<?= $transition['id'] ?>" 
                                                    title="<?= $transition['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                <i class="fas fa-<?= $transition['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                            <button type="button" class="btn-action btn-delete delete-transition" 
                                                    data-id="<?= $transition['id'] ?>" 
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
        
        <!-- Sidebar avec formulaire et matrice -->
        <div class="sidebar-layout">
            <!-- Formulaire de création -->
            <div class="transitions-form-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus-circle"></i>
                        Nouvelle Transition
                    </h3>
                </div>
                
                <div class="card-content">
                    <div class="form-container">
                        <form id="transitionForm" class="modern-form">
                            <div class="form-group">
                                <label for="from_status" class="form-label">
                                    <i class="fas fa-play"></i> Statut source
                                </label>
                                <select class="form-select" id="from_status" name="from_status" required>
                                    <option value="">-- Choisir le statut de départ --</option>
                                    <?php foreach ($statuts as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">
                                    <i class="fas fa-info-circle"></i> Statut actuel du dossier
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="to_status" class="form-label">
                                    <i class="fas fa-flag-checkered"></i> Statut destination
                                </label>
                                <select class="form-select" id="to_status" name="to_status" required>
                                    <option value="">-- Choisir le statut d'arrivée --</option>
                                    <?php foreach ($statuts as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">
                                    <i class="fas fa-lightbulb"></i> Nouveau statut après transition
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="role_requis" class="form-label">
                                    <i class="fas fa-shield-alt"></i> Rôle minimum requis
                                </label>
                                <select class="form-select" id="role_requis" name="role_requis" required>
                                    <option value="">-- Choisir le niveau d'autorisation --</option>
                                    <?php foreach ($roles as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">
                                    <i class="fas fa-user-shield"></i> Niveau minimum pour effectuer cette transition
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i>
                                Créer la transition
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Matrice visuelle des transitions -->
            <div class="matrix-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-sitemap"></i>
                        Matrice des Transitions
                    </h3>
                </div>
                <div class="card-content">
                    <div class="matrix-container">
                        <?php
                        // Créer une matrice visuelle
                        $matrix = [];
                        foreach ($transitions as $t) {
                            if ($t['actif']) {
                                $matrix[$t['from_status']][$t['to_status']] = $t['role_requis'];
                            }
                        }
                        ?>
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">De \ Vers</th>
                                    <?php foreach ($statuts as $key => $label): ?>
                                        <th style="width: 80px;"><?= substr($label, 0, 6) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statuts as $from_key => $from_label): ?>
                                    <tr>
                                        <td class="status-label"><?= $from_label ?></td>
                                        <?php foreach ($statuts as $to_key => $to_label): ?>
                                            <td>
                                                <?php if (isset($matrix[$from_key][$to_key])): ?>
                                                    <div class="matrix-cell matrix-allowed" 
                                                         title="Autorisé pour <?= $roles[$matrix[$from_key][$to_key]] ?>">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                <?php elseif ($from_key !== $to_key): ?>
                                                    <div class="matrix-cell matrix-denied" title="Non autorisé">
                                                        <i class="fas fa-times"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="matrix-cell matrix-self" title="Même statut">
                                                        <i class="fas fa-minus"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
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
    console.log('🔄 Gestion des Transitions initialisée');
    
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

        document.querySelectorAll('.transitions-table-card, .transitions-form-card, .matrix-card').forEach(el => {
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
    
    // Gestion du formulaire de transition
    document.getElementById('transitionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validation côté client
        const fromStatus = document.getElementById('from_status').value;
        const toStatus = document.getElementById('to_status').value;
        const roleRequis = document.getElementById('role_requis').value;
        
        if (!fromStatus || !toStatus || !roleRequis) {
            showNotification('⚠️ Tous les champs sont obligatoires', 'warning');
            return;
        }
        
        if (fromStatus === toStatus) {
            showNotification('❌ Les statuts source et destination doivent être différents', 'error');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'create_transition');
        
        // Animation du bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Création...';
        submitBtn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
                
                // Animation de succès
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Créée !';
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
    
    // Toggle status des transitions
    document.querySelectorAll('.toggle-transition').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            
            const formData = new FormData();
            formData.append('action', 'toggle_transition');
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
    
    // Suppression des transitions
    document.querySelectorAll('.delete-transition').forEach(btn => {
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
                        text-align: center; max-width: 450px;
                        animation: slideIn 0.3s ease;
                    `;
                    
                    modal.innerHTML = `
                        <div style="color: #ff4757; font-size: 3rem; margin-bottom: 1rem;">
                            <i class="fas fa-trash"></i>
                        </div>
                        <h3 style="color: #2d3748; margin-bottom: 1rem;">Supprimer la transition</h3>
                        <p style="color: #6c757d; margin-bottom: 2rem;">
                            Êtes-vous sûr de vouloir supprimer cette règle de transition ?
                        </p>
                        <p style="color: #ff4757; font-size: 0.9rem; margin-bottom: 2rem;">
                            ⚠️ Cette action est irréversible et peut affecter les workflows
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
                formData.append('action', 'delete_transition');
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
    
    // Validation en temps réel des sélections
    const validateTransition = () => {
        const fromStatus = document.getElementById('from_status').value;
        const toStatus = document.getElementById('to_status').value;
        const submitBtn = document.querySelector('#transitionForm button[type="submit"]');
        
        if (fromStatus && toStatus && fromStatus === toStatus) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            showNotification('⚠️ Les statuts source et destination doivent être différents', 'warning');
        } else {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        }
    };
    
    document.getElementById('from_status').addEventListener('change', validateTransition);
    document.getElementById('to_status').addEventListener('change', validateTransition);
    
    // Animation des cellules de la matrice
    document.querySelectorAll('.matrix-cell').forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            // Mettre en évidence la ligne et la colonne
            const table = this.closest('table');
            const cells = table.querySelectorAll('.matrix-cell');
            
            cells.forEach(c => c.style.opacity = '0.3');
            this.style.opacity = '1';
            this.style.transform = 'scale(1.2)';
        });
        
        cell.addEventListener('mouseleave', function() {
            const table = this.closest('table');
            const cells = table.querySelectorAll('.matrix-cell');
            
            cells.forEach(c => {
                c.style.opacity = '1';
                c.style.transform = 'scale(1)';
            });
        });
    });
    
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
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 4000);
    };
    
    // Filtrage et recherche rapide
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = '🔍 Rechercher une transition...';
    searchInput.style.cssText = `
        width: 100%; padding: 0.75rem 1rem; margin-bottom: 1rem;
        border: 2px solid #e2e8f0; border-radius: 12px;
        font-size: 1rem; transition: all 0.3s ease;
    `;
    
    const tableContainer = document.querySelector('.transitions-table-card .card-content');
    if (tableContainer) {
        const firstChild = tableContainer.firstChild;
        tableContainer.insertBefore(searchInput, firstChild);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.modern-table tbody tr');
            
            rows.forEach(row => {
                const transitionFlow = row.querySelector('.transition-flow');
                const roleText = row.querySelector('.role-badge');
                
                const transitionText = transitionFlow ? transitionFlow.textContent.toLowerCase() : '';
                const roleTextContent = roleText ? roleText.textContent.toLowerCase() : '';
                
                if (transitionText.includes(searchTerm) || roleTextContent.includes(searchTerm)) {
                    row.style.display = '';
                    row.style.opacity = '1';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Animation de recherche
            this.style.borderColor = searchTerm ? '#667eea' : '#e2e8f0';
        });
        
        searchInput.addEventListener('focus', function() {
            this.style.borderColor = '#667eea';
            this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1)';
        });
        
        searchInput.addEventListener('blur', function() {
            this.style.boxShadow = 'none';
        });
    }
    
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
    
    console.log('✅ Gestion des Transitions prête !');
});
</script>

<?php 
function getStatusColor($status) {
    switch ($status) {
        case 'en_cours': return 'warning';
        case 'valide': return 'success';
        case 'rejete': return 'danger';
        case 'archive': return 'secondary';
        default: return 'light';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'en_cours': return 'clock';
        case 'valide': return 'check-circle';
        case 'rejete': return 'times-circle';
        case 'archive': return 'archive';
        default: return 'question-circle';
    }
}

function getRoleIcon($role) {
    switch ($role) {
        case ROLE_ADMIN: return 'crown';
        case ROLE_GESTIONNAIRE: return 'user-tie';
        case ROLE_CONSULTANT: return 'user';
        default: return 'question';
    }
}

include '../../includes/footer.php'; 
?>
