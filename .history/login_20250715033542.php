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
    <style>
        /* Améliorations modernes pour la page de connexion */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 10px 25px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            margin: 0;
            animation: loginFadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes loginFadeIn {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 35px;
            text-align: center;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .login-logo i {
            font-size: 32px;
            color: white;
        }

        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 400;
            margin: 0;
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: #667eea;
            width: 16px;
            text-align: center;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            background: #f8f9fa;
            color: #495057;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-group input:valid {
            border-color: #28a745;
        }

        .password-field {
            position: relative;
        }

        .toggle-password-btn {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s ease;
            z-index: 2;
        }

        .toggle-password-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 16px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            background: linear-gradient(135deg, #5a6fd8 0%, #6b4390 100%);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: alertSlideIn 0.5s ease;
        }

        @keyframes alertSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }

        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
        }

        .language-selector .btn {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #495057;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .language-selector .btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .login-wrapper {
                max-width: 100%;
            }

            .login-container {
                padding: 30px 25px;
                border-radius: 20px;
            }

            .login-title {
                font-size: 1.8rem;
            }

            .form-group input {
                padding: 14px 18px;
                font-size: 16px; /* Évite le zoom sur iOS */
            }

            .language-selector {
                position: static;
                margin-bottom: 20px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
                margin: 10px;
            }

            .login-title {
                font-size: 1.6rem;
            }

            .login-logo {
                width: 70px;
                height: 70px;
            }

            .login-logo i {
                font-size: 28px;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: rgba(30, 30, 30, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .form-group input {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #f8f9fa;
            }

            .form-group input:focus {
                background: rgba(255, 255, 255, 0.1);
            }

            .form-group label {
                color: #f8f9fa;
            }

            .login-subtitle {
                color: #adb5bd;
            }
        }

        /* Enhanced accessibility */
        .form-group:focus-within label {
            color: #667eea;
        }

        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Sélecteur de langue amélioré -->
        <div class="language-selector">
            <?= renderLanguageSelector('buttons') ?>
        </div>
        
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1 class="login-title"><?= t('login_title') ?></h1>
                <p class="login-subtitle"><?= t('app_name') ?></p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> 
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?= t('email') ?>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required 
                            autocomplete="username"
                            aria-describedby="email-error"
                            placeholder="exemple@domaine.com"
                        >
                    </div>
                    <div id="email-error" class="visually-hidden" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <?= t('password') ?>
                    </label>
                    <div class="password-field">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            aria-describedby="password-error password-toggle-desc"
                            placeholder="••••••••"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('password', this)" 
                            class="toggle-password-btn" 
                            aria-label="<?= t('show_hide_password') ?>"
                            title="<?= t('show_hide_password') ?>"
                        >
                            <i class="fas fa-eye" id="password-toggle-icon" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div id="password-error" class="visually-hidden" aria-live="polite"></div>
                    <div id="password-toggle-desc" class="visually-hidden">
                        Cliquez pour afficher ou masquer le mot de passe
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginButton">
                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                    <span class="btn-text"><?= t('login_button') ?></span>
                    <div class="loading-spinner" aria-hidden="true"></div>
                </button>
            </form>
        </div>
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