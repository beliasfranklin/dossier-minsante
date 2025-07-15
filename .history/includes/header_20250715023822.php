<?php
require_once __DIR__ . '/notifications.php';
/**
 * En-tête commune de l'application
 */
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mobile-optimized.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/mobile-optimizer.js?v=<?= time() ?>"></script>
    <style>
    /* Variables CSS modernes pour le header */
    :root {
        --header-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --header-secondary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --header-accent: rgba(255, 255, 255, 0.15);
        --header-hover: rgba(255, 255, 255, 0.25);
        --header-text: #ffffff;
        --header-text-secondary: rgba(255, 255, 255, 0.9);
        --header-shadow: 0 4px 20px rgba(102, 126, 234, 0.15);
        --header-shadow-hover: 0 8px 32px rgba(102, 126, 234, 0.25);
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-fast: all 0.15s ease;
        --radius-modern: 12px;
        --radius-pill: 25px;
    }

    /* Navigation principale modernisée */
    .main-nav ul {
        display: flex;
        flex-direction: row;
        gap: 8px;
        list-style: none;
        align-items: center;
        margin: 0;
        padding: 0;
    }
    
    .main-nav ul li a {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: var(--header-text);
        padding: 10px 16px;
        border-radius: var(--radius-modern);
        transition: var(--transition-smooth);
        text-decoration: none;
        font-size: 0.9em;
        position: relative;
        text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        background: transparent;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    
    .main-nav ul li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--header-accent);
        border-radius: var(--radius-modern);
        opacity: 0;
        transition: var(--transition-smooth);
        z-index: -1;
    }
    
    .main-nav ul li a:hover::before, 
    .main-nav ul li a.active::before {
        opacity: 1;
    }
    
    .main-nav ul li a:hover, 
    .main-nav ul li a.active {
        color: var(--header-text);
        transform: translateY(-2px);
        text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        box-shadow: 0 4px 15px rgba(255,255,255,0.1);
    }
    
    .main-nav ul li a i {
        color: var(--header-text);
        font-size: 1em;
        transition: var(--transition-smooth);
        text-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .main-nav ul li a:hover i {
        transform: scale(1.1);
    }
    
    .main-nav ul li .logout-link {
        color: var(--header-text);
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .main-nav ul li .logout-link::before {
        background: rgba(255,255,255,0.1);
    }
    
    .main-nav ul li .logout-link:hover {
        background: linear-gradient(135deg, #c0392b, #a93226);
        color: var(--header-text);
        box-shadow: 0 4px 20px rgba(231,76,60,0.3);
    }
    
    /* Menus déroulants modernisés */
    .dropdown {
        position: relative;
    }
    
    .dropdown-toggle {
        cursor: pointer;
        position: relative;
    }
    
    .dropdown-toggle .fa-chevron-down {
        transition: var(--transition-smooth);
        margin-left: 4px;
        font-size: 0.8em;
        opacity: 0.8;
    }
    
    .dropdown:hover .dropdown-toggle .fa-chevron-down {
        transform: rotate(180deg);
        opacity: 1;
    }
    
    .dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%);
        background: white;
        border-radius: var(--radius-modern);
        box-shadow: var(--header-shadow-hover);
        min-width: 240px;
        padding: 8px 0;
        z-index: 1000;
        opacity: 0;
        transform: translateX(-50%) translateY(-10px);
        transition: var(--transition-smooth);
        border: 1px solid rgba(102, 126, 234, 0.1);
        backdrop-filter: blur(10px);
    }
    
    .dropdown:hover .dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    
    .dropdown-menu::before {
        content: '';
        position: absolute;
        top: -6px;
        left: 50%;
        transform: translateX(-50%);
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-bottom: 6px solid white;
        filter: drop-shadow(0 -2px 4px rgba(0,0,0,0.1));
    }
    
    .dropdown-menu li {
        list-style: none;
        margin: 0;
    }
    
    .dropdown-menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #475569;
        text-decoration: none;
        transition: var(--transition-smooth);
        font-size: 0.9em;
        border-radius: 0;
        font-weight: 500;
        margin: 2px 8px;
        border-radius: 8px;
    }
    
    /* Couleurs thématiques modernisées pour les menus */
    .dropdown-menu.dossiers-menu li a {
        color: #059669;
    }
    .dropdown-menu.dossiers-menu li a:hover {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        color: #047857;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(5,150,105,0.1);
    }
    .dropdown-menu.dossiers-menu li a i {
        color: #10b981;
        width: 18px;
        text-align: center;
    }
    
    .dropdown-menu.gestion-menu li a {
        color: #7c3aed;
    }
    .dropdown-menu.gestion-menu li a:hover {
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #6d28d9;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(124,58,237,0.1);
    }
    .dropdown-menu.gestion-menu li a i {
        color: #8b5cf6;
        width: 18px;
        text-align: center;
    }
    
    .dropdown-menu.rapports-menu li a {
        color: #7c2d12;
    }
    .dropdown-menu.rapports-menu li a:hover {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        color: #9a3412;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(124,45,18,0.1);
    }
    .dropdown-menu.rapports-menu li a i {
        color: #ea580c;
        width: 18px;
        text-align: center;
    }
    
    .dropdown-menu.communication-menu li a {
        color: #0369a1;
    }
    .dropdown-menu.communication-menu li a:hover {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #0284c7;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(3,105,161,0.1);
    }
    .dropdown-menu.communication-menu li a i {
        color: #0ea5e9;
        width: 18px;
        text-align: center;
    }
    
    .dropdown-menu.langue-menu li a {
        color: #1e40af;
    }
    .dropdown-menu.langue-menu li a:hover {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #1d4ed8;
        transform: translateX(4px);
        box-shadow: 0 2px 8px rgba(30,64,175,0.1);
    }
    .dropdown-menu.langue-menu li a i {
        color: #3b82f6;
        width: 18px;
        text-align: center;
    }

    /* Système de notifications modernisé */
    .notification-menu {
        position: relative;
    }
    
    #notificationBell {
        position: relative;
        padding: 10px;
        border-radius: 50%;
        transition: var(--transition-smooth);
        background: var(--header-accent);
        border: 1px solid rgba(255,255,255,0.2);
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #notificationBell:hover {
        background: var(--header-hover);
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(255,255,255,0.1);
    }
    
    #notificationBell i {
        font-size: 1.1em;
        color: var(--header-text);
    }
    
    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 0.7em;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        box-shadow: 0 2px 12px rgba(231,76,60,0.4);
        animation: pulse 2s infinite;
        border: 2px solid white;
    }
    
    @keyframes pulse {
        0% { 
            box-shadow: 0 2px 12px rgba(231,76,60,0.4); 
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 2px 12px rgba(231,76,60,0.8), 0 0 0 4px rgba(231,76,60,0.2); 
            transform: scale(1.05);
        }
        100% { 
            box-shadow: 0 2px 12px rgba(231,76,60,0.4); 
            transform: scale(1);
        }
    }
    
    .notification-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        background: white;
        border-radius: var(--radius-modern);
        box-shadow: var(--header-shadow-hover);
        min-width: 380px;
        max-width: 420px;
        z-index: 1000;
        opacity: 0;
        transform: translateY(-10px);
        transition: var(--transition-smooth);
        border: 1px solid rgba(102, 126, 234, 0.1);
        backdrop-filter: blur(20px);
    }
    
    .notification-dropdown.show {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    
    .notification-dropdown::before {
        content: '';
        position: absolute;
        top: -6px;
        right: 20px;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-bottom: 6px solid white;
        filter: drop-shadow(0 -2px 4px rgba(0,0,0,0.1));
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        border-radius: var(--radius-modern) var(--radius-modern) 0 0;
    }
    
    .notification-header h4 {
        margin: 0;
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .notification-header h4 i {
        color: #667eea;
        font-size: 1em;
    }
    
    .notification-header a {
        color: #667eea;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
        transition: var(--transition-fast);
        padding: 6px 12px;
        border-radius: 6px;
        background: rgba(102, 126, 234, 0.1);
    }
    
    .notification-header a:hover {
        color: #4f46e5;
        background: rgba(102, 126, 234, 0.2);
        transform: translateY(-1px);
    }
    
    .notification-list {
        max-height: 420px;
        overflow-y: auto;
        padding: 0;
    }
    
    .notification-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .notification-list::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 3px;
    }
    
    .notification-list::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #cbd5e1, #94a3b8);
        border-radius: 3px;
    }
    
    .notification-list::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #94a3b8, #64748b);
    }
    
    .notification-item {
        display: block;
        padding: 16px 24px;
        border-bottom: 1px solid #f8fafc;
        text-decoration: none;
        transition: var(--transition-smooth);
        position: relative;
        background: white;
    }
    
    .notification-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        opacity: 0;
        transition: var(--transition-smooth);
    }
    
    .notification-item:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        transform: translateX(4px);
    }
    
    .notification-item:hover::before {
        opacity: 1;
    }
    
    .notification-item:last-child {
        border-bottom: none;
        border-radius: 0 0 var(--radius-modern) var(--radius-modern);
    }
    
    .notification-item .notification-title {
        display: block;
        color: #1e293b;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: 0.95em;
        line-height: 1.4;
    }
    
    .notification-item .notification-message {
        margin: 0 0 8px 0;
        color: #64748b;
        font-size: 0.85em;
        line-height: 1.5;
    }
    
    .notification-item .notification-time {
        color: #94a3b8;
        font-size: 0.8em;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .notification-item .notification-time i {
        font-size: 0.9em;
    }
    
    .notification-empty {
        text-align: center;
        padding: 60px 24px;
        color: #64748b;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 0 0 var(--radius-modern) var(--radius-modern);
    }
    
    .notification-empty i {
        font-size: 2.5em;
        margin-bottom: 16px;
        color: #cbd5e1;
        animation: float 3s ease-in-out infinite;
    }
    
    .notification-empty p {
        margin: 0;
        font-size: 0.9em;
        line-height: 1.5;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-8px); }
    }

    /* Logo modernisé */
    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9em;
        font-weight: 600;
        color: var(--header-text);
        letter-spacing: 0.3px;
        margin-right: 20px;
        padding: 8px 12px;
        text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 320px;
        background: var(--header-accent);
        border-radius: var(--radius-modern);
        border: 1px solid rgba(255,255,255,0.1);
        transition: var(--transition-smooth);
    }
    
    .logo:hover {
        background: var(--header-hover);
        transform: scale(1.02);
        box-shadow: 0 4px 20px rgba(255,255,255,0.1);
    }
    
    .logo-icon {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.2);
        transition: var(--transition-smooth);
    }
    
    .logo:hover .logo-icon {
        background: rgba(255,255,255,0.25);
        transform: scale(1.05);
    }
    
    .logo-icon i {
        font-size: 1.1em;
        color: var(--header-text);
        text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        transition: var(--transition-smooth);
    }
    
    .logo:hover .logo-icon i {
        transform: rotate(5deg);
    }
    
    .logo-icon .fa-notes-medical {
        color: #e8f8f5;
    }
    
    .logo-icon .fa-folder-open {
        color: #fdf2e9;
    }
    
    .logo-text {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
    }
    
    .logo-title {
        font-size: 1em;
        font-weight: 700;
        color: var(--header-text);
        margin: 0;
        text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    
    .logo-subtitle {
        font-size: 0.75em;
        color: var(--header-text-secondary);
        font-weight: 500;
        margin: 0;
        opacity: 0.9;
    }
    
    /* Header principal modernisé */
    .app-header .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 24px;
        min-height: 48px;
    }
    
    header.app-header {
        background: var(--header-primary);
        color: var(--header-text);
        box-shadow: var(--header-shadow);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        backdrop-filter: blur(10px);
    }

    /* Menu burger mobile */
    .burger-menu {
        display: none;
        flex-direction: column;
        justify-content: center;
        width: 36px;
        height: 36px;
        cursor: pointer;
        margin-left: 8px;
        padding: 4px;
        border-radius: 6px;
        transition: background 0.3s ease;
    }
    .burger-menu:hover {
        background: rgba(255,255,255,0.1);
    }
    .burger-bar {
        height: 3px;
        width: 100%;
        background: #fff;
        border-radius: 2px;
        margin: 3px 0;
        transition: 0.3s;
    }

    /* Responsive */
    @media (max-width: 800px) {
        .logo-text {
            display: none; /* Cache le texte sur mobile */
        }
        
        .logo {
            max-width: 60px; /* Plus compact sur mobile */
        }
        
        .logo-icon {
            padding: 4px 6px;
        }
        
        .main-nav ul {
            display: none;
            position: absolute;
            top: 40px;
            right: 10px;
            background: #2980b9;
            flex-direction: column;
            gap: 0;
            min-width: 200px;
            box-shadow: 0 4px 16px rgba(44,62,80,0.2);
            border-radius: 8px;
            z-index: 2000;
            padding: 4px 0;
        }
        .main-nav ul.show {
            display: flex;
        }
        .main-nav ul li a {
            padding: 10px 16px;
            font-size: 0.95em;
            border-radius: 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin: 0;
        }
        .main-nav ul li:last-child a {
            border-bottom: none;
        }
        .burger-menu {
            display: flex;
        }
        .dropdown-menu {
            position: static;
            box-shadow: none;
            margin: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin-top: 8px;
        }
        .dropdown-menu::before {
            display: none;
        }
        .dropdown-menu li a {
            color: #fff;
            padding: 10px 20px;
        }
        .dropdown-menu li a:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .notification-dropdown {
            position: fixed;
            top: 42px;
            right: 10px;
            left: 10px;
            margin: 0;
            max-width: none;
        }
    }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-notes-medical"></i>
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="logo-text">
                    <div class="logo-title"><?= t('app_name_short') ?></div>
                    <div class="logo-subtitle"><?= t('app_subtitle') ?></div>
                </div>
            </div>
            <div class="burger-menu" id="burgerMenu" aria-label="Ouvrir le menu" tabindex="0">
                <div class="burger-bar"></div>
                <div class="burger-bar"></div>
                <div class="burger-bar"></div>
            </div>
            <nav class="main-nav">
                <ul id="mainNavList">
                    <li><a href="<?= BASE_URL ?>dashboard.php" class="nav-link"><i class="fa-solid fa-gauge"></i> <?= t('dashboard') ?></a></li>
                    
                    <!-- Menu déroulant Dossiers -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-folder-open"></i> <?= t('dossiers') ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu dossiers-menu">
                            <li><a href="<?= BASE_URL ?>modules/dossiers/list.php"><i class="fas fa-list"></i> <?= t('dossiers_list') ?></a></li>
                            <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                            <li><a href="<?= BASE_URL ?>modules/dossiers/create.php"><i class="fas fa-plus"></i> <?= t('dossiers_new') ?></a></li>
                            <?php endif; ?>
                            <li><a href="<?= BASE_URL ?>modules/archivage/list.php"><i class="fas fa-archive"></i> <?= t('dossiers_archives') ?></a></li>
                        </ul>
                    </li>

                    <!-- Menu déroulant Gestion -->
                    <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-cogs"></i> Gestion 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu gestion-menu">
                            <li><a href="<?= BASE_URL ?>modules/echeances/dashboard.php"><i class="fas fa-clock"></i> Dashboard Échéances</a></li>
                            <li><a href="<?= BASE_URL ?>modules/echeances/config.php"><i class="fas fa-calendar-alt"></i> Config. Échéances</a></li>
                            <li><a href="<?= BASE_URL ?>modules/categories/index.php"><i class="fas fa-tags"></i> Catégories</a></li>
                            <?php if (hasPermission(ROLE_ADMIN)): ?>
                            <li><a href="<?= BASE_URL ?>modules/statuts/transitions.php"><i class="fas fa-exchange-alt"></i> Transitions Statuts</a></li>
                            <li><a href="<?= BASE_URL ?>modules/logs/index.php"><i class="fas fa-history"></i> Journal d'Audit</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Menu déroulant Rapports -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-chart-column"></i> <?= t('rapports') ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu rapports-menu">
                            <li><a href="<?= BASE_URL ?>modules/reporting/stats.php"><i class="fas fa-chart-pie"></i> <?= t('rapports_stats') ?></a></li>
                            <li><a href="<?= BASE_URL ?>modules/reporting/advanced.php"><i class="fas fa-chart-line"></i> <?= t('rapports_advanced') ?></a></li>
                            <li><a href="<?= BASE_URL ?>modules/export/export.php"><i class="fas fa-file-export"></i> <?= t('rapports_export') ?></a></li>
                        </ul>
                    </li>

                    <!-- Menu déroulant Communication -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-comments"></i> <?= t('communication') ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu communication-menu">
                            <li><a href="<?= BASE_URL ?>modules/messagerie/list.php"><i class="fas fa-envelope"></i> <?= t('communication_messagerie') ?></a></li>
                            <li><a href="<?= BASE_URL ?>modules/notifications/list.php"><i class="fas fa-bell"></i> <?= t('communication_notifications') ?></a></li>
                        </ul>
                    </li>

                    <li><a href="<?= BASE_URL ?>modules/users/profile.php" class="nav-link"><i class="fa-solid fa-user"></i> <?= t('profile') ?></a></li>
                    
                    <!-- Menu notifications amélioré -->
                    <li class="notification-menu">
                        <a href="#" id="notificationBell">
                            <i class="fa-solid fa-bell"></i>
                            <?php 
                            $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
                            $unreadCount = count($unreadNotifications);
                            if ($unreadCount > 0) : 
                            ?>
                                <span class="notification-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4><i class="fas fa-bell"></i> <?= t('notifications') ?></h4>
                                <a href="<?= BASE_URL ?>modules/notifications/list.php"><?= t('notifications_see_all') ?></a>
                            </div>
                            <div class="notification-list">
                                <?php if (!empty($unreadNotifications)): ?>
                                    <?php foreach (array_slice($unreadNotifications, 0, 5) as $notif): ?>
                                        <?php if (!empty($notif['title']) && trim($notif['title']) !== ''): ?>
                                        <a href="<?= $notif['related_module'] ? BASE_URL.'modules/'.$notif['related_module'].'/view.php?id='.$notif['related_id'] : '#' ?>" 
                                           class="notification-item" 
                                           data-id="<?= $notif['id'] ?>">
                                            <strong class="notification-title"><?= htmlspecialchars($notif['title']) ?></strong>
                                            <p class="notification-message"><?= htmlspecialchars(substr($notif['message'], 0, 80)) ?><?= strlen($notif['message']) > 80 ? '...' : '' ?></p>
                                            <small class="notification-time"><?= time_ago($notif['created_at']) ?></small>
                                        </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (count($unreadNotifications) > 5): ?>
                                        <div style="text-align: center; padding: 12px; border-top: 1px solid #f0f4f8; color: #7f8c8d; font-size: 0.9em;">
                                            <?= tf('notifications_and_others', ['count' => count($unreadNotifications) - 5]) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="notification-empty">
                                        <i class="fas fa-bell-slash"></i>
                                        <p><?= t('notifications_empty') ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    
                    <!-- Sélecteur de langue -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-globe"></i> <?= getCurrentLanguage() == 'fr' ? '🇫🇷' : '🇬🇧' ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu langue-menu">
                            <li><a href="<?= langUrl($_SERVER['REQUEST_URI'], 'fr') ?>"><i class="fas fa-flag"></i> 🇫🇷 <?= t('french') ?></a></li>
                            <li><a href="<?= langUrl($_SERVER['REQUEST_URI'], 'en') ?>"><i class="fas fa-flag"></i> 🇬🇧 <?= t('english') ?></a></li>
                        </ul>
                    </li>
                    
                    <?php if (hasPermission(ROLE_ADMIN)): ?>
                    <li><a href="<?= BASE_URL ?>admin.php" class="nav-link"><i class="fa-solid fa-cog"></i> <?= t('administration') ?></a></li>
                    <?php endif; ?>
                    
                    <li><a href="<?= BASE_URL ?>logout.php" class="logout-link nav-link" onclick="return confirm('<?= t('confirm_logout') ?>');"><i class="fa-solid fa-right-from-bracket"></i> <?= t('logout') ?></a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
    <script>
    // Menu burger mobile
    const burger = document.getElementById('burgerMenu');
    const navList = document.getElementById('mainNavList');
    
    burger.addEventListener('click', () => {
        navList.classList.toggle('show');
    });
    
    burger.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            navList.classList.toggle('show');
        }
    });

    // Fermeture automatique des menus déroulants
    document.addEventListener('click', function(e) {
        // Fermer le menu burger si on clique ailleurs
        if (!burger.contains(e.target) && !navList.contains(e.target)) {
            navList.classList.remove('show');
        }
        
        // Fermer les dropdowns si on clique ailleurs
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        }
        
        if (!e.target.closest('.notification-menu')) {
            notificationDropdown.classList.remove('show');
        }
    });

    // Gestion des menus déroulants sur mobile
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 800) {
                e.preventDefault();
                const dropdown = this.nextElementSibling;
                const isVisible = dropdown.style.display === 'block';
                
                // Fermer tous les autres dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
                
                // Toggle le dropdown actuel
                dropdown.style.display = isVisible ? 'none' : 'block';
            }
        });
    });

    // Gestion des notifications améliorées
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        notificationDropdown.classList.toggle('show');
        
        // Animation du badge
        const badge = this.querySelector('.notification-badge');
        if (badge) {
            badge.style.animation = 'none';
            setTimeout(() => {
                badge.style.animation = 'pulse 2s infinite';
            }, 100);
        }
    });

    // Marquer les notifications comme lues quand on clique dessus
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const notificationId = this.getAttribute('data-id');
            if (notificationId) {
                // Marquer comme lu via AJAX
                fetch('<?= BASE_URL ?>api/notifications/mark_as_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notification_id: notificationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Réduire le compteur de badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            let count = parseInt(badge.textContent);
                            count--;
                            if (count <= 0) {
                                badge.remove();
                            } else {
                                badge.textContent = count > 99 ? '99+' : count;
                            }
                        }
                        
                        // Marquer visuellement comme lu
                        this.style.opacity = '0.7';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du marquage de la notification:', error);
                });
            }
        });
    });

    // Smooth scroll pour les liens internes
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

    // Mise à jour automatique du compteur de notifications (toutes les 30 secondes)
    setInterval(() => {
        fetch('<?= BASE_URL ?>api/notifications/unread_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                const bell = document.getElementById('notificationBell');
                
                if (data.count > 0) {
                    if (!badge) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = data.count > 99 ? '99+' : data.count;
                        bell.appendChild(newBadge);
                    } else {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                    }
                } else if (badge) {
                    badge.remove();
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour du compteur:', error);
            });
    }, 30000);
    </script>
    <script src="<?= BASE_URL ?>assets/js/access_control.js?v=1"></script>