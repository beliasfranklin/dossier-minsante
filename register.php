<?php
require_once __DIR__ . '/includes/config.php';

// Si vous voulez limiter les inscriptions aux admins seulement

// Rediriger les utilisateurs déjà connectés
if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = ROLE_CONSULTANT; // Par défaut

    // Validation
    if (empty($name)) {
        $errors['name'] = "Le nom est obligatoire";
    }

    if (empty($email)) {
        $errors['email'] = "L'email est obligatoire";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'email n'est pas valide";
    } elseif (emailExists($email)) {
        $errors['email'] = "Cet email est déjà utilisé";
    }

    // Validation améliorée du mot de passe
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors['password'] = "Le mot de passe doit faire au moins ".MIN_PASSWORD_LENGTH." caractères";
    } elseif (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors['password'] = "Le mot de passe doit contenir au moins une majuscule";
    } elseif (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        $errors['password'] = "Le mot de passe doit contenir au moins un chiffre";
    } elseif (PASSWORD_REQUIRE_SPECIAL_CHAR && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['password'] = "Le mot de passe doit contenir au moins un caractère spécial";
    }

    // Si pas d'erreurs, création du compte
    if (empty($errors)) {
        $hashed_password = generateSecurePassword($password);
        
        $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)";
        $stmt = executeQuery($sql, [$name, $email, $hashed_password, $role]);
        
        if ($stmt->rowCount() > 0) {
            $success = true;
            logAction($pdo->lastInsertId(), 'user_registered');
            
            // Auto-login après inscription
            login($email, $password);
            header("Location: dashboard.php");
            exit();
        } else {
            $errors['general'] = "Une erreur est survenue lors de la création du compte";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
            <img src="assets/img/admin-users.svg" alt="Inscription" style="width:70px;height:70px;box-shadow:0 4px 24px #6dd5fa33;border-radius:50%;background:#fff;">
            <h1 style="display:flex;align-items:center;gap:10px;"><i class="fas fa-user-plus" style="color:#2980b9;"></i> Créer un compte</h1>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Compte créé avec succès! Redirection en cours...</div>
        <?php endif; ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $errors['general'] ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name"><i class="fas fa-user"></i> Nom complet</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?>
                    <span class="error-message"><?= $errors['name'] ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?= $errors['email'] ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group<?= isset($errors['password']) ? ' has-error' : '' ?>">
                <label for="password"><i class="fas fa-lock"></i> Mot de passe (8 caractères minimum)</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required style="padding-right:40px;">
                    <button type="button" onclick="togglePassword('password', this)" class="toggle-password-btn" aria-label="Afficher/masquer le mot de passe">
                        <span id="toggle-password-icon">👁️</span>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <span class="error-message"><?= $errors['password'] ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group<?= isset($errors['password_confirm']) ? ' has-error' : '' ?>">
                <label for="password_confirm"><i class="fas fa-lock"></i> Confirmer le mot de passe</label>
                <div class="password-field">
                    <input type="password" id="password_confirm" name="password_confirm" required style="padding-right:40px;">
                    <button type="button" onclick="togglePassword('password_confirm', this)" class="toggle-password-btn" aria-label="Afficher/masquer le mot de passe">
                        <span id="toggle-password-confirm-icon">👁️</span>
                    </button>
                </div>
                <?php if (isset($errors['password_confirm'])): ?>
                    <span class="error-message"><?= $errors['password_confirm'] ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn"><i class="fas fa-user-plus"></i> S'inscrire</button>
            <div class="auth-links">
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Déjà un compte ? Se connecter</a>
            </div>
        </form>
    </div>
    <script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.querySelector('span').textContent = '🙈';
        } else {
            input.type = 'password';
            btn.querySelector('span').textContent = '👁️';
        }
    }
    document.querySelectorAll('.toggle-password-btn').forEach(btn => {
        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btn.click();
            }
        });
    });
    </script>
</body>
</html>