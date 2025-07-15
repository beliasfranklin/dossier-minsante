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

// Fonction utilitaire pour l'affichage des rôles
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

<style>
:root {
    --dossier-primary: #667eea;
    --dossier-secondary: #764ba2;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-bg: #f8fafc;
    --white: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.dossier-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    animation: fadeIn 0.8s ease-out;
}

.back-navigation {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--dossier-primary);
    text-decoration: none;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 50px;
    background: rgba(102, 126, 234, 0.1);
    transition: all 0.3s ease;
    margin-bottom: 24px;
}

.back-navigation:hover {
    background: var(--dossier-primary);
    color: var(--white);
    transform: translateX(-4px);
}

.dossier-hero {
    background: linear-gradient(135deg, var(--dossier-primary) 0%, var(--dossier-secondary) 100%);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow);
    animation: slideInDown 0.8s ease-out;
}

.dossier-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-header {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 24px;
}

.hero-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    color: var(--white);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}

.hero-info h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 12px 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    line-height: 1.2;
}

.hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.hero-badge {
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: var(--white);
    background: rgba(255, 255, 255, 0.2);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-btn {
    padding: 14px 24px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50px;
    background: rgba(255, 255, 255, 0.2);
    color: var(--white);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 8px;
}

.hero-btn:hover {
    background: var(--white);
    color: var(--dossier-primary);
    transform: translateY(-2px);
}

.main-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 32px;
    align-items: start;
}

.content-section {
    background: var(--white);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
}

.content-section:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--light-bg);
}

.section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--dossier-primary), var(--dossier-secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.2rem;
}

.section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.info-card {
    background: var(--light-bg);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.1);
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dossier-primary);
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-card-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    word-break: break-word;
}

.workflow-container {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 0;
    overflow-x: auto;
    scrollbar-width: thin;
}

.workflow-step {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 200px;
    flex-shrink: 0;
}

