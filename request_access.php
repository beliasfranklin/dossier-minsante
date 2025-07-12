<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$resource = isset($_GET['resource']) ? urldecode($_GET['resource']) : '';

// Message d'information
$message = "Votre demande d'accès a été transmise à un administrateur. Vous serez notifié dès qu'une décision sera prise.";

// Log de la demande
logAction($_SESSION['user_id'], 'permission_request', null, "Demande d'accès à : $resource");

include __DIR__ . '/includes/header.php';
?>
<div class="container">
    <h1>Demande d'accès</h1>
    <div class="alert alert-info">
        <?= $message ?>
    </div>
    <p>Ressource demandée : <strong><?= htmlspecialchars($resource) ?></strong></p>
    <a href="javascript:history.back()" class="btn btn-secondary">Retour</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>