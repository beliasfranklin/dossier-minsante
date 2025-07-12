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

$page_title = "Gestion des Catégories";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tags"></i> Gestion des Catégories</h5>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nouvelle catégorie
                    </a>
                </div>
                
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="categoriesTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Catégorie</th>
                                    <th>Parent</th>
                                    <th>Couleur</th>
                                    <th>Dossiers</th>
                                    <th>Sous-catégories</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr data-id="<?= $cat['id'] ?>" class="<?= $cat['actif'] ? '' : 'table-secondary' ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-<?= htmlspecialchars($cat['icone']) ?> mr-2" 
                                                   style="color: <?= htmlspecialchars($cat['couleur']) ?>"></i>
                                                <span class="<?= $cat['actif'] ? '' : 'text-muted' ?>">
                                                    <?= htmlspecialchars($cat['nom']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($cat['parent_nom']): ?>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($cat['parent_nom']) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge badge-primary">Principal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($cat['couleur']) ?>">
                                                <?= htmlspecialchars($cat['couleur']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= $cat['nb_dossiers'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?= $cat['nb_sous_categories'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $cat['actif'] ? 'success' : 'secondary' ?>">
                                                <?= $cat['actif'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?= $cat['id'] ?>" 
                                                   class="btn btn-outline-primary" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-warning toggle-status" 
                                                        data-id="<?= $cat['id'] ?>" 
                                                        title="<?= $cat['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                    <i class="fas fa-<?= $cat['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                                <?php if ($cat['nb_dossiers'] == 0 && $cat['nb_sous_categories'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger delete-category" 
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
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable
    $('#categoriesTable').DataTable({
        language: {
            url: '../../assets/js/datatables-fr.json'
        },
        order: [[1, 'asc'], [0, 'asc']],
        columnDefs: [
            { targets: [6], orderable: false } // Actions non triables
        ]
    });
    
    // Toggle status
    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de communication');
                console.error(error);
            });
        });
    });
    
    // Delete category
    document.querySelectorAll('.delete-category').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            if (confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${name}" ?\n\nCette action est irréversible.`)) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de communication');
                    console.error(error);
                });
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
