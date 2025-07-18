<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    
    if (login($email, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = t('incorrect_credentials');
    }
}

if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Sélecteur de langue amélioré -->
        <div class="language-selector" style="position: absolute; top: 20px; right: 20px;">
            <?= renderLanguageSelector('buttons') ?>
        </div>
        
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
            <img src="assets/img/admin-users.svg" alt="<?= t('login') ?>" style="width:70px;height:70px;box-shadow:0 4px 24px #6dd5fa33;border-radius:50%;background:#fff;">
            <h1 style="display:flex;align-items:center;gap:10px;"><i class="fas fa-sign-in-alt" style="color:#2980b9;"></i> <?= t('login_title') ?></h1>
        </div>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> <?= t('email') ?></label>
                <input type="email" id="email" name="email" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> <?= t('password') ?></label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" onclick="togglePassword('password', this)" class="toggle-password-btn" aria-label="<?= t('show_hide_password') ?>">
                        <span id="toggle-password-icon">👁️</span>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> <?= t('login_button') ?></button>
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