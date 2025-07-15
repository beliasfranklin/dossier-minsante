<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/PreferencesManager.php';

requireRole(ROLE_GESTIONNAIRE);

// Gestion création catégorie (POST classique)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    try {
        $nom = trim($_POST['nom']);
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $couleur = !empty($_POST['couleur']) ? $_POST['couleur'] : '#2980b9';
        $icone = !empty($_POST['icone']) ? $_POST['icone'] : 'folder';
        if (empty($nom)) {
            throw new Exception("Le nom de la catégorie est obligatoire");
        }
        // Vérifier les doublons
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
        $stmt->execute([$nom, $parent_id, $parent_id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Une catégorie avec ce nom existe déjà à ce niveau");
        }
        $stmt = $pdo->prepare("INSERT INTO categories (nom, parent_id, couleur, icone, actif) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$nom, $parent_id, $couleur, $icone]);
        logAction($_SESSION['user_id'], 'CREATE_CATEGORY', null, "Création catégorie: $nom");
        $_SESSION['success'] = "Catégorie créée avec succès";
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE categories SET actif = NOT actif WHERE id = ?");
                $stmt->execute([$id]);
                
                logAction($_SESSION['user_id'], 'TOGGLE_CATEGORY_STATUS', null, "Basculer statut catégorie ID: $id");
                echo json_encode(['success' => true, 'message' => 'Statut modifié']);
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Vérifier s'il y a des dossiers liés
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE category_id = ?");
                $stmt->execute([$id]);
                $count_dossiers = $stmt->fetchColumn();
                
                // Vérifier s'il y a des sous-catégories
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
                $stmt->execute([$id]);
                $count_children = $stmt->fetchColumn();
                
                if ($count_dossiers > 0) {
                    throw new Exception("Impossible de supprimer: $count_dossiers dossier(s) utilisent cette catégorie");
                }
                
                if ($count_children > 0) {
                    throw new Exception("Impossible de supprimer: cette catégorie a des sous-catégories");
                }
                
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                
                logAction($_SESSION['user_id'], 'DELETE_CATEGORY', null, "Suppression catégorie ID: $id");
                echo json_encode(['success' => true, 'message' => 'Catégorie supprimée']);
                break;
                
            default:
                throw new Exception("Action non reconnue");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Récupération des catégories avec hiérarchie
$sql = "
    SELECT 
        c.*,
        p.nom as parent_nom,
        COUNT(d.id) as nb_dossiers,
        COUNT(sub.id) as nb_sous_categories
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    LEFT JOIN dossiers d ON c.id = d.category_id
    LEFT JOIN categories sub ON c.id = sub.parent_id
    GROUP BY c.id
    ORDER BY p.nom ASC, c.nom ASC
";

$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);
$themeVars = $preferencesManager->getThemeVariables();

$pageTitle = "Gestion des Catégories";
include '../../includes/header.php';
// Récupérer les catégories parent pour le select
$categories_parent = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL AND actif = 1 ORDER BY nom")->fetchAll();
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables modernes pour catégories */
    --categories-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.categories-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.categories-header {
    background: var(--categories-gradient);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.categories-header::before {
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
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    margin-bottom: 2rem;
}

.categories-table-card, .categories-form-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.8);
    transition: var(--transition);
}

.categories-table-card:hover, .categories-form-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: between;
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
    background: var(--categories-gradient);
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

.category-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    font-weight: 600;
}

.category-details {
    flex: 1;
}

.category-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    color: #2d3748;
}

