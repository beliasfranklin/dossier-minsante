<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $dossierId = (int)($_POST['dossier_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'start_workflow':
            $result = startWorkflowProcess($dossierId);
            echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
            exit;
            
        case 'approve_step':
            $stepId = (int)$_POST['step_id'];
            $comments = cleanInput($_POST['comments'] ?? '');
            $result = approveWorkflowStep($dossierId, $stepId, $userId, $comments);
            echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
            exit;
            
        case 'reject_step':
            $stepId = (int)$_POST['step_id'];
            $reason = cleanInput($_POST['reason'] ?? '');
            $result = rejectWorkflowStep($dossierId, $stepId, $userId, $reason);
            echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
            exit;
            
        case 'delegate_approval':
            $stepId = (int)$_POST['step_id'];
            $delegateToId = (int)$_POST['delegate_to'];
            $result = delegateApproval($dossierId, $stepId, $userId, $delegateToId);
            echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
            exit;
    }
}

/**
 * Démarre le processus de workflow pour un dossier
 */
function startWorkflowProcess($dossierId) {
    try {
        $dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);
        if (!$dossier) {
            return ['success' => false, 'message' => 'Dossier introuvable'];
        }
        
        // Récupérer le workflow pour ce type de dossier
        $workflowSteps = fetchAll(
            "SELECT * FROM workflows WHERE type_dossier = ? ORDER BY ordre ASC",
            [$dossier['type']]
        );
        
        if (empty($workflowSteps)) {
            return ['success' => false, 'message' => 'Aucun workflow défini pour ce type de dossier'];
        }
        
        // Créer les instances d'étapes pour ce dossier
        foreach ($workflowSteps as $step) {
            executeQuery(
                "INSERT INTO workflow_instances (dossier_id, workflow_step_id, status, ordre, role_requis) 
                 VALUES (?, ?, 'pending', ?, ?)",
                [$dossierId, $step['id'], $step['ordre'], $step['role_requis']]
            );
        }
        
        // Activer la première étape
        $firstStep = $workflowSteps[0];
        executeQuery(
            "UPDATE workflow_instances 
             SET status = 'active', started_at = NOW() 
             WHERE dossier_id = ? AND ordre = 1",
            [$dossierId]
        );
        
        // Assigner aux utilisateurs ayant le bon rôle
        $assignees = fetchAll(
            "SELECT id, name, email FROM users WHERE role >= ?",
            [$firstStep['role_requis']]
        );
        
        foreach ($assignees as $assignee) {
            createNotification(
                $assignee['id'],
                "Nouvelle tâche de workflow",
                "Le dossier {$dossier['reference']} nécessite votre attention pour l'étape : {$firstStep['etape']}",
                'workflow',
                $dossierId
            );
        }
        
        // Mettre à jour le statut du dossier
        executeQuery(
            "UPDATE dossiers SET status = 'en_cours', workflow_started = 1 WHERE id = ?",
            [$dossierId]
        );
        
        logAction($_SESSION['user_id'], 'workflow_started', $dossierId, "Workflow démarré");
        
        return ['success' => true, 'message' => 'Workflow démarré avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Approuve une étape du workflow
 */
function approveWorkflowStep($dossierId, $stepId, $userId, $comments = '') {
    try {
        // Vérifier que l'utilisateur a le bon rôle
        $step = fetchOne(
            "SELECT wi.*, w.etape, w.role_requis 
             FROM workflow_instances wi
             JOIN workflows w ON wi.workflow_step_id = w.id
             WHERE wi.id = ? AND wi.dossier_id = ? AND wi.status = 'active'",
            [$stepId, $dossierId]
        );
        
        if (!$step) {
            return ['success' => false, 'message' => 'Étape introuvable ou inactive'];
        }
        
        $user = fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($user['role'] < $step['role_requis']) {
            return ['success' => false, 'message' => 'Permissions insuffisantes pour cette étape'];
        }
        
        // Marquer l'étape comme approuvée
        executeQuery(
            "UPDATE workflow_instances 
             SET status = 'approved', 
                 approved_by = ?, 
                 approved_at = NOW(), 
                 comments = ?
             WHERE id = ?",
            [$userId, $comments, $stepId]
        );
        
        // Passer à l'étape suivante
        $nextStep = fetchOne(
            "SELECT * FROM workflow_instances 
             WHERE dossier_id = ? AND ordre = ? AND status = 'pending'",
            [$dossierId, $step['ordre'] + 1]
        );
        
        if ($nextStep) {
            // Activer l'étape suivante
            executeQuery(
                "UPDATE workflow_instances 
                 SET status = 'active', started_at = NOW() 
                 WHERE id = ?",
                [$nextStep['id']]
            );
            
            // Notifier les responsables de l'étape suivante
            $nextWorkflow = fetchOne("SELECT etape FROM workflows WHERE id = ?", [$nextStep['workflow_step_id']]);
            $assignees = fetchAll(
                "SELECT id, name, email FROM users WHERE role >= ?",
                [$nextStep['role_requis']]
            );
            
            foreach ($assignees as $assignee) {
                createNotification(
                    $assignee['id'],
                    "Nouvelle étape de workflow",
                    "L'étape '{$nextWorkflow['etape']}' du dossier nécessite votre attention",
                    'workflow',
                    $dossierId
                );
            }
        } else {
            // Workflow terminé
            executeQuery(
                "UPDATE dossiers SET status = 'valide', workflow_completed = 1 WHERE id = ?",
                [$dossierId]
            );
            
            // Notifier le responsable du dossier
            $dossier = fetchOne(
                "SELECT d.reference, d.responsable_id, u.email 
                 FROM dossiers d JOIN users u ON d.responsable_id = u.id 
                 WHERE d.id = ?",
                [$dossierId]
            );
            
            createNotification(
                $dossier['responsable_id'],
                "Workflow terminé",
                "Le workflow du dossier {$dossier['reference']} s'est terminé avec succès",
                'workflow',
                $dossierId
            );
        }
        
        logAction($userId, 'workflow_approved', $dossierId, "Étape approuvée : {$step['etape']}");
        
        return ['success' => true, 'message' => 'Étape approuvée avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Rejette une étape du workflow
 */
function rejectWorkflowStep($dossierId, $stepId, $userId, $reason) {
    try {
        $step = fetchOne(
            "SELECT wi.*, w.etape 
             FROM workflow_instances wi
             JOIN workflows w ON wi.workflow_step_id = w.id
             WHERE wi.id = ? AND wi.dossier_id = ? AND wi.status = 'active'",
            [$stepId, $dossierId]
        );
        
        if (!$step) {
            return ['success' => false, 'message' => 'Étape introuvable ou inactive'];
        }
        
        // Marquer l'étape comme rejetée
        executeQuery(
            "UPDATE workflow_instances 
             SET status = 'rejected', 
                 rejected_by = ?, 
                 rejected_at = NOW(), 
                 rejection_reason = ?
             WHERE id = ?",
            [$userId, $reason, $stepId]
        );
        
        // Marquer le dossier comme rejeté
        executeQuery(
            "UPDATE dossiers SET status = 'rejete' WHERE id = ?",
            [$dossierId]
        );
        
        // Notifier le responsable du dossier
        $dossier = fetchOne(
            "SELECT d.reference, d.responsable_id 
             FROM dossiers d 
             WHERE d.id = ?",
            [$dossierId]
        );
        
        createNotification(
            $dossier['responsable_id'],
            "Dossier rejeté",
            "Le dossier {$dossier['reference']} a été rejeté à l'étape '{$step['etape']}'. Raison : {$reason}",
            'workflow',
            $dossierId
        );
        
        logAction($userId, 'workflow_rejected', $dossierId, "Étape rejetée : {$step['etape']} - {$reason}");
        
        return ['success' => true, 'message' => 'Étape rejetée'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Délègue l'approbation à un autre utilisateur
 */
function delegateApproval($dossierId, $stepId, $fromUserId, $toUserId) {
    try {
        $step = fetchOne(
            "SELECT wi.*, w.etape 
             FROM workflow_instances wi
             JOIN workflows w ON wi.workflow_step_id = w.id
             WHERE wi.id = ? AND wi.dossier_id = ? AND wi.status = 'active'",
            [$stepId, $dossierId]
        );
        
        if (!$step) {
            return ['success' => false, 'message' => 'Étape introuvable ou inactive'];
        }
        
        // Vérifier que le destinataire a les bonnes permissions
        $toUser = fetchOne("SELECT role, name FROM users WHERE id = ?", [$toUserId]);
        if ($toUser['role'] < $step['role_requis']) {
            return ['success' => false, 'message' => 'Le destinataire n\'a pas les permissions suffisantes'];
        }
        
        // Enregistrer la délégation
        executeQuery(
            "UPDATE workflow_instances 
             SET delegated_to = ?, delegated_at = NOW() 
             WHERE id = ?",
            [$toUserId, $stepId]
        );
        
        // Notifier le destinataire
        $dossier = fetchOne("SELECT reference FROM dossiers WHERE id = ?", [$dossierId]);
        createNotification(
            $toUserId,
            "Délégation d'approbation",
            "L'approbation du dossier {$dossier['reference']} à l'étape '{$step['etape']}' vous a été déléguée",
            'workflow',
            $dossierId
        );
        
        logAction($fromUserId, 'workflow_delegated', $dossierId, "Délégation à {$toUser['name']} pour l'étape {$step['etape']}");
        
        return ['success' => true, 'message' => 'Approbation déléguée avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

// Interface utilisateur pour le workflow automatique
if (!isset($_GET['dossier_id'])) {
    header('Location: ../dossiers/list.php');
    exit;
}

$dossierId = (int)$_GET['dossier_id'];
$dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);
if (!$dossier) {
    die('Dossier introuvable');
}

// Récupérer les étapes du workflow pour ce dossier
$workflowInstances = fetchAll("
    SELECT wi.*, w.etape, w.role_requis, u1.name as approved_by_name, u2.name as rejected_by_name
    FROM workflow_instances wi
    JOIN workflows w ON wi.workflow_step_id = w.id
    LEFT JOIN users u1 ON wi.approved_by = u1.id
    LEFT JOIN users u2 ON wi.rejected_by = u2.id
    WHERE wi.dossier_id = ?
    ORDER BY wi.ordre ASC
", [$dossierId]);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Automatique - <?= htmlspecialchars($dossier['reference']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .workflow-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .workflow-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .workflow-step {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #e1e8ed;
            transition: all 0.3s ease;
        }
        
        .workflow-step.pending {
            border-left-color: #f39c12;
            background: #fef9e7;
        }
        
        .workflow-step.active {
            border-left-color: #3498db;
            background: #ebf3fd;
            transform: scale(1.02);
        }
        
        .workflow-step.approved {
            border-left-color: #27ae60;
            background: #eafaf1;
        }
        
        .workflow-step.rejected {
            border-left-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .step-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #2d3748;
        }
        
        .step-status {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .status-pending {
            background: #f39c12;
            color: white;
        }
        
        .status-active {
            background: #3498db;
            color: white;
            animation: pulse 2s infinite;
        }
        
        .status-approved {
            background: #27ae60;
            color: white;
        }
        
        .status-rejected {
            background: #e74c3c;
            color: white;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .step-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-workflow {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-approve {
            background: #27ae60;
            color: white;
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delegate {
            background: #f39c12;
            color: white;
        }
        
        .btn-workflow:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .step-details {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.5);
            border-radius: 8px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #e1e8ed;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #3498db;
        }
        
        .timeline-item.completed::before {
            background: #27ae60;
        }
        
        .timeline-item.rejected::before {
            background: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="workflow-container">
        <!-- En-tête -->
        <div class="workflow-header">
            <h1><i class="fas fa-sitemap"></i> Workflow Automatique</h1>
            <p>Dossier : <strong><?= htmlspecialchars($dossier['reference']) ?></strong> - <?= htmlspecialchars($dossier['titre']) ?></p>
            <p>Statut actuel : <span class="status-badge status-<?= $dossier['status'] ?>"><?= ucfirst($dossier['status']) ?></span></p>
        </div>
        
        <!-- Actions principales -->
        <div style="text-align: center; margin-bottom: 30px;">
            <?php if (empty($workflowInstances) && $dossier['status'] !== 'archive'): ?>
                <button onclick="startWorkflow()" class="btn-workflow btn-approve" style="font-size: 1.1em; padding: 15px 30px;">
                    <i class="fas fa-play"></i> Démarrer le Workflow
                </button>
            <?php endif; ?>
            
            <a href="../dossiers/view.php?id=<?= $dossierId ?>" class="btn-workflow" style="background: #636e72; color: white; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Retour au dossier
            </a>
        </div>
        
        <!-- Étapes du workflow -->
        <?php if (!empty($workflowInstances)): ?>
            <div class="timeline">
                <?php foreach ($workflowInstances as $instance): ?>
                    <div class="workflow-step <?= $instance['status'] ?> timeline-item <?= $instance['status'] === 'approved' ? 'completed' : ($instance['status'] === 'rejected' ? 'rejected' : '') ?>">
                        <div class="step-header">
                            <div class="step-title">
                                <i class="fas fa-cog"></i> <?= htmlspecialchars($instance['etape']) ?>
                            </div>
                            <div class="step-status status-<?= $instance['status'] ?>">
                                <?php
                                switch($instance['status']) {
                                    case 'pending': echo '<i class="fas fa-clock"></i> En attente'; break;
                                    case 'active': echo '<i class="fas fa-play-circle"></i> Actif'; break;
                                    case 'approved': echo '<i class="fas fa-check-circle"></i> Approuvé'; break;
                                    case 'rejected': echo '<i class="fas fa-times-circle"></i> Rejeté'; break;
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div style="color: #64748b; margin-bottom: 10px;">
                            <i class="fas fa-user-tag"></i> Rôle requis : <?= getRoleName($instance['role_requis']) ?>
                        </div>
                        
                        <?php if ($instance['status'] === 'active' && hasPermission($instance['role_requis'])): ?>
                            <div class="step-actions">
                                <button onclick="approveStep(<?= $instance['id'] ?>)" class="btn-workflow btn-approve">
                                    <i class="fas fa-check"></i> Approuver
                                </button>
                                <button onclick="rejectStep(<?= $instance['id'] ?>)" class="btn-workflow btn-reject">
                                    <i class="fas fa-times"></i> Rejeter
                                </button>
                                <button onclick="delegateStep(<?= $instance['id'] ?>)" class="btn-workflow btn-delegate">
                                    <i class="fas fa-share"></i> Déléguer
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($instance['status'] === 'approved'): ?>
                            <div class="step-details">
                                <p><strong>Approuvé par :</strong> <?= htmlspecialchars($instance['approved_by_name']) ?></p>
                                <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($instance['approved_at'])) ?></p>
                                <?php if ($instance['comments']): ?>
                                    <p><strong>Commentaires :</strong> <?= htmlspecialchars($instance['comments']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($instance['status'] === 'rejected'): ?>
                            <div class="step-details">
                                <p><strong>Rejeté par :</strong> <?= htmlspecialchars($instance['rejected_by_name']) ?></p>
                                <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($instance['rejected_at'])) ?></p>
                                <p><strong>Raison :</strong> <?= htmlspecialchars($instance['rejection_reason']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #64748b;">
                <i class="fas fa-info-circle" style="font-size: 3em; margin-bottom: 20px;"></i>
                <h3>Aucun workflow actif</h3>
                <p>Ce dossier n'a pas encore de workflow démarré.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modals -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-check-circle"></i> Approuver l'étape</h3>
            <form id="approveForm">
                <div class="form-group">
                    <label for="approveComments">Commentaires (optionnel) :</label>
                    <textarea id="approveComments" class="form-control" rows="4" placeholder="Ajoutez vos commentaires..."></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('approveModal')" style="background: #636e72;" class="btn-workflow">Annuler</button>
                    <button type="submit" class="btn-workflow btn-approve">Confirmer l'approbation</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-times-circle"></i> Rejeter l'étape</h3>
            <form id="rejectForm">
                <div class="form-group">
                    <label for="rejectReason">Raison du rejet * :</label>
                    <textarea id="rejectReason" class="form-control" rows="4" placeholder="Expliquez la raison du rejet..." required></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('rejectModal')" style="background: #636e72;" class="btn-workflow">Annuler</button>
                    <button type="submit" class="btn-workflow btn-reject">Confirmer le rejet</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="delegateModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-share"></i> Déléguer l'approbation</h3>
            <form id="delegateForm">
                <div class="form-group">
                    <label for="delegateTo">Déléguer à :</label>
                    <select id="delegateTo" class="form-control" required>
                        <option value="">Sélectionner un utilisateur...</option>
                        <?php
                        $users = fetchAll("SELECT id, name, role FROM users WHERE role >= ? ORDER BY name", [ROLE_GESTIONNAIRE]);
                        foreach ($users as $user):
                        ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= getRoleName($user['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('delegateModal')" style="background: #636e72;" class="btn-workflow">Annuler</button>
                    <button type="submit" class="btn-workflow btn-delegate">Déléguer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentStepId = null;
        
        function startWorkflow() {
            if (confirm('Êtes-vous sûr de vouloir démarrer le workflow pour ce dossier ?')) {
                fetch('automatic.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=start_workflow&dossier_id=<?= $dossierId ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Erreur : ' + data.message);
                    }
                });
            }
        }
        
        function approveStep(stepId) {
            currentStepId = stepId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function rejectStep(stepId) {
            currentStepId = stepId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function delegateStep(stepId) {
            currentStepId = stepId;
            document.getElementById('delegateModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            currentStepId = null;
        }
        
        // Gestionnaires de formulaires
        document.getElementById('approveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const comments = document.getElementById('approveComments').value;
            
            fetch('automatic.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=approve_step&dossier_id=<?= $dossierId ?>&step_id=${currentStepId}&comments=${encodeURIComponent(comments)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        });
        
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const reason = document.getElementById('rejectReason').value;
            
            fetch('automatic.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=reject_step&dossier_id=<?= $dossierId ?>&step_id=${currentStepId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        });
        
        document.getElementById('delegateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const delegateTo = document.getElementById('delegateTo').value;
            
            fetch('automatic.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delegate_approval&dossier_id=<?= $dossierId ?>&step_id=${currentStepId}&delegate_to=${delegateTo}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        });
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                currentStepId = null;
            }
        }
    </script>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
