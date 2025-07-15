<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireRole(ROLE_CONSULTANT);

// Récupération du dossier avec toutes les informations
$dossierId = (int)$_GET['id'];

if (!$dossierId) {
    header('Location: list.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.*, 
           u1.prenom as created_by_prenom, u1.nom as created_by_nom,
           u2.prenom as responsable_prenom, u2.nom as responsable_nom,
           c.nom as category_name, c.couleur as category_color, c.icone as category_icon
    FROM dossiers d
    LEFT JOIN users u1 ON d.created_by = u1.id
    LEFT JOIN users u2 ON d.responsable_id = u2.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.id = ?
");
$stmt->execute([$dossierId]);
$dossier = $stmt->fetch();

if (!$dossier) {
    $_SESSION['error'] = "Dossier non trouvé";
    header('Location: list.php');
    exit;
}

// Vérification des permissions
if (!canViewDossier($_SESSION['user_id'], $_SESSION['role'], $dossier['responsable_id'])) {
    $_SESSION['error'] = "Accès non autorisé";
    header('Location: list.php');
    exit;
}

// Récupération des transitions autorisées
$stmt = $pdo->prepare("
    SELECT st.to_status, st.role_requis
    FROM status_transitions st
    WHERE st.from_status = ? AND st.actif = 1 AND st.role_requis >= ?
    ORDER BY st.to_status
");
$stmt->execute([$dossier['status'], $_SESSION['role']]);
$transitions_autorisees = $stmt->fetchAll();

// Récupération des pièces jointes
$stmt = $pdo->prepare("
    SELECT * FROM attachments 
    WHERE dossier_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$dossierId]);
$attachments = $stmt->fetchAll();

// Récupération des logs liés au dossier
$stmt = $pdo->prepare("
    SELECT l.*, u.prenom, u.nom
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.dossier_id = ?
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute([$dossierId]);
$logs = $stmt->fetchAll();

// Récupération des commentaires
$stmt = $pdo->prepare("
    SELECT c.*, u.prenom, u.nom,
           CONCAT(u.prenom, ' ', u.nom) as user_name
    FROM dossier_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.dossier_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$dossierId]);
$commentaires = $stmt->fetchAll();

// Récupération des étapes du workflow
$stmt = $pdo->prepare("
    SELECT etape, role_requis, ordre
    FROM workflow_steps
    WHERE actif = 1
    ORDER BY ordre ASC
");
$stmt->execute();
$workflowSteps = $stmt->fetchAll();

// Si pas de workflow défini, utiliser un workflow par défaut
if (empty($workflowSteps)) {
    $workflowSteps = [
        ['etape' => 'en_attente', 'role_requis' => ROLE_CONSULTANT, 'ordre' => 1],
        ['etape' => 'en_cours', 'role_requis' => ROLE_CONSULTANT, 'ordre' => 2],
        ['etape' => 'valide', 'role_requis' => ROLE_GESTIONNAIRE, 'ordre' => 3],
        ['etape' => 'archive', 'role_requis' => ROLE_ADMIN, 'ordre' => 4]
    ];
}

// Calcul du temps restant pour l'échéance
$temps_restant = null;
$alerte_echeance = null;
if ($dossier['deadline']) {
    $today = new DateTime();
    $deadline = new DateTime($dossier['deadline']);
    $diff = $today->diff($deadline);
    
    if ($deadline < $today) {
        $temps_restant = "Dépassée de " . $diff->days . " jour(s)";
        $alerte_echeance = 'danger';
    } elseif ($diff->days == 0) {
        $temps_restant = "Aujourd'hui";
        $alerte_echeance = 'warning';
    } elseif ($diff->days <= 3) {
        $temps_restant = $diff->days . " jour(s) restant(s)";
        $alerte_echeance = 'warning';
    } elseif ($diff->days <= 7) {
        $temps_restant = $diff->days . " jour(s) restant(s)";
        $alerte_echeance = 'info';
    } else {
        $temps_restant = $diff->days . " jour(s) restant(s)";
        $alerte_echeance = 'success';
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_comment':
                    $comment = trim($_POST['comment']);
                    if (empty($comment)) {
                        throw new Exception("Le commentaire ne peut pas être vide");
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO dossier_comments (dossier_id, user_id, comment) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$dossierId, $_SESSION['user_id'], $comment]);
                    
                    logAction($_SESSION['user_id'], 'ADD_COMMENT', $dossierId, "Commentaire ajouté");
                    
                    echo json_encode(['success' => true, 'message' => 'Commentaire ajouté']);
                    exit;
                    
                default:
                    throw new Exception("Action non reconnue");
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fonction utilitaire pour vérifier les permissions de visualisation
function canViewDossier($user_id, $user_role, $responsable_id) {
    return $user_role <= ROLE_GESTIONNAIRE || $user_id == $responsable_id;
}

// Fonctions utilitaires pour l'affichage
function formatDate($date) {
    return date('d/m/Y à H:i', strtotime($date));
}

function getRoleName($role) {
    $roles = [
        ROLE_ADMIN => 'Administrateur',
        ROLE_GESTIONNAIRE => 'Gestionnaire',
        ROLE_CONSULTANT => 'Consultant'
    ];
    return $roles[$role] ?? 'Inconnu';
}

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="container" style="max-width:1200px;margin:32px auto;padding:0 24px;">
    <div class="dossier-header" style="background:linear-gradient(135deg,#fff,#f8fafc);border-radius:16px;padding:32px;box-shadow:0 4px 20px rgba(41,128,185,0.1);margin-bottom:32px;">
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
            <div style="background:linear-gradient(135deg,#2980b9,#6dd5fa);padding:20px;border-radius:50%;box-shadow:0 4px 20px rgba(41,128,185,0.2);">
                <i class="fas fa-folder-open" style="font-size:32px;color:#fff;"></i>
            </div>
            <div>
                <h1 style="color:#2980b9;font-size:2em;margin:0 0 8px 0;"><?= htmlspecialchars($dossier['titre']) ?></h1>
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <span style="background:#eaf6fb;color:#2980b9;padding:6px 16px;border-radius:50px;font-size:0.9em;">
                        <i class="fas fa-hashtag"></i> <?= $dossier['reference'] ?>
                    </span>
                    <span id="statusBadge" style="background:<?= getStatusColor($dossier['status']) ?>;color:#fff;padding:6px 16px;border-radius:50px;font-size:0.9em;">
                        <i class="fas fa-circle"></i> <span id="statusText"><?= getStatusLabel($dossier['status']) ?></span>
                    </span>
                    <?php if ($dossier['category_name']): ?>
                        <span style="background:<?= $dossier['category_color'] ?>;color:#fff;padding:6px 16px;border-radius:50px;font-size:0.9em;">
                            <i class="fas fa-<?= $dossier['category_icon'] ?>"></i> <?= htmlspecialchars($dossier['category_name']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($temps_restant): ?>
                        <span class="badge badge-<?= $alerte_echeance ?>" style="padding:6px 16px;border-radius:50px;font-size:0.9em;">
                            <i class="fas fa-clock"></i> <?= $temps_restant ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="dossier-content" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
        <div class="main-content">
            <!-- Informations principales -->
            <div class="info-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:32px;">
                <?php
                $infoCards = [
                    ['icon' => 'folder', 'title' => 'Type', 'value' => $dossier['type']],
                    ['icon' => 'building', 'title' => 'Service', 'value' => $dossier['service']],
                    ['icon' => 'user', 'title' => 'Créé par', 'value' => ($dossier['created_by_prenom'] ?? '') . ' ' . ($dossier['created_by_nom'] ?? '')],
                    ['icon' => 'user-shield', 'title' => 'Responsable', 'value' => ($dossier['responsable_prenom'] ?? '') . ' ' . ($dossier['responsable_nom'] ?? '')],
                    ['icon' => 'calendar-plus', 'title' => 'Date création', 'value' => date('d/m/Y H:i', strtotime($dossier['created_at']))],
                    ['icon' => 'calendar-check', 'title' => 'Dernière modification', 'value' => date('d/m/Y H:i', strtotime($dossier['updated_at']))]
                ];
                
                if ($dossier['deadline']) {
                    $infoCards[] = ['icon' => 'calendar-alt', 'title' => 'Échéance', 'value' => date('d/m/Y', strtotime($dossier['deadline']))];
                }
                foreach ($infoCards as $card):
                ?>
                <div style="background:#fff;border-radius:12px;padding:16px;box-shadow:0 2px 12px rgba(41,128,185,0.08);">
                    <div style="color:#2980b9;font-size:0.9em;margin-bottom:8px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-<?= $card['icon'] ?>"></i> <?= $card['title'] ?>
                    </div>
                    <div style="color:#2d3436;font-size:1.1em;font-weight:500;"><?= htmlspecialchars($card['value']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Description -->
            <div class="section-card" style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:32px;">
                <h2 style="color:#2980b9;font-size:1.3em;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-align-left"></i> Description
                </h2>
                <div style="color:#2d3436;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($dossier['description'] ?? 'Aucune description')) ?>
                </div>
            </div>

            <!-- Workflow -->
            <div class="section-card" style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:32px;">
                <h2 style="color:#2980b9;font-size:1.3em;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-project-diagram"></i> Workflow
                </h2>
                <div class="workflow-steps" style="display:flex;align-items:center;gap:24px;padding:16px 0;overflow-x:auto;">
                    <?php foreach ($workflowSteps as $index => $step): 
                        $isActive = $index === array_search($dossier['status'], array_column($workflowSteps, 'etape'));
                        $isCompleted = $index < array_search($dossier['status'], array_column($workflowSteps, 'etape'));
                    ?>
                    <div class="step" style="display:flex;align-items:center;gap:12px;min-width:200px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:<?= $isCompleted ? '#27ae60' : ($isActive ? '#2980b9' : '#f0f4f8') ?>;color:<?= $isCompleted || $isActive ? '#fff' : '#95a5a6' ?>;display:flex;align-items:center;justify-content:center;font-weight:600;">
                            <?= $isCompleted ? '<i class="fas fa-check"></i>' : ($index + 1) ?>
                        </div>
                        <div>
                            <div style="color:<?= $isActive ? '#2980b9' : '#2d3436' ?>;font-weight:<?= $isActive ? '600' : '500' ?>;">
                                <?= ucfirst(str_replace('_', ' ', $step['etape'])) ?>
                            </div>
                            <div style="color:#95a5a6;font-size:0.9em;"><?= getRoleName($step['role_requis']) ?></div>
                        </div>
                    </div>
                    <?php if ($index < count($workflowSteps) - 1): ?>
                        <div style="flex-grow:1;height:2px;background:<?= $isCompleted ? '#27ae60' : '#f0f4f8' ?>;min-width:40px;"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Commentaires -->
            <div class="section-card" style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:32px;">
                <h2 style="color:#2980b9;font-size:1.3em;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-comments"></i> Commentaires (<?= count($commentaires) ?>)
                </h2>
                
                <form method="post" style="margin-bottom:24px;">
                    <div style="margin-bottom:16px;">
                        <textarea id="comment" name="comment" rows="3" required 
                            style="width:100%;padding:12px;border:1.5px solid #e0eafc;border-radius:8px;resize:vertical;min-height:100px;"></textarea>
                    </div>
                    <button type="submit" name="add_comment" style="background:#2980b9;color:#fff;border:none;padding:12px 24px;border-radius:8px;display:flex;align-items:center;gap:8px;cursor:pointer;transition:all 0.2s;">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </form>

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <?php foreach ($commentaires as $comment): ?>
                    <div style="background:#f8fafc;border-radius:12px;padding:16px;border-left:4px solid #2980b9;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                            <div style="color:#2980b9;font-weight:600;"><?= htmlspecialchars($comment['user_name']) ?></div>
                            <div style="color:#95a5a6;font-size:0.9em;"><?= formatDate($comment['created_at']) ?></div>
                        </div>
                        <div style="color:#2d3436;line-height:1.5;">
                            <?= nl2br(htmlspecialchars($comment['contenu'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="side-content">
            <!-- Pièces jointes -->
            <div class="section-card" style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:32px;">
                <h2 style="color:#2980b9;font-size:1.3em;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-paperclip"></i> Pièces Jointes (<?= count($attachments) ?>)
                </h2>
                
                <?php if (empty($attachments)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file fa-3x mb-3"></i>
                        <p>Aucune pièce jointe</p>
                    </div>
                <?php else: ?>
                    <div class="attachments-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:16px;">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-card" style="background:#f8fafc;border-radius:8px;padding:16px;border:1px solid #e9ecef;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="attachment-info">
                                        <div class="attachment-name font-weight-bold">
                                            <i class="fas fa-file-alt text-primary"></i>
                                            <?= htmlspecialchars(substr($attachment['original_name'], 0, 30)) ?>
                                            <?= strlen($attachment['original_name']) > 30 ? '...' : '' ?>
                                        </div>
                                        <div class="attachment-meta text-muted small">
                                            <?= formatFileSize($attachment['file_size']) ?> • 
                                            <?= date('d/m/Y', strtotime($attachment['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="attachment-actions">
                                        <a href="<?= BASE_URL ?>uploads/<?= $attachment['file_name'] ?>" 
                                           class="btn btn-outline-primary btn-sm" target="_blank" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Historique des actions -->
            <div class="section-card" style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(41,128,185,0.08);">
                <h2 style="color:#2980b9;font-size:1.3em;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-history"></i> Historique des Actions
                </h2>
                
                <?php if (empty($logs)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-history fa-3x mb-3"></i>
                        <p>Aucune action enregistrée</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logs as $index => $log): ?>
                            <div class="timeline-item <?= $index === 0 ? 'latest' : '' ?>" style="position:relative;padding-left:40px;margin-bottom:20px;">
                                <div class="timeline-marker" style="position:absolute;left:0;top:2px;width:20px;height:20px;border-radius:50%;background:<?= getActionColor($log['action_type']) ?>;border:3px solid #fff;box-shadow:0 0 0 3px <?= getActionColor($log['action_type']) ?>33;"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header" style="display:flex;justify-content:between;align-items:center;margin-bottom:4px;">
                                        <span class="action-type badge badge-<?= getActionTypeColor($log['action_type']) ?>" style="font-size:0.8em;">
                                            <?= htmlspecialchars($log['action_type']) ?>
                                        </span>
                                        <span class="timeline-time text-muted small">
                                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="timeline-body">
                                        <strong><?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?></strong> - 
                                        <?= htmlspecialchars($log['details']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>modules/logs/index.php?dossier_id=<?= $dossierId ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-external-link-alt"></i> Voir l'historique complet
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Actions rapides -->
            <div class="sidebar-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:20px;">
                <h3 style="color:#2980b9;font-size:1.1em;margin:0 0 16px 0;">Actions Rapides</h3>
                <div class="actions-list" style="display:flex;flex-direction:column;gap:8px;">
                    <?php if (canEditDossier($_SESSION['user_id'], $_SESSION['role'], $dossier['responsable_id'])): ?>
                        <a href="edit.php?id=<?= $dossierId ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?= BASE_URL ?>modules/export/export_pdf.php?id=<?= $dossierId ?>" 
                       class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    
                    <a href="list.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </div>
            
            <!-- Informations sur l'échéance -->
            <?php if ($dossier['deadline']): ?>
                <div class="sidebar-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(41,128,185,0.08);margin-bottom:20px;">
                    <h3 style="color:#2980b9;font-size:1.1em;margin:0 0 16px 0;">
                        <i class="fas fa-clock"></i> Échéance
                    </h3>
                    <div class="echeance-info">
                        <div class="echeance-date" style="font-size:1.2em;font-weight:bold;color:#2d3436;margin-bottom:8px;">
                            <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                        </div>
                        <div class="echeance-status">
                            <span class="badge badge-<?= $alerte_echeance ?> badge-lg">
                                <?= $temps_restant ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques du dossier -->
            <div class="sidebar-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(41,128,185,0.08);">
                <h3 style="color:#2980b9;font-size:1.1em;margin:0 0 16px 0;">Statistiques</h3>
                <div class="stats-list" style="display:flex;flex-direction:column;gap:12px;">
                    <div class="stat-item" style="display:flex;justify-content:between;align-items:center;">
                        <span class="stat-label" style="color:#7f8c8d;">Pièces jointes:</span>
                        <span class="stat-value font-weight-bold"><?= count($attachments) ?></span>
                    </div>
                    <div class="stat-item" style="display:flex;justify-content:between;align-items:center;">
                        <span class="stat-label" style="color:#7f8c8d;">Actions enregistrées:</span>
                        <span class="stat-value font-weight-bold"><?= count($logs) ?></span>
                    </div>
                    <div class="stat-item" style="display:flex;justify-content:between;align-items:center;">
                        <span class="stat-label" style="color:#7f8c8d;">Âge du dossier:</span>
                        <span class="stat-value font-weight-bold">
                            <?php 
                            $created = new DateTime($dossier['created_at']);
                            $now = new DateTime();
                            $age = $created->diff($now);
                            echo $age->days . ' jour' . ($age->days > 1 ? 's' : '');
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de changement de statut -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Changer le Statut</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="changeStatusForm">
                    <input type="hidden" id="dossierId" value="<?= $dossierId ?>">
                    <input type="hidden" id="newStatus">
                    
                    <div class="mb-3">
                        <label>Changement de statut :</label>
                        <div class="status-change-info">
                            <span class="badge badge-<?= getStatusColor($dossier['status']) ?>">
                                <?= getStatusLabel($dossier['status']) ?>
                            </span>
                            <i class="fas fa-arrow-right mx-2"></i>
                            <span id="targetStatus" class="badge"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Commentaire (optionnel):</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3" 
                                  placeholder="Raison du changement de statut..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChange">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du changement de statut
    document.querySelectorAll('.change-status').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const newStatus = this.dataset.status;
            const dossierId = this.dataset.dossier;
            
            document.getElementById('newStatus').value = newStatus;
            document.getElementById('targetStatus').textContent = this.textContent.trim();
            document.getElementById('targetStatus').className = 'badge badge-' + getStatusBootstrapColor(newStatus);
            
            $('#changeStatusModal').modal('show');
        });
    });
    
    // Confirmation du changement de statut
    document.getElementById('confirmStatusChange').addEventListener('click', function() {
        const dossierId = document.getElementById('dossierId').value;
        const newStatus = document.getElementById('newStatus').value;
        const comment = document.getElementById('comment').value;
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changement...';
        
        fetch('actions/change_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `dossier_id=${dossierId}&new_status=${newStatus}&comment=${encodeURIComponent(comment)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'interface
                document.getElementById('statusText').textContent = data.status_label;
                document.getElementById('statusBadge').style.background = getStatusHexColor(data.new_status);
                
                $('#changeStatusModal').modal('hide');
                
                // Afficher une notification
                showNotification('Statut modifié avec succès', 'success');
                
                // Recharger la page après 2 secondes
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Erreur: ' + data.message, 'error');
                this.disabled = false;
                this.innerHTML = 'Confirmer';
            }
        })
        .catch(error => {
            showNotification('Erreur de communication', 'error');
            this.disabled = false;
            this.innerHTML = 'Confirmer';
        });
    });
});

// Fonctions utilitaires
function getStatusBootstrapColor(status) {
    const colors = {
        'en_cours': 'warning',
        'valide': 'success', 
        'rejete': 'danger',
        'archive': 'secondary'
    };
    return colors[status] || 'light';
}

function getStatusHexColor(status) {
    const colors = {
        'en_cours': '#f39c12',
        'valide': '#27ae60',
        'rejete': '#e74c3c',
        'archive': '#95a5a6'
    };
    return colors[status] || '#6c757d';
}

function showNotification(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = `<div class="alert ${alertClass} alert-dismissible fade show" style="position:fixed;top:20px;right:20px;z-index:9999;">
        ${message}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', alert);
}
</script>

<?php 
// Fonctions utilitaires pour l'affichage
function getStatusColor($status) {
    $colors = [
        'en_cours' => '#f39c12',
        'valide' => '#27ae60', 
        'rejete' => '#e74c3c',
        'archive' => '#95a5a6'
    ];
    return $colors[$status] ?? '#6c757d';
}

function getStatusLabel($status) {
    $labels = [
        'en_cours' => 'En cours',
        'valide' => 'Validé',
        'rejete' => 'Rejeté', 
        'archive' => 'Archivé'
    ];
    return $labels[$status] ?? $status;
}

function getActionColor($action) {
    if (strpos($action, 'CREATE') !== false) return '#3498db';
    if (strpos($action, 'UPDATE') !== false) return '#f39c12';
    if (strpos($action, 'DELETE') !== false) return '#e74c3c';
    if (strpos($action, 'CHANGE_STATUS') !== false) return '#9b59b6';
    return '#95a5a6';
}

function getActionTypeColor($action) {
    if (strpos($action, 'CREATE') !== false) return 'primary';
    if (strpos($action, 'UPDATE') !== false) return 'warning';
    if (strpos($action, 'DELETE') !== false) return 'danger';
    if (strpos($action, 'CHANGE_STATUS') !== false) return 'info';
    return 'secondary';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function canEditDossier($user_id, $user_role, $responsable_id) {
    return $user_role <= ROLE_GESTIONNAIRE || $user_id == $responsable_id;
}

include '../../includes/footer.php'; 
?>