<?php
require_once __DIR__ . '/notifications.php';

if (!isset($pageTitle)) {
    $pageTitle = t('app_name_short') . ' - ' . t('app_subtitle');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Design System Moderne -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/design-system.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
    /* Styles spécifiques du header */
    .header-modern {
        background: var(--gradient-primary);
        box-shadow: var(--shadow-lg);
        position: sticky;
        top: 0;
        z-index: var(--z-sticky);
        transition: var(--transition-all);
    }
    
    .header-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0;
        position: relative;
    }
    
    .logo-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .logo-icon {
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: var(--transition-all);
    }
    
    .logo-icon:hover {
        transform: scale(1.05) rotate(5deg);
        background: rgba(255, 255, 255, 0.2);
    }
    
    .logo-text h1 {
        color: white;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .logo-text p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.875rem;
        margin: 0;
        font-weight: 400;
    }
    
    .main-navigation {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .nav-menu {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .nav-item .nav-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        font-size: 0.875rem;
        transition: var(--transition-all);
        position: relative;
        backdrop-filter: blur(10px);
    }
    
    .nav-item .nav-link:hover,
    .nav-item .nav-link.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .nav-item .nav-link i {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .user-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .notifications-btn {
        position: relative;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        width: 42px;
        height: 42px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition-all);
        backdrop-filter: blur(10px);
    }
    
    .notifications-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }
    
    .notification-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: var(--danger-500);
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.125rem 0.375rem;
        border-radius: var(--radius-full);
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .user-menu {
        position: relative;
    }
    
    .user-avatar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-full);
        cursor: pointer;
        transition: var(--transition-all);
        backdrop-filter: blur(10px);
    }
    
    .user-avatar:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }
    
    .avatar-image {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .user-name {
        color: white;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1;
    }
    
    .user-role {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        line-height: 1;
        margin-top: 0.125rem;
    }
    
    .dropdown-arrow {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        transition: var(--transition-all);
    }
    
    .user-menu.show .dropdown-arrow {
        transform: rotate(180deg);
    }
    
    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        min-width: 220px;
        padding: 0.75rem 0;
        z-index: var(--z-dropdown);
        opacity: 0;
        transform: translateY(-10px);
        transition: var(--transition-all);
        pointer-events: none;
        margin-top: 0.5rem;
    }
    
    .user-menu.show .user-dropdown {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    
    .dropdown-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--gray-200);
        margin-bottom: 0.5rem;
    }
    
    .dropdown-user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .dropdown-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .dropdown-user-details .user-name {
        color: var(--gray-900);
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.125rem;
    }
    
    .dropdown-user-details .user-email {
        color: var(--gray-600);
        font-size: 0.8rem;
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: var(--gray-700);
        text-decoration: none;
        transition: var(--transition-fast);
    }
    
    .dropdown-item:hover {
        background: var(--gray-50);
        color: var(--gray-900);
    }
    
    .dropdown-item i {
        width: 16px;
        color: var(--gray-500);
    }
    
    .dropdown-divider {
        height: 1px;
        background: var(--gray-200);
        margin: 0.5rem 0;
    }
    
    .mobile-menu-btn {
        display: none;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        width: 42px;
        height: 42px;
        border-radius: var(--radius-md);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition-all);
    }
    
    .mobile-menu-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .main-navigation {
            display: none;
        }
        
        .mobile-menu-btn {
            display: flex;
        }
        
        .logo-text h1 {
            font-size: 1.25rem;
        }
        
        .logo-text p {
            display: none;
        }
        
        .user-info {
            display: none;
        }
    }
    
    @media (max-width: 480px) {
        .header-container {
            padding: 0.75rem 0;
        }
        
        .logo-icon {
            width: 38px;
            height: 38px;
            font-size: 1.25rem;
        }
        
        .notifications-btn,
        .mobile-menu-btn {
            width: 38px;
            height: 38px;
        }
    }
    </style>
</head>
<body>
    <!-- Header Moderne -->
    <header class="header-modern">
        <div class="container">
            <div class="header-container">
                <!-- Logo et Titre -->
                <div class="logo-section">
                    <div class="logo-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="logo-text">
                        <h1>MINSANTE</h1>
                        <p>Gestion des Dossiers</p>
                    </div>
                </div>
                
                <!-- Navigation Principale -->
                <nav class="main-navigation">
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>modules/dossiers/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dossiers') !== false ? 'active' : '' ?>">
                                <i class="fas fa-folder-open"></i>
                                <span>Dossiers</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>modules/echeances/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'echeances') !== false ? 'active' : '' ?>">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Échéances</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>modules/reporting/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'reporting') !== false ? 'active' : '' ?>">
                                <i class="fas fa-chart-bar"></i>
                                <span>Rapports</span>
                            </a>
                        </li>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>admin.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">
                                <i class="fas fa-cog"></i>
                                <span>Admin</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <!-- Section Utilisateur -->
                <div class="user-section">
                    <!-- Notifications -->
                    <div class="notifications-btn" onclick="toggleNotifications()" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php
                        $notificationCount = getNotificationCount($_SESSION['user']['id']);
                        if ($notificationCount > 0):
                        ?>
                        <span class="notification-badge"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Menu Utilisateur -->
                    <div class="user-menu" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <div class="avatar-image">
                                <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 2)) ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Utilisateur') ?></div>
                                <div class="user-role"><?= ucfirst($_SESSION['user']['role'] ?? 'user') ?></div>
                            </div>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </div>
                        
                        <!-- Dropdown Menu -->
                        <div class="user-dropdown">
                            <div class="dropdown-header">
                                <div class="dropdown-user-info">
                                    <div class="dropdown-avatar">
                                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 2)) ?>
                                    </div>
                                    <div class="dropdown-user-details">
                                        <div class="user-name"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Utilisateur') ?></div>
                                        <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="<?= BASE_URL ?>modules/users/profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>Mon Profil</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/users/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Paramètres</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/messagerie/inbox.php" class="dropdown-item">
                                <i class="fas fa-envelope"></i>
                                <span>Messages</span>
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <a href="<?= BASE_URL ?>modules/export/excel.php" class="dropdown-item">
                                <i class="fas fa-download"></i>
                                <span>Exports</span>
                            </a>
                            <a href="<?= BASE_URL ?>docs/" class="dropdown-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Aide</span>
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <a href="<?= BASE_URL ?>logout.php" class="dropdown-item" style="color: var(--danger-600);">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Déconnexion</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Bouton Menu Mobile -->
                    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Scripts du Header -->
    <script>
    // Toggle User Menu
    function toggleUserMenu() {
        const userMenu = document.querySelector('.user-menu');
        userMenu.classList.toggle('show');
        
        // Fermer si clic à l'extérieur
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('show');
            }
        });
    }
    
    // Toggle Notifications
    function toggleNotifications() {
        // Implémenter l'ouverture du panneau de notifications
        console.log('Toggle notifications');
    }
    
    // Toggle Mobile Menu
    function toggleMobileMenu() {
        // Implémenter le menu mobile
        console.log('Toggle mobile menu');
    }
    
    // Smooth scroll pour les ancres
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Animation d'apparition du header
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.querySelector('.header-modern');
        header.style.transform = 'translateY(-100%)';
        header.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        
        setTimeout(() => {
            header.style.transform = 'translateY(0)';
        }, 100);
    });
    </script>
