<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérifier les permissions - seuls les admins et gestionnaires peuvent configurer les workflows
if (!hasPermission(ROLE_GESTIONNAIRE)) {
    header("Location: " . BASE_URL . "error.php?code=403");
    exit();
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'save_workflow':
            $result = saveWorkflowConfiguration($_POST);
            echo json_encode($result);
            exit;
            
        case 'delete_workflow':
            $workflowId = (int)$_POST['workflow_id'];
            $result = deleteWorkflow($workflowId);
            echo json_encode($result);
            exit;
            
        case 'reorder_steps':
            $result = reorderWorkflowSteps($_POST['steps']);
            echo json_encode($result);
            exit;
    }
}

/**
 * Sauvegarde la configuration d'un workflow
 */
function saveWorkflowConfiguration($data) {
    try {
        $typeDossier = cleanInput($data['type_dossier'] ?? '');
        $etape = cleanInput($data['etape'] ?? '');
        $description = cleanInput($data['description'] ?? '');
        $roleRequis = (int)($data['role_requis'] ?? ROLE_CONSULTANT);
        $ordre = (int)($data['ordre'] ?? 1);
        $delaiJours = (int)($data['delai_jours'] ?? 0);
        $workflowId = (int)($data['workflow_id'] ?? 0);
        
        if (empty($typeDossier) || empty($etape)) {
            return ['success' => false, 'message' => 'Type de dossier et étape requis'];
        }
        
        if ($workflowId > 0) {
            // Mise à jour
            executeQuery(
                "UPDATE workflows 
                 SET type_dossier = ?, etape = ?, description = ?, role_requis = ?, 
                     ordre = ?, delai_jours = ?, updated_at = NOW()
                 WHERE id = ?",
                [$typeDossier, $etape, $description, $roleRequis, $ordre, $delaiJours, $workflowId]
            );
            
            logAction($_SESSION['user_id'], 'workflow_updated', $workflowId, "Workflow modifié : $etape");
            return ['success' => true, 'message' => 'Workflow mis à jour avec succès'];
        } else {
            // Création
            executeQuery(
                "INSERT INTO workflows (type_dossier, etape, description, role_requis, ordre, delai_jours, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$typeDossier, $etape, $description, $roleRequis, $ordre, $delaiJours]
            );
            
            $newId = getLastInsertId();
            logAction($_SESSION['user_id'], 'workflow_created', $newId, "Nouveau workflow créé : $etape");
            return ['success' => true, 'message' => 'Workflow créé avec succès'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Supprime un workflow
 */
function deleteWorkflow($workflowId) {
    try {
        $workflow = fetchOne("SELECT * FROM workflows WHERE id = ?", [$workflowId]);
        if (!$workflow) {
            return ['success' => false, 'message' => 'Workflow introuvable'];
        }
        
        // Vérifier qu'il n'y a pas d'instances actives
        $activeInstances = fetchOne(
            "SELECT COUNT(*) as count FROM workflow_instances WHERE workflow_step_id = ?",
            [$workflowId]
        );
        
        if ($activeInstances['count'] > 0) {
            return ['success' => false, 'message' => 'Impossible de supprimer : des instances actives existent'];
        }
        
        executeQuery("DELETE FROM workflows WHERE id = ?", [$workflowId]);
        
        logAction($_SESSION['user_id'], 'workflow_deleted', $workflowId, "Workflow supprimé : {$workflow['etape']}");
        return ['success' => true, 'message' => 'Workflow supprimé avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Réorganise l'ordre des étapes
 */
function reorderWorkflowSteps($steps) {
    try {
        foreach ($steps as $stepData) {
            $id = (int)$stepData['id'];
            $ordre = (int)$stepData['ordre'];
            executeQuery("UPDATE workflows SET ordre = ? WHERE id = ?", [$ordre, $id]);
        }
        
        logAction($_SESSION['user_id'], 'workflow_reordered', 0, "Ordre des workflows modifié");
        return ['success' => true, 'message' => 'Ordre mis à jour avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

// Récupérer les données pour l'affichage
$workflows = fetchAll("SELECT * FROM workflows ORDER BY type_dossier, ordre");
$typesDossiers = ['consultation', 'autorisation', 'inspection', 'plainte', 'demande_info'];

$roles = [
    ROLE_CONSULTANT => "Consultant",
    ROLE_GESTIONNAIRE => "Gestionnaire", 
    ROLE_ADMIN => "Administrateur"
];

// Messages de session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Workflows - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.css">
    <style>
    /* === PAGE CONFIGURATION WORKFLOW - STYLE ADMIN MODERNE === */
    .workflow-config-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .workflow-config-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        color: var(--gray-600);
        font-size: 0.875rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.3s ease-out;
    }
    
    .breadcrumb a {
        color: var(--primary-600);
        text-decoration: none;
        transition: var(--transition-all);
    }
    
    .breadcrumb a:hover {
        color: var(--primary-800);
    }
    
    .page-header {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .header-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .header-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
        animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-6px) rotate(2deg); }
    }
    
    .header-details h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .header-details .subtitle {
        color: var(--gray-600);
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .main-content {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
    }
    
    .workflow-list-container {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInLeft 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .workflow-list-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .workflow-list-container:hover::before {
        transform: scaleX(1);
    }
    
    .section-header {
        display: flex;
        align-items: center;
        justify-content: between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }
    
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .section-title i {
        color: var(--primary-500);
        font-size: 1.25rem;
    }
    
    .workflow-filters {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .filter-select {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: white;
        font-size: 0.875rem;
        transition: var(--transition-all);
        min-width: 150px;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .workflow-groups {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .workflow-group {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        border: 1px solid var(--gray-200);
        transition: var(--transition-all);
    }
    
    .workflow-group:hover {
        background: white;
        box-shadow: var(--shadow-sm);
        transform: translateY(-2px);
    }
    
    .group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .group-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    
    .group-title i {
        color: var(--primary-500);
    }
    
    .group-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .workflow-steps {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-height: 60px;
    }
    
    .workflow-step {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.25rem;
        border: 2px solid var(--gray-200);
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
        cursor: move;
        position: relative;
        overflow: hidden;
    }
    
    .workflow-step::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--primary-500);
        transform: scaleY(0);
        transition: transform 0.3s ease;
        transform-origin: top;
    }
    
    .workflow-step:hover {
        border-color: var(--primary-300);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .workflow-step:hover::before {
        transform: scaleY(1);
    }
    
    .step-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    
    .step-title {
        font-weight: 600;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .step-order {
        background: var(--primary-500);
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .step-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .step-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: var(--gray-600);
        margin-bottom: 0.5rem;
    }
    
    .step-description {
        color: var(--gray-700);
        font-size: 0.875rem;
        line-height: 1.5;
        margin: 0;
    }
    
    .role-badge {
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .role-admin {
        background: var(--danger-100);
        color: var(--danger-700);
    }
    
    .role-gestionnaire {
        background: var(--primary-100);
        color: var(--primary-700);
    }
    
    .role-consultant {
        background: var(--success-100);
        color: var(--success-700);
    }
    
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .sidebar-card {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInRight 0.6s ease-out;
        position: relative;
        overflow: hidden;
        transition: var(--transition-all);
    }
    
    .sidebar-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .sidebar-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .sidebar-card:hover::before {
        transform: scaleX(1);
    }
    
    .sidebar-card h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .sidebar-card h3 i {
        color: var(--primary-500);
    }
    
    .form-container {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        border: 1px solid var(--gray-200);
        margin-bottom: 1rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group:last-child {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--primary-500);
        width: 16px;
    }
    
    .form-label .required {
        color: var(--danger-500);
    }
    
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
        box-sizing: border-box;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-lg);
        font-weight: 500;
        transition: var(--transition-all);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
    }
    
    .btn-secondary:hover {
        background: var(--gray-200);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }
    
    .btn-danger {
        background: var(--danger-500);
        color: white;
    }
    
    .btn-danger:hover {
        background: var(--danger-600);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        animation: slideInDown 0.5s ease-out;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-700);
        border: 1px solid var(--success-200);
        border-left: 4px solid var(--success-500);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
        border-left: 4px solid var(--danger-500);
    }
    
    .alert i {
        font-size: 1.125rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: var(--gray-50);
        padding: 1rem;
        border-radius: var(--radius-lg);
        text-align: center;
        border: 1px solid var(--gray-200);
        transition: var(--transition-all);
    }
    
    .stat-card:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-600);
        display: block;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray-500);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .sortable-ghost {
        opacity: 0.5;
        background: var(--primary-50);
        border-color: var(--primary-300);
    }
    
    .drag-handle {
        cursor: grab;
        color: var(--gray-400);
        margin-right: 0.5rem;
    }
    
    .drag-handle:hover {
        color: var(--primary-500);
    }
    
    /* Animations */
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
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .main-content {
            grid-template-columns: 1fr;
        }
        
        .sidebar {
            order: -1;
        }
        
        .workflow-filters {
            flex-direction: column;
        }
        
        .filter-select {
            min-width: auto;
        }
    }
    
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .header-info {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .header-details h1 {
            font-size: 1.5rem;
        }
        
        .workflow-step {
            padding: 1rem;
        }
        
        .step-header {
            flex-direction: column;
            align-items: start;
            gap: 0.5rem;
        }
        
        .step-actions {
            align-self: flex-end;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
</head>
<body>
    <div class="workflow-config-page">
        <div class="workflow-config-container">
            <!-- Fil d'Ariane -->
            <nav class="breadcrumb">
                <a href="<?= BASE_URL ?>dashboard.php">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= BASE_URL ?>modules/workflow/">
                    <i class="fas fa-sitemap"></i>
                    Workflows
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Configuration</span>
            </nav>

            <!-- En-tête -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <div class="header-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="header-details">
                            <h1>Configuration des Workflows</h1>
                            <p class="subtitle">
                                <i class="fas fa-network-wired"></i>
                                Gérer les processus de validation et d'approbation
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button onclick="showNewWorkflowForm()" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouvelle étape
                        </button>
                        <a href="<?= BASE_URL ?>modules/workflow/automatic.php" class="btn btn-secondary">
                            <i class="fas fa-play-circle"></i>
                            Tester workflow
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages d'alerte -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Contenu principal -->
            <div class="main-content">
                <!-- Liste des workflows -->
                <div class="workflow-list-container">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-list-ol"></i>
                            Étapes de workflow configurées
                        </h2>
                    </div>

                    <!-- Filtres -->
                    <div class="workflow-filters">
                        <select id="typeFilter" class="filter-select">
                            <option value="">Tous les types de dossiers</option>
                            <?php foreach ($typesDossiers as $type): ?>
                                <option value="<?= $type ?>"><?= ucfirst(str_replace('_', ' ', $type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select id="roleFilter" class="filter-select">
                            <option value="">Tous les rôles</option>
                            <?php foreach ($roles as $roleId => $roleName): ?>
                                <option value="<?= $roleId ?>"><?= $roleName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Groupes de workflows -->
                    <div class="workflow-groups">
                        <?php
                        $groupedWorkflows = [];
                        foreach ($workflows as $workflow) {
                            $groupedWorkflows[$workflow['type_dossier']][] = $workflow;
                        }
                        
                        if (empty($groupedWorkflows)):
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-network-wired"></i>
                                <h3>Aucun workflow configuré</h3>
                                <p>Commencez par créer votre première étape de workflow.</p>
                                <button onclick="showNewWorkflowForm()" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i>
                                    Créer la première étape
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($groupedWorkflows as $typeDossier => $workflowSteps): ?>
                                <div class="workflow-group" data-type="<?= $typeDossier ?>">
                                    <div class="group-header">
                                        <h3 class="group-title">
                                            <i class="fas fa-folder"></i>
                                            <?= ucfirst(str_replace('_', ' ', $typeDossier)) ?>
                                        </h3>
                                        <div class="group-actions">
                                            <span class="role-badge role-gestionnaire">
                                                <?= count($workflowSteps) ?> étape<?= count($workflowSteps) > 1 ? 's' : '' ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="workflow-steps" data-type="<?= $typeDossier ?>">
                                        <?php foreach ($workflowSteps as $workflow): ?>
                                            <div class="workflow-step" data-id="<?= $workflow['id'] ?>" data-ordre="<?= $workflow['ordre'] ?>">
                                                <div class="step-header">
                                                    <div class="step-title">
                                                        <i class="fas fa-grip-vertical drag-handle"></i>
                                                        <span class="step-order"><?= $workflow['ordre'] ?></span>
                                                        <?= htmlspecialchars($workflow['etape']) ?>
                                                    </div>
                                                    <div class="step-actions">
                                                        <button onclick="editWorkflow(<?= $workflow['id'] ?>)" class="btn btn-secondary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="deleteWorkflow(<?= $workflow['id'] ?>)" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="step-meta">
                                                    <span>
                                                        <i class="fas fa-user-shield"></i>
                                                        <span class="role-badge role-<?= $workflow['role_requis'] == ROLE_ADMIN ? 'admin' : ($workflow['role_requis'] == ROLE_GESTIONNAIRE ? 'gestionnaire' : 'consultant') ?>">
                                                            <?= $roles[$workflow['role_requis']] ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($workflow['delai_jours'] > 0): ?>
                                                        <span>
                                                            <i class="fas fa-clock"></i>
                                                            <?= $workflow['delai_jours'] ?> jour<?= $workflow['delai_jours'] > 1 ? 's' : '' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($workflow['description'])): ?>
                                                    <p class="step-description"><?= htmlspecialchars($workflow['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Formulaire de création/édition -->
                    <div class="sidebar-card" id="workflowForm" style="display: none;">
                        <h3>
                            <i class="fas fa-plus-circle"></i>
                            <span id="formTitle">Nouvelle étape</span>
                        </h3>
                        
                        <form id="workflowFormElement" class="form-container">
                            <input type="hidden" id="workflowId" name="workflow_id">
                            
                            <div class="form-group">
                                <label for="typeDossier" class="form-label">
                                    <i class="fas fa-folder"></i>
                                    Type de dossier <span class="required">*</span>
                                </label>
                                <select id="typeDossier" name="type_dossier" class="form-select" required>
                                    <option value="">Sélectionner un type...</option>
                                    <?php foreach ($typesDossiers as $type): ?>
                                        <option value="<?= $type ?>"><?= ucfirst(str_replace('_', ' ', $type)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="etape" class="form-label">
                                    <i class="fas fa-tasks"></i>
                                    Nom de l'étape <span class="required">*</span>
                                </label>
                                <input type="text" id="etape" name="etape" class="form-input" required placeholder="Ex: Validation technique">
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left"></i>
                                    Description
                                </label>
                                <textarea id="description" name="description" class="form-textarea" placeholder="Description de l'étape..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="roleRequis" class="form-label">
                                    <i class="fas fa-user-shield"></i>
                                    Rôle requis <span class="required">*</span>
                                </label>
                                <select id="roleRequis" name="role_requis" class="form-select" required>
                                    <?php foreach ($roles as $roleId => $roleName): ?>
                                        <option value="<?= $roleId ?>"><?= $roleName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="ordre" class="form-label">
                                    <i class="fas fa-sort-numeric-up"></i>
                                    Ordre d'exécution
                                </label>
                                <input type="number" id="ordre" name="ordre" class="form-input" min="1" value="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="delaiJours" class="form-label">
                                    <i class="fas fa-clock"></i>
                                    Délai (jours)
                                </label>
                                <input type="number" id="delaiJours" name="delai_jours" class="form-input" min="0" value="0" placeholder="0 = pas de délai">
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-save"></i>
                                    Sauvegarder
                                </button>
                                <button type="button" onclick="cancelForm()" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistiques -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-chart-bar"></i>
                            Statistiques
                        </h3>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <span class="stat-number"><?= count($workflows) ?></span>
                                <span class="stat-label">Étapes totales</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?= count($groupedWorkflows) ?></span>
                                <span class="stat-label">Types configurés</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <h4 style="font-size: 0.875rem; color: var(--gray-700); margin-bottom: 0.5rem;">Répartition par type :</h4>
                            <?php foreach ($groupedWorkflows as $type => $steps): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem; font-size: 0.75rem;">
                                    <span><?= ucfirst(str_replace('_', ' ', $type)) ?></span>
                                    <span class="role-badge role-consultant"><?= count($steps) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Aide -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-question-circle"></i>
                            Guide d'utilisation
                        </h3>
                        
                        <div style="color: var(--gray-700); font-size: 0.875rem; line-height: 1.5;">
                            <p><strong>Créer une étape :</strong> Cliquez sur "Nouvelle étape" pour ajouter une étape au workflow.</p>
                            
                            <p><strong>Réorganiser :</strong> Glissez-déposez les étapes pour changer leur ordre d'exécution.</p>
                            
                            <p><strong>Rôles :</strong> Définissez qui peut valider chaque étape selon les permissions.</p>
                            
                            <p><strong>Délais :</strong> Optionnel - délai maximum pour valider une étape.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script>
        // Variables globales
        let currentEditId = null;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeSortable();
            initializeFilters();
            animateOnLoad();
        });
        
        // Animation des éléments au chargement
        function animateOnLoad() {
            const cards = document.querySelectorAll('.workflow-group');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // Initialiser le drag & drop
        function initializeSortable() {
            const stepContainers = document.querySelectorAll('.workflow-steps');
            
            stepContainers.forEach(container => {
                new Sortable(container, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    handle: '.drag-handle',
                    onEnd: function(evt) {
                        updateStepOrder(container);
                    }
                });
            });
        }
        
        // Mettre à jour l'ordre des étapes
        function updateStepOrder(container) {
            const steps = container.querySelectorAll('.workflow-step');
            const stepsData = [];
            
            steps.forEach((step, index) => {
                const id = step.dataset.id;
                const newOrder = index + 1;
                
                // Mettre à jour visuellement l'ordre
                const orderElement = step.querySelector('.step-order');
                orderElement.textContent = newOrder;
                step.dataset.ordre = newOrder;
                
                stepsData.push({
                    id: id,
                    ordre: newOrder
                });
            });
            
            // Envoyer au serveur
            fetch('config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'reorder_steps',
                    steps: JSON.stringify(stepsData)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur lors de la mise à jour : ' + data.message);
                    location.reload(); // Recharger en cas d'erreur
                }
            });
        }
        
        // Initialiser les filtres
        function initializeFilters() {
            const typeFilter = document.getElementById('typeFilter');
            const roleFilter = document.getElementById('roleFilter');
            
            typeFilter.addEventListener('change', applyFilters);
            roleFilter.addEventListener('change', applyFilters);
        }
        
        // Appliquer les filtres
        function applyFilters() {
            const typeFilter = document.getElementById('typeFilter').value;
            const roleFilter = document.getElementById('roleFilter').value;
            const groups = document.querySelectorAll('.workflow-group');
            
            groups.forEach(group => {
                let showGroup = true;
                
                // Filtre par type
                if (typeFilter && group.dataset.type !== typeFilter) {
                    showGroup = false;
                }
                
                // Filtre par rôle
                if (roleFilter && showGroup) {
                    const steps = group.querySelectorAll('.workflow-step');
                    let hasMatchingRole = false;
                    
                    steps.forEach(step => {
                        const roleBadge = step.querySelector('.role-badge');
                        if (roleBadge && roleBadge.classList.contains('role-' + getRoleClass(roleFilter))) {
                            hasMatchingRole = true;
                        }
                    });
                    
                    if (!hasMatchingRole) {
                        showGroup = false;
                    }
                }
                
                // Afficher/masquer le groupe
                group.style.display = showGroup ? 'block' : 'none';
            });
        }
        
        // Obtenir la classe CSS pour un rôle
        function getRoleClass(roleId) {
            const roleClasses = {
                '<?= ROLE_ADMIN ?>': 'admin',
                '<?= ROLE_GESTIONNAIRE ?>': 'gestionnaire',
                '<?= ROLE_CONSULTANT ?>': 'consultant'
            };
            return roleClasses[roleId] || 'consultant';
        }
        
        // Afficher le formulaire de nouvelle étape
        function showNewWorkflowForm() {
            currentEditId = null;
            document.getElementById('formTitle').textContent = 'Nouvelle étape';
            document.getElementById('workflowForm').style.display = 'block';
            document.getElementById('workflowFormElement').reset();
            document.getElementById('workflowId').value = '';
            
            // Animation d'apparition
            const form = document.getElementById('workflowForm');
            form.style.opacity = '0';
            form.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                form.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 10);
        }
        
        // Éditer une étape existante
        function editWorkflow(id) {
            currentEditId = id;
            document.getElementById('formTitle').textContent = 'Modifier l\'étape';
            document.getElementById('workflowForm').style.display = 'block';
            
            // Récupérer les données de l'étape depuis le DOM
            const stepElement = document.querySelector(`[data-id="${id}"]`);
            if (!stepElement) return;
            
            const title = stepElement.querySelector('.step-title').textContent.trim();
            const description = stepElement.querySelector('.step-description');
            const roleBadge = stepElement.querySelector('.role-badge');
            const ordre = stepElement.dataset.ordre;
            
            // Remplir le formulaire
            document.getElementById('workflowId').value = id;
            document.getElementById('etape').value = title.replace(/^\d+\s+/, ''); // Enlever le numéro d'ordre
            if (description) {
                document.getElementById('description').value = description.textContent;
            }
            document.getElementById('ordre').value = ordre;
            
            // Déterminer le type depuis le groupe parent
            const group = stepElement.closest('.workflow-group');
            if (group) {
                document.getElementById('typeDossier').value = group.dataset.type;
            }
            
            // Déterminer le rôle depuis la classe CSS
            if (roleBadge) {
                let roleValue = '<?= ROLE_CONSULTANT ?>';
                if (roleBadge.classList.contains('role-admin')) {
                    roleValue = '<?= ROLE_ADMIN ?>';
                } else if (roleBadge.classList.contains('role-gestionnaire')) {
                    roleValue = '<?= ROLE_GESTIONNAIRE ?>';
                }
                document.getElementById('roleRequis').value = roleValue;
            }
        }
        
        // Supprimer une étape
        function deleteWorkflow(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette étape de workflow ?')) {
                return;
            }
            
            fetch('config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'delete_workflow',
                    workflow_id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de suppression
                    const element = document.querySelector(`[data-id="${id}"]`);
                    element.style.transition = 'all 0.3s ease';
                    element.style.opacity = '0';
                    element.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Annuler le formulaire
        function cancelForm() {
            document.getElementById('workflowForm').style.display = 'none';
            document.getElementById('workflowFormElement').reset();
            currentEditId = null;
        }
        
        // Gestionnaire de soumission du formulaire
        document.getElementById('workflowFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax_action', 'save_workflow');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
            submitBtn.disabled = true;
            
            fetch('config.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de succès
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Sauvegardé !';
                    submitBtn.style.background = 'var(--success-500)';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Erreur : ' + data.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                alert('Erreur de connexion : ' + error.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Fermer le formulaire en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            const form = document.getElementById('workflowForm');
            const formElement = document.getElementById('workflowFormElement');
            
            if (form.style.display !== 'none' && 
                !formElement.contains(e.target) && 
                !e.target.closest('button[onclick*="showNewWorkflowForm"]') &&
                !e.target.closest('button[onclick*="editWorkflow"]')) {
                
                if (confirm('Fermer le formulaire sans sauvegarder ?')) {
                    cancelForm();
                }
            }
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
