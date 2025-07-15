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
            opacity: 1;
            transform: translateY(0) scale(1);
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
            opacity: 1;
            transform: translateY(0);
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

        /* États de validation des champs */
        .form-group input.is-valid {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff8 0%, #d4edda 100%);
        }

        .form-group input.is-invalid {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #f8d7da 100%);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-group .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .form-group .field-error:not(.visually-hidden) {
            opacity: 1;
            transform: translateY(0);
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
            opacity: 1;
            transform: translateY(0);
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

            .form-group input.is-valid {
                border-color: #198754;
                background: rgba(25, 135, 84, 0.1);
            }

            .form-group input.is-invalid {
                border-color: #dc3545;
                background: rgba(220, 53, 69, 0.1);
            }
        }

        /* Animation de pulsation pour les éléments importants */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Styles pour les toasts de notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
            z-index: 1000;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.error {
            border-left-color: #dc3545;
        }

        .toast.warning {
            border-left-color: #ffc107;
        }

        /* Améliorations d'accessibilité avancées */
        .sr-only {
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

        /* Focus amélioré pour la navigation au clavier */
        *:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        /* Réduction de mouvement pour les utilisateurs préférant moins d'animations */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Affichage immédiat et permanent */
        .login-wrapper,
        .login-container,
        .login-header,
        .form-group,
        .login-btn {
            opacity: 1 !important;
            transform: none !important;
            animation: none !important;
            transition: none !important;
        }

        /* Exception pour les interactions utilisateur */
        .login-btn:hover,
        .form-group input:focus,
        .toggle-password-btn:hover {
            transition: all 0.3s ease !important;
        }

        /* S'assurer que le contenu est visible dès le chargement */
        body.loaded .login-wrapper,
        body.loaded .login-container,
        body.loaded .form-group {
            visibility: visible !important;
            opacity: 1 !important;
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
        // Configuration et variables globales
        const CONFIG = {
            showPasswordDuration: 3000,
            formValidationDelay: 300,
            animationDuration: 300
        };

        // Gestion améliorée de l'affichage du mot de passe
        function togglePassword(fieldId, button) {
            const input = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (!input || !icon) return;
            
            const isPassword = input.type === 'password';
            
            // Changer le type de champ
            input.type = isPassword ? 'text' : 'password';
            
            // Mettre à jour l'icône avec animation
            icon.style.transform = 'scale(0.8)';
            setTimeout(() => {
                icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
                icon.style.transform = 'scale(1)';
            }, 150);
            
            // Mettre à jour l'aria-label
            const newLabel = isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
            button.setAttribute('aria-label', newLabel);
            button.setAttribute('title', newLabel);
            
            // Focus sur le champ après l'action
            input.focus();
        }

        // Validation en temps réel des champs
        function setupFieldValidation() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (emailField) {
                emailField.addEventListener('input', debounce(validateEmail, CONFIG.formValidationDelay));
                emailField.addEventListener('blur', validateEmail);
            }
            
            if (passwordField) {
                passwordField.addEventListener('input', debounce(validatePassword, CONFIG.formValidationDelay));
                passwordField.addEventListener('blur', validatePassword);
            }
        }

        // Validation email
        function validateEmail() {
            const emailField = document.getElementById('email');
            const errorElement = document.getElementById('email-error');
            
            if (!emailField || !errorElement) return;
            
            const email = emailField.value.trim();
            const isValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            updateFieldState(emailField, errorElement, isValid, 
                isValid ? '' : 'Veuillez saisir une adresse email valide');
        }

        // Validation mot de passe
        function validatePassword() {
            const passwordField = document.getElementById('password');
            const errorElement = document.getElementById('password-error');
            
            if (!passwordField || !errorElement) return;
            
            const password = passwordField.value;
            const isValid = password.length >= 6;
            
            updateFieldState(passwordField, errorElement, isValid, 
                isValid ? '' : 'Le mot de passe doit contenir au moins 6 caractères');
        }

        // Mise à jour de l'état d'un champ
        function updateFieldState(field, errorElement, isValid, errorMessage) {
            // Mise à jour des classes CSS
            field.classList.toggle('is-valid', isValid && field.value.length > 0);
            field.classList.toggle('is-invalid', !isValid && field.value.length > 0);
            
            // Mise à jour du message d'erreur
            errorElement.textContent = errorMessage;
            errorElement.classList.toggle('visually-hidden', isValid || !field.value.length);
            
            // Mise à jour des attributs ARIA
            field.setAttribute('aria-invalid', !isValid);
        }

        // Fonction utilitaire debounce
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Gestion améliorée de la soumission du formulaire
        function setupFormSubmission() {
            const form = document.getElementById('loginForm');
            const submitButton = document.getElementById('loginButton');
            
            if (!form || !submitButton) return;
            
            form.addEventListener('submit', function(e) {
                // Validation finale avant soumission
                validateEmail();
                validatePassword();
                
                const hasErrors = form.querySelectorAll('.is-invalid').length > 0;
                
                if (hasErrors) {
                    e.preventDefault();
                    showFormError('Veuillez corriger les erreurs avant de continuer');
                    return;
                }
                
                // Animation de chargement
                showLoadingState(submitButton);
            });
        }

        // Affichage de l'état de chargement
        function showLoadingState(button) {
            const btnText = button.querySelector('.btn-text');
            const spinner = button.querySelector('.loading-spinner');
            const icon = button.querySelector('i');
            
            if (btnText && spinner && icon) {
                button.disabled = true;
                btnText.textContent = 'Connexion...';
                icon.style.display = 'none';
                spinner.style.display = 'block';
                
                // Timeout de sécurité pour réactiver le bouton
                setTimeout(() => {
                    resetLoadingState(button);
                }, 10000);
            }
        }

        // Réinitialisation de l'état de chargement
        function resetLoadingState(button) {
            const btnText = button.querySelector('.btn-text');
            const spinner = button.querySelector('.loading-spinner');
            const icon = button.querySelector('i');
            
            if (btnText && spinner && icon) {
                button.disabled = false;
                btnText.textContent = '<?= t('login_button') ?>';
                icon.style.display = 'block';
                spinner.style.display = 'none';
            }
        }

        // Affichage d'erreur de formulaire
        function showFormError(message) {
            // Supprimer les anciennes erreurs
            const existingError = document.querySelector('.alert-error.dynamic');
            if (existingError) {
                existingError.remove();
            }
            
            // Créer la nouvelle erreur
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error dynamic';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ${message}`;
            
            // Insérer avant le formulaire
            const form = document.getElementById('loginForm');
            if (form) {
                form.parentNode.insertBefore(errorDiv, form);
                
                // Scroll vers l'erreur
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Supprimer après 5 secondes
                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            }
        }

        // Gestion des raccourcis clavier
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Entrée sur le bouton toggle password
                const toggleButtons = document.querySelectorAll('.toggle-password-btn');
                toggleButtons.forEach(btn => {
                    btn.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            btn.click();
                        }
                    });
                });
                
                // Échap pour réinitialiser les erreurs
                if (e.key === 'Escape') {
                    const dynamicErrors = document.querySelectorAll('.alert.dynamic');
                    dynamicErrors.forEach(error => error.remove());
                }
            });
        }

        // Amélioration de l'accessibilité
        function setupAccessibility() {
            // Ajouter des régions ARIA
            const form = document.getElementById('loginForm');
            if (form) {
                form.setAttribute('role', 'form');
                form.setAttribute('aria-label', 'Formulaire de connexion');
            }
            
            // Améliorer les messages d'erreur
            const errorElements = document.querySelectorAll('[id$="-error"]');
            errorElements.forEach(element => {
                element.setAttribute('role', 'alert');
            });
        }

        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la classe loaded au body pour forcer l'affichage
            document.body.classList.add('loaded');
            
            setupFieldValidation();
            setupFormSubmission();
            setupKeyboardShortcuts();
            setupAccessibility();
            
            // Focus automatique sur le premier champ vide
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (emailField && !emailField.value) {
                emailField.focus();
            } else if (passwordField && !passwordField.value) {
                passwordField.focus();
            }
            
            // Affichage immédiat des champs (sans animation)
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group) => {
                group.style.opacity = '1';
                group.style.transform = 'translateY(0)';
                group.style.visibility = 'visible';
            });
        });

        // Gestion de la visibilité de la page (pour la sécurité)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Masquer automatiquement les mots de passe visibles quand on quitte la page
                const passwordFields = document.querySelectorAll('input[type="text"][name="password"]');
                passwordFields.forEach(field => {
                    const toggleBtn = field.parentNode.querySelector('.toggle-password-btn');
                    if (toggleBtn) {
                        togglePassword(field.id, toggleBtn);
                    }
                });
            }
        });
    </script>
</body>
</html>