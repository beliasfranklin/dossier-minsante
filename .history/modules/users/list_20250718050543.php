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
            --primary-dark: #1e5e7a;
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
            --shadow-active: 0 12px 40px rgba(0,0,0,0.16);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
            --glassmorphism: rgba(255, 255, 255, 0.25);
            --glassmorphism-border: rgba(255, 255, 255, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Overlay pour effet glassmorphism */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
        }

        /* En-tête de page avec effet glassmorphism */
        .page-header {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            color: white;
            padding: 3rem 2.5rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-hover);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(30%, -30%) rotate(0deg); }
            50% { transform: translate(35%, -25%) rotate(180deg); }
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header h1 i {
            font-size: 2.2rem;
            opacity: 0.9;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .page-header p {
            margin: 16px 0 0 0;
            opacity: 0.95;
            font-size: 1.2rem;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }

        /* Stats cards flottantes */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: var(--transition);
        }

        .stat-card:hover::before {
            transform: translateX(100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-active);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: white;
        }

        .stat-card p {
            margin: 0;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }

        /* Barre d'actions avec glassmorphism */
        .users-actions {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
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
            padding: 14px 20px 14px 50px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            font-size: 1rem;
            width: 350px;
            transition: var(--transition);
            background: rgba(255,255,255,0.1);
            color: white;
            backdrop-filter: blur(10px);
        }

        .search-box input::placeholder {
            color: rgba(255,255,255,0.8);
        }

        .search-box input:focus {
            outline: none;
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.2);
        }

        .search-box i {
            position: absolute;
            left: 18px;
            color: rgba(255,255,255,0.8);
            z-index: 1;
            font-size: 1.1rem;
        }

        .stats-badge {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }

        .stats-badge:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.2));
            transform: translateY(-2px);
        }

        /* Tableau avec glassmorphism */
        .users-table {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-hover);
            overflow: hidden;
        }

        .table {
            margin: 0;
            border-collapse: collapse;
            width: 100%;
        }

        .table th {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 1.5rem 1.2rem;
            font-weight: 700;
            color: white;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table td {
            padding: 1.5rem 1.2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            vertical-align: middle;
            transition: var(--transition);
            color: white;
        }

        .table tr {
            transition: var(--transition);
        }

        .table tr:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .table tr:hover td {
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
        }

        /* Avatar utilisateur avec effet 3D */
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: relative;
            transition: var(--transition);
        }

        .user-avatar::before {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.05); }
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0,0,0,0.3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-details h4 {
            margin: 0;
            font-weight: 700;
            color: white;
            font-size: 1.1rem;
        }

        .user-details span {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
            display: block;
            margin-top: 3px;
        }

        /* Badges modernisés avec glassmorphism */
        .user-role {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }

        .user-role:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .role-admin { 
            background: linear-gradient(135deg, rgba(231,76,60,0.8), rgba(192,57,43,0.8));
            color: white;
        }
        
        .role-gestionnaire { 
            background: linear-gradient(135deg, rgba(243,156,18,0.8), rgba(230,126,34,0.8));
            color: white;
        }
        
        .role-consultant { 
            background: linear-gradient(135deg, rgba(39,174,96,0.8), rgba(46,204,113,0.8));
            color: white;
        }

        .user-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }

        .user-status:hover {
            transform: translateY(-2px);
        }

        .status-active { 
            background: linear-gradient(135deg, rgba(39,174,96,0.8), rgba(46,204,113,0.8));
            color: white;
        }
        
        .status-inactive { 
            background: linear-gradient(135deg, rgba(231,76,60,0.8), rgba(192,57,43,0.8));
            color: white;
        }

        .department-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 14px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }

        .department-badge:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
        }

        /* Boutons avec effet glassmorphism */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover { 
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .btn-primary { 
            background: linear-gradient(135deg, rgba(41,128,185,0.8), rgba(52,152,219,0.8));
            color: white;
        }

        .btn-info { 
            background: linear-gradient(135deg, rgba(23,162,184,0.8), rgba(32,201,151,0.8));
            color: white;
        }

        .btn-success { 
            background: linear-gradient(135deg, rgba(39,174,96,0.8), rgba(46,204,113,0.8));
            color: white;
        }

        /* Boutons d'action avec effet 3D */
        .action-btn {
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: var(--transition);
        }

        .action-btn:hover::before {
            transform: translateX(100%);
        }

        .action-btn-edit {
            background: linear-gradient(135deg, rgba(52,152,219,0.8), rgba(41,128,185,0.8));
            color: white;
        }

        .action-btn-view {
            background: linear-gradient(135deg, rgba(39,174,96,0.8), rgba(46,204,113,0.8));
            color: white;
        }

        .action-btn-delete {
            background: linear-gradient(135deg, rgba(231,76,60,0.8), rgba(192,57,43,0.8));
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* États d'alerte améliorés */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 16px;
            font-weight: 500;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            backdrop-filter: blur(6px);
            background: rgba(255,255,255,0.7);
            transition: box-shadow 0.3s, background 0.3s;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            border-radius: var(--radius) 0 0 var(--radius);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            opacity: 0.15;
        }

        .alert i {
            font-size: 1.5rem;
            margin-right: 8px;
            animation: alertIconPulse 1.5s infinite alternate;
        }

        @keyframes alertIconPulse {
            0% { transform: scale(1); filter: drop-shadow(0 0 0px var(--primary-light)); }
            100% { transform: scale(1.15); filter: drop-shadow(0 0 8px var(--primary-light)); }
        }

        .alert-success { 
            background: linear-gradient(135deg, #d4edda 80%, #c3e6cb 100%);
            color: #155724;
            border-color: #c3e6cb;
            box-shadow: 0 8px 32px rgba(39,174,96,0.13);
        }

        .alert-success i {
            color: var(--success-color);
            animation: alertIconPulse 1.5s infinite alternate;
        }

        .alert-danger { 
            background: linear-gradient(135deg, #f8d7da 80%, #f5c6cb 100%);
            color: #721c24;
            border-color: #f5c6cb;
            box-shadow: 0 8px 32px rgba(231,76,60,0.13);
        }

        .alert-danger i {
            color: var(--danger-color);
            animation: alertIconPulse 1.5s infinite alternate;
        }

        .alert-info { 
            background: linear-gradient(135deg, #d1ecf1 80%, #bee5eb 100%);
            color: #0c5460;
            border-color: #bee5eb;
            box-shadow: 0 8px 32px rgba(23,162,184,0.13);
        }

        .alert-info i {
            color: var(--info-color);
            animation: alertIconPulse 1.5s infinite alternate;
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
        // Configuration et variables globales
        const originalUsers = <?= json_encode($users ?? []) ?>;
        let filteredUsers = [...originalUsers];

        // Initialisation de la page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch();
            initializeAnimations();
            initializeTooltips();
            initializeSyncConfirmation();
        });

        // Fonction de recherche en temps réel
        function initializeSearch() {
            const searchInput = document.getElementById('userSearch');
            const userRows = document.querySelectorAll('.user-row');
            const userCount = document.getElementById('userCount');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    let visibleCount = 0;
                    
                    userRows.forEach(row => {
                        const searchData = row.getAttribute('data-search');
                        const isVisible = searchData.includes(searchTerm);
                        
                        if (isVisible) {
                            row.style.display = '';
                            visibleCount++;
                            // Animation d'apparition
                            row.style.animation = 'fadeInUp 0.3s ease-out';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Mise à jour du compteur
                    userCount.textContent = visibleCount;
                    
                    // Affichage du message "Aucun résultat" si nécessaire
                    showNoResultsMessage(visibleCount === 0 && searchTerm !== '');
                });
            }
        }

        // Affichage du message "Aucun résultat"
        function showNoResultsMessage(show) {
            let noResultsRow = document.getElementById('noResultsRow');
            
            if (show && !noResultsRow) {
                const tbody = document.querySelector('#usersTable tbody');
                if (tbody) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'noResultsRow';
                    noResultsRow.innerHTML = `
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; color: #bdc3c7;"></i>
                            <h4 style="margin: 0 0 8px 0; color: var(--text-color);">Aucun résultat trouvé</h4>
                            <p style="margin: 0;">Essayez de modifier vos critères de recherche</p>
                        </td>
                    `;
                    tbody.appendChild(noResultsRow);
                }
            } else if (!show && noResultsRow) {
                noResultsRow.remove();
            }
        }

        // Animations d'entrée pour les éléments
        function initializeAnimations() {
            const animatedElements = document.querySelectorAll('.fade-in-up');
            
            animatedElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animation des lignes du tableau
            const rows = document.querySelectorAll('.user-row');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, (index * 50) + 200);
            });
        }

        // Initialisation des tooltips
        function initializeTooltips() {
            const tooltipElements = document.querySelectorAll('[title]');
            
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'custom-tooltip';
                    tooltip.textContent = this.getAttribute('title');
                    tooltip.style.cssText = `
                        position: absolute;
                        background: var(--text-color);
                        color: white;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 0.85rem;
                        white-space: nowrap;
                        z-index: 1000;
                        box-shadow: var(--shadow);
                        pointer-events: none;
                        opacity: 0;
                        transform: translateY(-10px);
                        transition: all 0.2s ease;
                    `;
                    
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
                    
                    setTimeout(() => {
                        tooltip.style.opacity = '1';
                        tooltip.style.transform = 'translateY(0)';
                    }, 10);
                    
                    this.tooltipElement = tooltip;
                });
                
                element.addEventListener('mouseleave', function() {
                    if (this.tooltipElement) {
                        this.tooltipElement.remove();
                        this.tooltipElement = null;
                    }
                });
            });
        }

        // Confirmation pour la synchronisation RH
        function initializeSyncConfirmation() {
            const syncButton = document.querySelector('button[name="sync_rh"]');
            if (syncButton) {
                syncButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    showConfirmDialog(
                        'Synchronisation RH',
                        'Êtes-vous sûr de vouloir synchroniser avec le système RH ? Cette opération peut prendre quelques minutes et mettre à jour les données existantes.',
                        'Synchroniser',
                        'Annuler',
                        () => {
                            // Afficher un indicateur de chargement
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Synchronisation...';
                            this.disabled = true;
                            
                            // Soumettre le formulaire
                            this.form.submit();
                        }
                    );
                });
            }
        }

        // Confirmation de suppression
        function confirmDelete(userId, userName) {
            showConfirmDialog(
                'Supprimer l\'utilisateur',
                `Êtes-vous sûr de vouloir supprimer l'utilisateur <strong>${userName}</strong> ? Cette action est irréversible.`,
                'Supprimer',
                'Annuler',
                () => {
                    // Redirection vers la page de suppression
                    window.location.href = `delete.php?id=${userId}`;
                },
                'danger'
            );
        }

        // Dialogue de confirmation personnalisé
        function showConfirmDialog(title, message, confirmText, cancelText, onConfirm, type = 'info') {
            const overlay = document.createElement('div');
            overlay.className = 'dialog-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            const dialog = document.createElement('div');
            dialog.className = 'confirm-dialog';
            dialog.style.cssText = `
                background: white;
                padding: 2rem;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-hover);
                max-width: 500px;
                width: 90%;
                transform: scale(0.8);
                transition: transform 0.3s ease;
            `;
            
            const typeColor = type === 'danger' ? 'var(--danger-color)' : 'var(--info-color)';
            const typeIcon = type === 'danger' ? 'fas fa-exclamation-triangle' : 'fas fa-question-circle';
            
            dialog.innerHTML = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <i class="${typeIcon}" style="font-size: 3rem; color: ${typeColor}; margin-bottom: 1rem;"></i>
                    <h3 style="margin: 0 0 1rem 0; color: var(--text-color);">${title}</h3>
                    <p style="margin: 0; color: var(--text-muted); line-height: 1.5;">${message}</p>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button class="btn btn-primary cancel-btn" style="background: #6c757d;">${cancelText}</button>
                    <button class="btn confirm-btn" style="background: ${typeColor};">${confirmText}</button>
                </div>
            `;
            
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            
            // Animation d'apparition
            setTimeout(() => {
                overlay.style.opacity = '1';
                dialog.style.transform = 'scale(1)';
            }, 10);
            
            // Gestionnaires d'événements
            const cancelBtn = dialog.querySelector('.cancel-btn');
            const confirmBtn = dialog.querySelector('.confirm-btn');
            
            cancelBtn.addEventListener('click', () => {
                closeDialog();
            });
            
            confirmBtn.addEventListener('click', () => {
                closeDialog();
                onConfirm();
            });
            
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    closeDialog();
                }
            });
            
            function closeDialog() {
                overlay.style.opacity = '0';
                dialog.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    overlay.remove();
                }, 300);
            }
        }

        // Fonction utilitaire pour formater les dates
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Fonction pour exporter les données (fonctionnalité future)
        function exportUsers() {
            // Logique d'export à implémenter
            console.log('Export des utilisateurs...');
        }

        // Gestion des erreurs globales
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
            // Optionnel : afficher un message d'erreur à l'utilisateur
        });
    </script>
</body>
</html>