.step-number {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.step-info h4 {
    margin: 0 0 4px 0;
    font-size: 1rem;
    font-weight: 600;
}

.step-info p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.step-connector {
    flex: 1;
    height: 3px;
    border-radius: 2px;
    min-width: 40px;
    transition: all 0.3s ease;
}

.step-active .step-number {
    background: var(--dossier-primary);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.step-completed .step-number {
    background: var(--success-color);
    color: var(--white);
}

.step-pending .step-number {
    background: var(--light-bg);
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.step-completed .step-connector,
.step-active .step-connector {
    background: var(--success-color);
}

.step-pending .step-connector {
    background: var(--border-color);
}

.comments-section {
    max-height: 600px;
    overflow-y: auto;
}

.comment-form {
    background: var(--light-bg);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.form-textarea {
    width: 100%;
    min-height: 120px;
    padding: 16px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    resize: vertical;
    font-family: inherit;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-textarea:focus {
    outline: none;
    border-color: var(--dossier-primary);
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.submit-btn {
    background: linear-gradient(135deg, var(--dossier-primary), var(--dossier-secondary));
    color: var(--white);
    border: none;
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.comment-item {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--dossier-primary);
    transition: all 0.3s ease;
}

.comment-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.comment-author {
    font-weight: 600;
    color: var(--dossier-primary);
    font-size: 1rem;
}

.comment-date {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.comment-content {
    color: var(--text-primary);
    line-height: 1.6;
    font-size: 1rem;
}

.sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.sidebar-card {
    background: var(--white);
    border-radius: 20px;
    padding: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    animation: fadeInRight 0.6s ease-out;
}

.sidebar-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-bottom: 8px;
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    background: var(--white);
}

.action-btn:hover {
    background: var(--dossier-primary);
    color: var(--white);
    border-color: var(--dossier-primary);
    transform: translateX(4px);
}

.action-btn.primary {
    background: linear-gradient(135deg, var(--dossier-primary), var(--dossier-secondary));
    color: var(--white);
    border-color: var(--dossier-primary);
}

.stat-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.stat-value {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.attachment-grid {
    display: grid;
    gap: 12px;
}

.attachment-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: var(--light-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.attachment-item:hover {
    background: var(--white);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
}

.attachment-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.attachment-icon {
    width: 40px;
    height: 40px;
    background: var(--dossier-primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
}

.attachment-details h5 {
    margin: 0 0 4px 0;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.attachment-meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.download-btn {
    padding: 8px 12px;
    background: var(--dossier-primary);
    color: var(--white);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.9rem;
}

.download-btn:hover {
    background: var(--dossier-secondary);
    transform: scale(1.05);
}

.timeline {
    position: relative;
    padding-left: 32px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: var(--light-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.timeline-item:hover {
    background: var(--white);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--dossier-primary);
    border: 3px solid var(--white);
    box-shadow: 0 0 0 2px var(--dossier-primary);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.timeline-action {
    background: var(--dossier-primary);
    color: var(--white);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.timeline-date {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.timeline-content {
    color: var(--text-primary);
    line-height: 1.5;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: var(--light-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 2rem;
    color: var(--text-secondary);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@media (max-width: 1024px) {
    .main-layout {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .hero-header {
        flex-direction: column;
        text-align: center;
    }
    
    .hero-info h1 {
        font-size: 2rem;
    }
    
    .info-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
    }
}

@media (max-width: 768px) {
    .dossier-container {
        padding: 16px;
    }
    
    .dossier-hero {
        padding: 24px;
    }
    
    .content-section {
        padding: 20px;
    }
    
    .workflow-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .workflow-step {
        min-width: unset;
    }
    
    .step-connector {
        display: none;
    }
    
    .hero-badges {
        justify-content: center;
    }
    
    .hero-actions {
        justify-content: center;
    }
}
</style>

<div class="dossier-container">
    <a href="list.php" class="back-navigation">
        <i class="fas fa-arrow-left"></i>
        Retour à la liste des dossiers
    </a>

    <div class="dossier-hero">
        <div class="hero-content">
            <div class="hero-header">
                <div class="hero-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="hero-info">
                    <h1><?= htmlspecialchars($dossier['titre']) ?></h1>
                    <div class="hero-badges">
                        <div class="hero-badge">
                            <i class="fas fa-hashtag"></i>
                            <?= htmlspecialchars($dossier['reference']) ?>
                        </div>
                        <div class="hero-badge" style="background: <?= getStatusColor($dossier['status']) ?>;">
                            <i class="fas fa-circle"></i>
                            <?= getStatusLabel($dossier['status']) ?>
                        </div>
                        <?php if ($dossier['category_name']): ?>
                            <div class="hero-badge" style="background: <?= htmlspecialchars($dossier['category_color']) ?>;">
                                <i class="fas fa-<?= htmlspecialchars($dossier['category_icon']) ?>"></i>
                                <?= htmlspecialchars($dossier['category_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($temps_restant): ?>
                            <div class="hero-badge" style="background: <?= $alerte_echeance === 'danger' ? 'var(--danger-color)' : ($alerte_echeance === 'warning' ? 'var(--warning-color)' : 'var(--success-color)') ?>;">
                                <i class="fas fa-clock"></i>
                                <?= htmlspecialchars($temps_restant) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="hero-actions">
                <?php if (canEditDossier($_SESSION['user_id'], $_SESSION['role'], $dossier['responsable_id'])): ?>
                    <a href="edit.php?id=<?= $dossierId ?>" class="hero-btn">
                        <i class="fas fa-edit"></i>
                        Modifier le dossier
                    </a>
                <?php endif; ?>
                <a href="../export/export.php?format=pdf&id=<?= $dossierId ?>" class="hero-btn">
                    <i class="fas fa-file-pdf"></i>
                    Exporter en PDF
                </a>
            </div>
        </div>
    </div>

    <div class="main-layout">
    <div class="main-layout">
        <div class="main-content">
            <!-- Informations principales -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h2 class="section-title">Informations du Dossier</h2>
                </div>
                
                <div class="info-grid">
                    <?php
                    $infoCards = [
                        ['icon' => 'folder', 'title' => 'Type', 'value' => $dossier['type'] ?? 'Non défini'],
                        ['icon' => 'building', 'title' => 'Service', 'value' => $dossier['service'] ?? 'Non défini'],
                        ['icon' => 'user-plus', 'title' => 'Créé par', 'value' => trim(($dossier['created_by_prenom'] ?? '') . ' ' . ($dossier['created_by_nom'] ?? '')) ?: 'Non défini'],
                        ['icon' => 'user-shield', 'title' => 'Responsable', 'value' => trim(($dossier['responsable_prenom'] ?? '') . ' ' . ($dossier['responsable_nom'] ?? '')) ?: 'Non assigné'],
                        ['icon' => 'calendar-plus', 'title' => 'Date de création', 'value' => date('d/m/Y à H:i', strtotime($dossier['created_at']))],
                        ['icon' => 'calendar-check', 'title' => 'Dernière modification', 'value' => date('d/m/Y à H:i', strtotime($dossier['updated_at']))]
                    ];
                    
                    if ($dossier['deadline']) {
                        $infoCards[] = ['icon' => 'calendar-alt', 'title' => 'Échéance', 'value' => date('d/m/Y', strtotime($dossier['deadline']))];
                    }
                    
                    foreach ($infoCards as $card):
                    ?>
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-<?= $card['icon'] ?>"></i>
                            <?= $card['title'] ?>
                        </div>
                        <div class="info-card-value"><?= htmlspecialchars($card['value']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-align-left"></i>
                    </div>
                    <h2 class="section-title">Description</h2>
                </div>
                <div class="section-content">
                    <?php if (!empty($dossier['description'])): ?>
                        <p style="line-height: 1.7; color: var(--text-primary); font-size: 1.1rem;">
                            <?= nl2br(htmlspecialchars($dossier['description'])) ?>
                        </p>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <p>Aucune description fournie pour ce dossier.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Workflow -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h2 class="section-title">Progression du Workflow</h2>
                </div>
                
                <div class="workflow-container">
                    <?php 
                    $currentStepIndex = array_search($dossier['status'], array_column($workflowSteps, 'etape'));
                    if ($currentStepIndex === false) $currentStepIndex = -1;
                    
                    foreach ($workflowSteps as $index => $step): 
                        $isActive = $index === $currentStepIndex;
                        $isCompleted = $index < $currentStepIndex;
                        $isPending = $index > $currentStepIndex;
                        
                        $stepClass = $isCompleted ? 'step-completed' : ($isActive ? 'step-active' : 'step-pending');
                    ?>
                    <div class="workflow-step <?= $stepClass ?>">
                        <div class="step-number">
                            <?php if ($isCompleted): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?= $index + 1 ?>
                            <?php endif; ?>
                        </div>
                        <div class="step-info">
                            <h4><?= ucfirst(str_replace('_', ' ', $step['etape'])) ?></h4>
                            <p><?= getRoleName($step['role_requis']) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($index < count($workflowSteps) - 1): ?>
                        <div class="step-connector <?= $isCompleted ? 'step-completed' : ($isActive ? 'step-active' : 'step-pending') ?>"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Commentaires -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h2 class="section-title">Commentaires (<?= count($commentaires) ?>)</h2>
                </div>
                
                <div class="comments-section">
                    <!-- Formulaire d'ajout de commentaire -->
                    <form class="comment-form" method="post" id="commentForm">
                        <input type="hidden" name="action" value="add_comment">
                        <textarea 
                            class="form-textarea" 
                            name="comment" 
                            placeholder="Ajouter un commentaire..."
                            required
                        ></textarea>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Publier le commentaire
                        </button>
                    </form>

                    <!-- Liste des commentaires -->
                    <div class="comments-list">
                        <?php if (empty($commentaires)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <p>Aucun commentaire pour le moment.</p>
                                <small>Soyez le premier à commenter ce dossier.</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($commentaires as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <i class="fas fa-user-circle"></i>
                                        <?= htmlspecialchars($comment['user_name']) ?>
                                    </div>
                                    <div class="comment-date">
                                        <i class="fas fa-clock"></i>
                                        <?= formatDate($comment['created_at']) ?>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Actions rapides -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-bolt"></i>
                    Actions Rapides
                </h3>
                <div class="actions-list">
                    <?php if (canEditDossier($_SESSION['user_id'], $_SESSION['role'], $dossier['responsable_id'])): ?>
                        <a href="edit.php?id=<?= $dossierId ?>" class="action-btn primary">
                            <i class="fas fa-edit"></i>
                            Modifier le dossier
                        </a>
                    <?php endif; ?>
                    
                    <a href="../export/export.php?format=pdf&id=<?= $dossierId ?>" class="action-btn">
                        <i class="fas fa-file-pdf"></i>
                        Exporter en PDF
                    </a>
                    
                    <a href="../export/export.php?format=excel&id=<?= $dossierId ?>" class="action-btn">
                        <i class="fas fa-file-excel"></i>
                        Exporter en Excel
                    </a>
                    
                    <a href="list.php" class="action-btn">
                        <i class="fas fa-arrow-left"></i>
                        Retour à la liste
                    </a>
                </div>
            </div>

            <!-- Informations sur l'échéance -->
            <?php if ($dossier['deadline']): ?>
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <i class="fas fa-calendar-alt"></i>
                        Échéance
                    </h3>
                    <div class="deadline-info">
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                            <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                        </div>
                        <div style="padding: 8px 16px; border-radius: 20px; text-align: center; font-weight: 600; color: white; background: <?= $alerte_echeance === 'danger' ? 'var(--danger-color)' : ($alerte_echeance === 'warning' ? 'var(--warning-color)' : 'var(--success-color)') ?>;">
                            <i class="fas fa-clock"></i>
                            <?= htmlspecialchars($temps_restant) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pièces jointes -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-paperclip"></i>
                    Pièces Jointes (<?= count($attachments) ?>)
                </h3>
                
                <?php if (empty($attachments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <p>Aucune pièce jointe</p>
                    </div>
                <?php else: ?>
                    <div class="attachment-grid">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <div class="attachment-info">
                                    <div class="attachment-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="attachment-details">
                                        <h5><?= htmlspecialchars(substr($attachment['original_name'], 0, 25)) ?><?= strlen($attachment['original_name']) > 25 ? '...' : '' ?></h5>
                                        <div class="attachment-meta">
                                            <?= formatFileSize($attachment['file_size']) ?> • 
                                            <?= date('d/m/Y', strtotime($attachment['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>uploads/<?= $attachment['file_name'] ?>" 
                                   class="download-btn" target="_blank" title="Télécharger">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistiques -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques
                </h3>
                <div class="stat-list">
                    <div class="stat-item">
                        <span class="stat-label">Pièces jointes:</span>
                        <span class="stat-value"><?= count($attachments) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Commentaires:</span>
                        <span class="stat-value"><?= count($commentaires) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Actions enregistrées:</span>
                        <span class="stat-value"><?= count($logs) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Âge du dossier:</span>
                        <span class="stat-value">
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

            <!-- Historique des actions -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-history"></i>
                    Historique Récent
                </h3>
                
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <p>Aucune action enregistrée</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach (array_slice($logs, 0, 5) as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-header">
                                    <div class="timeline-action">
                                        <?= htmlspecialchars($log['action_type']) ?>
                                    </div>
                                    <div class="timeline-date">
                                        <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="timeline-content">
                                    <strong><?= htmlspecialchars(($log['prenom'] ?? '') . ' ' . ($log['nom'] ?? '')) ?></strong><br>
                                    <?= htmlspecialchars($log['details']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($logs) > 5): ?>
                            <div style="text-align: center; margin-top: 16px;">
                                <a href="../logs/index.php?dossier_id=<?= $dossierId ?>" class="action-btn">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir l'historique complet
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des sections
    const sections = document.querySelectorAll('.content-section, .sidebar-card');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Gestion du formulaire de commentaire
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.submit-btn');
            const textarea = this.querySelector('textarea[name="comment"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Animation du bouton de soumission
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(this);
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Réinitialiser le formulaire
                    textarea.value = '';
                    
                    // Afficher une notification de succès
                    showNotification('Commentaire ajouté avec succès !', 'success');
                    
                    // Recharger la page après 1.5 secondes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Erreur: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Erreur de communication avec le serveur', 'error');
            } finally {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
        });
    }

    // Animation des cartes d'information au survol
    const infoCards = document.querySelectorAll('.info-card');
    infoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
            this.style.boxShadow = '0 12px 30px rgba(102, 126, 234, 0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.1)';
        });
    });

    // Animation du workflow
    const workflowSteps = document.querySelectorAll('.workflow-step');
    workflowSteps.forEach((step, index) => {
        step.style.opacity = '0';
        step.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            step.style.transition = 'all 0.5s ease';
            step.style.opacity = '1';
            step.style.transform = 'translateX(0)';
        }, index * 200);
    });

    // Effet de ripple sur les boutons d'action
    const actionButtons = document.querySelectorAll('.action-btn, .hero-btn, .submit-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            createRippleEffect(this, e);
        });
    });

    // Scroll fluide vers les sections
    const scrollLinks = document.querySelectorAll('a[href^="#"]');
    scrollLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Animation des commentaires
    const comments = document.querySelectorAll('.comment-item');
    comments.forEach((comment, index) => {
        comment.style.opacity = '0';
        comment.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            comment.style.transition = 'all 0.5s ease';
            comment.style.opacity = '1';
            comment.style.transform = 'translateX(0)';
        }, index * 150);
    });

    // Gestion du redimensionnement automatique du textarea
    const textarea = document.querySelector('.form-textarea');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(120, this.scrollHeight) + 'px';
        });
    }

    console.log('📁 Page de visualisation du dossier initialisée avec succès');
});

// Fonction pour créer un effet de ripple
function createRippleEffect(element, event) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple-effect 0.6s ease-out;
        pointer-events: none;
        z-index: 100;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Fonction pour afficher des notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'var(--success-color)' : 
                   type === 'error' ? 'var(--danger-color)' : 
                   type === 'warning' ? 'var(--warning-color)' : 'var(--info-color)';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        max-width: 400px;
        transform: translateX(100%);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
    `;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                type === 'error' ? 'fa-exclamation-circle' : 
                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; margin-left: auto;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Suppression automatique après 5 secondes
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Animation CSS pour l'effet ripple
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple-effect {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

// Gestion des raccourcis clavier
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + E pour éditer (si autorisé)
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        const editLink = document.querySelector('a[href*="edit.php"]');
        if (editLink) {
            window.location.href = editLink.href;
        }
    }
    
    // Ctrl/Cmd + P pour imprimer/PDF
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        const pdfLink = document.querySelector('a[href*="pdf"]');
        if (pdfLink) {
            window.open(pdfLink.href, '_blank');
        }
    }
    
    // Échap pour revenir à la liste
    if (e.key === 'Escape') {
        window.location.href = 'list.php';
    }
});

// Optimisation des performances avec Intersection Observer
const observerOptions = {
    threshold: 0.1,
    rootMargin: '50px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-in-view');
        }
    });
}, observerOptions);

// Observer les éléments à animer
document.querySelectorAll('.content-section, .sidebar-card, .timeline-item').forEach(el => {
    observer.observe(el);
});
</script>

<?php 
// Fonctions utilitaires pour l'affichage (déplacées à la fin)
function getStatusColor($status) {
    $colors = [
        'en_attente' => '#f39c12',
        'en_cours' => '#3498db',
        'valide' => '#27ae60', 
        'rejete' => '#e74c3c',
        'archive' => '#95a5a6'
    ];
    return $colors[$status] ?? '#6c757d';
}

function getStatusLabel($status) {
    $labels = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours',
        'valide' => 'Validé',
        'rejete' => 'Rejeté', 
        'archive' => 'Archivé'
    ];
    return $labels[$status] ?? ucfirst($status);
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