<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();
requirePermission(ROLE_ADMIN);

// Matrice des transitions autorisées
$transitions_autorisees = [
    'en_cours' => ['valide', 'rejete', 'archive'],
    'valide' => ['archive'],
    'rejete' => ['en_cours', 'archive'],
    'archive' => [] // Aucune transition possible depuis archive
];

// Rôles requis pour chaque transition
$roles_requis = [
    'en_cours_to_valide' => ROLE_GESTIONNAIRE,
    'en_cours_to_rejete' => ROLE_GESTIONNAIRE,
    'en_cours_to_archive' => ROLE_ADMIN,
    'valide_to_archive' => ROLE_ADMIN,
    'rejete_to_en_cours' => ROLE_GESTIONNAIRE,
    'rejete_to_archive' => ROLE_ADMIN
];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_status'])) {
        $dossierId = (int)$_POST['dossier_id'];
        $newStatus = $_POST['new_status'];
        $motif = cleanInput($_POST['motif'] ?? '');
        
        try {
            // Récupérer le dossier actuel
            $dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);
            if (!$dossier) {
                throw new Exception("Dossier non trouvé");
            }
            
            $currentStatus = $dossier['status'];
            $transitionKey = $currentStatus . '_to_' . $newStatus;
            
            // Vérifier si la transition est autorisée
            if (!in_array($newStatus, $transitions_autorisees[$currentStatus] ?? [])) {
                throw new Exception("Transition non autorisée : $currentStatus → $newStatus");
            }
            
            // Vérifier les permissions
            $roleRequis = $roles_requis[$transitionKey] ?? ROLE_ADMIN;
            if ($_SESSION['user_role'] > $roleRequis) {
                throw new Exception("Permissions insuffisantes pour cette transition");
            }
            
            // Effectuer la transition
            executeQuery(
                "UPDATE dossiers SET status = ?, motif_rejet = ?, updated_at = NOW() WHERE id = ?",
                [$newStatus, $motif, $dossierId]
            );
            
            // Logger l'action
            logAction(
                $_SESSION['user_id'], 
                'status_change', 
                $dossierId, 
                "Changement: $currentStatus → $newStatus" . ($motif ? " | Motif: $motif" : "")
            );
            
            // Notification au responsable
            createNotification(
                $dossier['responsable_id'],
                "Changement de statut",
                "Le dossier {$dossier['reference']} est passé de '$currentStatus' à '$newStatus'",
                'dossiers',
                $dossierId
            );
            
            $_SESSION['flash']['success'] = "Statut mis à jour avec succès";
            
        } catch (Exception $e) {
            $_SESSION['flash']['error'] = $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Récupérer tous les dossiers non archivés pour gestion
$dossiers = fetchAll("
    SELECT d.*, u.name as responsable_name 
    FROM dossiers d
    JOIN users u ON d.responsable_id = u.id
    WHERE d.status != 'archive'
    ORDER BY d.created_at DESC
");

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="container" style="max-width:1000px;margin:auto;">
    <div class="header-section" style="display:flex;align-items:center;gap:24px;margin-bottom:32px;">
        <div><i class="fas fa-exchange-alt" style="font-size:48px;color:#2980b9;"></i></div>
        <div>
            <h1 style="color:#2980b9;margin:0;">Gestion des Statuts</h1>
            <p style="color:#666;margin:8px 0 0 0;">Contrôle des transitions et validation des changements de statut</p>
        </div>
    </div>

    <!-- Matrice des transitions -->
    <div class="card mb-4" style="background:#f8f9fa;border-radius:12px;padding:24px;margin-bottom:32px;">
        <h3 style="color:#2980b9;margin-bottom:20px;"><i class="fas fa-sitemap"></i> Matrice des Transitions Autorisées</h3>
        <table class="table" style="background:#fff;border-radius:8px;overflow:hidden;">
            <thead style="background:#2980b9;color:#fff;">
                <tr>
                    <th>Statut Actuel</th>
                    <th>Transitions Possibles</th>
                    <th>Rôle Requis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transitions_autorisees as $from => $tos): ?>
                <tr>
                    <td><span class="badge badge-primary"><?= ucfirst($from) ?></span></td>
                    <td>
                        <?php if (!empty($tos)): ?>
                            <?php foreach ($tos as $to): ?>
                                <?php 
                                $transitionKey = $from . '_to_' . $to;
                                $roleRequis = $roles_requis[$transitionKey] ?? ROLE_ADMIN;
                                $roleName = $roleRequis == ROLE_ADMIN ? 'Admin' : ($roleRequis == ROLE_GESTIONNAIRE ? 'Gestionnaire' : 'Utilisateur');
                                ?>
                                <span class="badge badge-success"><?= ucfirst($to) ?></span>
                                <small class="text-muted">(<?= $roleName ?>)</small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Aucune transition</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($tos)): ?>
                            Selon transition
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Liste des dossiers pour changement de statut -->
    <div class="card" style="background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.1);">
        <div class="card-header" style="background:#2980b9;color:#fff;padding:20px;border-radius:12px 12px 0 0;">
            <h3 style="margin:0;"><i class="fas fa-list"></i> Dossiers Actifs</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;">
                <thead style="background:#f8f9fa;">
                    <tr>
                        <th>Référence</th>
                        <th>Titre</th>
                        <th>Statut Actuel</th>
                        <th>Responsable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $d): ?>
                    <tr>
                        <td><strong><?= $d['reference'] ?></strong></td>
                        <td><?= htmlspecialchars(substr($d['titre'], 0, 40)) ?>...</td>
                        <td>
                            <?php
                            $statusColors = [
                                'en_cours' => 'primary',
                                'valide' => 'success',
                                'rejete' => 'danger'
                            ];
                            $color = $statusColors[$d['status']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $color ?>"><?= ucfirst($d['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($d['responsable_name']) ?></td>
                        <td>
                            <?php $possibleTransitions = $transitions_autorisees[$d['status']] ?? []; ?>
                            <?php if (!empty($possibleTransitions)): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="openStatusModal(<?= $d['id'] ?>, '<?= $d['status'] ?>', '<?= htmlspecialchars($d['reference']) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Changer
                                </button>
                            <?php else: ?>
                                <span class="text-muted">Aucune action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de changement de statut -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Changer le statut</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="dossier_id" id="modal_dossier_id">
                    <div class="form-group">
                        <label>Dossier :</label>
                        <div id="modal_dossier_info" class="font-weight-bold"></div>
                    </div>
                    <div class="form-group">
                        <label>Statut actuel :</label>
                        <div id="modal_current_status"></div>
                    </div>
                    <div class="form-group">
                        <label for="new_status">Nouveau statut :</label>
                        <select name="new_status" id="new_status" class="form-control" required>
                            <!-- Options remplies par JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="motif">Motif (optionnel) :</label>
                        <textarea name="motif" id="motif" class="form-control" rows="3" placeholder="Raison du changement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="change_status" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
// Données JavaScript des transitions
const transitions = <?= json_encode($transitions_autorisees) ?>;
const userRole = <?= $_SESSION['user_role'] ?>;
const roleRequis = <?= json_encode($roles_requis) ?>;

function openStatusModal(dossierId, currentStatus, reference) {
    document.getElementById('modal_dossier_id').value = dossierId;
    document.getElementById('modal_dossier_info').textContent = reference;
    document.getElementById('modal_current_status').innerHTML = '<span class="badge badge-primary">' + currentStatus + '</span>';
    
    // Remplir les options de nouveau statut
    const select = document.getElementById('new_status');
    select.innerHTML = '';
    
    const possibleTransitions = transitions[currentStatus] || [];
    possibleTransitions.forEach(status => {
        const transitionKey = currentStatus + '_to_' + status;
        const requiredRole = roleRequis[transitionKey] || 1; // ROLE_ADMIN
        
        if (userRole <= requiredRole) {
            const option = document.createElement('option');
            option.value = status;
            option.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            select.appendChild(option);
        }
    });
    
    $('#statusModal').modal('show');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
