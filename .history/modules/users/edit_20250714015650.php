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

// Vérifier les permissions - seuls les admins peuvent modifier les profils des autres
if ($userId != $_SESSION['user_id'] && !hasPermission(ROLE_ADMIN)) {
    die("Accès non autorisé - Vous ne pouvez modifier que votre propre profil");
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = '';
    
    // Validation des données
    $name = cleanInput($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation pour admin seulement
    if (hasPermission(ROLE_ADMIN) && isset($_POST['role'])) {
        $role = (int)$_POST['role'];
        $allowedRoles = [ROLE_ADMIN, ROLE_GESTIONNAIRE, ROLE_CONSULTANT];
        if (!in_array($role, $allowedRoles)) {
            $errors[] = "Rôle invalide sélectionné";
        }
    } else {
        $role = $user['role']; // Garder le rôle actuel
    }
    
    // Validations
    if (empty($name)) {
        $errors[] = "Le nom est requis";
    }
    
    if (!$email) {
        $errors[] = "Email invalide";
    }
    
    // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
    $existingUser = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existingUser) {
        $errors[] = "Cet email est déjà utilisé par un autre utilisateur";
    }
    
    // Validation du mot de passe si fourni
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "Les mots de passe ne correspondent pas";
        }
    }
    
    // Si pas d'erreurs, mettre à jour
    if (empty($errors)) {
        try {
            if (!empty($newPassword)) {
                // Mise à jour avec nouveau mot de passe
                $hashedPassword = generateSecurePassword($newPassword);
                executeQuery(
                    "UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?",
                    [$name, $email, $hashedPassword, $role, $userId]
                );
            } else {
                // Mise à jour sans changer le mot de passe
                executeQuery(
                    "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?",
                    [$name, $email, $role, $userId]
                );
            }
            
            // Mettre à jour les données de l'utilisateur pour l'affichage
            $user['name'] = $name;
            $user['email'] = $email;
            $user['role'] = $role;
            
            $success = "Profil mis à jour avec succès!";
            
            // Rediriger vers la page de visualisation après succès
            if (hasPermission(ROLE_ADMIN) && $userId != $_SESSION['user_id']) {
                header("Location: " . BASE_URL . "modules/users/view.php?id=" . $userId . "&success=1");
                exit();
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la mise à jour: " . $e->getMessage();
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
    <title>Modifier <?= htmlspecialchars($user['name']) ?> - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE MODIFICATION UTILISATEUR - STYLE ADMIN === */
    .user-edit-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .user-edit-container {
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
    
    .user-details h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .user-details .subtitle {
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
    
    .form-container {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInUp 0.6s ease-out;
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
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .form-section {
        background: var(--gray-50);
        padding: 1.5rem;
        border-radius: var(--radius-xl);
        border: 1px solid var(--gray-200);
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
    
    .password-section {
        background: var(--warning-50);
        border: 1px solid var(--warning-200);
        border-left: 4px solid var(--warning-500);
    }
    
    .password-section .section-title {
        color: var(--warning-800);
    }
    
    .current-role {
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
    }
    
    .role-gestionnaire {
        background: var(--primary-100);
        color: var(--primary-700);
        border: 1px solid var(--primary-200);
    }
    
    .role-consultant {
        background: var(--success-100);
        color: var(--success-700);
        border: 1px solid var(--success-200);
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
    
    .btn-danger {
        background: var(--gradient-danger);
        color: white;
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
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
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .user-info {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .form-actions {
            justify-content: center;
            flex-direction: column;
        }
        
        .user-avatar-large {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .user-details h1 {
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>
    <div class="user-edit-page">
        <div class="user-edit-container">
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
                <a href="<?= BASE_URL ?>modules/users/view.php?id=<?= $user['id'] ?>">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($user['name']) ?>
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Modifier</span>
            </nav>

            <!-- En-tête utilisateur -->
            <div class="page-header">
                <div class="header-content">
                    <div class="user-info">
                        <div class="user-avatar-large">
                            <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                        </div>
                        <div class="user-details">
                            <h1>Modifier le profil</h1>
                            <p class="subtitle">
                                <i class="fas fa-user-edit"></i>
                                <?= htmlspecialchars($user['name']) ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    (Votre profil)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="<?= BASE_URL ?>modules/users/view.php?id=<?= $user['id'] ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                            Voir le profil
                        </a>
                        <a href="<?= BASE_URL ?>modules/users/list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour à la liste
                        </a>
                    </div>
                </div>
            </div>

            <!-- Formulaire de modification -->
            <div class="form-container">
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-edit"></i>
                        Informations du compte
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

                <form method="POST" action="">
                    <div class="form-grid">
                        <!-- Informations de base -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Informations de base
                            </h3>
                            
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
                                    value="<?= htmlspecialchars($user['name']) ?>" 
                                    required
                                    placeholder="Entrez le nom complet"
                                >
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
                                    value="<?= htmlspecialchars($user['email']) ?>" 
                                    required
                                    placeholder="exemple@domaine.com"
                                >
                                <div class="form-help">
                                    <i class="fas fa-info-circle"></i>
                                    Cette adresse sera utilisée pour la connexion
                                </div>
                            </div>
                        </div>

                        <!-- Gestion des rôles (Admin seulement) -->
                        <?php if (hasPermission(ROLE_ADMIN)): ?>
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-shield-alt"></i>
                                Droits et permissions
                            </h3>
                            
                            <div class="current-role role-<?= strtolower(getRoleName($user['role'])) ?>">
                                <i class="fas fa-shield-alt"></i>
                                Rôle actuel: <?= getRoleName($user['role']) ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">
                                    <i class="fas fa-users-cog"></i>
                                    Nouveau rôle
                                </label>
                                <select id="role" name="role" class="form-select">
                                    <?php foreach ($roles as $roleId => $roleName): ?>
                                        <option value="<?= $roleId ?>" <?= $user['role'] == $roleId ? 'selected' : '' ?>>
                                            <?= $roleName ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Attention: Changer le rôle modifie les permissions d'accès
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Section mot de passe -->
                    <div class="form-section password-section">
                        <h3 class="section-title">
                            <i class="fas fa-lock"></i>
                            Sécurité du compte
                        </h3>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">
                                <i class="fas fa-key"></i>
                                Nouveau mot de passe
                            </label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-input" 
                                placeholder="Laissez vide pour conserver l'actuel"
                            >
                            <div class="form-help">
                                <i class="fas fa-info-circle"></i>
                                Minimum 6 caractères. Laissez vide pour ne pas changer
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-check-double"></i>
                                Confirmer le mot de passe
                            </label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input" 
                                placeholder="Répétez le nouveau mot de passe"
                            >
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <a href="<?= BASE_URL ?>modules/users/view.php?id=<?= $user['id'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Validation côté client et animations
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const passwordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
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
            
            // Validation en temps réel des mots de passe
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Retirer les styles d'erreur précédents
                passwordInput.style.borderColor = '';
                confirmPasswordInput.style.borderColor = '';
                
                if (password && password.length < 6) {
                    passwordInput.style.borderColor = 'var(--danger-500)';
                    return false;
                }
                
                if (password && confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = 'var(--danger-500)';
                    return false;
                }
                
                if (password && confirmPassword && password === confirmPassword) {
                    passwordInput.style.borderColor = 'var(--success-500)';
                    confirmPasswordInput.style.borderColor = 'var(--success-500)';
                }
                
                return true;
            }
            
            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            // Validation avant soumission
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                
                if (!name) {
                    e.preventDefault();
                    alert('Le nom est requis');
                    document.getElementById('name').focus();
                    return;
                }
                
                if (!email) {
                    e.preventDefault();
                    alert('L\'email est requis');
                    document.getElementById('email').focus();
                    return;
                }
                
                if (!validatePasswords()) {
                    e.preventDefault();
                    alert('Veuillez vérifier les mots de passe');
                    return;
                }
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
            
            // Confirmation pour changement de rôle (admin)
            const roleSelect = document.getElementById('role');
            if (roleSelect) {
                const originalRole = roleSelect.value;
                roleSelect.addEventListener('change', function() {
                    if (this.value !== originalRole) {
                        const confirm = window.confirm(
                            'Êtes-vous sûr de vouloir changer le rôle de cet utilisateur? ' +
                            'Cela modifiera ses permissions d\'accès.'
                        );
                        if (!confirm) {
                            this.value = originalRole;
                        }
                    }
                });
            }
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
