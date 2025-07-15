<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérification du rôle gestionnaire
if (!hasPermission(ROLE_GESTIONNAIRE)) {
    $_SESSION['flash']['warning'] = "Demande d'élévation envoyée aux administrateurs";
    logAction($_SESSION['user_id'], 'permission_request', null, "Création dossier");
    header("Location: /request_access.php?resource=create_dossier");
    exit();
}

// Données pré-remplies pour les selects
$services = ['DEP', 'Finance', 'RH', 'Logistique'];
$types = ['Etude', 'Projet', 'Administratif', 'Autre'];
$currentYear = date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $titre = cleanInput($_POST['titre']);
        $description = cleanInput($_POST['description']);
        $type = $_POST['type'];
        $service = $_POST['service'];
        $responsable_id = (int)$_POST['responsable_id'];
        
        // Génération référence automatique
        $reference = generateDossierReference($type);

        // Insertion
        $sql = "INSERT INTO dossiers 
                (reference, titre, description, type, service, created_by, responsable_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = executeQuery($sql, [
            $reference, $titre, $description, 
            $type, $service, $_SESSION['user_id'], $responsable_id
        ]);

        $dossierId = $pdo->lastInsertId();
        logAction($_SESSION['user_id'], 'dossier_created', $dossierId);

        // Gestion fichiers joints
        if (!empty($_FILES['pieces_jointes'])) {
            handleFileUploads($dossierId, $_FILES['pieces_jointes']);
        }

        $_SESSION['flash']['success'] = "Dossier créé avec succès (Réf: $reference)";
        header("Location: view.php?id=$dossierId");
        exit();

    } catch (PDOException $e) {
        $error = "Erreur technique: " . $e->getMessage();
        error_log("Erreur création dossier: " . $e->getMessage());
    }
}

// Récupère la liste des gestionnaires
$responsables = fetchAll("SELECT id, name FROM users WHERE role <= ? ORDER BY name", [ROLE_GESTIONNAIRE]);

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary-color: #2980b9;
    --primary-dark: #1f5f8b;
    --success-color: #27AE60;
    --warning-color: #F39C12;
    --danger-color: #E74C3C;
    --text-primary: #2C3E50;
    --text-secondary: #7F8C8D;
    --background-light: #F8F9FA;
    --border-light: #E1E8ED;
    --shadow-light: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --gradient-primary: linear-gradient(135deg, #3498db, #2980b9);
    --gradient-success: linear-gradient(135deg, #2ecc71, #27ae60);
}

.dossier-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 120px);
    padding: 2rem;
    position: relative;
}

.dossier-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/><circle cx="30" cy="30" r="1" fill="white" opacity="0.1"/><circle cx="70" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="90" cy="80" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    z-index: 0;
}

.dossier-card {
    background: white;
    border-radius: 24px;
    padding: 0;
    box-shadow: var(--shadow-medium);
    border: 1px solid var(--border-light);
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
    overflow: hidden;
}

.dossier-header {
    background: var(--gradient-primary);
    color: white;
    padding: 2rem;
    text-align: center;
    position: relative;
}

.dossier-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100"><polygon fill="rgba(255,255,255,0.1)" points="0,0 1000,0 1000,80 0,100"/></svg>');
    z-index: 1;
}

.header-content {
    position: relative;
    z-index: 2;
}

.dossier-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.3);
}

.dossier-icon i {
    font-size: 2.5rem;
    color: white;
}

.section-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
}

.section-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0.5rem 0 0 0;
}

.form-container {
    padding: 2.5rem;
}

.form-section {
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--background-light);
}

