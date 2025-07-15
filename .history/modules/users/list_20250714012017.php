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
        <!-- Ici, affichez la liste des utilisateurs -->
        <?php
        // Exemple d'affichage (à adapter selon votre structure)
        $users = fetchAll("SELECT * FROM users ORDER BY name ASC");
        if ($users) {
            echo '<table class="table"><thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Département</th></tr></thead><tbody>';
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['external_id'] ?? $user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['name']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($user['department']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">Aucun utilisateur trouvé.</div>';
        }
        ?>
    </div>
</body>
</html>
