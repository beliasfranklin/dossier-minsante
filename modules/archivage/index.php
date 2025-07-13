<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/language_manager.php';

requireAuth();
$pageTitle = _t('Archivage');
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-archive"></i> <?= _t('Gestion des Archives') ?></h1>
    <p class="text-muted"><?= _t('Consultez et gérez les dossiers archivés') ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= _t('Dossiers Archivés') ?></h3>
        <div class="card-actions">
            <button class="btn btn-outline-primary">
                <i class="fas fa-search"></i> <?= _t('Recherche avancée') ?>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <?= _t('Module d\'archivage en cours de développement. Vous pourrez consulter et restaurer les dossiers archivés.') ?>
        </div>
        
        <div class="empty-state">
            <i class="fas fa-archive fa-3x text-muted"></i>
            <h4><?= _t('Aucun dossier archivé') ?></h4>
            <p><?= _t('Les dossiers archivés apparaîtront ici') ?></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
