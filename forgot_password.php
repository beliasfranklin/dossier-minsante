<?php
require_once 'includes/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        executeQuery("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?", [$token, $expires, $user['id']]);
        $resetLink = BASE_URL . "reset_password.php?token=$token";
        
        // Fonction d'envoi d'email simple
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: noreply@minsante.local',
            'Reply-To: noreply@minsante.local',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $emailBody = "<p>Bonjour,<br>Pour réinitialiser votre mot de passe, cliquez sur ce lien : <a href='$resetLink'>$resetLink</a><br>Ce lien expire dans 1 heure.</p>";
        
        if (mail($email, t('reset_password_title'), $emailBody, implode("\r\n", $headers))) {
            $msg = t('reset_email_sent');
        } else {
            $msg = 'Erreur lors de l\'envoi de l\'email.';
        }
    } else {
        $msg = t('user_not_found');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('forgot_password_title') ?> - MINSANTE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #3b82f6;
            --primary-purple: #8b5cf6;
            --accent-cyan: #06b6d4;
            --success-green: #10b981;
            --error-red: #ef4444;
            --warning-orange: #f59e0b;
            
            --text-dark: #1f2937;
            --text-gray: #6b7280;
            --text-light: #9ca3af;
            
            --bg-white: #ffffff;
            --bg-gray-50: #f9fafb;
            --bg-gray-100: #f3f4f6;
            --border-gray: #e5e7eb;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.3s ease-in-out;
            --transition-slow: 0.5s ease-in-out;
        }

        [data-theme="dark"] {
            --text-dark: #f9fafb;
            --text-gray: #d1d5db;
            --text-light: #9ca3af;
            
            --bg-white: #111827;
            --bg-gray-50: #1f2937;
            --bg-gray-100: #374151;
            --border-gray: #374151;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, 
                #667eea 0%, 
                #764ba2 20%, 
                #f093fb 40%, 
                #f5576c 60%, 
                #4facfe 80%, 
                #00f2fe 100%);
            background-size: 400% 400%;
            animation: gradientFlow 20s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Effets de background géométriques */
        .geometric-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }

        .shape-1 {
            top: 15%;
            left: 15%;
            width: 120px;
            height: 120px;
            background: var(--primary-blue);
            border-radius: 50%;
            animation-delay: -3s;
        }

        .shape-2 {
            top: 65%;
            right: 20%;
            width: 90px;
            height: 90px;
            background: var(--accent-cyan);
            transform: rotate(45deg);
            animation-delay: -8s;
        }

        .shape-3 {
            bottom: 25%;
            left: 25%;
            width: 70px;
            height: 70px;
            background: var(--primary-purple);
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            animation-delay: -12s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            25% { transform: translateY(-25px) rotate(90deg) scale(1.1); }
            50% { transform: translateY(0px) rotate(180deg) scale(0.9); }
            75% { transform: translateY(25px) rotate(270deg) scale(1.1); }
        }

        /* Conteneur principal */
        .forgot-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-2xl);
            padding: 2rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 
                var(--shadow-2xl),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 10;
            animation: slideInUp 0.8s cubic-bezier(0.23, 1, 0.320, 1);
        }

        [data-theme="dark"] .forgot-container {
            background: rgba(17, 24, 39, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(60px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Theme toggle */
        .theme-toggle {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-normal);
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .theme-toggle i {
            color: white;
            font-size: 1.2rem;
        }

        /* Header avec icône */
        .forgot-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .icon-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--warning-orange), var(--error-red));
            border-radius: var(--radius-2xl);
            margin-bottom: 1.25rem;
            box-shadow: 
                0 20px 40px rgba(245, 87, 108, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            animation: iconPulse 3s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .icon-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent, 
                rgba(255, 255, 255, 0.1), 
                transparent);
            animation: shimmer 4s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .icon-container i {
            font-size: 2rem;
            color: white;
            z-index: 2;
            position: relative;
            animation: iconBounce 2s ease-in-out infinite;
        }

        @keyframes iconBounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-8px); }
            60% { transform: translateY(-4px); }
        }

        .forgot-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, var(--warning-orange), var(--error-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .forgot-subtitle {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-weight: 400;
            line-height: 1.5;
            max-width: 360px;
            margin: 0 auto;
        }

        /* Messages et alertes */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
            border: 1px solid;
            font-size: 0.9rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            color: var(--success-green);
            border-color: #86efac;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            color: var(--error-red);
            border-color: #fca5a5;
        }

        .alert-info {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: var(--primary-blue);
            border-color: #93c5fd;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Formulaire */
        .forgot-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.825rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--warning-orange);
        }

        .input-wrapper {
            position: relative;
            transition: transform var(--transition-normal);
        }

        .input-wrapper:hover {
            transform: translateY(-1px);
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-gray);
            border-radius: var(--radius-xl);
            font-size: 0.95rem;
            font-weight: 400;
            background: var(--bg-gray-50);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            color: var(--text-dark);
        }

        .form-input::placeholder {
            color: var(--text-light);
            transition: opacity var(--transition-normal);
        }

        .form-input:focus {
            border-color: var(--warning-orange);
            background: var(--bg-white);
            box-shadow: 
                0 0 0 4px rgba(245, 158, 11, 0.1),
                0 4px 12px rgba(245, 158, 11, 0.15);
            transform: translateY(-2px);
        }

        .form-input:focus::placeholder {
            opacity: 0.5;
        }

        /* Bouton d'envoi */
        .btn-submit {
            width: 100%;
            padding: 1rem 1.75rem;
            background: linear-gradient(135deg, var(--warning-orange), var(--error-red));
            color: white;
            border: none;
            border-radius: var(--radius-xl);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 
                0 10px 25px rgba(245, 87, 108, 0.3),
                0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.2), 
                transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 35px rgba(245, 87, 108, 0.4),
                0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Lien de retour */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--text-gray);
            text-decoration: none;
            font-weight: 500;
            margin-top: 1.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
            border: 1px solid var(--border-gray);
            background: var(--bg-gray-50);
            font-size: 0.9rem;
        }

        .back-link:hover {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .back-link i {
            transition: transform var(--transition-fast);
        }

        .back-link:hover i {
            transform: translateX(-2px);
        }

        /* Loading animation */
        .loading {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 640px) {
            .forgot-container {
                padding: 1.5rem 1.25rem;
                margin: 1rem;
                border-radius: var(--radius-xl);
                max-width: 360px;
            }
            
            .forgot-title {
                font-size: 1.5rem;
            }
            
            .icon-container {
                width: 70px;
                height: 70px;
            }
            
            .icon-container i {
                font-size: 1.75rem;
            }
            
            .form-input {
                padding: 0.875rem 1rem;
            }
            
            .theme-toggle {
                position: fixed;
                top: 1rem;
                right: 1rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0.75rem;
            }
            
            .forgot-container {
                padding: 1.25rem 1rem;
                max-width: 320px;
            }
            
            .forgot-title {
                font-size: 1.35rem;
            }
        }

        /* Améliorations d'accessibilité */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Styles de focus pour l'accessibilité */
        .btn-submit:focus,
        .theme-toggle:focus,
        .form-input:focus,
        .back-link:focus {
            outline: 2px solid var(--warning-orange);
            outline-offset: 2px;
        }

        /* Navigation au clavier */
        .keyboard-navigation *:focus {
            outline: 2px solid var(--warning-orange) !important;
            outline-offset: 2px !important;
        }
    </style>
</head>
<body>
    <!-- Background géométrique -->
    <div class="geometric-bg">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Theme toggle -->
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Changer le thème">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="forgot-container">
        <!-- Header -->
        <div class="forgot-header">
            <div class="icon-container">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="forgot-title"><?= t('forgot_password_title') ?></h1>
            <p class="forgot-subtitle">
                <?= t('forgot_password_desc') ?><br>
                <strong>Nous vous enverrons un lien de réinitialisation sécurisé</strong>
            </p>
        </div>

        <!-- Messages -->
        <?php if (!empty($msg)): ?>
            <?php 
            $alertType = 'info';
            if (strpos($msg, 'envoyé') !== false || strpos($msg, 'sent') !== false) {
                $alertType = 'success';
            } elseif (strpos($msg, 'Erreur') !== false || strpos($msg, 'not found') !== false) {
                $alertType = 'error';
            }
            ?>
            <div class="alert alert-<?= $alertType ?>">
                <i class="fas fa-<?= $alertType === 'success' ? 'check-circle' : ($alertType === 'error' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" class="forgot-form" id="forgotForm">
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i>
                    Adresse Email Professionnelle
                </label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="form-input"
                        placeholder="Entrez votre adresse email MINSANTE"
                        required 
                        autocomplete="email"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    >
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane" id="submitIcon"></i>
                <span id="submitText"><?= t('send_reset_link') ?></span>
            </button>
        </form>

        <!-- Lien de retour -->
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <?= t('back_to_login') ?>
        </a>
    </div>

    <script>
        // Theme management
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }

        // Load saved theme
        window.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const themeIcon = document.getElementById('themeIcon');
            
            if (savedTheme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
            }
        });

        // Form submission with loading state
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const icon = document.getElementById('submitIcon');
            const text = document.getElementById('submitText');
            
            btn.disabled = true;
            icon.className = 'fas fa-spinner loading';
            text.textContent = 'Envoi en cours...';
            
            // Add timeout fallback
            setTimeout(() => {
                if (btn.disabled) {
                    btn.disabled = false;
                    icon.className = 'fas fa-paper-plane';
                    text.textContent = '<?= t('send_reset_link') ?>';
                }
            }, 10000);
        });

        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('email');
            
            // Animate input on load
            input.style.opacity = '0';
            input.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                input.style.transition = 'all 0.6s cubic-bezier(0.23, 1, 0.320, 1)';
                input.style.opacity = '1';
                input.style.transform = 'translateY(0)';
            }, 200);

            // Enhanced input interactions
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });

            // Auto-focus input
            input.focus();
        });

        // Keyboard navigation improvements
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Email validation enhancement
        document.getElementById('email').addEventListener('input', function(e) {
            const email = e.target.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if (email.length > 0) {
                if (isValid) {
                    e.target.style.borderColor = 'var(--success-green)';
                } else {
                    e.target.style.borderColor = 'var(--error-red)';
                }
            } else {
                e.target.style.borderColor = 'var(--border-gray)';
            }
        });
    </script>
</body>
</html>
