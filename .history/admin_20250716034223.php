<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

// Vérification des droits admin
if (!hasPermission(ROLE_ADMIN)) {
    header("Location: /error.php?code=403");
    exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_role'])) {
        $userId = (int)$_POST['user_id'];
        $newRole = (int)$_POST['new_role'];
        
        // Validation du rôle
        $allowedRoles = [ROLE_ADMIN, ROLE_GESTIONNAIRE, ROLE_CONSULTANT];
        if (in_array($newRole, $allowedRoles)) {
            executeQuery("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId]);
            $_SESSION['flash']['success'] = "Rôle mis à jour avec succès";
        }
    }
    
    if (isset($_POST['create_user'])) {
        // Validation des données
        $name = cleanInput($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $role = (int)$_POST['role'];
        $tempPassword = bin2hex(random_bytes(4)); // Mot de passe temporaire
        
        if ($email) {
            $hashedPassword = generateSecurePassword($tempPassword);
            executeQuery(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)",
                [$name, $email, $hashedPassword, $role]
            );
            
            // Envoyer le mot de passe par email (à implémenter)
            $_SESSION['flash']['success'] = "Utilisateur créé. Mot de passe temporaire : $tempPassword";
        }
    }
}

// Récupération des utilisateurs
$users = fetchAll("SELECT id, name, email, role FROM users ORDER BY name");
$roles = [
    ROLE_ADMIN => "Administrateur",
    ROLE_GESTIONNAIRE => "Gestionnaire",
    ROLE_CONSULTANT => "Consultant"
];

