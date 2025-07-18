<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Récupérer les workflows définis
$workflows = fetchAll("SELECT * FROM workflows ORDER BY type_dossier, ordre");

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_step'])) {
        $type = cleanInput($_POST['type_dossier']);
        $etape = cleanInput($_POST['etape']);
        $roleRequis = (int)$_POST['role_requis'];
        $ordre = (int)$_POST['ordre'];
        
        executeQuery(
            "INSERT INTO workflows (type_dossier, etape, role_requis, ordre) 
             VALUES (?, ?, ?, ?)",
            [$type, $etape, $roleRequis, $ordre]
        );
        $_SESSION['flash']['success'] = "Étape ajoutée avec succès";
        header("Refresh:0");
        exit();
    }
    
    if (isset($_POST['update_step'])) {
        // Même logique que pour l'ajout
    }
    
    if (isset($_POST['delete_step'])) {
        $id = (int)$_POST['step_id'];
        executeQuery("DELETE FROM workflows WHERE id = ?", [$id]);
        $_SESSION['flash']['success'] = "Étape supprimée";
        header("Refresh:0");
        exit();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="container workflow-section" style="max-width:900px;margin:auto;">
    <div class="workflow-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="workflow-icon"><i class="fas fa-project-diagram" style="font-size:48px;color:#2980b9;"></i></div>
        <h1 class="section-title" style="color:#2980b9;">Gestion des Workflows</h1>
    </div>
    <!-- Formulaire d'ajout -->
    <div class="card mb-4" style="background:#f4f8fb;border-radius:12px;box-shadow:0 2px 8px #2980b922;">
        <div class="card-header" style="font-weight:600;color:#2980b9;background:#eaf6fb;border-radius:12px 12px 0 0;">Ajouter une étape</div>
        <div class="card-body">
            <form method="post" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="min-width:160px;">
                    <label>Type de dossier</label>
                    <select name="type_dossier" class="form-control" required>
                        <option value="Etude">Étude</option>
                        <option value="Projet">Projet</option>
                        <option value="Administratif">Administratif</option>
                    </select>
                </div>
                <div class="form-group" style="min-width:160px;">
                    <label>Nom de l'étape</label>
                    <input type="text" name="etape" class="form-control" required>
                </div>
                <div class="form-group" style="min-width:140px;">
                    <label>Rôle requis</label>
                    <select name="role_requis" class="form-control" required>
                        <option value="<?= ROLE_ADMIN ?>">Administrateur</option>
                        <option value="<?= ROLE_GESTIONNAIRE ?>">Gestionnaire</option>
                        <option value="<?= ROLE_CONSULTANT ?>">Consultant</option>
                    </select>
                </div>
                <div class="form-group" style="min-width:120px;">
                    <label for="ordre" style="font-weight:600;color:#2980b9;display:flex;align-items:center;gap:6px;">
                        <i class="fa fa-sort-numeric-up"></i> Ordre
                    </label>
                    <div style="position:relative;">
                        <input type="number" name="ordre" id="ordre" class="form-control modern-ordre" min="1" max="99" required style="border-radius:10px;padding:12px 18px 12px 38px;font-size:1.08em;background:#f8fafc;border:1.5px solid #e0eafc;box-shadow:0 2px 8px #2980b91a;outline:none;transition:border 0.18s,box-shadow 0.18s;">
                        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#27ae60;font-size:1.2em;"><i class="fa fa-hashtag"></i></span>
                    </div>
                </div>
                <div class="form-group" style="align-self:end;">
                    <button type="submit" name="add_step" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Liste des workflows -->
    <div class="card" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px #2980b922;">
        <div class="card-header" style="font-weight:600;color:#2980b9;background:#eaf6fb;border-radius:12px 12px 0 0;">Workflows définis</div>
        <div class="card-body">
            <table class="table" style="width:100%;border-radius:8px;overflow:hidden;">
                <thead>
                    <tr style="background:#2980b9;color:#fff;">
                        <th>Type</th>
                        <th>Étape</th>
                        <th>Rôle requis</th>
                        <th>Ordre</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workflows as $wf): ?>
                    <tr>
                        <td><?= htmlspecialchars($wf['type_dossier']) ?></td>
                        <td><?= htmlspecialchars($wf['etape']) ?></td>
                        <td><?= getRoleName($wf['role_requis']) ?></td>
                        <td><?= $wf['ordre'] ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="step_id" value="<?= $wf['id'] ?>">
                                <button type="submit" name="delete_step" class="btn btn-sm btn-danger" style="padding:6px 12px;border-radius:7px;"
                                    onclick="return confirm('Supprimer cette étape?')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
input.modern-ordre:focus {
    border:1.5px solid #27ae60;
    box-shadow:0 0 0 2px #27ae6044;
    background:#fff;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>