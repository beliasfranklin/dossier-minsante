<?php
/**
 * Système de gestion des langues
 */

// Démarrer la session si elle n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Définir la langue par défaut
define('DEFAULT_LANGUAGE', 'fr');
define('AVAILABLE_LANGUAGES', ['fr', 'en']);

/**
 * Obtenir la langue actuelle
 */
function getCurrentLanguage() {
    // Vérifier si une langue est demandée via GET
    if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $_GET['lang'];
        // Rediriger pour nettoyer l'URL (éviter le paramètre lang dans l'URL)
        $currentUrl = $_SERVER['REQUEST_URI'];
        $cleanUrl = preg_replace('/[?&]lang=[^&]*/', '', $currentUrl);
        $cleanUrl = rtrim($cleanUrl, '?&');
        if ($cleanUrl !== $_SERVER['REQUEST_URI']) {
            header("Location: $cleanUrl");
            exit();
        }
        return $_GET['lang'];
    }
    
    // Vérifier la session
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        return $_SESSION['language'];
    }
    
    // Vérifier l'en-tête Accept-Language du navigateur
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGUAGES)) {
            $_SESSION['language'] = $browserLang;
            return $browserLang;
        }
    }
    
    // Langue par défaut
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
    
    $langFile = __DIR__ . "/{$lang}.php";
    if (file_exists($langFile)) {
        return include $langFile;
    }
    
    // Fallback vers la langue par défaut
    $defaultFile = __DIR__ . "/" . DEFAULT_LANGUAGE . ".php";
    if (file_exists($defaultFile)) {
        return include $defaultFile;
    }
    
    return [];
}

/**
 * Obtenir une traduction
 */
function t($key, $default = null) {
    static $translations = null;
    
    if ($translations === null) {
        $translations = loadTranslations();
    }
    
    return $translations[$key] ?? $default ?? $key;
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
 * Changer la langue et rediriger
 */
function setLanguage($lang) {
    if (in_array($lang, AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $lang;
        // Rediriger vers la même page pour appliquer la nouvelle langue
        $currentUrl = $_SERVER['REQUEST_URI'];
        $cleanUrl = preg_replace('/[?&]lang=[^&]*/', '', $currentUrl);
        $cleanUrl = rtrim($cleanUrl, '?&');
        header("Location: $cleanUrl");
        exit();
    }
}

/**
 * Obtenir toutes les langues disponibles
 */
function getAvailableLanguages() {
    return [
        'fr' => 'Français',
        'en' => 'English'
    ];
}

// Initialiser la langue au chargement du fichier
getCurrentLanguage();
