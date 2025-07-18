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

// Récupération des utilisateurs
try {
    $pdo = getDB();
    
    // Requête pour récupérer tous les utilisateurs
    $sql = "SELECT id, nom, prenom, email, role, departement, statut, derniere_connexion, created_at 
            FROM users 
            ORDER BY nom, prenom";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le nombre total d'utilisateurs
    $totalUsers = count($users);
    
    // Si aucun utilisateur, initialiser un tableau vide
    if (!$users) {
        $users = [];
        $totalUsers = 0;
    }
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des utilisateurs: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    
    // Afficher un message d'erreur à l'utilisateur
    echo '<div class="alert alert-danger">Erreur lors de la récupération des utilisateurs. Veuillez réessayer.</div>';
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
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
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

        /* Animations et effets avancés */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            animation: slideIn 0.6s ease-out;
        }

        .users-table {
            animation: slideIn 0.8s ease-out;
        }

        /* Pagination avec glassmorphism */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 2rem 0;
            margin: 0;
        }

        .pagination .page-link {
            padding: 12px 18px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            min-width: 50px;
            text-align: center;
        }

        .pagination .page-link:hover,
        .pagination .page-link.active {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Filtres rapides */
        .quick-filters {
            display: flex;
            gap: 8px;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            color: white;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }

        /* Sélection multiple */
        .bulk-actions {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-actions .selected-count {
            color: white;
            font-weight: 600;
        }

        .bulk-actions .btn {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        /* Checkbox personnalisé */
        .custom-checkbox {
            position: relative;
            display: inline-block;
            width: 24px;
            height: 24px;
        }

        .custom-checkbox input[type="checkbox"] {
            opacity: 0;
            width: 100%;
            height: 100%;
            position: absolute;
            cursor: pointer;
        }

        .custom-checkbox .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkmark {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: rgba(255,255,255,0.5);
        }

        .custom-checkbox .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkmark:after {
            display: block;
        }

        .custom-checkbox .checkmark:after {
            left: 7px;
            top: 3px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Bouton flottant */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
            cursor: pointer;
            transition: var(--transition);
            z-index: 1000;
        }

        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
        }

        /* Notifications toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            color: #333;
            font-weight: 600;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .toast.success {
            background: linear-gradient(135deg, rgba(39,174,96,0.9), rgba(46,204,113,0.9));
            color: white;
        }

        .toast.error {
            background: linear-gradient(135deg, rgba(231,76,60,0.9), rgba(192,57,43,0.9));
            color: white;
        }

        /* État vide amélioré */
        .data-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255,255,255,0.8);
        }

        .data-empty i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .data-empty h3 {
            color: white;
            margin-bottom: 0.5rem;
        }

        .data-empty p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 2rem;
        }

        /* Responsive design amélioré */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .search-box input {
                width: 100%;
            }

            .table {
                font-size: 0.9rem;
            }

            .table th,
            .table td {
                padding: 0.8rem 0.6rem;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .action-btn {
                padding: 6px 8px;
                min-width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }

            .fab {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
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

<div class="container-fluid">
    <div class="page-header">
        <div class="header-title">
            <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
            <p>Gérez les comptes utilisateurs, leurs rôles et leurs permissions</p>
        </div>
        <div class="header-actions">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un utilisateur..." id="searchInput">
            </div>
            <div class="stats-badge">
                <i class="fas fa-users"></i>
                <span><?php echo $totalUsers; ?> utilisateurs</span>
            </div>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvel Utilisateur
            </a>
        </div>
    </div>

    <!-- Filtres rapides -->
    <div class="quick-filters">
        <div class="filter-chip active" data-filter="all">
            <i class="fas fa-users"></i> Tous
        </div>
        <div class="filter-chip" data-filter="admin">
            <i class="fas fa-crown"></i> Administrateurs
        </div>
        <div class="filter-chip" data-filter="gestionnaire">
            <i class="fas fa-user-tie"></i> Gestionnaires
        </div>
        <div class="filter-chip" data-filter="consultant">
            <i class="fas fa-user"></i> Consultants
        </div>
        <div class="filter-chip" data-filter="active">
            <i class="fas fa-circle" style="color: #2ecc71;"></i> Actifs
        </div>
        <div class="filter-chip" data-filter="inactive">
            <i class="fas fa-circle" style="color: #e74c3c;"></i> Inactifs
        </div>
    </div>

    <!-- Actions en lot -->
    <div class="bulk-actions" id="bulkActions">
        <div class="selected-count">
            <span id="selectedCount">0</span> utilisateur(s) sélectionné(s)
        </div>
        <button class="btn btn-info" onclick="bulkAction('activate')">
            <i class="fas fa-check"></i> Activer
        </button>
        <button class="btn btn-warning" onclick="bulkAction('deactivate')">
            <i class="fas fa-ban"></i> Désactiver
        </button>
        <button class="btn btn-danger" onclick="bulkAction('delete')">
            <i class="fas fa-trash"></i> Supprimer
        </button>
    </div>

    <?php if ($totalUsers > 0): ?>
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <div class="custom-checkbox">
                                <input type="checkbox" id="selectAll">
                                <span class="checkmark"></span>
                            </div>
                        </th>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Département</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="user-row" data-role="<?php echo strtolower($user['role']); ?>" data-status="<?php echo $user['statut'] ? 'active' : 'inactive'; ?>">
                        <td>
                            <div class="custom-checkbox">
                                <input type="checkbox" class="user-select" value="<?php echo $user['id']; ?>">
                                <span class="checkmark"></span>
                            </div>
                        </td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h4><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h4>
                                    <span>ID: <?php echo $user['id']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        </td>
                        <td>
                            <span class="user-role role-<?php echo strtolower($user['role']); ?>">
                                <?php 
                                $roleIcons = [
                                    'admin' => 'fas fa-crown',
                                    'gestionnaire' => 'fas fa-user-tie',
                                    'consultant' => 'fas fa-user'
                                ];
                                $icon = $roleIcons[strtolower($user['role'])] ?? 'fas fa-user';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="department-badge">
                                <?php echo htmlspecialchars($user['departement'] ?? 'Non défini'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="user-status status-<?php echo $user['statut'] ? 'active' : 'inactive'; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo $user['statut'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-muted">
                                <?php echo $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="view.php?id=<?php echo $user['id']; ?>" class="action-btn action-btn-view" title="Voir le profil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="action-btn action-btn-edit" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $user['id']; ?>" class="action-btn action-btn-delete" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <a href="#" class="page-link">« Précédent</a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">Suivant »</a>
        </div>
    <?php else: ?>
        <div class="data-empty">
            <i class="fas fa-users"></i>
            <h3>Aucun utilisateur trouvé</h3>
            <p>Commencez par créer votre premier utilisateur</p>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Créer un utilisateur
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Bouton flottant pour actions rapides -->
<div class="fab" title="Actions rapides">
    <i class="fas fa-plus"></i>
</div>

<script>
    // Recherche en temps réel
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.user-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Filtres rapides
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            // Mise à jour de l'état actif
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                let show = true;
                
                if (filter === 'all') {
                    show = true;
                } else if (filter === 'active' || filter === 'inactive') {
                    show = row.dataset.status === filter;
                } else {
                    show = row.dataset.role === filter;
                }
                
                row.style.display = show ? '' : 'none';
            });
        });
    });

    // Sélection multiple
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.user-select');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });

    document.querySelectorAll('.user-select').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    function updateBulkActions() {
        const selected = document.querySelectorAll('.user-select:checked');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        if (selected.length > 0) {
            bulkActions.classList.add('show');
            selectedCount.textContent = selected.length;
        } else {
            bulkActions.classList.remove('show');
        }
    }

    // Actions en lot
    function bulkAction(action) {
        const selected = document.querySelectorAll('.user-select:checked');
        const userIds = Array.from(selected).map(cb => cb.value);
        
        if (userIds.length === 0) {
            alert('Veuillez sélectionner au moins un utilisateur');
            return;
        }
        
        const confirmMsg = `Êtes-vous sûr de vouloir ${action === 'delete' ? 'supprimer' : (action === 'activate' ? 'activer' : 'désactiver')} ${userIds.length} utilisateur(s) ?`;
        
        if (confirm(confirmMsg)) {
            // Ici vous pourriez envoyer une requête AJAX
            console.log(`Action ${action} pour les utilisateurs:`, userIds);
            
            // Simulation d'une notification
            showToast(`${userIds.length} utilisateur(s) ${action === 'delete' ? 'supprimé(s)' : (action === 'activate' ? 'activé(s)' : 'désactivé(s)')} avec succès`, 'success');
        }
    }

    // Notifications toast
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Bouton flottant
    document.querySelector('.fab').addEventListener('click', function() {
        window.location.href = 'add.php';
    });
</script>

</body>
</html>