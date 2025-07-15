<?php
/**
 * Gestionnaire global de langue
 * Ce fichier doit être inclus au début de chaque page pour gérer les langues
 */

// Démarrer la session si elle n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Définir les langues disponibles
if (!defined('DEFAULT_LANGUAGE')) define('DEFAULT_LANGUAGE', 'fr');
if (!defined('AVAILABLE_LANGUAGES')) define('AVAILABLE_LANGUAGES', ['fr', 'en']);

/**
 * Traiter le changement de langue en priorité
 */
function processLanguageChange() {
    // Vérifier si une langue est demandée via GET
    if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $_GET['lang'];
        
        // Nettoyer l'URL pour rediriger sans le paramètre lang
        $currentUrl = $_SERVER['REQUEST_URI'];
        $cleanUrl = preg_replace('/[?&]lang=[^&]*/', '', $currentUrl);
        $cleanUrl = rtrim($cleanUrl, '?&');
        
        // Rediriger seulement si l'URL a changé
        if ($cleanUrl !== $currentUrl) {
            header("Location: $cleanUrl");
            exit();
        }
    }
}

/**
 * Obtenir la langue actuelle (version améliorée)
 */
function getCurrentLanguage() {
    // Priorité 1: Session (langue déjà choisie)
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        return $_SESSION['language'];
    }
    
    // Priorité 2: En-tête Accept-Language du navigateur
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGUAGES)) {
            $_SESSION['language'] = $browserLang;
            return $browserLang;
        }
    }
    
    // Priorité 3: Langue par défaut
    $_SESSION['language'] = DEFAULT_LANGUAGE;
    return DEFAULT_LANGUAGE;
}

/**
 * Charger les traductions pour la langue actuelle
 */
function loadTranslations($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    $langFile = __DIR__ . "/../lang/{$lang}.php";
    if (file_exists($langFile)) {
        return include $langFile;
    }
    
    // Fallback vers la langue par défaut
    $defaultFile = __DIR__ . "/../lang/" . DEFAULT_LANGUAGE . ".php";
    if (file_exists($defaultFile)) {
        return include $defaultFile;
    }
    
    return [];
}

/**
 * Cache global des traductions
 */
$GLOBALS['translations_cache'] = null;

/**
 * Obtenir une traduction (version optimisée)
 */
function t($key, $default = null) {
    // Utiliser le cache global
    if ($GLOBALS['translations_cache'] === null) {
        $GLOBALS['translations_cache'] = loadTranslations();
    }
    
    return $GLOBALS['translations_cache'][$key] ?? $default ?? $key;
}

/**
 * Obtenir une traduction avec des paramètres
 */
function tf($key, $params = [], $default = null) {
    $text = t($key, $default);
    
    foreach ($params as $param => $value) {
        $text = str_replace("{{{$param}}}", $value, $text);
    }
    
    return $text;
}

/**
 * Générer un lien avec la langue spécifiée
 */
function langUrl($url, $lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    // Nettoyer l'URL des paramètres de langue existants
    $cleanUrl = preg_replace('/[?&]lang=[^&]*/', '', $url);
    $cleanUrl = rtrim($cleanUrl, '?&');
    
    $separator = strpos($cleanUrl, '?') !== false ? '&' : '?';
    return $cleanUrl . $separator . 'lang=' . $lang;
}

/**
 * Obtenir toutes les langues disponibles
 */
function getAvailableLanguages() {
    return [
        'fr' => [
            'name' => 'Français',
            'flag' => '🇫🇷',
            'code' => 'fr'
        ],
        'en' => [
            'name' => 'English', 
            'flag' => '🇬🇧',
            'code' => 'en'
        ]
    ];
}

/**
 * Générer le sélecteur de langue HTML
 */
function renderLanguageSelector($style = 'dropdown') {
    $currentLang = getCurrentLanguage();
    $languages = getAvailableLanguages();
    $currentUrl = $_SERVER['REQUEST_URI'];
    
    if ($style === 'buttons') {
        // Style boutons (pour login, etc.)
        $html = '<div class="language-selector">';
        foreach ($languages as $code => $lang) {
            $active = $currentLang === $code ? 'active' : '';
            $bgColor = $currentLang === $code ? '#2980b9' : '#ecf0f1';
            $textColor = $currentLang === $code ? '#fff' : '#2c3e50';
            
            $html .= '<a href="' . langUrl($currentUrl, $code) . '" ';
            $html .= 'class="lang-btn ' . $active . '" ';
            $html .= 'style="margin-right: 8px; padding: 6px 12px; background: ' . $bgColor . '; ';
            $html .= 'color: ' . $textColor . '; text-decoration: none; border-radius: 6px; font-size: 0.9em;">';
            $html .= $lang['flag'] . ' ' . strtoupper($code);
            $html .= '</a>';
        }
        $html .= '</div>';
        return $html;
    }
    
    // Style par défaut (pour header)
    return '';
}

// Traiter le changement de langue dès le chargement
processLanguageChange();

// Initialiser la langue
getCurrentLanguage();
?>
