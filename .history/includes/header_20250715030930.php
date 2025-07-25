<?php
// Inclure d'abord le config principal pour avoir accès aux fonctions de base
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/module_highlights.php';
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
    /* Boutons de fermeture pour les menus */
    .menu-close-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.8em;
        color: #64748b;
        transition: var(--transition-fast);
        z-index: 10;
    }
    
    .menu-close-btn:hover {
        background: #e2e8f0;
        color: #374151;
        transform: scale(1.1);
    }
    
    .dropdown-menu {
        position: relative;
    }

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

    /* Menu burger mobile modernisé */
    .burger-menu {
        display: none;
        flex-direction: column;
        justify-content: center;
        width: 44px;
        height: 44px;
        cursor: pointer;
        margin-left: 12px;
        padding: 8px;
        border-radius: var(--radius-modern);
        transition: var(--transition-smooth);
        background: var(--header-accent);
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .burger-menu:hover {
        background: var(--header-hover);
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(255,255,255,0.1);
    }
    
    .burger-bar {
        height: 3px;
        width: 100%;
        background: var(--header-text);
        border-radius: 2px;
        margin: 3px 0;
        transition: var(--transition-smooth);
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .burger-menu.active .burger-bar:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
    }
    
    .burger-menu.active .burger-bar:nth-child(2) {
        opacity: 0;
        transform: translateX(20px);
    }
    
    .burger-menu.active .burger-bar:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
    }

    /* Section des éléments importants du module */
    .module-highlights {
        background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 100%);
        border-radius: var(--radius-modern);
        padding: 16px 20px;
        margin: 12px 0;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        backdrop-filter: blur(10px);
        display: none;
        animation: slideDown 0.3s ease-out;
    }
    
    .module-highlights.show {
        display: block;
    }
    
    .module-highlights-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid rgba(102, 126, 234, 0.1);
    }
    
    .module-highlights-title {
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .module-highlights-title i {
        color: #667eea;
        font-size: 1em;
    }
    
    .highlights-toggle-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.8em;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .highlights-toggle-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    .highlights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .highlight-card {
        background: white;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: var(--transition-smooth);
        position: relative;
        overflow: hidden;
    }
    
    .highlight-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleX(0);
        transition: var(--transition-smooth);
        transform-origin: left;
    }
    
    .highlight-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .highlight-card:hover::before {
        transform: scaleX(1);
    }
    
    .highlight-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2em;
        color: white;
        margin-bottom: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .highlight-card.urgent .highlight-icon {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    
    .highlight-card.important .highlight-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .highlight-card.success .highlight-icon {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .highlight-card.info .highlight-icon {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }
    
    .highlight-title {
        font-size: 0.9em;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 4px 0;
        line-height: 1.3;
    }
    
    .highlight-value {
        font-size: 1.4em;
        font-weight: 800;
        margin: 0 0 4px 0;
        line-height: 1;
    }
    
    .highlight-card.urgent .highlight-value {
        color: #ef4444;
    }
    
    .highlight-card.important .highlight-value {
        color: #f59e0b;
    }
    
    .highlight-card.success .highlight-value {
        color: #10b981;
    }
    
    .highlight-card.info .highlight-value {
        color: #3b82f6;
    }
    
    .highlight-description {
        font-size: 0.8em;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
    }
    
    .highlight-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #667eea;
        text-decoration: none;
        font-size: 0.8em;
        font-weight: 600;
        margin-top: 8px;
        padding: 4px 8px;
        border-radius: 6px;
        transition: var(--transition-fast);
        background: rgba(102, 126, 234, 0.05);
    }
    
    .highlight-action:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #4f46e5;
        transform: translateX(2px);
    }
    
    .highlights-quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }
    
    .quick-action-btn {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.8em;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition-fast);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .quick-action-btn:hover {
        background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
        color: #334155;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .quick-action-btn i {
        font-size: 0.9em;
    }
    
    /* Responsive pour les éléments importants */
    @media (max-width: 800px) {
        .module-highlights {
            margin: 8px 12px;
            padding: 12px 16px;
            border-radius: 8px;
        }
        
        .highlights-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
        }
        
        .highlight-card {
            padding: 12px;
            border-radius: 8px;
        }
        
        .highlight-icon {
            width: 32px;
            height: 32px;
            font-size: 1em;
            margin-bottom: 8px;
        }
        
        .highlight-title {
            font-size: 0.85em;
        }
        
        .highlight-value {
            font-size: 1.2em;
        }
        
        .highlights-quick-actions {
            flex-direction: column;
            gap: 6px;
        }
        
        .quick-action-btn {
            justify-content: center;
            padding: 10px 12px;
        }
    }
    
    @media (max-width: 480px) {
        .highlights-grid {
            grid-template-columns: 1fr;
        }
        
        .module-highlights-header {
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
        
        .highlights-toggle-btn {
            align-self: stretch;
            justify-content: center;
        }
    }

    /* ...existing code... */
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
                    <li class="mobile-menu-close" style="display: none;">
                        <button class="menu-close-btn" onclick="closeBurgerMenu()" title="Fermer le menu" style="position: relative; top: 0; right: 0; margin: 8px;">×</button>
                    </li>
                    <li><a href="<?= BASE_URL ?>dashboard.php" class="nav-link"><i class="fa-solid fa-gauge"></i> <?= t('dashboard') ?></a></li>
                    
                    <!-- Menu déroulant Dossiers -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fa-solid fa-folder-open"></i> <?= t('dossiers') ?> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu dossiers-menu">
                            <button class="menu-close-btn" onclick="closeDossierMenu(event)" title="Fermer le menu">×</button>
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
                            <button class="menu-close-btn" onclick="closeGestionMenu(event)" title="Fermer le menu">×</button>
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
                            <button class="menu-close-btn" onclick="closeRapportsMenu(event)" title="Fermer le menu">×</button>
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
                            <button class="menu-close-btn" onclick="closeCommunicationMenu(event)" title="Fermer le menu">×</button>
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
                            <button class="menu-close-btn" onclick="closeLangueMenu(event)" title="Fermer le menu">×</button>
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
        
        <!-- Section des éléments importants du module -->
        <div class="module-highlights" id="moduleHighlights">
            <div class="module-highlights-header">
                <h3 class="module-highlights-title">
                    <i class="fas fa-star"></i>
                    Éléments Importants du Module
                </h3>
                <button class="highlights-toggle-btn" onclick="toggleHighlights()" id="highlightsToggle">
                    <i class="fas fa-eye"></i>
                    Masquer
                </button>
            </div>
            
            <div class="highlights-grid">
                <!-- Dossiers en retard -->
                <div class="highlight-card urgent">
                    <div class="highlight-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="highlight-title">Dossiers en Retard</div>
                    <div class="highlight-value" id="retardCount">--</div>
                    <div class="highlight-description">Nécessitent une attention immédiate</div>
                    <a href="<?= BASE_URL ?>modules/dossiers/list.php?filter=retard" class="highlight-action">
                        <i class="fas fa-arrow-right"></i>
                        Voir les dossiers
                    </a>
                </div>
                
                <!-- Dossiers urgents -->
                <div class="highlight-card important">
                    <div class="highlight-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="highlight-title">Échéances Prochaines</div>
                    <div class="highlight-value" id="urgentCount">--</div>
                    <div class="highlight-description">À traiter dans les 3 prochains jours</div>
                    <a href="<?= BASE_URL ?>modules/echeances/dashboard.php" class="highlight-action">
                        <i class="fas fa-arrow-right"></i>
                        Gérer échéances
                    </a>
                </div>
                
                <!-- Nouveaux dossiers -->
                <div class="highlight-card success">
                    <div class="highlight-icon">
                        <i class="fas fa-file-plus"></i>
                    </div>
                    <div class="highlight-title">Nouveaux Dossiers</div>
                    <div class="highlight-value" id="newCount">--</div>
                    <div class="highlight-description">Créés cette semaine</div>
                    <a href="<?= BASE_URL ?>modules/dossiers/list.php?filter=nouveaux" class="highlight-action">
                        <i class="fas fa-arrow-right"></i>
                        Consulter
                    </a>
                </div>
                
                <!-- Taux de validation -->
                <div class="highlight-card info">
                    <div class="highlight-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="highlight-title">Taux de Validation</div>
                    <div class="highlight-value" id="validationRate">--%</div>
                    <div class="highlight-description">Performance mensuelle</div>
                    <a href="<?= BASE_URL ?>modules/reporting/stats.php" class="highlight-action">
                        <i class="fas fa-arrow-right"></i>
                        Voir stats
                    </a>
                </div>
            </div>
            
            <div class="highlights-quick-actions">
                <a href="<?= BASE_URL ?>modules/dossiers/create.php" class="quick-action-btn">
                    <i class="fas fa-plus"></i>
                    Nouveau Dossier
                </a>
                <a href="<?= BASE_URL ?>modules/export/export.php" class="quick-action-btn">
                    <i class="fas fa-download"></i>
                    Export Rapide
                </a>
                <a href="<?= BASE_URL ?>modules/reporting/advanced.php" class="quick-action-btn">
                    <i class="fas fa-analytics"></i>
                    Rapports Avancés
                </a>
                <a href="<?= BASE_URL ?>modules/logs/index.php" class="quick-action-btn">
                    <i class="fas fa-history"></i>
                    Journal Audit
                </a>
            </div>
        </div>
    </header>
    <main class="container">
    <script>
    // Fonctions pour fermer les menus manuellement
    function closeBurgerMenu() {
        const burger = document.getElementById('burgerMenu');
        const navList = document.getElementById('mainNavList');
        navList.classList.remove('show');
        burger.classList.remove('active');
        burger.setAttribute('aria-expanded', 'false');
    }
    
    function closeDossierMenu(event) {
        event.stopPropagation();
        const menu = event.target.closest('.dropdown-menu');
        if (window.innerWidth <= 800) {
            menu.style.opacity = '0';
            setTimeout(() => {
                menu.style.display = 'none';
            }, 150);
        } else {
            menu.style.display = 'none';
            menu.style.opacity = '0';
            menu.style.transform = 'translateX(-50%) translateY(-10px)';
        }
    }
    
    function closeGestionMenu(event) {
        event.stopPropagation();
        const menu = event.target.closest('.dropdown-menu');
        if (window.innerWidth <= 800) {
            menu.style.opacity = '0';
            setTimeout(() => {
                menu.style.display = 'none';
            }, 150);
        } else {
            menu.style.display = 'none';
            menu.style.opacity = '0';
            menu.style.transform = 'translateX(-50%) translateY(-10px)';
        }
    }
    
    function closeRapportsMenu(event) {
        event.stopPropagation();
        const menu = event.target.closest('.dropdown-menu');
        if (window.innerWidth <= 800) {
            menu.style.opacity = '0';
            setTimeout(() => {
                menu.style.display = 'none';
            }, 150);
        } else {
            menu.style.display = 'none';
            menu.style.opacity = '0';
            menu.style.transform = 'translateX(-50%) translateY(-10px)';
        }
    }
    
    function closeCommunicationMenu(event) {
        event.stopPropagation();
        const menu = event.target.closest('.dropdown-menu');
        if (window.innerWidth <= 800) {
            menu.style.opacity = '0';
            setTimeout(() => {
                menu.style.display = 'none';
            }, 150);
        } else {
            menu.style.display = 'none';
            menu.style.opacity = '0';
            menu.style.transform = 'translateX(-50%) translateY(-10px)';
        }
    }
    
    function closeLangueMenu(event) {
        event.stopPropagation();
        const menu = event.target.closest('.dropdown-menu');
        if (window.innerWidth <= 800) {
            menu.style.opacity = '0';
            setTimeout(() => {
                menu.style.display = 'none';
            }, 150);
        } else {
            menu.style.display = 'none';
            menu.style.opacity = '0';
            menu.style.transform = 'translateX(-50%) translateY(-10px)';
        }
    }

    // Menu burger mobile modernisé
    const burger = document.getElementById('burgerMenu');
    const navList = document.getElementById('mainNavList');
    
    function toggleBurgerMenu() {
        navList.classList.toggle('show');
        burger.classList.toggle('active');
        
        // Gestion de l'aria-expanded pour l'accessibilité
        const isExpanded = navList.classList.contains('show');
        burger.setAttribute('aria-expanded', isExpanded);
    }
    
    burger.addEventListener('click', toggleBurgerMenu);
    
    burger.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleBurgerMenu();
        }
    });

    // Fermeture automatique désactivée - les menus restent ouverts
    // Les utilisateurs devront cliquer sur les boutons pour fermer les menus
    document.addEventListener('click', function(e) {
        // Code de fermeture automatique désactivé
        // Les menus ne se ferment plus automatiquement quand on clique ailleurs
        
        // Seule exception : fermer le dropdown de notifications si on clique sur un lien de notification
        if (e.target.closest('.notification-item')) {
            const notificationDropdown = document.getElementById('notificationDropdown');
            // Fermer après un délai pour permettre la navigation
            setTimeout(() => {
                notificationDropdown.classList.remove('show');
            }, 500);
        }
    });

    // Gestion améliorée des menus déroulants - plus de fermeture automatique
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            const isVisible = dropdown.style.display === 'block' || dropdown.style.opacity === '1';
            
            // Toggle le dropdown actuel avec animation (ne ferme plus les autres automatiquement)
            if (isVisible) {
                if (window.innerWidth <= 800) {
                    dropdown.style.opacity = '0';
                    setTimeout(() => {
                        dropdown.style.display = 'none';
                    }, 150);
                } else {
                    dropdown.style.display = 'none';
                    dropdown.style.opacity = '0';
                    dropdown.style.transform = 'translateX(-50%) translateY(-10px)';
                }
            } else {
                if (window.innerWidth <= 800) {
                    dropdown.style.display = 'block';
                    setTimeout(() => {
                        dropdown.style.opacity = '1';
                    }, 10);
                } else {
                    dropdown.style.display = 'block';
                    dropdown.style.opacity = '1';
                    dropdown.style.transform = 'translateX(-50%) translateY(0)';
                }
            }
        });
    });

    // Système de notifications modernisé
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        notificationDropdown.classList.toggle('show');
        
        // Animation du badge avec effet plus subtil
        const badge = this.querySelector('.notification-badge');
        if (badge) {
            badge.style.animation = 'none';
            badge.style.transform = 'scale(0.9)';
            setTimeout(() => {
                badge.style.animation = 'pulse 2s infinite';
                badge.style.transform = 'scale(1)';
            }, 200);
        }
        
        // Marquer visuellement que les notifications ont été vues
        if (notificationDropdown.classList.contains('show')) {
            setTimeout(() => {
                const notificationItems = notificationDropdown.querySelectorAll('.notification-item');
                notificationItems.forEach((item, index) => {
                    setTimeout(() => {
                        item.style.transform = 'translateX(2px)';
                        setTimeout(() => {
                            item.style.transform = 'translateX(0)';
                        }, 150);
                    }, index * 50);
                });
            }, 100);
        }
    });

    // Marquer les notifications comme lues avec feedback amélioré
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const notificationId = this.getAttribute('data-id');
            if (notificationId) {
                // Feedback visuel immédiat
                this.style.transform = 'scale(0.98)';
                this.style.opacity = '0.7';
                
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
                        // Animation de réduction du badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            let count = parseInt(badge.textContent);
                            count--;
                            if (count <= 0) {
                                badge.style.animation = 'none';
                                badge.style.transform = 'scale(0)';
                                setTimeout(() => badge.remove(), 300);
                            } else {
                                badge.textContent = count > 99 ? '99+' : count;
                                badge.style.animation = 'pulse 2s infinite';
                            }
                        }
                        
                        // Animation de confirmation
                        this.style.background = 'linear-gradient(135deg, #ecfdf5, #d1fae5)';
                        setTimeout(() => {
                            this.style.transform = 'scale(1)';
                        }, 150);
                    } else {
                        // Restaurer l'état en cas d'erreur
                        this.style.transform = 'scale(1)';
                        this.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du marquage de la notification:', error);
                    // Restaurer l'état en cas d'erreur
                    this.style.transform = 'scale(1)';
                    this.style.opacity = '1';
                });
            }
        });
    });

    // Smooth scroll amélioré pour les liens internes
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Effet de surbrillance temporaire
                target.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
                setTimeout(() => {
                    target.style.boxShadow = '';
                }, 2000);
            }
        });
    });

    // Mise à jour automatique intelligente du compteur de notifications
    let notificationUpdateInterval;
    
    function updateNotificationCount() {
        fetch('<?= BASE_URL ?>api/notifications/unread_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                const bell = document.getElementById('notificationBell');
                const currentCount = badge ? parseInt(badge.textContent) : 0;
                
                if (data.count > 0) {
                    if (!badge) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = data.count > 99 ? '99+' : data.count;
                        newBadge.style.transform = 'scale(0)';
                        bell.appendChild(newBadge);
                        
                        // Animation d'apparition
                        setTimeout(() => {
                            newBadge.style.transform = 'scale(1)';
                        }, 100);
                    } else if (data.count !== currentCount) {
                        // Animation de changement de nombre
                        badge.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.transform = 'scale(1)';
                        }, 150);
                    }
                } else if (badge) {
                    badge.style.transform = 'scale(0)';
                    setTimeout(() => badge.remove(), 300);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour du compteur:', error);
            });
    }
    
    // Démarrer la mise à jour automatique (toutes les 30 secondes)
    notificationUpdateInterval = setInterval(updateNotificationCount, 30000);
    
    // Pause quand la page n'est pas visible pour économiser les ressources
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(notificationUpdateInterval);
        } else {
            notificationUpdateInterval = setInterval(updateNotificationCount, 30000);
            // Mise à jour immédiate au retour de focus
            updateNotificationCount();
        }
    });
    
    // Animation d'entrée pour le header
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.querySelector('.app-header');
        header.style.transform = 'translateY(-100%)';
        setTimeout(() => {
            header.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            header.style.transform = 'translateY(0)';
        }, 100);
        
        // Initialiser les éléments importants du module
        initializeModuleHighlights();
        
        // Charger les données des éléments importants
        loadHighlightsData();
    });
    
    // Gestion des éléments importants du module
    function toggleHighlights() {
        const highlights = document.getElementById('moduleHighlights');
        const toggleBtn = document.getElementById('highlightsToggle');
        const isVisible = highlights.classList.contains('show');
        
        if (isVisible) {
            highlights.classList.remove('show');
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Afficher';
            localStorage.setItem('moduleHighlightsVisible', 'false');
        } else {
            highlights.classList.add('show');
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Masquer';
            localStorage.setItem('moduleHighlightsVisible', 'true');
            
            // Animation des cartes
            const cards = highlights.querySelectorAll('.highlight-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
    }
    
    function initializeModuleHighlights() {
        const highlights = document.getElementById('moduleHighlights');
        const isVisible = localStorage.getItem('moduleHighlightsVisible');
        
        // Afficher par défaut si c'est la première visite ou si l'utilisateur l'avait affiché
        if (isVisible === null || isVisible === 'true') {
            highlights.classList.add('show');
            document.getElementById('highlightsToggle').innerHTML = '<i class="fas fa-eye-slash"></i> Masquer';
        }
        
        // Ajouter un bouton toggle dans la navigation principale pour mobile
        if (window.innerWidth <= 800) {
            const nav = document.querySelector('.main-nav ul');
            const highlightToggle = document.createElement('li');
            highlightToggle.innerHTML = `
                <a href="#" onclick="toggleHighlights(); return false;" class="nav-link">
                    <i class="fas fa-star"></i> Éléments Importants
                </a>
            `;
            nav.insertBefore(highlightToggle, nav.children[1]);
        }
    }
    
    function loadHighlightsData() {
        // Charger les données en temps réel pour les éléments importants
        fetch('<?= BASE_URL ?>api/dashboard/highlights.php')
            .then(response => response.json())
            .then(data => {
                updateHighlightsDisplay(data);
            })
            .catch(error => {
                console.error('Erreur lors du chargement des éléments importants:', error);
                // Fallback avec des données par défaut
                updateHighlightsDisplay({
                    retard: 0,
                    urgent: 0,
                    nouveaux: 0,
                    validation_rate: 0
                });
            });
    }
    
    function updateHighlightsDisplay(data) {
        // Mettre à jour les compteurs avec animations
        updateCounterWithAnimation('retardCount', data.retard || 0);
        updateCounterWithAnimation('urgentCount', data.urgent || 0);
        updateCounterWithAnimation('newCount', data.nouveaux || 0);
        updateCounterWithAnimation('validationRate', (data.validation_rate || 0) + '%');
        
        // Mettre à jour les classes de style selon l'urgence
        updateCardUrgency('retardCount', data.retard, [0, 3, 10]);
        updateCardUrgency('urgentCount', data.urgent, [0, 5, 15]);
        updateCardUrgency('newCount', data.nouveaux, [0, 10, 25]);
    }
    
    function updateCounterWithAnimation(elementId, newValue) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const currentValue = element.textContent === '--' ? 0 : parseInt(element.textContent);
        const targetValue = parseInt(newValue) || newValue;
        
        if (typeof targetValue === 'number' && currentValue !== targetValue) {
            // Animation du compteur
            const increment = targetValue > currentValue ? 1 : -1;
            const step = Math.abs(targetValue - currentValue) / 20;
            let current = currentValue;
            
            const animate = () => {
                current += increment * step;
                if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                    element.textContent = targetValue;
                    element.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        element.style.transform = 'scale(1)';
                    }, 200);
                } else {
                    element.textContent = Math.round(current);
                    requestAnimationFrame(animate);
                }
            };
            
            animate();
        } else {
            element.textContent = newValue;
        }
    }
    
    function updateCardUrgency(counterId, value, thresholds) {
        const card = document.getElementById(counterId).closest('.highlight-card');
        if (!card) return;
        
        // Retirer les classes existantes
        card.classList.remove('urgent', 'important', 'success', 'info');
        
        // Ajouter la classe appropriée selon la valeur
        if (value >= thresholds[2]) {
            card.classList.add('urgent');
        } else if (value >= thresholds[1]) {
            card.classList.add('important');
        } else if (value > thresholds[0]) {
            card.classList.add('info');
        } else {
            card.classList.add('success');
        }
    }
    
    // Actualisation périodique des données importantes
    setInterval(loadHighlightsData, 300000); // Toutes les 5 minutes
    
    // Actualisation lors du retour de focus sur la page
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            loadHighlightsData();
        }
    });
    </script>
    <script src="<?= BASE_URL ?>assets/js/access_control.js?v=1"></script>