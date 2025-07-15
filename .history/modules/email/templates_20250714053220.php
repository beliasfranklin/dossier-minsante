<?php
/**
 * Gestion des templates d'email
 * Interface moderne pour créer, modifier et gérer les modèles d'email
 */

require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/preferences.php';

// Vérifier l'authentification
requireAuth();

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);

// Variables pour la gestion des templates
$success = false;
$error = '';
$message = '';
$editingTemplate = null;

// Créer la table des templates si elle n'existe pas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            variables TEXT,
            category ENUM('system', 'appointment', 'reminder', 'notification', 'custom') DEFAULT 'custom',
            is_active BOOLEAN DEFAULT 1,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_created_by (created_by),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    error_log("Erreur création table templates: " . $e->getMessage());
}

// Traitement des actions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
        case 'update':
            $name = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = $_POST['category'] ?? 'custom';
            $variables = trim($_POST['variables'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($subject) || empty($content)) {
                $error = 'Veuillez remplir tous les champs obligatoires.';
            } else {
                try {
                    if ($_POST['action'] === 'create') {
                        $stmt = $pdo->prepare("
                            INSERT INTO email_templates (name, subject, content, variables, category, is_active, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $subject, $content, $variables, $category, $is_active, $_SESSION['user_id']]);
                        $success = true;
                        $message = 'Template créé avec succès !';
                    } else {
                        $id = intval($_POST['id'] ?? 0);
                        $stmt = $pdo->prepare("
                            UPDATE email_templates 
                            SET name=?, subject=?, content=?, variables=?, category=?, is_active=?
                            WHERE id=? AND created_by=?
                        ");
                        $stmt->execute([$name, $subject, $content, $variables, $category, $is_active, $id, $_SESSION['user_id']]);
                        $success = true;
                        $message = 'Template mis à jour avec succès !';
                    }
                } catch (Exception $e) {
                    $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id=? AND created_by=?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                $success = true;
                $message = 'Template supprimé avec succès !';
            } catch (Exception $e) {
                $error = 'Erreur lors de la suppression : ' . $e->getMessage();
            }
            break;
            
        case 'duplicate':
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates (name, subject, content, variables, category, is_active, created_by)
                    SELECT CONCAT(name, ' - Copie'), subject, content, variables, category, 0, ?
                    FROM email_templates WHERE id=?
                ");
                $stmt->execute([$_SESSION['user_id'], $id]);
                $success = true;
                $message = 'Template dupliqué avec succès !';
            } catch (Exception $e) {
                $error = 'Erreur lors de la duplication : ' . $e->getMessage();
            }
            break;
    }
    
    if ($success) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = $error;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Récupérer le template à éditer
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id=? AND created_by=?");
        $stmt->execute([$editId, $_SESSION['user_id']]);
        $editingTemplate = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur récupération template: " . $e->getMessage());
    }
}

// Récupérer la liste des templates
try {
    $filterCategory = $_GET['category'] ?? '';
    $searchTerm = $_GET['search'] ?? '';
    
    $sql = "
        SELECT t.*, u.nom as creator_name
        FROM email_templates t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.created_by = ?
    ";
    $params = [$_SESSION['user_id']];
    
    if ($filterCategory) {
        $sql .= " AND t.category = ?";
        $params[] = $filterCategory;
    }
    
    if ($searchTerm) {
        $sql .= " AND (t.name LIKE ? OR t.subject LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            COUNT(DISTINCT category) as categories
        FROM email_templates 
        WHERE created_by = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $templates = [];
    $stats = ['total' => 0, 'active' => 0, 'categories' => 0];
    error_log("Erreur récupération templates: " . $e->getMessage());
}

$themeVars = $preferencesManager->getThemeVariables();
$pageTitle = "Gestion des Templates Email";
require_once '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Variables couleurs modernes */
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --email-blue: #1a73e8;
    --email-green: #34a853;
    --email-orange: #ea4335;
    --email-purple: #9c27b0;
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

.email-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeInUp 0.6s ease forwards;
}