include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - MINSANTE</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE ADMIN MODERNE (style dashboard) === */
    .admin-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    .admin-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    .admin-header {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.6s ease-out;
    }
    .admin-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    .admin-title-section {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .admin-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient-primary);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        box-shadow: var(--shadow-lg);
        animation: iconFloat 3s ease-in-out infinite;
    }
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-8px) rotate(5deg); }
    }
    .admin-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .admin-subtitle {
        color: var(--gray-600);
        font-size: 1.1rem;
        margin: 0.5rem 0 0 0;
        font-weight: 400;
    }
    
    .admin-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        padding: 0.5rem 1rem;
        margin-top: 1rem;
    }
    
    .admin-search {
        position: relative;
        min-width: 260px;
        max-width: 350px;
    }
    
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-full);
        background: var(--gray-100);
        transition: var(--transition-all);
        font-size: 0.95rem;
        box-shadow: var(--shadow-sm);
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--primary-500);
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
        font-size: 1.1rem;
    }
    
    .filter-controls {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        padding: 0.5rem 1rem;
    }
    
    .filter-select {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: white;
        font-size: 0.95rem;
        cursor: pointer;
        transition: var(--transition-all);
        box-shadow: var(--shadow-sm);
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .admin-tabs {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInUp 0.6s ease-out;
    }
    
    .tab-nav {
        display: flex;
        gap: 1rem;
        border-bottom: 2px solid var(--gray-100);
        margin-bottom: 2rem;
    }
    
    .tab-button {
        padding: 1rem 2rem;
        background: transparent;
        border: none;
        color: var(--gray-600);
        font-weight: 500;
        cursor: pointer;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        transition: var(--transition-all);
        position: relative;
    }
    
    .tab-button.active {
        color: var(--primary-600);
        background: var(--primary-50);
    }
    
    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary-500);
    }
    
    .tab-button:hover:not(.active) {
        background: var(--gray-50);
        color: var(--gray-800);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .users-section {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
        animation: fadeInUp 0.8s ease-out;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
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
    
    .users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    
    .user-card {
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        transition: var(--transition-all);
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        opacity: 0;
        transform: translateY(30px);
        animation: fadeInUp 0.8s ease-out forwards;
    }
    
    .user-card::before {
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
    
    .user-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
        background: white;
        border-color: var(--primary-300);
    }
    
    .user-card:hover::before {
        transform: scaleX(1);
    }
    
    .user-card.filtered-out {
        display: none;
    }
    
    .user-card.highlighted {
        background: var(--primary-50);
        border-color: var(--primary-400);
        transform: scale(1.02);
    }
    
    .user-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .user-avatar {
        width: 60px;
        height: 60px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
    }
    
    .user-info h4 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 0.25rem 0;
    }
    
    .user-email {
        color: var(--gray-600);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .user-meta {
        margin-bottom: 1.5rem;
    }
    
    .user-role {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }
    
    .role-admin {
        background: var(--danger-100);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
        box-shadow: 0 2px 8px rgba(239,68,68,0.08);
    }
    
    .role-gestionnaire {
        background: var(--primary-100);
        color: var(--primary-700);
        border: 1px solid var(--primary-200);
        box-shadow: 0 2px 8px rgba(59,130,246,0.08);
    }
    
    .role-consultant {
        background: var(--success-100);
        color: var(--success-700);
        border: 1px solid var(--success-200);
        box-shadow: 0 2px 8px rgba(16,185,129,0.08);
    }
    
    .user-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .role-form {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        width: 100%;
    }
    
    .role-select {
        flex: 1;
        padding: 0.5rem;
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
    }
    
    .role-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .create-user-form {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 2rem;
        border: 2px dashed var(--gray-300);
        margin-bottom: 2rem;
        transition: var(--transition-all);
    }
    
    .create-user-form:hover {
        border-color: var(--primary-400);
        background: var(--primary-50);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-group label {
        font-weight: 500;
        color: var(--gray-700);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-group label i {
        color: var(--primary-500);
        width: 16px;
    }
    
    .form-input {
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-lg);
        font-weight: 500;
        transition: var(--transition-all);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background: var(--gradient-primary);
        color: white;
        box-shadow: 0 2px 8px rgba(59,130,246,0.12);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-danger {
        background: var(--gradient-danger);
        color: white;
        box-shadow: 0 2px 8px rgba(239,68,68,0.12);
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--gray-600);
        border: 2px solid var(--gray-300);
    }
    
    .btn-outline:hover {
        background: var(--gray-100);
        color: var(--gray-800);
        border-color: var(--gray-400);
        transform: translateY(-1px);
    }
    
    .btn-export {
        background: var(--gradient-success);
        color: white;
        box-shadow: 0 2px 8px rgba(16,185,129,0.12);
    }
    
    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-refresh {
        background: var(--gradient-info);
        color: white;
        box-shadow: 0 2px 8px rgba(14,165,233,0.12);
    }
    
    .btn-refresh:hover {
        transform: translateY(-2px) rotate(180deg);
        box-shadow: var(--shadow-lg);
    }
    
    .alert {
        padding: 1rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        animation: slideInDown 0.5s ease-out;
    }
    
    .alert-success {
        background: var(--success-100);
        color: var(--success-700);
        border: 1px solid var(--success-500);
    }
    
    .alert-error {
        background: var(--danger-100);
        color: var(--danger-700);
        border: 1px solid var(--danger-500);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition-all);
        position: relative;
        overflow: hidden;
        opacity: 0;
        transform: translateY(30px);
        animation: fadeInUp 0.8s ease-out forwards;
        /* Accent couleur selon type */
    }
    .stat-card:nth-child(1) .stat-icon { background: var(--gradient-primary); }
    .stat-card:nth-child(2) .stat-icon { background: var(--gradient-danger); }
    .stat-card:nth-child(3) .stat-icon { background: var(--gradient-success); }
    .stat-card:nth-child(4) .stat-icon { background: var(--gradient-info); }
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
        margin: 0 auto 1rem;
        box-shadow: var(--shadow-md);
    }
    
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
    }
    
    /* Animations */
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
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .admin-header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .admin-title {
            font-size: 2rem;
        }
        
        .admin-actions {
            flex-direction: column;
            width: 100%;
        }
        
        .admin-search {
            min-width: 100%;
        }
        
        .filter-controls {
            width: 100%;
            justify-content: space-between;
        }
        
        .tab-nav {
            flex-wrap: wrap;
        }
        
        .users-grid {
            grid-template-columns: 1fr;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .user-actions {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .admin-container {
            padding: 0 0.5rem;
        }
        
        .admin-header {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-controls {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
    
    /* Animations personnalisées */
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .stat-card:hover .stat-value {
        animation: pulse 0.6s ease-in-out;
    }
    
    /* États de chargement */
    .loading {
        opacity: 0.6;
        pointer-events: none;
        position: relative;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid transparent;
        border-top: 2px solid var(--primary-500);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Tooltips */
    .tooltip {
        position: relative;
        cursor: help;
    }
    
    .tooltip::before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--gray-800);
        color: white;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s;
        z-index: 1000;
    }
    
    .tooltip:hover::before {
        opacity: 1;
    }
    
    /* Améliorations visuelles */
    .user-card:hover .user-avatar {
        transform: scale(1.1) rotate(5deg);
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
    }
    
    .btn:active {
        transform: translateY(1px);
    }
    
    /* Mode sombre (préparation future) */
    @media (prefers-color-scheme: dark) {
        .admin-page {
            background: var(--gray-900);
        }
        
        .admin-header,
        .users-section,
        .stat-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .admin-title,
        .section-title,
        .user-info h4 {
            color: var(--gray-100);
        }
        
        .admin-subtitle,
        .user-email,
        .stat-label {
            color: var(--gray-400);
        }
    }
    </style>
</head>
<body>
    <div class="admin-page">
        <div class="admin-container">
            <!-- En-tête Admin -->
            <div class="admin-header">
                <div class="admin-header-content">
                    <div class="admin-title-section">
                        <div class="admin-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h1 class="admin-title">Administration</h1>
                            <p class="admin-subtitle">Gestion des utilisateurs et paramètres système</p>
                        </div>
                    </div>
                    <div class="admin-actions">
                        <div class="admin-search">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="userSearch" class="search-input" placeholder="Rechercher un utilisateur...">
                        </div>
                        <div class="filter-controls">
                            <select id="roleFilter" class="filter-select">
                                <option value="">Tous les rôles</option>
                                <?php foreach ($roles as $roleId => $roleName): ?>
                                    <option value="<?= $roleId ?>"><?= $roleName ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-export" onclick="exportUsers()">
                                <i class="fas fa-download"></i>
                                Exporter
                            </button>
                            <button class="btn btn-refresh" onclick="refreshStats()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <button class="btn btn-primary" onclick="showCreateUserForm()">
                            <i class="fas fa-user-plus"></i>
                            Nouvel utilisateur
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages flash -->
            <?php if (isset($_SESSION['flash']['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['flash']['success']) ?>
                </div>
                <?php unset($_SESSION['flash']['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash']['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($_SESSION['flash']['error']) ?>
                </div>
                <?php unset($_SESSION['flash']['error']); ?>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-icon" style="background: var(--gradient-primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value" id="totalUsers"><?= count($users) ?></div>
                    <div class="stat-label">Utilisateurs total</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon" style="background: var(--gradient-danger);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value" id="adminCount"><?= count(array_filter($users, fn($u) => $u['role'] == ROLE_ADMIN)) ?></div>
                    <div class="stat-label">Administrateurs</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon" style="background: var(--gradient-success);">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="stat-value" id="managerCount"><?= count(array_filter($users, fn($u) => $u['role'] == ROLE_GESTIONNAIRE)) ?></div>
                    <div class="stat-label">Gestionnaires</div>
                </div>
                <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon" style="background: var(--gradient-info);">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-value" id="consultantCount"><?= count(array_filter($users, fn($u) => $u['role'] == ROLE_CONSULTANT)) ?></div>
                    <div class="stat-label">Consultants</div>
                </div>
            </div>

            <!-- Formulaire de création d'utilisateur -->
            <div class="create-user-form" id="createUserForm" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: var(--gray-800);">
                    <i class="fas fa-user-plus"></i>
                    Créer un nouvel utilisateur
                </h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i>
                                Nom complet
                            </label>
                            <input type="text" id="name" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email
                            </label>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="role">
                                <i class="fas fa-shield-alt"></i>
                                Rôle
                            </label>
                            <select id="role" name="role" class="form-input" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $roleId => $roleName): ?>
                                    <option value="<?= $roleId ?>"><?= $roleName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="create_user" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer l'utilisateur
                        </button>
                        <button type="button" class="btn btn-outline" onclick="hideCreateUserForm()">
                            <i class="fas fa-times"></i>
                            Annuler
                        </button>
                    </div>
                </form>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="users-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        Gestion des utilisateurs
                    </h2>
                </div>

                <div class="users-grid" id="usersGrid">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card" 
                             data-user-name="<?= strtolower($user['name']) ?>" 
                             data-user-email="<?= strtolower($user['email']) ?>"
                             data-user-role="<?= $user['role'] ?>"
                             data-aos="zoom-in" 
                             data-aos-delay="<?= array_search($user, $users) * 100 ?>">
                            <div class="user-header">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div class="user-info">
                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                    <div class="user-email">
                                        <i class="fas fa-envelope"></i>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="user-meta">
                                <span class="user-role role-<?= strtolower($roles[$user['role']]) ?>">
                                    <i class="fas fa-shield-alt"></i>
                                    <?= $roles[$user['role']] ?>
                                </span>
                            </div>

                            <div class="user-actions">
                                <form method="POST" class="role-form">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="new_role" class="role-select" onchange="confirmRoleChange(this)">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <option value="<?= $roleId ?>" <?= $user['role'] == $roleId ? 'selected' : '' ?>>
                                                <?= $roleName ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i>
                                        Sauver
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let users = <?= json_encode($users) ?>;
        let roles = <?= json_encode($roles) ?>;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch();
            initializeFilters();
            initializeAnimations();
            setupFormValidation();
        });

        // Fonction de recherche en temps réel
        function initializeSearch() {
            const searchInput = document.getElementById('userSearch');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterUsers(searchTerm, document.getElementById('roleFilter').value);
            });
        }

        // Fonction de filtrage par rôle
        function initializeFilters() {
            const roleFilter = document.getElementById('roleFilter');
            roleFilter.addEventListener('change', function() {
                const roleId = this.value;
                const searchTerm = document.getElementById('userSearch').value.toLowerCase();
                filterUsers(searchTerm, roleId);
            });
        }

        // Filtrage des utilisateurs
        function filterUsers(searchTerm, roleId) {
            const userCards = document.querySelectorAll('.user-card');
            let visibleCount = 0;

            userCards.forEach(card => {
                const userName = card.dataset.userName;
                const userEmail = card.dataset.userEmail;
                const userRole = card.dataset.userRole;

                const matchesSearch = !searchTerm || 
                    userName.includes(searchTerm) || 
                    userEmail.includes(searchTerm);
                
                const matchesRole = !roleId || userRole === roleId;

                if (matchesSearch && matchesRole) {
                    card.classList.remove('filtered-out');
                    card.style.display = 'block';
                    visibleCount++;
                    
                    // Animation d'apparition
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                        card.style.opacity = '1';
                    }, visibleCount * 50);
                } else {
                    card.classList.add('filtered-out');
                    card.style.transform = 'scale(0.8)';
                    card.style.opacity = '0.3';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });

            // Message si aucun résultat
            updateNoResultsMessage(visibleCount);
        }

        // Message aucun résultat
        function updateNoResultsMessage(count) {
            let noResultsMsg = document.getElementById('noResultsMessage');
            
            if (count === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMessage';
                    noResultsMsg.className = 'no-results-message';
                    noResultsMsg.innerHTML = `
                        <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>Aucun utilisateur trouvé</h3>
                            <p>Essayez de modifier vos critères de recherche</p>
                        </div>
                    `;
                    document.getElementById('usersGrid').appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }

        // Animations d'entrée
        function initializeAnimations() {
            const cards = document.querySelectorAll('.user-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'slideInUp 0.6s ease-out forwards';
            });
        }

        // Validation du formulaire
        function setupFormValidation() {
            const form = document.querySelector('#createUserForm form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const role = document.getElementById('role').value;

                    if (!name || !email || !role) {
                        e.preventDefault();
                        showNotification('Veuillez remplir tous les champs', 'error');
                        return;
                    }

                    if (!isValidEmail(email)) {
                        e.preventDefault();
                        showNotification('Veuillez entrer une adresse email valide', 'error');
                        return;
                    }
                });
            }
        }

        // Confirmation changement de rôle
        function confirmRoleChange(selectElement) {
            const form = selectElement.closest('form');
            const userName = selectElement.closest('.user-card').querySelector('h4').textContent;
            const newRole = selectElement.options[selectElement.selectedIndex].text;
            
            if (confirm(`Êtes-vous sûr de vouloir changer le rôle de ${userName} vers ${newRole} ?`)) {
                form.submit();
            } else {
                // Réinitialiser la sélection
                selectElement.selectedIndex = 0;
            }
        }

        // Afficher/masquer le formulaire de création
        function showCreateUserForm() {
            const form = document.getElementById('createUserForm');
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth' });
            
            // Animation d'entrée
            setTimeout(() => {
                form.style.transform = 'scale(1)';
                form.style.opacity = '1';
            }, 100);
        }

        function hideCreateUserForm() {
            const form = document.getElementById('createUserForm');
            form.style.transform = 'scale(0.95)';
            form.style.opacity = '0';
            setTimeout(() => {
                form.style.display = 'none';
            }, 300);
        }

        // Export des utilisateurs
        function exportUsers() {
            const data = users.map(user => ({
                nom: user.name,
                email: user.email,
                role: roles[user.role]
            }));

            const csv = convertToCSV(data);
            downloadCSV(csv, 'utilisateurs_minsante.csv');
            showNotification('Export réussi !', 'success');
        }

        // Actualiser les statistiques
        function refreshStats() {
            const refreshBtn = document.querySelector('.btn-refresh');
            refreshBtn.style.transform = 'rotate(360deg)';
            
            // Simulation de rafraîchissement
            setTimeout(() => {
                refreshBtn.style.transform = 'rotate(0deg)';
                showNotification('Statistiques actualisées', 'success');
                updateStatsWithAnimation();
            }, 1000);
        }

        // Mise à jour des stats avec animation
        function updateStatsWithAnimation() {
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                const currentValue = parseInt(stat.textContent);
                animateNumber(stat, 0, currentValue, 1000);
            });
        }

        // Animation des nombres
        function animateNumber(element, start, end, duration) {
            const startTime = performance.now();
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.floor(start + (end - start) * progress);
                
                element.textContent = current;
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            requestAnimationFrame(update);
        }

        // Utilitaires
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function convertToCSV(data) {
            const headers = Object.keys(data[0]).join(',');
            const rows = data.map(row => Object.values(row).join(','));
            return [headers, ...rows].join('\n');
        }

        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            document.querySelector('.admin-container').insertBefore(
                notification, 
                document.querySelector('.stats-grid')
            );
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl + N pour nouveau utilisateur
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showCreateUserForm();
            }
            
            // Escape pour fermer le formulaire
            if (e.key === 'Escape') {
                hideCreateUserForm();
            }
            
            // Ctrl + F pour focus sur la recherche
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('userSearch').focus();
            }
        });
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>