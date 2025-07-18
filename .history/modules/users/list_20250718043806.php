<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/integrations.php';

// Vérifier l'authentification
requireAuth();

// Synchronisation RH si demandé
if (isset($_POST['sync_rh'])) {
    $result = syncRHData();
    if ($result) {
        echo '<div class="alert alert-success">Synchronisation RH réussie !</div>';
    } else {
        echo '<div class="alert alert-danger">Échec de la synchronisation RH.</div>';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des utilisateurs - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />
    <style>
        /* Variables CSS pour une cohérence visuelle */
        :root {
            --primary-color: #2980b9;
            --primary-light: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-gray: #f8fafc;
            --border-color: #e1e8ed;
            --text-color: #2c3e50;
            --text-muted: #7f8c8d;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 32px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* En-tête de page amélioré */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            margin: 12px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        /* Barre d'actions améliorée */
        .users-actions {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .actions-left {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box input {
            padding: 12px 16px 12px 45px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 0.95rem;
            width: 300px;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(41, 128, 185, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            color: var(--text-muted);
            z-index: 1;
        }

        .stats-badge {
            background: linear-gradient(135deg, var(--info-color), #20c997);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Tableau amélioré */
        .users-table {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table {
            margin: 0;
            border-collapse: collapse;
            width: 100%;
        }

        .table th {
            background: linear-gradient(135deg, #f8fafc, #e1e8ed);
            padding: 1.2rem 1rem;
            font-weight: 700;
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
            transition: var(--transition);
        }

        .table tr:hover {
            background: linear-gradient(135deg, #f8fafc, #fff);
            transform: translateY(-1px);
        }

        .table tr:hover td {
            box-shadow: inset 0 0 0 1px rgba(41, 128, 185, 0.1);
        }

        /* Avatar utilisateur amélioré */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
        }

        .user-avatar::after {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-details h4 {
            margin: 0;
            font-weight: 700;
            color: var(--text-color);
            font-size: 1rem;
        }

        .user-details span {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }

        /* Badges améliorés */
        .user-role {
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .role-admin { 
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
            color: white;
        }
        
        .role-gestionnaire { 
            background: linear-gradient(135deg, var(--warning-color), #e67e22);
            color: white;
        }
        
        .role-consultant { 
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
            color: white;
        }

        .user-status {
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-inactive { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .department-badge {
            background: linear-gradient(135deg, #ecf0f1, #bdc3c7);
            color: var(--text-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            border: 1px solid var(--border-color);
        }

        /* Boutons améliorés */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            opacity: 0;
            transition: var(--transition);
        }

        .btn:hover::before {
            opacity: 1;
        }

        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: 0 4px 15px rgba(41, 128, 185, 0.3);
        }

        .btn-info { 
            background: linear-gradient(135deg, var(--info-color), #20c997);
            color: white;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-success { 
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn:hover { 
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        /* Boutons d'action en ligne */
        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
        }

        .action-btn-edit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .action-btn-view {
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
            color: white;
        }

        .action-btn-delete {
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* États d'alerte améliorés */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .alert i {
            font-size: 1.2rem;
        }

        .alert-success { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #f5c6cb;
        }

        .alert-info { 
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-color: #bee5eb;
        }

        /* État vide amélioré */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: #bdc3c7;
            opacity: 0.7;
        }

        .empty-state h3 {
            margin: 0 0 12px 0;
            font-size: 1.5rem;
            color: var(--text-color);
        }

        .empty-state p {
            margin: 0 0 2rem 0;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .page-header {
                padding: 2rem 1.5rem;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .actions-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box input {
                width: 100%;
            }

            .table {
                font-size: 0.9rem;
            }

            .table th,
            .table td {
                padding: 0.8rem 0.5rem;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
        }

        /* Animations */
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

        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        /* Loading state */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }

        .loading::after {
            content: '';
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    
    <!-- Header avec navigation -->
    <?php require_once '../../includes/header.php'; ?>
    
    <div class="container">
        <!-- En-tête de page -->
        <div class="page-header">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-users"></i>
                Liste des utilisateurs
            </h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9;">
                Gestion et synchronisation des comptes utilisateurs
            </p>
        </div>

        <!-- Actions utilisateur -->
        <div class="users-actions fade-in-up">
            <div class="actions-header">
                <div class="actions-left">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="sync_rh" class="btn btn-info">
                            <i class="fas fa-sync-alt"></i> 
                            Synchroniser RH
                        </button>
                    </form>
                    
                    <a href="create.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i>
                        Nouvel utilisateur
                    </a>
                </div>
                
                <div class="actions-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="userSearch" placeholder="Rechercher un utilisateur..." autocomplete="off">
                    </div>
                    
                    <div class="stats-badge">
                        <i class="fas fa-users"></i>
                        <span id="userCount"><?= count($users ?? []) ?></span> utilisateurs
                    </div>
                </div>
            </div>
        </div>
        <!-- Liste des utilisateurs -->
        <?php
        try {
            // Récupérer la liste des utilisateurs - en utilisant d'abord les colonnes de base
            $users = fetchAll("SELECT * FROM users ORDER BY name ASC");
            
            if ($users && count($users) > 0): ?>
                <div class="users-table fade-in-up">
                    <table class="table" id="usersTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-user"></i> Utilisateur</th>
                                <th><i class="fas fa-envelope"></i> Email</th>
                                <th><i class="fas fa-building"></i> Département</th>
                                <th><i class="fas fa-user-tag"></i> Rôle</th>
                                <th><i class="fas fa-toggle-on"></i> Statut</th>
                                <th><i class="fas fa-calendar"></i> Créé le</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="user-row" data-search="<?= htmlspecialchars(strtolower(($user['name'] ?? '') . ' ' . ($user['email'] ?? '') . ' ' . ($user['department'] ?? $user['service'] ?? ''))) ?>">
                                    <td>
                                        <strong style="color: var(--primary-color); font-weight: 700;">
                                            #<?= htmlspecialchars($user['external_id'] ?? $user['id'] ?? 'N/A') ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div class="user-details">
                                                <h4><?= htmlspecialchars($user['name'] ?? 'N/A') ?></h4>
                                                <?php if (!empty($user['prenom'])): ?>
                                                    <span><?= htmlspecialchars($user['prenom']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                           style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                                            <i class="fas fa-envelope" style="margin-right: 6px;"></i>
                                            <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="department-badge">
                                            <i class="fas fa-building" style="margin-right: 6px;"></i>
                                            <?= htmlspecialchars($user['department'] ?? $user['service'] ?? 'Non défini') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $roleClass = '';
                                        $roleName = '';
                                        $roleIcon = '';
                                        $roleValue = $user['role'] ?? 3;
                                        switch((int)$roleValue) {
                                            case 1:
                                                $roleClass = 'role-admin';
                                                $roleName = 'Administrateur';
                                                $roleIcon = 'fas fa-crown';
                                                break;
                                            case 2:
                                                $roleClass = 'role-gestionnaire';
                                                $roleName = 'Gestionnaire';
                                                $roleIcon = 'fas fa-user-tie';
                                                break;
                                            case 3:
                                                $roleClass = 'role-consultant';
                                                $roleName = 'Consultant';
                                                $roleIcon = 'fas fa-user';
                                                break;
                                            default:
                                                $roleClass = 'role-consultant';
                                                $roleName = 'Consultant';
                                                $roleIcon = 'fas fa-user';
                                        }
                                        ?>
                                        <span class="user-role <?= $roleClass ?>">
                                            <i class="<?= $roleIcon ?>"></i>
                                            <?= $roleName ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php $isActive = $user['active'] ?? $user['actif'] ?? 1; ?>
                                        <span class="user-status <?= $isActive ? 'status-active' : 'status-inactive' ?>">
                                            <i class="fas fa-<?= $isActive ? 'check-circle' : 'times-circle' ?>"></i>
                                            <?= $isActive ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $dateCreated = $user['created_at'] ?? $user['date_creation'] ?? null;
                                        if (!empty($dateCreated)): ?>
                                            <span style="color: var(--success-color); font-size: 0.9rem; font-weight: 500;">
                                                <i class="fas fa-calendar-plus"></i>
                                                <?= date('d/m/Y', strtotime($dateCreated)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--danger-color); font-size: 0.9rem; font-weight: 500;">
                                                <i class="fas fa-minus-circle"></i>
                                                Non défini
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="edit.php?id=<?= $user['id'] ?>" 
                                               class="action-btn action-btn-edit"
                                               title="Modifier l'utilisateur">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view.php?id=<?= $user['id'] ?>" 
                                               class="action-btn action-btn-view"
                                               title="Voir le profil">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission(ROLE_ADMIN)): ?>
                                            <a href="#" 
                                               class="action-btn action-btn-delete"
                                               title="Supprimer l'utilisateur"
                                               onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'] ?? 'Utilisateur') ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="users-table fade-in-up">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Aucun utilisateur trouvé</h3>
                        <p>
                            Il n'y a actuellement aucun utilisateur dans le système.<br>
                            Vous pouvez synchroniser avec le système RH ou créer un nouvel utilisateur manuellement.
                        </p>
                        <div>
                            <form method="post" style="display: inline-block; margin-right: 1rem;">
                                <button type="submit" name="sync_rh" class="btn btn-info">
                                    <i class="fas fa-sync-alt"></i> 
                                    Synchroniser RH
                                </button>
                            </form>
                            <a href="create.php" class="btn btn-success">
                                <i class="fas fa-user-plus"></i>
                                Créer un utilisateur
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">';
            echo '<i class="fas fa-exclamation-triangle"></i> ';
            echo 'Erreur lors de la récupération des utilisateurs: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>

    <!-- Footer -->
    <?php require_once '../../includes/footer.php'; ?>

    <script>
        // Animation d'entrée pour les lignes du tableau
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

        // Confirmation pour la synchronisation RH
        document.querySelector('button[name="sync_rh"]').addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir synchroniser avec le système RH ? Cette opération peut prendre quelques minutes.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>