.email-header {
    background: var(--gradient-primary);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
}

.email-header::before {
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

.email-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.email-header p {
    font-size: 1.2rem;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow-soft);
    transition: var(--transition);
    border: 1px solid rgba(255,255,255,0.8);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
}

.stat-value {
    font-size: 3rem;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.toolbar {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-soft);
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
}

.search-filters {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
}

.search-input {
    flex: 1;
    max-width: 400px;
    padding: 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    font-size: 1rem;
    transition: var(--transition);
    background: #fafbfc;
}

.search-input:focus {
    outline: none;
    border-color: var(--email-blue);
    background: white;
    box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.1);
}

.filter-select {
    padding: 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    background: white;
    font-size: 1rem;
    cursor: pointer;
    transition: var(--transition);
}

.filter-select:focus {
    outline: none;
    border-color: var(--email-blue);
    box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.1);
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
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: var(--gradient-success);
    color: white;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
}

.btn-warning {
    background: var(--gradient-warning);
    color: white;
    box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
}

.btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.main-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
}

.templates-section {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-header i {
    font-size: 1.5rem;
    color: var(--email-blue);
}

.templates-grid {
    padding: 1.5rem;
    display: grid;
    gap: 1rem;
    max-height: 70vh;
    overflow-y: auto;
}

.template-card {
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    padding: 1.5rem;
    transition: var(--transition);
    background: #fafbfc;
    position: relative;
}

.template-card:hover {
    border-color: var(--email-blue);
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    background: white;
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.template-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.template-category {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-system { background: #e3f2fd; color: #1976d2; }
.category-appointment { background: #e8f5e8; color: #2e7d32; }
.category-reminder { background: #fff3e0; color: #f57c00; }
.category-notification { background: #fce4ec; color: #c2185b; }
.category-custom { background: #f3e5f5; color: #7b1fa2; }

.template-subject {
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    font-style: italic;
}

.template-preview {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.template-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e1e8ed;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.template-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-active {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--email-green);
}

.status-inactive {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ccc;
}

.template-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    font-size: 0.9rem;
}

.action-edit {
    background: #e3f2fd;
    color: #1976d2;
}

.action-edit:hover {
    background: #1976d2;
    color: white;
}

.action-duplicate {
    background: #e8f5e8;
    color: #2e7d32;
}

.action-duplicate:hover {
    background: #2e7d32;
    color: white;
}

.action-delete {
    background: #ffebee;
    color: #d32f2f;
}

.action-delete:hover {
    background: #d32f2f;
    color: white;
}

.editor-panel {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    position: sticky;
    top: 2rem;
    max-height: calc(100vh - 4rem);
    display: flex;
    flex-direction: column;
}

.editor-header {
    background: var(--gradient-info);
    color: white;
    padding: 1.5rem;
    font-weight: 700;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.editor-form {
    padding: 1.5rem;
    flex: 1;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    font-size: 1rem;
    transition: var(--transition);
    background: #fafbfc;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--email-blue);
    background: white;
    box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.1);
}

.form-control-textarea {
    min-height: 200px;
    resize: vertical;
    font-family: 'Courier New', monospace;
}

.form-control-small {
    min-height: 120px;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    transition: var(--transition);
    cursor: pointer;
    border: 2px solid #e1e8ed;
}

.checkbox-wrapper:hover {
    background: #f8f9fa;
    border-color: var(--email-blue);
}

.checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.variables-help {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.variables-help h5 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.variables-help code {
    background: #e9ecef;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
}

.alert {
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    border: none;
    box-shadow: var(--shadow-soft);
}

.alert i {
    font-size: 1.5rem;
}

.alert-success {
    background: var(--gradient-success);
    color: white;
}

.alert-error {
    background: var(--gradient-warning);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
    color: var(--email-blue);
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
    font-size: 1.5rem;
}

.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

/* Scrollbar personnalisée */
.templates-grid::-webkit-scrollbar,
.editor-form::-webkit-scrollbar {
    width: 8px;
}

.templates-grid::-webkit-scrollbar-track,
.editor-form::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.templates-grid::-webkit-scrollbar-thumb,
.editor-form::-webkit-scrollbar-thumb {
    background: var(--gradient-primary);
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

.stat-card {
    animation: fadeInUp 0.6s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }

.template-card {
    animation: fadeInUp 0.4s ease forwards;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .main-content {
        grid-template-columns: 1fr;
    }
    
    .editor-panel {
        position: relative;
        max-height: none;
    }
    
    .email-container {
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .email-header {
        padding: 2rem 1.5rem;
    }
    
    .email-header h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-filters {
        flex-direction: column;
    }
    
    .search-input {
        max-width: none;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-value {
        font-size: 2.5rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .template-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Print styles */
@media print {
    .toolbar,
    .editor-panel,
    .template-actions {
        display: none;
    }
    
    .email-header {
        background: #f8f9fa !important;
        color: #333 !important;
    }
}
</style>

<div class="email-container">
    <!-- En-tête -->
    <div class="email-header">
        <h1>
            <i class="fas fa-envelope"></i>
            Gestion des Templates Email
        </h1>
        <p>Créez et gérez vos modèles d'emails professionnels</p>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Templates</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['active'] ?></div>
            <div class="stat-label">Templates Actifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['categories'] ?></div>
            <div class="stat-label">Catégories</div>
        </div>
    </div>

    <!-- Barre d'outils -->
    <div class="toolbar">
        <div class="search-filters">
            <form method="GET" style="display: flex; gap: 1rem; flex: 1;">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="🔍 Rechercher un template..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    <option value="system" <?= ($_GET['category'] ?? '') === 'system' ? 'selected' : '' ?>>Système</option>
                    <option value="appointment" <?= ($_GET['category'] ?? '') === 'appointment' ? 'selected' : '' ?>>Rendez-vous</option>
                    <option value="reminder" <?= ($_GET['category'] ?? '') === 'reminder' ? 'selected' : '' ?>>Rappel</option>
                    <option value="notification" <?= ($_GET['category'] ?? '') === 'notification' ? 'selected' : '' ?>>Notification</option>
                    <option value="custom" <?= ($_GET['category'] ?? '') === 'custom' ? 'selected' : '' ?>>Personnalisé</option>
                </select>
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <button onclick="resetForm()" class="btn btn-success">
            <i class="fas fa-plus"></i>
            Nouveau Template
        </button>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Liste des templates -->
        <div class="templates-section">
            <div class="section-header">
                <i class="fas fa-list"></i>
                Mes Templates
            </div>
            
            <div class="templates-grid">
                <?php if (empty($templates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <h3>Aucun template trouvé</h3>
                        <p>Commencez par créer votre premier template d'email</p>
                        <button onclick="resetForm()" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer un template
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card">
                            <div class="template-header">
                                <div>
                                    <div class="template-title"><?= htmlspecialchars($template['name']) ?></div>
                                    <span class="template-category category-<?= $template['category'] ?>">
                                        <?= ucfirst($template['category']) ?>
                                    </span>
                                </div>
                                <div class="template-actions">
                                    <button onclick="editTemplate(<?= $template['id'] ?>)" class="action-btn action-edit" title="Éditer">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="duplicateTemplate(<?= $template['id'] ?>)" class="action-btn action-duplicate" title="Dupliquer">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button onclick="deleteTemplate(<?= $template['id'] ?>)" class="action-btn action-delete" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="template-subject">
                                "<?= htmlspecialchars($template['subject']) ?>"
                            </div>
                            
                            <div class="template-preview">
                                <?= htmlspecialchars(substr(strip_tags($template['content']), 0, 150)) ?>
                                <?= strlen(strip_tags($template['content'])) > 150 ? '...' : '' ?>
                            </div>
                            
                            <div class="template-meta">
                                <div class="template-status">
                                    <div class="<?= $template['is_active'] ? 'status-active' : 'status-inactive' ?>"></div>
                                    <?= $template['is_active'] ? 'Actif' : 'Inactif' ?>
                                </div>
                                <div>
                                    <?= date('d/m/Y', strtotime($template['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel d'édition -->
        <div class="editor-panel">
            <div class="editor-header">
                <i class="fas fa-edit"></i>
                <span id="editorTitle">Nouveau Template</span>
            </div>
            
            <form method="POST" class="editor-form" id="templateForm">
                <input type="hidden" name="action" value="create" id="formAction">
                <input type="hidden" name="id" value="" id="templateId">
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Nom du template *
                    </label>
                    <input type="text" 
                           name="name" 
                           class="form-control" 
                           placeholder="Ex: Confirmation de rendez-vous"
                           id="templateName"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-folder"></i> Catégorie
                    </label>
                    <select name="category" class="form-control" id="templateCategory">
                        <option value="custom">Personnalisé</option>
                        <option value="system">Système</option>
                        <option value="appointment">Rendez-vous</option>
                        <option value="reminder">Rappel</option>
                        <option value="notification">Notification</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-text-width"></i> Sujet de l'email *
                    </label>
                    <input type="text" 
                           name="subject" 
                           class="form-control" 
                           placeholder="Ex: Votre rendez-vous du {{date}}"
                           id="templateSubject"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-file-alt"></i> Contenu de l'email *
                    </label>
                    <textarea name="content" 
                              class="form-control form-control-textarea" 
                              placeholder="Saisissez le contenu de votre email..."
                              id="templateContent"
                              required></textarea>
                    
                    <div class="variables-help">
                        <h5><i class="fas fa-info-circle"></i> Variables disponibles :</h5>
                        <p>
                            <code>{{nom}}</code> <code>{{prenom}}</code> <code>{{email}}</code> 
                            <code>{{telephone}}</code> <code>{{date}}</code> <code>{{heure}}</code>
                            <code>{{numero_dossier}}</code> <code>{{medecin}}</code>
                        </p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-code"></i> Variables personnalisées
                    </label>
                    <textarea name="variables" 
                              class="form-control form-control-small" 
                              placeholder="Variables supplémentaires (JSON format)&#10;Ex: {&quot;clinique&quot;: &quot;Centre Médical&quot;, &quot;adresse&quot;: &quot;123 Rue de la Santé&quot;}"
                              id="templateVariables"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_active" id="templateActive" checked>
                        <span>
                            <i class="fas fa-toggle-on"></i>
                            Template actif
                        </span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i>
                        Sauvegarder
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestion du formulaire d'édition
function resetForm() {
    document.getElementById('templateForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('templateId').value = '';
    document.getElementById('editorTitle').textContent = 'Nouveau Template';
    document.getElementById('templateActive').checked = true;
    
    // Scroll vers le formulaire sur mobile
    if (window.innerWidth <= 1024) {
        document.querySelector('.editor-panel').scrollIntoView({ behavior: 'smooth' });
    }
}

function editTemplate(id) {
    // Récupérer les données du template via AJAX ou directement depuis PHP
    const templateCard = document.querySelector(`[onclick="editTemplate(${id})"]`).closest('.template-card');
    const name = templateCard.querySelector('.template-title').textContent.trim();
    const category = templateCard.querySelector('.template-category').textContent.toLowerCase().trim();
    
    // Rediriger vers la page avec le paramètre edit
    window.location.href = `?edit=${id}`;
}

function duplicateTemplate(id) {
    if (confirm('Voulez-vous vraiment dupliquer ce template ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="${id}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteTemplate(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce template ? Cette action est irréversible.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Chargement des données pour l'édition
<?php if ($editingTemplate): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formAction').value = 'update';
    document.getElementById('templateId').value = '<?= $editingTemplate['id'] ?>';
    document.getElementById('templateName').value = '<?= addslashes($editingTemplate['name']) ?>';
    document.getElementById('templateCategory').value = '<?= $editingTemplate['category'] ?>';
    document.getElementById('templateSubject').value = '<?= addslashes($editingTemplate['subject']) ?>';
    document.getElementById('templateContent').value = '<?= addslashes($editingTemplate['content']) ?>';
    document.getElementById('templateVariables').value = '<?= addslashes($editingTemplate['variables']) ?>';
    document.getElementById('templateActive').checked = <?= $editingTemplate['is_active'] ? 'true' : 'false' ?>;
    document.getElementById('editorTitle').textContent = 'Modifier le Template';
});
<?php endif; ?>

// Animation des cartes au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8) translateY(30px)';
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'scale(1) translateY(0)';
            }, 100);
        }, index * 200);
    });
    
    // Animation des templates
    const templateCards = document.querySelectorAll('.template-card');
    templateCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateX(-30px)';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateX(0)';
            }, 50);
        }, index * 100);
    });
    
    // Améliorer l'expérience des formulaires
    enhanceFormExperience();
});

// Amélioration de l'expérience des formulaires
function enhanceFormExperience() {
    const form = document.getElementById('templateForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
        
        // Réactiver après 5 secondes au cas où
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Sauvegarder';
        }, 5000);
    });
    
    // Auto-resize des textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    // Compteur de caractères pour le sujet
    const subjectInput = document.getElementById('templateSubject');
    const contentTextarea = document.getElementById('templateContent');
    
    function createCharCounter(element, maxLength = null) {
        const counter = document.createElement('div');
        counter.style.cssText = `
            text-align: right;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        `;
        
        element.parentNode.insertBefore(counter, element.nextSibling);
        
        function updateCounter() {
            const length = element.value.length;
            counter.textContent = maxLength ? 
                `${length}/${maxLength} caractères` : 
                `${length} caractères`;
                
            if (maxLength && length > maxLength * 0.9) {
                counter.style.color = 'var(--email-orange)';
            } else {
                counter.style.color = 'var(--text-secondary)';
            }
        }
        
        element.addEventListener('input', updateCounter);
        updateCounter();
    }
    
    createCharCounter(subjectInput, 100);
    createCharCounter(contentTextarea);
}

// Fonction pour prévisualiser le template
function previewTemplate() {
    const name = document.getElementById('templateName').value;
    const subject = document.getElementById('templateSubject').value;
    const content = document.getElementById('templateContent').value;
    
    if (!name || !subject || !content) {
        alert('Veuillez remplir tous les champs obligatoires avant de prévisualiser.');
        return;
    }
    
    // Ouvrir une fenêtre de prévisualisation
    const previewWindow = window.open('', '_blank', 'width=800,height=600');
    previewWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Prévisualisation: ${name}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .email-preview { max-width: 600px; margin: 0 auto; }
                .subject { font-size: 18px; font-weight: bold; margin-bottom: 20px; }
                .content { line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="email-preview">
                <div class="subject">Sujet: ${subject}</div>
                <div class="content">${content.replace(/\n/g, '<br>')}</div>
            </div>
        </body>
        </html>
    `);
}

// Validation JSON pour les variables personnalisées
document.getElementById('templateVariables').addEventListener('blur', function() {
    const value = this.value.trim();
    if (value && value !== '') {
        try {
            JSON.parse(value);
            this.style.borderColor = 'var(--email-green)';
        } catch (e) {
            this.style.borderColor = 'var(--email-orange)';
            this.title = 'Format JSON invalide';
        }
    } else {
        this.style.borderColor = '';
        this.title = '';
    }
});

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    // Ctrl+S pour sauvegarder
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('templateForm').submit();
    }
    
    // Ctrl+N pour nouveau template
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        resetForm();
    }
    
    // Escape pour annuler
    if (e.key === 'Escape') {
        resetForm();
    }
});

console.log('📧 Interface de gestion des templates chargée');
console.log('📊 Templates disponibles:', <?= count($templates) ?>);
console.log('✨ Mode édition:', <?= $editingTemplate ? 'true' : 'false' ?>);
</script>

<?php require_once '../../includes/footer.php'; ?>
