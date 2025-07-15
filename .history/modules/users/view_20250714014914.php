<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header("Location: " . BASE_URL . "modules/users/list.php");
    exit();
}

$user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    header("Location: " . BASE_URL . "error.php?code=404");
    exit();
}

// Vérifier les permissions - seuls les admins peuvent voir les profils des autres
if ($userId != $_SESSION['user_id'] && !hasPermission(ROLE_ADMIN)) {
    die("Accès non autorisé - Vous ne pouvez voir que votre propre profil");
}

// Récupérer les statistiques utilisateur
$userDossiers = fetchAll("SELECT * FROM dossiers WHERE responsable_id = ? ORDER BY created_at DESC", [$userId]);
$totalDossiers = count($userDossiers);
$dossiersEnCours = count(array_filter($userDossiers, function($d) { return $d['status'] == 'en_cours'; }));
$dossiersValides = count(array_filter($userDossiers, function($d) { return $d['status'] == 'valide'; }));
$dossiersRejetes = count(array_filter($userDossiers, function($d) { return $d['status'] == 'rejete'; }));

// Dossiers récents (5 derniers)
$recentDossiers = array_slice($userDossiers, 0, 5);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?= htmlspecialchars($user['name']) ?> - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE PROFIL UTILISATEUR MODERNE - STYLE ADMIN === */
    .user-view-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .user-view-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        color: var(--gray-600);
        font-size: 0.875rem;
    }
    
    .breadcrumb a {
        color: var(--primary-600);
        text-decoration: none;
        transition: var(--transition-all);
    }
    
    .breadcrumb a:hover {
        color: var(--primary-800);
    }
    
    .page-header {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .user-avatar-large {
        width: 100px;
        height: 100px;
        background: var(--gradient-primary);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
        position: relative;
        animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-6px) rotate(2deg); }
    }
    
    .user-details h1 {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .user-details .user-email {
        color: var(--gray-600);
        font-size: 1.125rem;
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .user-meta {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--gray-100);
        border-radius: var(--radius-full);
        font-size: 0.875rem;
        color: var(--gray-700);
    }
    
    .meta-item i {
        color: var(--primary-500);
    }
    
    .role-badge {
        padding: 0.75rem 1.25rem;
        border-radius: var(--radius-full);
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .role-admin {
        background: var(--danger-100);
        color: var(--danger-700);
        border: 2px solid var(--danger-200);
    }
    
    .role-gestionnaire {
        background: var(--primary-100);
        color: var(--primary-700);
        border: 2px solid var(--primary-200);
    }
    
    .role-consultant {
        background: var(--success-100);
        color: var(--success-700);
        border: 2px solid var(--success-200);
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .main-content {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 2rem;
    }
    
    .content-left {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .content-section {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInUp 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .content-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .content-section:hover::before {
        transform: scaleX(1);
    }
    
    .section-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }
    
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0;
    }
    
    .section-title i {
        color: var(--primary-500);
        font-size: 1.25rem;
    }
    
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition-all);
        animation: slideInUp 0.8s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-card:hover::before {
        transform: scaleX(1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        box-shadow: var(--shadow-md);
    }
    
    .stat-total .stat-icon { background: var(--gradient-primary); }
    .stat-progress .stat-icon { background: var(--gradient-info); }
    .stat-success .stat-icon { background: var(--gradient-success); }
    .stat-danger .stat-icon { background: var(--gradient-danger); }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: var(--gray-600);
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .permissions-grid {
        display: grid;
        gap: 1rem;
    }
    
    .permission-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
        border-left: 4px solid var(--success-500);
        transition: var(--transition-all);
        position: relative;
        overflow: hidden;
    }
    
    .permission-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--gradient-success);
        transform: scaleX(0);
        transition: transform 0.3s ease;
        transform-origin: left;
    }
    
    .permission-item:hover {
        background: white;
        transform: translateX(5px);
        border-left-color: var(--success-600);
        box-shadow: var(--shadow-md);
    }
    
    .permission-item:hover::before {
        transform: scaleX(1);
    }
    
    .permission-item i {
        color: var(--success-600);
        font-size: 1.25rem;
        width: 24px;
        flex-shrink: 0;
    }
    
    .permission-text {
        color: var(--gray-700);
        font-weight: 500;
        line-height: 1.5;
    }
    
    .dossiers-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    
    .dossiers-table th,
    .dossiers-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .dossiers-table th {
        background: var(--gray-50);
        font-weight: 600;
        color: var(--gray-700);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .dossiers-table tbody tr {
        transition: var(--transition-all);
        background: white;
    }
    
    .dossiers-table tbody tr:hover {
        background: var(--primary-50);
        transform: translateX(3px);
    }
    
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .sidebar-card {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInRight 0.6s ease-out;
        position: relative;
        overflow: hidden;
        transition: var(--transition-all);
    }
    
    .sidebar-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .sidebar-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .sidebar-card:hover::before {
        transform: scaleX(1);
    }
    
    .sidebar-card h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .sidebar-card h3 i {
        color: var(--primary-500);
    }
    
    .quick-stats {
        display: grid;
        gap: 1rem;
    }
    
    .quick-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
        transition: var(--transition-all);
    }
    
    .quick-stat:hover {
        background: white;
        transform: translateX(3px);
        border-color: var(--primary-300);
        box-shadow: var(--shadow-sm);
    }
    
    .quick-stat-label {
        color: var(--gray-600);
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .quick-stat-value {
        font-weight: 600;
        color: var(--gray-800);
        font-size: 1.125rem;
    }
    
    /* Styles pour les badges et éléments du tableau */
    .ref-badge {
        background: var(--primary-100);
        color: var(--primary-700);
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border: 1px solid var(--primary-200);
    }
    
    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border: 1px solid;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .status-en_cours {
        background: var(--info-100);
        color: var(--info-700);
        border-color: var(--info-200);
    }
    
    .status-valide {
        background: var(--success-100);
        color: var(--success-700);
        border-color: var(--success-200);
    }
    
    .status-rejete {
        background: var(--danger-100);
        color: var(--danger-700);
        border-color: var(--danger-200);
    }
    
    .status-en_attente {
        background: var(--warning-100);
        color: var(--warning-700);
        border-color: var(--warning-200);
    }
    
    .priority-badge {
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius-md);
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .priority-high {
        background: var(--danger-100);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
    }
    
    .priority-medium {
        background: var(--warning-100);
        color: var(--warning-700);
        border: 1px solid var(--warning-200);
    }
    
    .priority-low {
        background: var(--success-100);
        color: var(--success-700);
        border: 1px solid var(--success-200);
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    /* Style pour les liens du tableau */
    .dossiers-table a {
        text-decoration: none;
    }
    
    .dossiers-table a:hover {
        text-decoration: none;
    }
    </style>
</head>
<body>
    <div class="user-view-page">
        <div class="user-view-container">
            <!-- Fil d'Ariane -->
            <nav class="breadcrumb">
                <a href="<?= BASE_URL ?>dashboard.php">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= BASE_URL ?>modules/users/list.php">
                    <i class="fas fa-users"></i>
                    Utilisateurs
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Profil de <?= htmlspecialchars($user['name']) ?></span>
            </nav>

            <!-- En-tête utilisateur -->
            <div class="page-header">
                <div class="header-content">
                    <div class="user-info">
                        <div class="user-avatar-large">
                            <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                        </div>
                        <div class="user-details">
                            <h1>
                                <?= htmlspecialchars($user['name']) ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-star"></i>
                                        Votre profil
                                    </span>
                                <?php endif; ?>
                            </h1>
                            <p class="user-email">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($user['email']) ?>
                            </p>
                            <div class="user-meta">
                                <span class="role-badge role-<?= strtolower(getRoleName($user['role'])) ?>">
                                    <i class="fas fa-shield-alt"></i>
                                    <?= getRoleName($user['role']) ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    Membre depuis <?= date('Y', strtotime($user['created_at'] ?? 'now')) ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-check-circle"></i>
                                    Compte actif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <a href="<?= BASE_URL ?>modules/users/profile.php" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Modifier mon profil
                            </a>
                        <?php elseif (hasPermission(ROLE_ADMIN)): ?>
                            <a href="<?= BASE_URL ?>modules/users/edit.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Modifier
                            </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>modules/users/list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour à la liste
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="stats-overview">
                <div class="stat-card stat-total">
                    <div class="stat-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-value"><?= $totalDossiers ?></div>
                    <div class="stat-label">Dossiers Total</div>
                </div>
                <div class="stat-card stat-progress">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= $dossiersEnCours ?></div>
                    <div class="stat-label">En Cours</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-value"><?= $dossiersValides ?></div>
                    <div class="stat-label">Validés</div>
                </div>
                <div class="stat-card stat-danger">
                    <div class="stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="stat-value"><?= $dossiersRejetes ?></div>
                    <div class="stat-label">Rejetés</div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="main-content">
                <div class="content-left">
                    <!-- Permissions et droits -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-key"></i>
                                Droits et permissions
                            </h3>
                        </div>
                        
                        <div class="permissions-grid">
                            <?php if ($user['role'] == ROLE_ADMIN): ?>
                                <div class="permission-item">
                                    <i class="fas fa-crown"></i>
                                    <span class="permission-text">Administrateur système avec accès total à toutes les fonctionnalités</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-users-cog"></i>
                                    <span class="permission-text">Gestion complète des utilisateurs et attribution des droits</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-edit"></i>
                                    <span class="permission-text">Création, modification et suppression de tous les dossiers</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-cogs"></i>
                                    <span class="permission-text">Configuration système et paramètres avancés</span>
                                </div>
                            <?php elseif ($user['role'] == ROLE_GESTIONNAIRE): ?>
                                <div class="permission-item">
                                    <i class="fas fa-folder-plus"></i>
                                    <span class="permission-text">Création et gestion complète des dossiers</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-paperclip"></i>
                                    <span class="permission-text">Ajout, modification et suppression de pièces jointes</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-random"></i>
                                    <span class="permission-text">Gestion du workflow et changement de statut des dossiers assignés</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-users"></i>
                                    <span class="permission-text">Assignment et réassignment des dossiers aux utilisateurs</span>
                                </div>
                            <?php elseif ($user['role'] == ROLE_CONSULTANT): ?>
                                <div class="permission-item">
                                    <i class="fas fa-eye"></i>
                                    <span class="permission-text">Consultation des dossiers accessibles selon les permissions</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-comment"></i>
                                    <span class="permission-text">Ajout de commentaires et annotations sur les dossiers</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-download"></i>
                                    <span class="permission-text">Téléchargement des pièces jointes autorisées</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-search"></i>
                                    <span class="permission-text">Recherche et filtrage dans les dossiers accessibles</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dossiers récents -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-folder-open"></i>
                                Dossiers récents gérés par <?= htmlspecialchars($user['name']) ?>
                            </h3>
                        </div>

                        <?php if ($recentDossiers): ?>
                            <div class="table-responsive">
                                <table class="dossiers-table">
                                    <thead>
                                        <tr>
                                            <th>Référence</th>
                                            <th>Titre</th>
                                            <th>Statut</th>
                                            <th>Priorité</th>
                                            <th>Créé le</th>
                                            <th>Échéance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentDossiers as $dossier): ?>
                                        <tr>
                                            <td>
                                                <span class="ref-badge">
                                                    <i class="fas fa-hashtag"></i>
                                                    <?= htmlspecialchars($dossier['reference'] ?? 'REF-' . $dossier['id']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($dossier['titre']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $dossier['status'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $dossier['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (isset($dossier['priority']) && $dossier['priority']): ?>
                                                    <span class="priority-badge priority-<?= $dossier['priority'] ?>">
                                                        <?= ucfirst($dossier['priority']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400)">Non définie</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($dossier['created_at'])) ?></td>
                                            <td>
                                                <?php if (isset($dossier['deadline']) && $dossier['deadline']): ?>
                                                    <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400)">Non définie</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="<?= BASE_URL ?>modules/dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.75rem;">
                                                        <i class="fas fa-eye"></i>
                                                        Voir
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($totalDossiers > 5): ?>
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <a href="<?= BASE_URL ?>modules/dossiers/list.php?responsable=<?= $user['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-list"></i>
                                        Voir tous les dossiers (<?= $totalDossiers ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <h4>Aucun dossier assigné</h4>
                                <p><?= htmlspecialchars($user['name']) ?> n'est responsable d'aucun dossier pour le moment</p>
                                <?php if (hasPermission(ROLE_GESTIONNAIRE) || hasPermission(ROLE_ADMIN)): ?>
                                    <a href="<?= BASE_URL ?>modules/dossiers/create.php" class="btn btn-primary" style="margin-top: 1rem;">
                                        <i class="fas fa-plus"></i>
                                        Créer un dossier
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Statistiques détaillées -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-chart-bar"></i>
                            Statistiques détaillées
                        </h3>
                        <div class="quick-stats">
                            <div class="quick-stat">
                                <span class="quick-stat-label">Total dossiers</span>
                                <span class="quick-stat-value"><?= $totalDossiers ?></span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">En cours</span>
                                <span class="quick-stat-value"><?= $dossiersEnCours ?></span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Validés</span>
                                <span class="quick-stat-value"><?= $dossiersValides ?></span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Rejetés</span>
                                <span class="quick-stat-value"><?= $dossiersRejetes ?></span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Taux de réussite</span>
                                <span class="quick-stat-value">
                                    <?= $totalDossiers > 0 ? round(($dossiersValides / $totalDossiers) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Informations du compte -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Informations du compte
                        </h3>
                        <div class="quick-stats">
                            <div class="quick-stat">
                                <span class="quick-stat-label">ID Utilisateur</span>
                                <span class="quick-stat-value">#<?= $user['id'] ?></span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Statut</span>
                                <span class="quick-stat-value" style="color: var(--success-600);">
                                    <i class="fas fa-check-circle"></i> Actif
                                </span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Dernière connexion</span>
                                <span class="quick-stat-value">Aujourd'hui</span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-stat-label">Compte créé</span>
                                <span class="quick-stat-value"><?= date('d/m/Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-bolt"></i>
                            Actions rapides
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php if (hasPermission(ROLE_GESTIONNAIRE) || hasPermission(ROLE_ADMIN)): ?>
                                <a href="<?= BASE_URL ?>modules/dossiers/create.php?responsable=<?= $user['id'] ?>" class="btn btn-primary" style="justify-content: center;">
                                    <i class="fas fa-plus"></i>
                                    Créer un dossier
                                </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>modules/dossiers/list.php?responsable=<?= $user['id'] ?>" class="btn btn-secondary" style="justify-content: center;">
                                <i class="fas fa-list"></i>
                                Voir tous ses dossiers
                            </a>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <a href="<?= BASE_URL ?>modules/users/settings.php" class="btn btn-secondary" style="justify-content: center;">
                                    <i class="fas fa-cog"></i>
                                    Paramètres
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des valeurs statistiques
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach((stat, index) => {
                const finalValue = parseInt(stat.textContent);
                stat.textContent = '0';
                
                setTimeout(() => {
                    let current = 0;
                    const increment = finalValue / 20;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(current);
                        }
                    }, 50);
                }, index * 200);
            });

            // Animation des stats rapides
            const quickStats = document.querySelectorAll('.quick-stat-value');
            quickStats.forEach((stat, index) => {
                stat.style.opacity = '0';
                setTimeout(() => {
                    stat.style.transition = 'opacity 0.5s ease';
                    stat.style.opacity = '1';
                }, index * 100);
            });

            // Effet hover sur les cartes
            const cards = document.querySelectorAll('.stat-card, .sidebar-card, .content-section');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
