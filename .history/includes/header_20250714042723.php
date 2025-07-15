<?php
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/../lang/language.php';
require_once __DIR__ . '/preferences.php';

/**
 * En-tête commune de l'application avec support des préférences
 */
if (!isset($pageTitle)) {
    $pageTitle = t('app_name_short') . ' - ' . t('app_subtitle');
}

// Initialiser le gestionnaire de préférences
if (!isset($preferencesManager) && isset($pdo)) {
    $preferencesManager = new PreferencesManager($pdo);
}

// Obtenir les informations de thème
$currentTheme = $preferencesManager ? $preferencesManager->getTheme() : 'light';
$themeStyles = $preferencesManager ? $preferencesManager->getThemeStyles() : '';
$currentLanguage = $preferencesManager ? $preferencesManager->getLanguage() : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= $currentLanguage ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- CSS de base -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mobile-optimized.css?v=<?= time() ?>">
    
    <!-- CSS dynamique basé sur les préférences -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dynamic-theme.css.php?v=<?= time() ?>" id="dynamic-theme">
    
    <!-- Animations et transitions pour les préférences -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/preferences-animations.css?v=<?= time() ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Scripts mobiles -->
    <script src="<?= BASE_URL ?>assets/js/mobile-optimizer.js?v=<?= time() ?>"></script>
    
    <!-- Styles inline pour le thème -->
    <style>
    :root {
        <?= $themeStyles ?>
    }
    
    /* Indicateur de thème */
    .theme-indicator {
        position: fixed;
        top: 10px;
        right: 10px;
        background: var(--accent-color, #16a085);
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        z-index: 1000;
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }
    
    .theme-indicator:hover {
        opacity: 1;
    }
    .main-nav ul {
        display: flex;
        flex-direction: row;
        gap: 12px;
        list-style: none;
        align-items: center;
        margin: 0;
        padding: 0;
    }
    .main-nav ul li a {
        display: flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
        font-size: 0.8em;
        position: relative;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .main-nav ul li a:hover, .main-nav ul li a.active {
        background: rgba(255,255,255,0.2);
        color: #fff;
        transform: translateY(-1px);
        text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .main-nav ul li a i {
        color: #fff;
        font-size: 0.9em;
        margin-right: 3px;
        transition: color 0.2s;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .main-nav ul li .logout-link {
        color: #fff;
        background: rgba(231,76,60,0.2);
    }
    .main-nav ul li .logout-link:hover {
        background: rgba(231,76,60,0.3);
        color: #fff;
    }
    
    /* Styles pour les menus déroulants */
    .dropdown {
        position: relative;
    }
    .dropdown-toggle {
        cursor: pointer;
    }
    .dropdown-toggle .fa-chevron-down {
        transition: transform 0.3s ease;
    }
    .dropdown:hover .dropdown-toggle .fa-chevron-down {
        transform: rotate(180deg);
    }
    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(44,62,80,0.2);
        min-width: 220px;
        margin-top: 8px;
        padding: 8px 0;
        z-index: 1000;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }
    .dropdown:hover .dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    .dropdown-menu::before {
        content: '';
        position: absolute;
        top: -8px;
        left: 20px;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid #fff;
    }
    .dropdown-menu li {
        list-style: none;
    }
    .dropdown-menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        color: #2c3e50;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.95em;
        border-radius: 0;
    }
    
    /* Couleurs logiques pour les différents menus */
    .dropdown-menu.dossiers-menu li a {
        color: #27ae60; /* Vert pour les dossiers (gestion de documents) */
    }
    .dropdown-menu.dossiers-menu li a:hover {
        background: #e8f8f5;
        color: #1e8449;
        transform: translateX(4px);
    }
    .dropdown-menu.dossiers-menu li a i {
        color: #27ae60;
    }
    
    .dropdown-menu.rapports-menu li a {
        color: #8e44ad; /* Violet pour les rapports (analyses) */
    }
    .dropdown-menu.rapports-menu li a:hover {
        background: #f4ecf7;
        color: #6c3483;
        transform: translateX(4px);
    }
    .dropdown-menu.rapports-menu li a i {
        color: #8e44ad;
    }
    
    .dropdown-menu.communication-menu li a {
        color: #e67e22; /* Orange pour la communication */
    }
    .dropdown-menu.communication-menu li a:hover {
        background: #fdf2e9;
        color: #d35400;
        transform: translateX(4px);
    }
    .dropdown-menu.communication-menu li a i {
        color: #e67e22;
    }
    
    .dropdown-menu.langue-menu li a {
        color: #3498db; /* Bleu pour les langues */
    }
    .dropdown-menu.langue-menu li a:hover {
        background: #ebf3fd;
        color: #2980b9;
        transform: translateX(4px);
    }
    .dropdown-menu.langue-menu li a i {
        color: #3498db;
    }
    
    .dropdown-menu.profil-menu li a {
        color: #16a085; /* Turquoise pour profil */
    }
    .dropdown-menu.profil-menu li a:hover {
        background: #e8f8f5;
        color: #138d75;
        transform: translateX(4px);
    }
    .dropdown-menu.profil-menu li a i {
        color: #16a085;
    }
    
    .dropdown-menu.aide-menu li a {
        color: #f39c12; /* Orange pour aide */
    }
    .dropdown-menu.aide-menu li a:hover {
        background: #fef9e7;
        color: #d68910;
        transform: translateX(4px);
    }
    .dropdown-menu.aide-menu li a i {
        color: #f39c12;
    }
    
    .dropdown-menu.gestion-menu li a {
        color: #9b59b6; /* Violet pour gestion */
    }
    .dropdown-menu.gestion-menu li a:hover {
        background: #f4ecf7;
        color: #7d3c98;
        transform: translateX(4px);
    }
    .dropdown-menu.gestion-menu li a i {
        color: #9b59b6;
    }
    
    /* Style par défaut pour les menus sans classe spécifique */
    .dropdown-menu li a:hover {
        background: #f8fafc;
        color: #2980b9;
        transform: translateX(4px);
    }
    .dropdown-menu li a i {
        width: 18px;
        color: inherit;
        font-size: 1.1em;
    }

    /* Style des notifications améliorées */
    .notification-menu {
        position: relative;
    }
    #notificationBell {
        position: relative;
        padding: 6px;
        border-radius: 50%;
        transition: all 0.2s ease;
        background: rgba(255,255,255,0.1);
    }
    #notificationBell:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    .notification-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #e74c3c;
        color: #fff;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 0.75em;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(231,76,60,0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 2px 8px rgba(231,76,60,0.4); }
        50% { box-shadow: 0 2px 8px rgba(231,76,60,0.8), 0 0 0 4px rgba(231,76,60,0.2); }
        100% { box-shadow: 0 2px 8px rgba(231,76,60,0.4); }
    }
    .notification-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 12px 48px rgba(44,62,80,0.2);
        min-width: 350px;
        max-width: 400px;
        margin-top: 12px;
        z-index: 1000;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }
    .notification-dropdown.show {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    .notification-dropdown::before {
        content: '';
        position: absolute;
        top: -8px;
        right: 24px;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid #fff;
    }
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #f0f4f8;
        background: linear-gradient(135deg, #f8fafc, #fff);
        border-radius: 16px 16px 0 0;
    }
    .notification-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.2em;
        font-weight: 600;
    }
    .notification-header a {
        color: #2980b9;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
        transition: color 0.2s ease;
    }
    .notification-header a:hover {
        color: #3498db;
    }
    .notification-list {
        max-height: 400px;
        overflow-y: auto;
        padding: 8px 0;
    }
    .notification-list::-webkit-scrollbar {
        width: 6px;
    }
    .notification-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    .notification-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    .notification-list::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    .notification-item {
        display: block;
        padding: 16px 20px;
        border-bottom: 1px solid #f8fafc;
        text-decoration: none;
        transition: all 0.2s ease;
        position: relative;
    }
    .notification-item:hover {
        background: linear-gradient(135deg, #f8fafc, #edf2f7);
        transform: translateX(4px);
    }
    .notification-item:last-child {
        border-bottom: none;
        border-radius: 0 0 16px 16px;
    }
    .notification-item .notification-title {
        display: block;
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: 0.95em;
    }
    .notification-item .notification-message {
        margin: 0;
        color: #5a6c7d;
        font-size: 0.9em;
        line-height: 1.4;
        margin-bottom: 8px;
    }
    .notification-item .notification-time {
        color: #95a5a6;
        font-size: 0.85em;
        font-weight: 500;
    }
    .notification-empty {
        text-align: center;
        padding: 40px 20px;
        color: #7f8c8d;
    }
    .notification-empty i {
        font-size: 2em;
        margin-bottom: 12px;
        color: #bdc3c7;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.65em;
        font-weight: bold;
        color: #fff;
        letter-spacing: 0.3px;
        margin-right: 15px;
        padding: 0 4px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 280px;
    }
    
    .logo-icon {
        display: flex;
        align-items: center;
        gap: 4px;
        background: rgba(255,255,255,0.1);
        padding: 6px 8px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .logo-icon i {
        font-size: 1.2em;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    
    .logo-icon .fa-notes-medical {
        color: #e8f8f5; /* Vert clair médical */
    }
    
    .logo-icon .fa-folder-open {
        color: #fdf2e9; /* Orange clair dossiers */
    }
    
    .logo-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    
    .logo-title {
        font-size: 1em;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    
    .logo-subtitle {
        font-size: 0.75em;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin: 0;
    }
    
    /* Animation subtile du logo */
    .logo:hover .logo-icon {
        background: rgba(255,255,255,0.15);
        transform: scale(1.05);
        transition: all 0.2s ease;
    }
    
    .logo:hover .logo-icon i {
        transform: rotate(5deg);
        transition: transform 0.2s ease;
    }

    /* Barre de recherche */
    .search-container {
        flex: 1;
        max-width: 400px;
        margin: 0 2rem;
        position: relative;
    }
    .search-form {
        position: relative;
    }
    .search-input-group {
        display: flex;
        align-items: center;
        background: rgba(255,255,255,0.15);
        border-radius: 25px;
        padding: 2px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        transition: all 0.3s ease;
    }
    .search-input-group:focus-within {
        background: rgba(255,255,255,0.25);
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        transform: translateY(-1px);
    }
    .search-input {
        flex: 1;
        background: transparent;
        border: none;
        padding: 10px 16px;
        color: #fff;
        font-size: 0.9em;
        outline: none;
    }
    .search-input::placeholder {
        color: rgba(255,255,255,0.7);
    }
    .search-button {
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        padding: 8px 12px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-right: 4px;
    }
    .search-button:hover {
        background: rgba(255,255,255,0.3);
        transform: scale(1.05);
    }
    .search-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        margin-top: 8px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }
    .search-suggestion {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f8;
        cursor: pointer;
        transition: background 0.2s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .search-suggestion:hover {
        background: #f8fafc;
    }
    .search-suggestion.active {
        background: #e3f2fd;
        color: #1976d2;
    }
    .search-suggestion.active i {
        color: #1976d2;
    }
    .search-suggestion:last-child {
        border-bottom: none;
        border-radius: 0 0 12px 12px;
    }
    .search-suggestion:first-child {
        border-radius: 12px 12px 0 0;
    }
    .search-suggestion i {
        color: #3498db;
        width: 16px;
    }
    .search-suggestion-text {
        flex: 1;
    }
    .search-suggestion-type {
        font-size: 0.8em;
        color: #7f8c8d;
        background: #ecf0f1;
        padding: 2px 8px;
        border-radius: 12px;
    }
    .app-header .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 2px 0;
        min-height: 28px;
    }
    header.app-header {
        background: #2980b9;
        color: #fff;
        box-shadow: 0 2px 8px rgba(44,62,80,0.15);
        border-bottom: 2px solid #3498db;
        position: sticky;
        top: 0;
        z-index: 1000;
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
        .search-container {
            display: none; /* Cache la recherche sur mobile pour économiser l'espace */
        }
        
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
<body class="theme-<?= $currentTheme ?>" data-theme="<?= $currentTheme ?>" data-language="<?= $currentLanguage ?>">
    
    <!-- Indicateur de thème -->
    <?php if (isset($_GET['debug']) || (isset($_SESSION['user_id']) && $preferencesManager)): ?>
    <div class="theme-indicator" title="Thème actuel: <?= ucfirst($currentTheme) ?>">
        <?= $currentTheme === 'dark' ? '🌙' : '🌞' ?> <?= ucfirst($currentTheme) ?>
    </div>
    <?php endif; ?>
    
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

            <!-- Barre de recherche rapide -->
            <div class="search-container">
                <form action="<?= BASE_URL ?>modules/search/global.php" method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" 
                               name="q" 
                               placeholder="Rechercher dossiers, utilisateurs..." 
                               class="search-input"
                               autocomplete="off"
                               minlength="2">
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="search-suggestions" id="searchSuggestions"></div>
                </form>
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
                            <li><a href="<?= BASE_URL ?>modules/workflow/config.php"><i class="fas fa-project-diagram"></i> Configuration Workflow</a></li>
                            <li><a href="<?= BASE_URL ?>modules/users/list.php"><i class="fas fa-users"></i> Gestion Utilisateurs</a></li>
                            <?php if (hasPermission(ROLE_ADMIN)): ?>
                            <li><a href="<?= BASE_URL ?>modules/statuts/transitions.php"><i class="fas fa-exchange-alt"></i> Transitions Statuts</a></li>
                            <li><a href="<?= BASE_URL ?>modules/logs/index.php"><i class="fas fa-history"></i> Journal d'Audit</a></li>
                            <li><a href="<?= BASE_URL ?>modules/backup/index.php"><i class="fas fa-database"></i> Sauvegarde</a></li>
                            <li><a href="<?= BASE_URL ?>modules/system/health.php"><i class="fas fa-heartbeat"></i> État Système</a></li>
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
                            <li><a href="<?= BASE_URL ?>modules/reporting/performance.php"><i class="fas fa-tachometer-alt"></i> Indicateurs Performance</a></li>
                            <li><a href="<?= BASE_URL ?>modules/export/export.php"><i class="fas fa-file-export"></i> <?= t('rapports_export') ?></a></li>
                            <li><a href="<?= BASE_URL ?>modules/export/pdf.php"><i class="fas fa-file-pdf"></i> Export PDF</a></li>
                            <li><a href="<?= BASE_URL ?>modules/export/excel.php"><i class="fas fa-file-excel"></i> Export Excel</a></li>
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
                            <li><a href="<?= BASE_URL ?>modules/messagerie/compose.php"><i class="fas fa-edit"></i> Nouveau Message</a></li>
                            <li><a href="<?= BASE_URL ?>modules/notifications/list.php"><i class="fas fa-bell"></i> <?= t('communication_notifications') ?></a></li>
                            <li><a href="<?= BASE_URL ?>modules/notifications/config.php"><i class="fas fa-cog"></i> Config. Notifications</a></li>
                            <li><a href="<?= BASE_URL ?>modules/whatsapp/send.php"><i class="fab fa-whatsapp"></i> WhatsApp</a></li>
                            <li><a href="<?= BASE_URL ?>modules/email/templates.php"><i class="fas fa-envelope-open-text"></i> Templates Email</a></li>
                        </ul>
                    </li>

                    <!-- Menu déroulant Profil et Aide -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-user"></i> <?= t('profile') ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu profil-menu">
                            <li><a href="<?= BASE_URL ?>modules/users/profile.php"><i class="fas fa-user-edit"></i> Mon Profil</a></li>
                            <li><a href="<?= BASE_URL ?>modules/users/settings.php"><i class="fas fa-cog"></i> Préférences</a></li>
                            <li><a href="<?= BASE_URL ?>modules/users/security.php"><i class="fas fa-shield-alt"></i> Sécurité</a></li>
                            <li><a href="<?= BASE_URL ?>modules/users/activity.php"><i class="fas fa-history"></i> Mon Activité</a></li>
                        </ul>
                    </li>

                    <!-- Menu Aide et Support -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-question-circle"></i> Aide 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu aide-menu">
                            <li><a href="<?= BASE_URL ?>modules/help/guide.php"><i class="fas fa-book"></i> Guide Utilisateur</a></li>
                            <li><a href="<?= BASE_URL ?>modules/help/faq.php"><i class="fas fa-question"></i> FAQ</a></li>
                            <li><a href="<?= BASE_URL ?>modules/help/tutorials.php"><i class="fas fa-play-circle"></i> Tutoriels</a></li>
                            <li><a href="<?= BASE_URL ?>modules/help/shortcuts.php"><i class="fas fa-keyboard"></i> Raccourcis</a></li>
                            <li><a href="<?= BASE_URL ?>modules/support/ticket.php"><i class="fas fa-life-ring"></i> Support Technique</a></li>
                            <li><a href="<?= BASE_URL ?>modules/help/about.php"><i class="fas fa-info-circle"></i> À propos</a></li>
                        </ul>
                    </li>
                    
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

    // Recherche en temps réel
    const searchInput = document.querySelector('.search-input');
    const searchSuggestions = document.getElementById('searchSuggestions');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`<?= BASE_URL ?>api/search/suggestions.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.suggestions && data.suggestions.length > 0) {
                            let html = '';
                            data.suggestions.forEach(item => {
                                html += `
                                    <div class="search-suggestion" onclick="window.location.href='${item.url}'">
                                        <i class="${item.icon}"></i>
                                        <div class="search-suggestion-text">
                                            <strong>${item.title}</strong>
                                            ${item.description ? `<br><small>${item.description}</small>` : ''}
                                        </div>
                                        <span class="search-suggestion-type">${item.type}</span>
                                    </div>
                                `;
                            });
                            searchSuggestions.innerHTML = html;
                            searchSuggestions.style.display = 'block';
                        } else {
                            searchSuggestions.innerHTML = `
                                <div class="search-suggestion">
                                    <i class="fas fa-search"></i>
                                    <div class="search-suggestion-text">Aucun résultat trouvé</div>
                                </div>
                            `;
                            searchSuggestions.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur de recherche:', error);
                        searchSuggestions.style.display = 'none';
                    });
            }, 300);
        });

        // Fermer les suggestions quand on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                searchSuggestions.style.display = 'none';
            }
        });

        // Navigation au clavier dans les suggestions
        searchInput.addEventListener('keydown', function(e) {
            const suggestions = searchSuggestions.querySelectorAll('.search-suggestion');
            const currentActive = searchSuggestions.querySelector('.search-suggestion.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (currentActive) {
                    currentActive.classList.remove('active');
                    const next = currentActive.nextElementSibling;
                    if (next) {
                        next.classList.add('active');
                    } else {
                        suggestions[0]?.classList.add('active');
                    }
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (currentActive) {
                    currentActive.classList.remove('active');
                    const prev = currentActive.previousElementSibling;
                    if (prev) {
                        prev.classList.add('active');
                    } else {
                        suggestions[suggestions.length - 1]?.classList.add('active');
                    }
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentActive) {
                    currentActive.click();
                } else {
                    // Soumettre le formulaire de recherche
                    this.closest('form').submit();
                }
            } else if (e.key === 'Escape') {
                searchSuggestions.style.display = 'none';
            }
        });
    }
    </script>
    <script src="<?= BASE_URL ?>assets/js/access_control.js?v=1"></script>