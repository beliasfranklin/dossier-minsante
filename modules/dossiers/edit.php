<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

$dossierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($dossierId <= 0) {
    echo '<div class="alert alert-danger">ID de dossier invalide.</div>';
    exit();
}
$dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);

// Vérification permissions
// Debug temporaire pour comprendre le refus d'accès
if (!$dossier || !canAccessDossier($_SESSION['user_id'], $dossierId, true)) {
    error_log('DEBUG: user_id=' . $_SESSION['user_id'] . ', dossierId=' . $dossierId . ', created_by=' . ($dossier['created_by'] ?? 'null') . ', responsable_id=' . ($dossier['responsable_id'] ?? 'null'));
    header("Location: /error.php?code=403");
    exit();
}

// Données pour les selects
$services = ['DEP', 'Finance', 'RH', 'Logistique'];
$types = ['Etude', 'Projet', 'Administratif', 'Autre'];
$statuses = ['En cours', 'Valide', 'Rejete', 'Archive'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_dossier'])) {
    try {
        $titre = cleanInput($_POST['titre']);
        $description = cleanInput($_POST['description']);
        $type = $_POST['type'];
        $service = $_POST['service'];
        $responsable_id = (int)$_POST['responsable_id'];
        if (!isset($_POST['status'])) {
            throw new Exception("Le statut est requis.");
        }
        $status = $_POST['status'];
        $motif_rejet = ($status === 'Rejete') ? cleanInput($_POST['motif_rejet']) : null;

        // Vérification transition de statut
        if (!isStatusTransitionAllowed($dossier['status'], $status, $dossier['type'])) {
            throw new Exception("Transition de statut non autorisée");
        }

        $sql = "UPDATE dossiers SET
                titre = ?, description = ?, type = ?, 
                service = ?, responsable_id = ?, status = ?,
                motif_rejet = ?, updated_at = NOW()
                WHERE id = ?";
        
        executeQuery($sql, [
            $titre, $description, $type, $service,
            $responsable_id, $status, $motif_rejet, $dossierId
        ]);

        logAction($_SESSION['user_id'], 'dossier_updated', $dossierId, 
                "Statut changé à: " . $status);

        $_SESSION['flash']['success'] = "Dossier mis à jour";
        header("Location: view.php?id=$dossierId");
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Erreur modification dossier #$dossierId: " . $e->getMessage());
    }
}

$responsables = fetchAll("SELECT id, name FROM users WHERE role <= ? ORDER BY name", [ROLE_GESTIONNAIRE]);
$piecesJointes = getDossierAttachments($dossierId);

include __DIR__ . '/../../includes/header.php';
?>

<div class="container dossier-section" style="max-width:700px;margin:auto;">
    <div class="dossier-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="dossier-icon"><i class="fas fa-edit" style="font-size:48px;color:#2980b9;"></i></div>
        <h1 class="section-title" style="color:#2980b9;">Modifier Dossier <?= htmlspecialchars($dossier['reference']) ?></h1>
    </div>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="post" class="dossier-form">
        <input type="hidden" name="edit_dossier" value="1">
        <div class="form-group">
            <label>Référence</label>
            <input type="text" value="<?= $dossier['reference'] ?>" disabled>
        </div>
        <div class="form-group">
            <label for="titre">Titre*</label>
            <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($dossier['titre']) ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description*</label>
            <textarea id="description" name="description" rows="3" required><?= htmlspecialchars($dossier['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="type">Type*</label>
            <select id="type" name="type" required>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= selected($t, $dossier['type']) ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="service">Service*</label>
            <select id="service" name="service" required>
                <?php foreach ($services as $s): ?>
                    <option value="<?= $s ?>" <?= selected($s, $dossier['service']) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="responsable_id">Responsable*</label>
            <select id="responsable_id" name="responsable_id" required>
                <?php foreach ($responsables as $resp): ?>
                    <option value="<?= $resp['id'] ?>" <?= selected($resp['id'], $dossier['responsable_id']) ?>><?= htmlspecialchars($resp['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Statut*</label>
            <select id="status" name="status" required onchange="toggleRejectReason(this.value)">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= selected($s, $dossier['status']) ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="reject-reason-group" class="form-group" style="display:none">
            <label for="motif_rejet">Motif de rejet*</label>
            <textarea id="motif_rejet" name="motif_rejet" rows="3"><?= htmlspecialchars($dossier['motif_rejet'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Pièces jointes</label>
            <?php if ($piecesJointes): ?>
                <ul class="file-list">
                    <?php foreach ($piecesJointes as $fichier): ?>
                        <li>
                            <a href="/dossier-minsante/download.php?id=<?= $fichier['id'] ?>" target="_blank">
                                <?= htmlspecialchars($fichier['nom_fichier']) ?>
                            </a>
                            (<?= formatFileSize(filesize($fichier['chemin'])) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Aucune pièce jointe</p>
            <?php endif; ?>
        </div>
        <div class="form-actions" style="display:flex;gap:16px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="view.php?id=<?= $dossierId ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
        </div>
    </form>
    <div class="form-group">
        <form action="/dossier-minsante/modules/dossiers/actions/upload_attachment.php" method="post" enctype="multipart/form-data" style="margin-top:10px;">
            <input type="hidden" name="dossier_id" value="<?= $dossierId ?>">
            <label for="attachment">Ajouter une pièce jointe :</label>
            <input type="file" name="attachment" id="attachment" required accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt">
            <button type="submit" class="btn btn-info" style="margin-left:10px;"><i class="fas fa-upload"></i> Téléverser</button>
        </form>
    </div>
</div>
<script>
function toggleRejectReason(status) {
    document.getElementById('reject-reason-group').style.display = 
        (status === 'Rejete') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', () => {
    toggleRejectReason(document.getElementById('status').value);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>