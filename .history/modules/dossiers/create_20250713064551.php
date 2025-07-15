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
<div class="container dossier-section" style="max-width:700px;margin:auto;">
    <div class="dossier-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="dossier-icon"><i class="fas fa-folder-plus" style="font-size:48px;color:#2980b9;"></i></div>
        <h1 class="section-title" style="color:#2980b9;">Nouveau Dossier</h1>
    </div>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="dossier-form">
        <div class="form-row">
            <div class="form-group">
                <label for="titre">Titre*</label>
                <input type="text" id="titre" name="titre" required 
                       value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="type">Type*</label>
                <select id="type" name="type" required>
                    <?php foreach (
                        $types as $t): ?>
                        <option value="<?= $t ?>" <?= selected($t, $_POST['type'] ?? '') ?>>
                            <?= $t ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5"><?= 
                htmlspecialchars($_POST['description'] ?? '') 
            ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="service">Service*</label>
                <select id="service" name="service" required>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s ?>" <?= selected($s, $_POST['service'] ?? '') ?>>
                            <?= $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="responsable_id">Responsable*</label>
                <select id="responsable_id" name="responsable_id" required>
                    <?php foreach ($responsables as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= selected($r['id'], $_POST['responsable_id'] ?? '') ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="pieces_jointes">Pièces jointes (max 5Mo)</label>
            <input type="file" id="pieces_jointes" name="pieces_jointes[]" multiple 
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
        </div>
        <div class="form-actions" style="display:flex;gap:16px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Créer le dossier</button>
            <a href="list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>