.section-header i {
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-header h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.1rem;
    font-weight: 600;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-label i {
    color: var(--primary-color);
    font-size: 0.9rem;
}

.form-label .required {
    color: var(--danger-color);
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #FAFBFC;
    font-family: inherit;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    background: white;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    transform: translateY(-1px);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.file-upload-area {
    border: 2px dashed var(--border-light);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    background: var(--background-light);
    position: relative;
    overflow: hidden;
}

.file-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(52, 152, 219, 0.05);
}

.file-upload-area.dragover {
    border-color: var(--success-color);
    background: rgba(39, 174, 96, 0.1);
}

.file-upload-icon {
    font-size: 3rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.file-upload-text {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.file-upload-input {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--background-light);
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    min-width: 140px;
    justify-content: center;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
    transform: translateY(-1px);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: var(--background-light);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 1rem;
}

.progress-fill {
    height: 100%;
    background: var(--gradient-success);
    width: 0%;
    transition: width 0.3s ease;
}

@media (max-width: 768px) {
    .dossier-container {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-container {
        padding: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.dossier-card {
    animation: slideInUp 0.6s ease forwards;
}

.btn:active {
    animation: pulse 0.2s ease;
}

/* Indicateur de validation en temps réel */
.form-group.valid .form-input,
.form-group.valid .form-select,
.form-group.valid .form-textarea {
    border-color: var(--success-color);
    background: rgba(39, 174, 96, 0.05);
}

.form-group.invalid .form-input,
.form-group.invalid .form-select,
.form-group.invalid .form-textarea {
    border-color: var(--danger-color);
    background: rgba(231, 76, 60, 0.05);
}
</style>

<div class="dossier-container">
    <div class="dossier-card">
        <div class="dossier-header">
            <div class="header-content">
                <div class="dossier-icon">
                    <i class="fas fa-folder-plus"></i>
                </div>
                <h1 class="section-title">Nouveau Dossier</h1>
                <p class="section-subtitle">Créez un nouveau dossier administratif pour le MinSanté</p>
            </div>
        </div>
        
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" class="dossier-form" id="dossierForm">
                <!-- Section Informations générales -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Informations générales</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="titre" class="form-label">
                                <i class="fas fa-heading"></i>
                                Titre du dossier
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="titre" 
                                   name="titre" 
                                   class="form-input" 
                                   placeholder="Saisissez le titre du dossier"
                                   required 
                                   value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="type" class="form-label">
                                <i class="fas fa-tag"></i>
                                Type de dossier
                                <span class="required">*</span>
                            </label>
                            <select id="type" name="type" class="form-select" required>
                                <option value="">Sélectionnez un type</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t ?>" <?= selected($t, $_POST['type'] ?? '') ?>>
                                        <?= $t ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left"></i>
                            Description détaillée
                        </label>
                        <textarea id="description" 
                                  name="description" 
                                  class="form-textarea" 
                                  placeholder="Décrivez le contenu et l'objectif du dossier..."
                                  rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <!-- Section Organisation -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-users"></i>
                        <h3>Organisation et responsabilité</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="service" class="form-label">
                                <i class="fas fa-building"></i>
                                Service demandeur
                                <span class="required">*</span>
                            </label>
                            <select id="service" name="service" class="form-select" required>
                                <option value="">Choisissez le service</option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?= $s ?>" <?= selected($s, $_POST['service'] ?? '') ?>>
                                        <?= $s ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="responsable_id" class="form-label">
                                <i class="fas fa-user-tie"></i>
                                Responsable assigné
                                <span class="required">*</span>
                            </label>
                            <select id="responsable_id" name="responsable_id" class="form-select" required>
                                <option value="">Assignez un responsable</option>
                                <?php foreach ($responsables as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= selected($r['id'], $_POST['responsable_id'] ?? '') ?>>
                                        <?= htmlspecialchars($r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Pièces jointes -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-paperclip"></i>
                        <h3>Pièces jointes</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="pieces_jointes" class="form-label">
                            <i class="fas fa-upload"></i>
                            Documents à joindre
                        </label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">
                                <strong>Cliquez pour sélectionner</strong> ou glissez-déposez vos fichiers<br>
                                <small>PDF, DOC, XLS, JPG, PNG - Max 5Mo par fichier</small>
                            </div>
                            <input type="file" 
                                   id="pieces_jointes" 
                                   name="pieces_jointes[]" 
                                   class="file-upload-input"
                                   multiple 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                        </div>
                        <div id="fileList" class="file-list" style="margin-top: 1rem;"></div>
                        <div class="progress-bar" id="progressBar" style="display: none;">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-plus"></i>
                        Créer le dossier
                    </button>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>