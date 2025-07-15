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

$page_title = "Gestion des Transitions de Statuts";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Transitions de Statuts Configurées</h5>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="transitionsTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>De</th>
                                    <th>Vers</th>
                                    <th>Rôle requis</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transitions as $transition): ?>
                                    <tr class="<?= $transition['actif'] ? '' : 'table-secondary' ?>">
                                        <td>
                                            <span class="badge badge-<?= getStatusColor($transition['from_status']) ?>">
                                                <?= $statuts[$transition['from_status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= getStatusColor($transition['to_status']) ?>">
                                                <?= $statuts[$transition['to_status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= $transition['role_nom'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $transition['actif'] ? 'success' : 'secondary' ?>">
                                                <?= $transition['actif'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-warning toggle-transition" 
                                                        data-id="<?= $transition['id'] ?>" 
                                                        title="<?= $transition['actif'] ? 'Désactiver' : 'Activer' ?>">
                                                    <i class="fas fa-<?= $transition['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-transition" 
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
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Nouvelle Transition</h5>
                </div>
                
                <div class="card-body">
                    <form id="transitionForm">
                        <div class="form-group">
                            <label for="from_status">Statut source</label>
                            <select class="form-control" id="from_status" name="from_status" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($statuts as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="to_status">Statut destination</label>
                            <select class="form-control" id="to_status" name="to_status" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($statuts as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="role_requis">Rôle minimum requis</label>
                            <select class="form-control" id="role_requis" name="role_requis" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($roles as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Créer la transition
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Matrice visuelle des transitions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-sitemap"></i> Matrice des Transitions</h6>
                </div>
                <div class="card-body">
                    <div class="transition-matrix">
                        <?php
                        // Créer une matrice visuelle
                        $matrix = [];
                        foreach ($transitions as $t) {
                            if ($t['actif']) {
                                $matrix[$t['from_status']][$t['to_status']] = $t['role_requis'];
                            }
                        }
                        ?>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th></th>
                                    <?php foreach ($statuts as $key => $label): ?>
                                        <th class="text-center small"><?= substr($label, 0, 3) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statuts as $from_key => $from_label): ?>
                                    <tr>
                                        <td class="small font-weight-bold"><?= substr($from_label, 0, 3) ?></td>
                                        <?php foreach ($statuts as $to_key => $to_label): ?>
                                            <td class="text-center">
                                                <?php if (isset($matrix[$from_key][$to_key])): ?>
                                                    <i class="fas fa-check text-success" 
                                                       title="Autorisé pour rôle <?= $roles[$matrix[$from_key][$to_key]] ?>"></i>
                                                <?php elseif ($from_key !== $to_key): ?>
                                                    <i class="fas fa-times text-danger"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-minus text-muted"></i>
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
    // DataTable
    $('#transitionsTable').DataTable({
        language: {
            url: '../../assets/js/datatables-fr.json'
        },
        order: [[0, 'asc'], [1, 'asc']],
        columnDefs: [
            { targets: [4], orderable: false }
        ]
    });
    
    // Formulaire création transition
    document.getElementById('transitionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create_transition');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
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
    
    // Toggle transition
    document.querySelectorAll('.toggle-transition').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            
            const formData = new FormData();
            formData.append('action', 'toggle_transition');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        });
    });
    
    // Delete transition
    document.querySelectorAll('.delete-transition').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            
            if (confirm('Êtes-vous sûr de vouloir supprimer cette transition ?')) {
                const formData = new FormData();
                formData.append('action', 'delete_transition');
                formData.append('id', id);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        });
    });
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

include '../../includes/footer.php'; 
?>
