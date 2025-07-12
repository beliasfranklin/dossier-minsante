<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Configuration des délais par défaut (en jours)
$delais_defaut = [
    'Etude' => ['DEP' => 30, 'Finance' => 15, 'RH' => 20, 'Logistique' => 25],
    'Projet' => ['DEP' => 60, 'Finance' => 45, 'RH' => 30, 'Logistique' => 90],
    'Administratif' => ['DEP' => 10, 'Finance' => 7, 'RH' => 14, 'Logistique' => 21],
    'Autre' => ['DEP' => 15, 'Finance' => 10, 'RH' => 15, 'Logistique' => 20]
];

// Alertes par défaut (jours avant échéance)
$alertes_defaut = [7, 3, 1, 0]; // 7 jours, 3 jours, 1 jour, jour J

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_deadline'])) {
        $dossierId = (int)$_POST['dossier_id'];
        $deadline = $_POST['deadline'];
        
        try {
            executeQuery(
                "UPDATE dossiers SET deadline = ? WHERE id = ?",
                [$deadline, $dossierId]
            );
            
            logAction($_SESSION['user_id'], 'deadline_set', $dossierId, "Échéance: $deadline");
            $_SESSION['flash']['success'] = "Échéance mise à jour";
            
        } catch (Exception $e) {
            $_SESSION['flash']['error'] = "Erreur: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['auto_calculate'])) {
        $dossierId = (int)$_POST['dossier_id'];
        
        try {
            $dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);
            $delai = $delais_defaut[$dossier['type']][$dossier['service']] ?? 30;
            
            $deadline = date('Y-m-d', strtotime($dossier['created_at'] . ' + ' . $delai . ' days'));
            
            executeQuery(
                "UPDATE dossiers SET deadline = ? WHERE id = ?",
                [$deadline, $dossierId]
            );
            
            logAction($_SESSION['user_id'], 'deadline_auto', $dossierId, "Échéance auto: $deadline ($delai jours)");
            $_SESSION['flash']['success'] = "Échéance calculée automatiquement ($delai jours)";
            
        } catch (Exception $e) {
            $_SESSION['flash']['error'] = "Erreur: " . $e->getMessage();
        }
    }
}