.category-description {
    font-size: 0.85rem;
    color: #6c757d;
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
    background: var(--categories-gradient);
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

.badge-danger {
    background: var(--danger-gradient);
    color: white;
}

.color-preview {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    display: inline-block;
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

.color-input-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.form-color {
    width: 60px;
    height: 50px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: var(--transition);
}

.form-color:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.icon-input-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.icon-preview {
    width: 50px;
    height: 50px;
    background: #f8f9fa;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #667eea;
    transition: var(--transition);
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
    background: var(--categories-gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-secondary:hover {
    background: #cbd5e0;
    transform: translateY(-3px);
}

.btn-block {
    width: 100%;
    justify-content: center;
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

.hierarchy-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.parent-indicator {
    padding: 0.25rem 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 0.8rem;
}

/* Scrollbar personnalisée */
.categories-table-card::-webkit-scrollbar {
    width: 8px;
}

.categories-table-card::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.categories-table-card::-webkit-scrollbar-thumb {
    background: var(--categories-gradient);
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

.categories-table-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.1s;
}

.categories-form-card {
    animation: fadeInUp 0.6s ease forwards;
    animation-delay: 0.2s;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .categories-container {
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
    .categories-header {
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
}

@media (max-width: 480px) {
    .header-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .category-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
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
    
    .categories-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
    
    .categories-table-card,
    .categories-form-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<div class="categories-container">
    <!-- En-tête moderne -->
    <div class="categories-header">
        <div class="header-content">
            <div class="header-main">
                <div class="header-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="header-text">
                    <h1>Gestion des Catégories</h1>
                    <p>Organisez et structurez vos dossiers avec un système de catégories hiérarchique</p>
                </div>
            </div>
            <div class="header-stats">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_categories,
                        COUNT(CASE WHEN actif = 1 THEN 1 END) as categories_actives,
                        COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as categories_principales
                    FROM categories
                ");
                $stats = $stmt->fetch();
                ?>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['total_categories'] ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['categories_actives'] ?></span>
                    <span class="stat-label">Actives</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['categories_principales'] ?></span>
                    <span class="stat-label">Principales</span>
                </div>
            </div>
        </div>
    </div>

    <div class="content-layout">
        <!-- Table des catégories -->
        <div class="categories-table-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Catégories existantes
                </h3>
            </div>
            
            <div class="card-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div style="padding: 1rem 2rem;">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= $_SESSION['success'] ?>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <div style="overflow-x: auto; max-height: 600px;">
                    <table class="modern-table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th>Hiérarchie</th>
                                <th>Couleur</th>
                                <th>Dossiers</th>
                                <th>Sous-cat.</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr class="<?= $cat['actif'] ? 'row-active' : 'row-inactive' ?>" data-category-id="<?= $cat['id'] ?>">
                                    <td>
                                        <div class="category-info">
                                            <div class="category-icon" style="background-color: <?= htmlspecialchars($cat['couleur']) ?>">
                                                <i class="fas fa-<?= htmlspecialchars($cat['icone']) ?>"></i>
                                            </div>
                                            <div class="category-details">
                                                <div class="category-name">
                                                    <?= htmlspecialchars($cat['nom']) ?>
                                                </div>
                                                <?php if (!$cat['actif']): ?>
                                                    <div class="category-description">Catégorie désactivée</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($cat['parent_nom']): ?>
                                            <div class="hierarchy-indicator">
                                                <i class="fas fa-arrow-right"></i>
                                                <span class="parent-indicator">
                                                    <?= htmlspecialchars($cat['parent_nom']) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-primary">
                                                <i class="fas fa-crown"></i> Principale
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="color-preview" style="background-color: <?= htmlspecialchars($cat['couleur']) ?>" title="<?= htmlspecialchars($cat['couleur']) ?>"></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <i class="fas fa-folder"></i> <?= $cat['nb_dossiers'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-sitemap"></i> <?= $cat['nb_sous_categories'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $cat['actif'] ? 'success' : 'secondary' ?>">
                                            <i class="fas fa-<?= $cat['actif'] ? 'check-circle' : 'pause-circle' ?>"></i>
                                            <?= $cat['actif'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-action btn-edit edit-category" 
                                                    data-id="<?= $cat['id'] ?>"
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn-action btn-toggle toggle-status" 
                                                    data-id="<?= $cat['id'] ?>" 
                                                    title="<?= $cat['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                <i class="fas fa-<?= $cat['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                            <?php if ($cat['nb_dossiers'] == 0 && $cat['nb_sous_categories'] == 0): ?>
                                                <button type="button" class="btn-action btn-delete delete-category" 
                                                        data-id="<?= $cat['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($cat['nom']) ?>" 
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Formulaire de création -->
        <div class="categories-form-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-plus-circle"></i>
                    Nouvelle Catégorie
                </h3>
            </div>
            
            <div class="card-content">
                <div class="form-container">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="modern-form" id="categoryForm">
                        <input type="hidden" name="create_category" value="1">
                        
                        <div class="form-group">
                            <label for="nom" class="form-label">
                                <i class="fas fa-tag"></i> Nom de la catégorie
                            </label>
                            <input type="text" class="form-input" id="nom" name="nom" 
                                   required maxlength="100" 
                                   value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>"
                                   placeholder="Ex: Documents administratifs">
                            <div class="form-help">
                                <i class="fas fa-info-circle"></i> Nom descriptif pour identifier la catégorie
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_id" class="form-label">
                                <i class="fas fa-sitemap"></i> Catégorie parent
                            </label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Catégorie principale --</option>
                                <?php foreach ($categories_parent as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['parent_id']) && $_POST['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">
                                <i class="fas fa-lightbulb"></i> Laissez vide pour une catégorie principale
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="couleur" class="form-label">
                                <i class="fas fa-palette"></i> Couleur d'identification
                            </label>
                            <div class="color-input-wrapper">
                                <input type="color" class="form-color" id="couleur" name="couleur" 
                                       value="<?= isset($_POST['couleur']) ? $_POST['couleur'] : '#667eea' ?>">
                                <input type="text" class="form-input" id="couleur_text" 
                                       value="<?= isset($_POST['couleur']) ? $_POST['couleur'] : '#667eea' ?>" 
                                       style="flex: 1;" readonly>
                            </div>
                            <div class="form-help">
                                <i class="fas fa-eye"></i> Couleur pour identifier visuellement la catégorie
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="icone" class="form-label">
                                <i class="fas fa-icons"></i> Icône FontAwesome
                            </label>
                            <div class="icon-input-wrapper">
                                <div class="icon-preview" id="icon-preview-display">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <input type="text" class="form-input" id="icone" name="icone" 
                                       value="<?= isset($_POST['icone']) ? htmlspecialchars($_POST['icone']) : 'folder' ?>"
                                       placeholder="Ex: folder, file-text, building" style="flex: 1;">
                            </div>
                            <div class="form-help">
                                <i class="fas fa-question-circle"></i> Nom de l'icône FontAwesome sans le préfixe "fa-"
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i>
                            Créer la catégorie
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🏷️ Gestion des Catégories initialisée');
    
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

        document.querySelectorAll('.categories-table-card, .categories-form-card').forEach(el => {
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
    
    // Gestion de l'aperçu de couleur
    const couleurInput = document.getElementById('couleur');
    const couleurText = document.getElementById('couleur_text');
    
    if (couleurInput && couleurText) {
        couleurInput.addEventListener('input', function() {
            couleurText.value = this.value;
            
            // Animation de mise à jour
            couleurText.style.transform = 'scale(1.05)';
            setTimeout(() => couleurText.style.transform = 'scale(1)', 200);
        });
        
        couleurText.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                couleurInput.value = this.value;
            }
        });
    }
    
    // Gestion de l'aperçu d'icône
    const iconeInput = document.getElementById('icone');
    const iconPreview = document.getElementById('icon-preview-display');
    
    if (iconeInput && iconPreview) {
        iconeInput.addEventListener('input', function() {
            const iconName = this.value.trim();
            const iconElement = iconPreview.querySelector('i');
            
            if (iconName) {
                iconElement.className = `fas fa-${iconName}`;
                
                // Animation de changement d'icône
                iconPreview.style.transform = 'scale(0.8)';
                iconPreview.style.opacity = '0.7';
                setTimeout(() => {
                    iconPreview.style.transform = 'scale(1)';
                    iconPreview.style.opacity = '1';
                }, 150);
            } else {
                iconElement.className = 'fas fa-folder';
            }
        });
    }
    
    // Gestion du formulaire de catégorie
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        // Validation côté client
        const nom = document.getElementById('nom').value.trim();
        
        if (!nom) {
            e.preventDefault();
            showNotification('⚠️ Le nom de la catégorie est obligatoire', 'warning');
            document.getElementById('nom').focus();
            return;
        }
        
        // Animation du bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Création...';
        submitBtn.disabled = true;
        
        // Restaurer le bouton en cas d'erreur côté serveur
        setTimeout(() => {
            if (submitBtn.disabled) {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            }
        }, 5000);
    });
    
    // Toggle status des catégories
    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            
            const formData = new FormData();
            formData.append('action', 'toggle_status');
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
    
    // Suppression des catégories
    document.querySelectorAll('.delete-category').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
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
                        <h3 style="color: #2d3748; margin-bottom: 1rem;">Supprimer la catégorie</h3>
                        <p style="color: #6c757d; margin-bottom: 1rem;">
                            Êtes-vous sûr de vouloir supprimer la catégorie
                        </p>
                        <p style="color: #2d3748; font-weight: 600; margin-bottom: 2rem;">
                            "${name}" ?
                        </p>
                        <p style="color: #ff4757; font-size: 0.9rem; margin-bottom: 2rem;">
                            ⚠️ Cette action est irréversible
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
                formData.append('action', 'delete');
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
    
    // Validation en temps réel du nom
    const nomInput = document.getElementById('nom');
    if (nomInput) {
        nomInput.addEventListener('input', function() {
            const value = this.value.trim();
            
            if (value.length < 2) {
                this.classList.add('form-input-error');
                this.style.borderColor = '#ff4757';
            } else if (value.length > 100) {
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
    
    // Filtrage et recherche rapide
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = '🔍 Rechercher une catégorie...';
    searchInput.style.cssText = `
        width: 100%; padding: 0.75rem 1rem; margin-bottom: 1rem;
        border: 2px solid #e2e8f0; border-radius: 12px;
        font-size: 1rem; transition: all 0.3s ease;
    `;
    
    const tableContainer = document.querySelector('.categories-table-card .card-content');
    if (tableContainer) {
        const firstChild = tableContainer.firstChild;
        tableContainer.insertBefore(searchInput, firstChild);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.modern-table tbody tr');
            
            rows.forEach(row => {
                const categoryName = row.querySelector('.category-name').textContent.toLowerCase();
                const parentText = row.querySelector('.hierarchy-indicator, .badge-primary');
                const parentName = parentText ? parentText.textContent.toLowerCase() : '';
                
                if (categoryName.includes(searchTerm) || parentName.includes(searchTerm)) {
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
    
    console.log('✅ Gestion des Catégories prête !');
});
</script>

<?php include '../../includes/footer.php'; ?>
