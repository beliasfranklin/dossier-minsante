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
        .page-header {
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 12px;
        }
        .users-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .users-table {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .table {
            margin: 0;
            border-collapse: collapse;
            width: 100%;
        }
        .table th {
            background: linear-gradient(135deg, #f8fafc, #e1e8ed);
            padding: 1rem;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e8ed;
        }
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }
        .table tr:hover {
            background: #f8fafc;
        }
        .user-role {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .role-admin { background: #e74c3c; color: white; }
        .role-gestionnaire { background: #f39c12; color: white; }
        .role-consultant { background: #27ae60; color: white; }
        .user-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #2980b9, #3498db); color: white; }
        .btn-info { background: linear-gradient(135deg, #17a2b8, #20c997); color: white; }
        .btn-success { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #bdc3c7;
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
        <div class="users-actions">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
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
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="color: #7f8c8d; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i>
                        Total: <strong><?= count($users ?? []) ?></strong> utilisateurs
                    </span>
                </div>
            </div>
        </div>
        <!-- Liste des utilisateurs -->
        <?php
        try {
            // Récupérer la liste des utilisateurs - en utilisant d'abord les colonnes de base
            $users = fetchAll("SELECT * FROM users ORDER BY name ASC");
            
            if ($users && count($users) > 0): ?>
                <div class="users-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-user"></i> Nom complet</th>
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
                                <tr>
                                    <td>
                                        <strong style="color: #2980b9;">
                                            <?= htmlspecialchars($user['external_id'] ?? $user['id'] ?? 'N/A') ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2980b9); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.8rem;">
                                                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #2c3e50;">
                                                    <?= htmlspecialchars($user['name'] ?? 'N/A') ?>
                                                </div>
                                                <?php if (!empty($user['prenom'])): ?>
                                                    <div style="font-size: 0.85rem; color: #7f8c8d;">
                                                        <?= htmlspecialchars($user['prenom']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                           style="color: #3498db; text-decoration: none;">
                                            <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="background: #ecf0f1; color: #2c3e50; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem;">
                                            <?= htmlspecialchars($user['department'] ?? $user['service'] ?? 'Non défini') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $roleClass = '';
                                        $roleName = '';
                                        $roleValue = $user['role'] ?? 3;
                                        switch((int)$roleValue) {
                                            case 1:
                                                $roleClass = 'role-admin';
                                                $roleName = 'Administrateur';
                                                break;
                                            case 2:
                                                $roleClass = 'role-gestionnaire';
                                                $roleName = 'Gestionnaire';
                                                break;
                                            case 3:
                                                $roleClass = 'role-consultant';
                                                $roleName = 'Consultant';
                                                break;
                                            default:
                                                $roleClass = 'role-consultant';
                                                $roleName = 'Consultant';
                                        }
                                        ?>
                                        <span class="user-role <?= $roleClass ?>">
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
                                            <span style="color: #27ae60; font-size: 0.85rem;">
                                                <i class="fas fa-calendar-plus"></i>
                                                <?= date('d/m/Y', strtotime($dateCreated)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #e74c3c; font-size: 0.85rem;">
                                                <i class="fas fa-minus-circle"></i>
                                                Non défini
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="edit.php?id=<?= $user['id'] ?>" 
                                               style="background: #3498db; color: white; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 0.85rem;"
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view.php?id=<?= $user['id'] ?>" 
                                               style="background: #27ae60; color: white; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 0.85rem;"
                                               title="Voir le profil">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="users-table">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3 style="margin: 0 0 8px 0;">Aucun utilisateur trouvé</h3>
                        <p style="margin: 0; color: #7f8c8d;">
                            Il n'y a actuellement aucun utilisateur dans le système.<br>
                            Vous pouvez synchroniser avec le système RH ou créer un nouvel utilisateur.
                        </p>
                        <div style="margin-top: 1.5rem;">
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