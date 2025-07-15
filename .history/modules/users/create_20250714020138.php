<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérifier les permissions - seuls les admins peuvent créer des utilisateurs
if (!hasPermission(ROLE_ADMIN)) {
    header("Location: " . BASE_URL . "error.php?code=403");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = '';
    
    // Validation des données
    $name = cleanInput($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = (int)($_POST['role'] ?? ROLE_CONSULTANT);
    $sendEmail = isset($_POST['send_email']);
    
    // Validations
    if (empty($name)) {
        $errors[] = "Le nom est requis";
    }
    
    if (!$email) {
        $errors[] = "Email invalide";
    } else {
        // Vérifier si l'email existe déjà
        $existingUser = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existingUser) {
            $errors[] = "Cet email est déjà utilisé par un autre utilisateur";
        }
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    // Validation du rôle
    $allowedRoles = [ROLE_ADMIN, ROLE_GESTIONNAIRE, ROLE_CONSULTANT];
    if (!in_array($role, $allowedRoles)) {
        $errors[] = "Rôle invalide sélectionné";
    }
    
    // Si pas d'erreurs, créer l'utilisateur
    if (empty($errors)) {
        try {
            $hashedPassword = generateSecurePassword($password);
            
            executeQuery(
                "INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$name, $email, $hashedPassword, $role]
            );
            
            $newUserId = getLastInsertId();
            
            // Log de l'action
            logAction($_SESSION['user_id'], 'user_created', $newUserId, "Nouvel utilisateur créé : $name ($email)");
            
            // Envoyer un email de bienvenue si demandé
            if ($sendEmail) {
                // Ici vous pouvez implémenter l'envoi d'email
                // sendWelcomeEmail($email, $name, $password);
            }
            
            $success = "Utilisateur créé avec succès!";
            
            // Rediriger vers la liste des utilisateurs après succès
            header("Location: " . BASE_URL . "modules/users/list.php?success=created");
            exit();
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la création: " . $e->getMessage();
        }
    }
}

$roles = [
    ROLE_ADMIN => "Administrateur",
    ROLE_GESTIONNAIRE => "Gestionnaire",
    ROLE_CONSULTANT => "Consultant"
];

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un utilisateur - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE CRÉATION UTILISATEUR - STYLE ADMIN MODERNE === */
    .user-create-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .user-create-container {
        max-width: 1000px;
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
        background: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.3s ease-out;
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
    
    .header-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .header-icon {
        width: 80px;
        height: 80px;
        background: var(--gradient-primary);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
        animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-6px) rotate(2deg); }
    }
    
    .header-details h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .header-details .subtitle {
        color: var(--gray-600);
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
    
    .form-container {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInLeft 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .form-container::before {
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
    
    .form-container:hover::before {
        transform: scaleX(1);
    }
    
    .form-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }
    
    .form-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-title i {
        color: var(--primary-500);
        font-size: 1.25rem;
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        animation: slideInDown 0.5s ease-out;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-700);
        border: 1px solid var(--success-200);
        border-left: 4px solid var(--success-500);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
        border-left: 4px solid var(--danger-500);
    }
    
    .alert i {
        font-size: 1.125rem;
    }
    
    .form-section {
        background: var(--gray-50);
        padding: 1.5rem;
        border-radius: var(--radius-xl);
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
        transition: var(--transition-all);
    }
    
    .form-section:hover {
        background: white;
        box-shadow: var(--shadow-sm);
        transform: translateY(-2px);
    }
    
    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .section-title i {
        color: var(--primary-500);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group:last-child {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--primary-500);
        width: 16px;
    }
    
    .form-label .required {
        color: var(--danger-500);
    }
    
    .form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
        box-sizing: border-box;
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }
    
    .form-input:hover {
        border-color: var(--primary-300);
    }
    
    .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .form-help {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .form-help i {
        color: var(--info-500);
    }
    
    .form-checkbox {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: var(--primary-50);
        border: 1px solid var(--primary-200);
        border-radius: var(--radius-lg);
        transition: var(--transition-all);
    }
    
    .form-checkbox:hover {
        background: var(--primary-100);
    }
    
    .form-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-500);
    }
    
    .form-checkbox label {
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .password-strength {
        margin-top: 0.5rem;
        height: 4px;
        background: var(--gray-200);
        border-radius: 2px;
        overflow: hidden;
        transition: var(--transition-all);
    }
    
    .password-strength-bar {
        height: 100%;
        transition: var(--transition-all);
        width: 0%;
    }
    
    .strength-weak { background: var(--danger-500); width: 25%; }
    .strength-fair { background: var(--warning-500); width: 50%; }
    .strength-good { background: var(--info-500); width: 75%; }
    .strength-strong { background: var(--success-500); width: 100%; }
    
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
    
    .help-item {
        display: flex;
        align-items: start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        margin-bottom: 1rem;
        transition: var(--transition-all);
    }
    
    .help-item:hover {
        background: var(--primary-50);
        transform: translateX(3px);
    }
    
    .help-item i {
        color: var(--primary-500);
        font-size: 1.125rem;
        margin-top: 0.125rem;
        flex-shrink: 0;
    }
    
    .help-text {
        color: var(--gray-700);
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .role-preview {
        padding: 1rem;
        border-radius: var(--radius-lg);
        border: 2px solid var(--gray-200);
        margin-top: 1rem;
        transition: var(--transition-all);
    }
    
    .role-admin-preview {
        border-color: var(--danger-200);
        background: var(--danger-50);
    }
    
    .role-gestionnaire-preview {
        border-color: var(--primary-200);
        background: var(--primary-50);
    }
    
    .role-consultant-preview {
        border-color: var(--success-200);
        background: var(--success-50);
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 2px solid var(--gray-100);
        margin-top: 2rem;
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
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-primary:disabled {
        opacity: 0.6;
        transform: none;
        cursor: not-allowed;
    }
    
    .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
    }
    
    .btn-secondary:hover {
        background: var(--gray-200);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
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
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .main-content {
            grid-template-columns: 1fr;
        }
        
        .sidebar {
            order: -1;
        }
        
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .header-info {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-actions {
            justify-content: center;
            flex-direction: column;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .header-details h1 {
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>
    <div class="user-create-page">
        <div class="user-create-container">
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
                <span>Créer un utilisateur</span>
            </nav>

            <!-- En-tête -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <div class="header-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="header-details">
                            <h1>Créer un nouvel utilisateur</h1>
                            <p class="subtitle">
                                <i class="fas fa-plus-circle"></i>
                                Ajouter un membre à l'équipe MINSANTE
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="<?= BASE_URL ?>modules/users/list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour à la liste
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="main-content">
                <!-- Formulaire -->
                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-user-cog"></i>
                            Informations du nouvel utilisateur
                        </h2>
                    </div>

                    <!-- Messages d'alerte -->
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <?php foreach ($errors as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="createUserForm">
                        <!-- Informations de base -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Informations personnelles
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-signature"></i>
                                        Nom complet <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="name" 
                                        name="name" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                        required
                                        placeholder="Prénom Nom"
                                    >
                                    <div class="form-help">
                                        <i class="fas fa-info-circle"></i>
                                        Le nom complet qui apparaîtra dans l'interface
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Adresse email <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                        required
                                        placeholder="utilisateur@minsante.gov"
                                    >
                                    <div class="form-help">
                                        <i class="fas fa-info-circle"></i>
                                        Servira d'identifiant de connexion
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sécurité -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-lock"></i>
                                Sécurité du compte
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-key"></i>
                                        Mot de passe <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        class="form-input" 
                                        required
                                        placeholder="Minimum 6 caractères"
                                    >
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <div class="form-help">
                                        <i class="fas fa-shield-alt"></i>
                                        <span id="strengthText">Entrez un mot de passe sécurisé</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-check-double"></i>
                                        Confirmer le mot de passe <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        class="form-input" 
                                        required
                                        placeholder="Répétez le mot de passe"
                                    >
                                    <div class="form-help" id="matchText">
                                        <i class="fas fa-info-circle"></i>
                                        Doit être identique au mot de passe
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rôle et permissions -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-shield-alt"></i>
                                Rôle et permissions
                            </h3>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">
                                    <i class="fas fa-users-cog"></i>
                                    Rôle de l'utilisateur <span class="required">*</span>
                                </label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="">Sélectionner un rôle...</option>
                                    <?php foreach ($roles as $roleId => $roleName): ?>
                                        <option value="<?= $roleId ?>" <?= ($_POST['role'] ?? '') == $roleId ? 'selected' : '' ?>>
                                            <?= $roleName ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Détermine les permissions d'accès de l'utilisateur
                                </div>
                                
                                <div id="rolePreview" class="role-preview" style="display: none;">
                                    <div id="roleDescription"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Options supplémentaires -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-cogs"></i>
                                Options supplémentaires
                            </h3>
                            
                            <div class="form-checkbox">
                                <input 
                                    type="checkbox" 
                                    id="send_email" 
                                    name="send_email"
                                    <?= isset($_POST['send_email']) ? 'checked' : '' ?>
                                >
                                <label for="send_email">
                                    <i class="fas fa-envelope"></i>
                                    Envoyer un email de bienvenue avec les informations de connexion
                                </label>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="form-actions">
                            <a href="<?= BASE_URL ?>modules/users/list.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-plus"></i>
                                Créer l'utilisateur
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Aide -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-question-circle"></i>
                            Aide à la création
                        </h3>
                        
                        <div class="help-item">
                            <i class="fas fa-user-shield"></i>
                            <div class="help-text">
                                <strong>Administrateur</strong><br>
                                Accès complet au système, gestion des utilisateurs et configuration
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <i class="fas fa-user-cog"></i>
                            <div class="help-text">
                                <strong>Gestionnaire</strong><br>
                                Création et gestion des dossiers, workflow, pièces jointes
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <i class="fas fa-user"></i>
                            <div class="help-text">
                                <strong>Consultant</strong><br>
                                Consultation des dossiers autorisés, ajout de commentaires
                            </div>
                        </div>
                    </div>

                    <!-- Conseils sécurité -->
                    <div class="sidebar-card">
                        <h3>
                            <i class="fas fa-shield-alt"></i>
                            Conseils de sécurité
                        </h3>
                        
                        <div class="help-item">
                            <i class="fas fa-key"></i>
                            <div class="help-text">
                                Utilisez un mot de passe d'au moins 8 caractères avec majuscules, minuscules et chiffres
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <i class="fas fa-envelope-open-text"></i>
                            <div class="help-text">
                                L'email de bienvenue contient des informations sensibles. Assurez-vous de l'adresse
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <i class="fas fa-users-cog"></i>
                            <div class="help-text">
                                Assignez le rôle minimum nécessaire selon le principe du moindre privilège
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
                            <a href="<?= BASE_URL ?>modules/users/list.php" class="btn btn-secondary" style="justify-content: center;">
                                <i class="fas fa-list"></i>
                                Liste des utilisateurs
                            </a>
                            <a href="<?= BASE_URL ?>admin.php" class="btn btn-secondary" style="justify-content: center;">
                                <i class="fas fa-cogs"></i>
                                Administration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validation et interactions côté client
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createUserForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const roleSelect = document.getElementById('role');
            const submitBtn = document.getElementById('submitBtn');
            
            // Animation des sections au chargement
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 150);
            });
            
            // Validation de la force du mot de passe
            function checkPasswordStrength(password) {
                let strength = 0;
                let text = '';
                let className = '';
                
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                const strengthBar = document.getElementById('strengthBar');
                const strengthText = document.getElementById('strengthText');
                
                switch(strength) {
                    case 0:
                    case 1:
                        className = 'strength-weak';
                        text = 'Mot de passe faible';
                        break;
                    case 2:
                        className = 'strength-fair';
                        text = 'Mot de passe moyen';
                        break;
                    case 3:
                        className = 'strength-good';
                        text = 'Mot de passe bon';
                        break;
                    case 4:
                    case 5:
                        className = 'strength-strong';
                        text = 'Mot de passe fort';
                        break;
                }
                
                strengthBar.className = 'password-strength-bar ' + className;
                strengthText.textContent = text;
                
                return strength >= 2;
            }
            
            // Validation des mots de passe en temps réel
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const matchText = document.getElementById('matchText');
                
                // Vérifier la force
                const isStrong = checkPasswordStrength(password);
                
                // Vérifier la correspondance
                if (password && confirmPassword) {
                    if (password === confirmPassword) {
                        confirmPasswordInput.style.borderColor = 'var(--success-500)';
                        matchText.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-500);"></i> Les mots de passe correspondent';
                        matchText.style.color = 'var(--success-600)';
                        return true;
                    } else {
                        confirmPasswordInput.style.borderColor = 'var(--danger-500)';
                        matchText.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-500);"></i> Les mots de passe ne correspondent pas';
                        matchText.style.color = 'var(--danger-600)';
                        return false;
                    }
                } else {
                    confirmPasswordInput.style.borderColor = '';
                    matchText.innerHTML = '<i class="fas fa-info-circle"></i> Doit être identique au mot de passe';
                    matchText.style.color = '';
                    return false;
                }
            }
            
            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            // Prévisualisation du rôle
            const roleDescriptions = {
                '<?= ROLE_ADMIN ?>': {
                    text: 'Administrateur système avec accès complet à toutes les fonctionnalités, gestion des utilisateurs et configuration avancée.',
                    class: 'role-admin-preview'
                },
                '<?= ROLE_GESTIONNAIRE ?>': {
                    text: 'Gestionnaire pouvant créer et gérer des dossiers, gérer les workflows et les pièces jointes.',
                    class: 'role-gestionnaire-preview'
                },
                '<?= ROLE_CONSULTANT ?>': {
                    text: 'Consultant avec accès en lecture aux dossiers autorisés et possibilité d\'ajouter des commentaires.',
                    class: 'role-consultant-preview'
                }
            };
            
            roleSelect.addEventListener('change', function() {
                const rolePreview = document.getElementById('rolePreview');
                const roleDescription = document.getElementById('roleDescription');
                
                if (this.value && roleDescriptions[this.value]) {
                    const desc = roleDescriptions[this.value];
                    roleDescription.textContent = desc.text;
                    rolePreview.className = 'role-preview ' + desc.class;
                    rolePreview.style.display = 'block';
                } else {
                    rolePreview.style.display = 'none';
                }
            });
            
            // Validation avant soumission
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const role = roleSelect.value;
                
                let isValid = true;
                
                if (!name) {
                    alert('Le nom est requis');
                    document.getElementById('name').focus();
                    isValid = false;
                }
                
                if (!email) {
                    alert('L\'email est requis');
                    document.getElementById('email').focus();
                    isValid = false;
                }
                
                if (!password) {
                    alert('Le mot de passe est requis');
                    passwordInput.focus();
                    isValid = false;
                }
                
                if (password !== confirmPassword) {
                    alert('Les mots de passe ne correspondent pas');
                    confirmPasswordInput.focus();
                    isValid = false;
                }
                
                if (!role) {
                    alert('Veuillez sélectionner un rôle');
                    roleSelect.focus();
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Animation du bouton pendant la soumission
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
                submitBtn.disabled = true;
            });
            
            // Animation sur les inputs focus
            const inputs = document.querySelectorAll('.form-input, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