// Récupérer les dossiers avec leurs échéances
$dossiers = fetchAll("
    SELECT d.*, u.name as responsable_name,
           CASE 
               WHEN d.deadline IS NULL THEN 'Aucune'
               WHEN d.deadline < CURDATE() THEN 'Dépassée'
               WHEN d.deadline = CURDATE() THEN 'Aujourd\\'hui'
               WHEN d.deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Urgente'
               WHEN d.deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Proche'
               ELSE 'Normale'
           END as urgence,
           DATEDIFF(d.deadline, CURDATE()) as jours_restants
    FROM dossiers d
    JOIN users u ON d.responsable_id = u.id
    WHERE d.status IN ('en_cours', 'valide')
    ORDER BY 
        CASE WHEN d.deadline IS NULL THEN 1 ELSE 0 END,
        d.deadline ASC
");

// Statistiques des échéances
$stats = fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN deadline IS NULL THEN 1 ELSE 0 END) as sans_echeance,
        SUM(CASE WHEN deadline < CURDATE() THEN 1 ELSE 0 END) as depassees,
        SUM(CASE WHEN deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as proches
    FROM dossiers 
    WHERE status IN ('en_cours', 'valide')
");

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.urgence-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.urgence-depassee { background: #e74c3c; color: white; }
.urgence-aujourdhui { background: #f39c12; color: white; }
.urgence-urgente { background: #e67e22; color: white; }
.urgence-proche { background: #f1c40f; color: black; }
.urgence-normale { background: #2ecc71; color: white; }
.urgence-aucune { background: #95a5a6; color: white; }
</style>

<div class="container" style="max-width:1200px;margin:auto;">
    <div class="header-section" style="display:flex;align-items:center;gap:24px;margin-bottom:32px;">
        <div><i class="fas fa-clock" style="font-size:48px;color:#e74c3c;"></i></div>
        <div>
            <h1 style="color:#e74c3c;margin:0;">Gestion des Échéances</h1>
            <p style="color:#666;margin:8px 0 0 0;">Suivi et planification des délais de traitement des dossiers</p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center" style="border-left:4px solid #3498db;">
                <div class="card-body">
                    <h3 class="text-primary"><?= $stats['total'] ?></h3>
                    <p class="mb-0">Total dossiers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border-left:4px solid #95a5a6;">
                <div class="card-body">
                    <h3 class="text-muted"><?= $stats['sans_echeance'] ?></h3>
                    <p class="mb-0">Sans échéance</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border-left:4px solid #e74c3c;">
                <div class="card-body">
                    <h3 class="text-danger"><?= $stats['depassees'] ?></h3>
                    <p class="mb-0">Dépassées</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border-left:4px solid #f39c12;">
                <div class="card-body">
                    <h3 class="text-warning"><?= $stats['proches'] ?></h3>
                    <p class="mb-0">Proches (7j)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration des délais par défaut -->
    <div class="card mb-4" style="background:#f8f9fa;border-radius:12px;">
        <div class="card-header" style="background:#e74c3c;color:#fff;border-radius:12px 12px 0 0;">
            <h4 style="margin:0;"><i class="fas fa-cogs"></i> Délais Par Défaut (jours)</h4>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>DEP</th>
                        <th>Finance</th>
                        <th>RH</th>
                        <th>Logistique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delais_defaut as $type => $services): ?>
                    <tr>
                        <td><strong><?= $type ?></strong></td>
                        <?php foreach (['DEP', 'Finance', 'RH', 'Logistique'] as $service): ?>
                        <td>
                            <span class="badge badge-secondary"><?= $services[$service] ?>j</span>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <small class="text-muted">
                <i class="fas fa-info-circle"></i>
                Ces délais sont utilisés pour le calcul automatique des échéances selon le type et service du dossier.
            </small>
        </div>
    </div>

    <!-- Liste des dossiers -->
    <div class="card" style="background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.1);">
        <div class="card-header" style="background:#2980b9;color:#fff;padding:20px;border-radius:12px 12px 0 0;">
            <h3 style="margin:0;"><i class="fas fa-list"></i> Dossiers et Échéances</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;">
                <thead style="background:#f8f9fa;">
                    <tr>
                        <th>Référence</th>
                        <th>Titre</th>
                        <th>Type/Service</th>
                        <th>Responsable</th>
                        <th>Échéance</th>
                        <th>Urgence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $d): ?>
                    <tr>
                        <td><strong><?= $d['reference'] ?></strong></td>
                        <td><?= htmlspecialchars(substr($d['titre'], 0, 30)) ?>...</td>
                        <td>
                            <small class="text-muted"><?= $d['type'] ?></small><br>
                            <span class="badge badge-info"><?= $d['service'] ?></span>
                        </td>
                        <td><?= htmlspecialchars($d['responsable_name']) ?></td>
                        <td>
                            <?php if ($d['deadline']): ?>
                                <?= date('d/m/Y', strtotime($d['deadline'])) ?>
                                <?php if ($d['jours_restants'] !== null): ?>
                                    <br><small class="text-muted">
                                        <?php if ($d['jours_restants'] > 0): ?>
                                            Dans <?= $d['jours_restants'] ?> jour(s)
                                        <?php elseif ($d['jours_restants'] == 0): ?>
                                            Aujourd'hui
                                        <?php else: ?>
                                            Retard de <?= abs($d['jours_restants']) ?> jour(s)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Non définie</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="urgence-badge urgence-<?= strtolower(str_replace(['\\', ' ', "'"], ['', '-', ''], $d['urgence'])) ?>">
                                <?= $d['urgence'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="setDeadline(<?= $d['id'] ?>, '<?= $d['reference'] ?>', '<?= $d['deadline'] ?>')">
                                    <i class="fas fa-calendar"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="autoCalculate(<?= $d['id'] ?>, '<?= $d['reference'] ?>', '<?= $d['type'] ?>', '<?= $d['service'] ?>')">
                                    <i class="fas fa-magic"></i>
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

<!-- Modal définition échéance -->
<div class="modal fade" id="deadlineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Définir l'échéance</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="dossier_id" id="deadline_dossier_id">
                    <div class="form-group">
                        <label>Dossier :</label>
                        <div id="deadline_dossier_info" class="font-weight-bold"></div>
                    </div>
                    <div class="form-group">
                        <label for="deadline">Date d'échéance :</label>
                        <input type="date" name="deadline" id="deadline" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="set_deadline" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal calcul automatique -->
<div class="modal fade" id="autoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Calcul automatique</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="dossier_id" id="auto_dossier_id">
                    <div class="form-group">
                        <label>Dossier :</label>
                        <div id="auto_dossier_info" class="font-weight-bold"></div>
                    </div>
                    <div id="auto_calculation_info"></div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        L'échéance sera calculée automatiquement selon le type et service du dossier.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="auto_calculate" class="btn btn-success">Calculer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
const delaisDefaut = <?= json_encode($delais_defaut) ?>;

function setDeadline(dossierId, reference, currentDeadline) {
    document.getElementById('deadline_dossier_id').value = dossierId;
    document.getElementById('deadline_dossier_info').textContent = reference;
    document.getElementById('deadline').value = currentDeadline || '';
    $('#deadlineModal').modal('show');
}

function autoCalculate(dossierId, reference, type, service) {
    document.getElementById('auto_dossier_id').value = dossierId;
    document.getElementById('auto_dossier_info').textContent = reference;
    
    const delai = delaisDefaut[type] && delaisDefaut[type][service] ? delaisDefaut[type][service] : 30;
    document.getElementById('auto_calculation_info').innerHTML = 
        '<p><strong>Type:</strong> ' + type + '</p>' +
        '<p><strong>Service:</strong> ' + service + '</p>' +
        '<p><strong>Délai configuré:</strong> ' + delai + ' jour(s)</p>';
    
    $('#autoModal').modal('show');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
