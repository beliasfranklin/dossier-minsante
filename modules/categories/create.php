<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireRole(ROLE_GESTIONNAIRE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        $stmt = $pdo->prepare("INSERT INTO categories (nom, parent_id, couleur, icone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nom, $parent_id, $couleur, $icone]);
        
        logAction($_SESSION['user_id'], 'CREATE_CATEGORY', null, "Création catégorie: $nom");
        
        $_SESSION['success'] = "Catégorie créée avec succès";
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer les catégories parent pour le select
$stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL AND actif = 1 ORDER BY nom");
$categories_parent = $stmt->fetchAll();

$page_title = "Nouvelle Catégorie";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Nouvelle Catégorie</h5>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
                
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nom">Nom de la catégorie *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>" 
                                           required maxlength="100">
                                    <div class="invalid-feedback">Le nom est obligatoire</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="parent_id">Catégorie parent</label>
                                    <select class="form-control" id="parent_id" name="parent_id">
                                        <option value="">-- Catégorie principale --</option>
                                        <?php foreach ($categories_parent as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" 
                                                    <?= (isset($_POST['parent_id']) && $_POST['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="couleur">Couleur</label>
                                    <input type="color" class="form-control form-control-color" id="couleur" name="couleur" 
                                           value="<?= isset($_POST['couleur']) ? $_POST['couleur'] : '#2980b9' ?>" 
                                           title="Choisir une couleur">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="icone">Icône FontAwesome</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas" id="icon-preview"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" id="icone" name="icone" 
                                               value="<?= isset($_POST['icone']) ? htmlspecialchars($_POST['icone']) : 'folder' ?>" 
                                               placeholder="Ex: folder, file-text, building">
                                    </div>
                                    <small class="form-text text-muted">
                                        Nom de l'icône FontAwesome sans le préfixe "fa-"
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer la catégorie
                            </button>
                            <a href="index.php" class="btn btn-secondary ml-2">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Prévisualisation de l'icône
    const iconeInput = document.getElementById('icone');
    const iconPreview = document.getElementById('icon-preview');
    
    function updateIconPreview() {
        const iconName = iconeInput.value.trim();
        iconPreview.className = iconName ? `fas fa-${iconName}` : 'fas fa-folder';
    }
    
    iconeInput.addEventListener('input', updateIconPreview);
    updateIconPreview(); // Initial load
});
</script>

<?php include '../../includes/footer.php'; ?>
