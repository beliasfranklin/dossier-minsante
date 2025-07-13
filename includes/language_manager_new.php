<?php
/**
 * Gestionnaire global de langue amélioré
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
 * Fonction de traduction automatique avec dictionnaire intégré
 */
function _t($text) {
    $currentLang = getCurrentLanguage();
    
    // Si c'est déjà en français ou si la langue est française, retourner tel quel
    if ($currentLang === 'fr') {
        return $text;
    }
    
    // Dictionnaire de traduction automatique français -> anglais
    $autoTranslations = [
        // Navigation
        'Accueil' => 'Home',
        'Tableau de bord' => 'Dashboard',
        'Dashboard' => 'Dashboard',
        'Dossiers' => 'Files',
        'Liste des dossiers' => 'Files List',
        'Nouveau dossier' => 'New File',
        'Archives' => 'Archives',
        'Rapports' => 'Reports',
        'Statistiques' => 'Statistics',
        'Export' => 'Export',
        'Communication' => 'Communication',
        'Messagerie' => 'Messaging',
        'Notifications' => 'Notifications',
        'Profil' => 'Profile',
        'Administration' => 'Administration',
        'Déconnexion' => 'Logout',
        
        // Actions
        'Créer' => 'Create',
        'Créer le dossier' => 'Create File',
        'Modifier' => 'Edit',
        'Supprimer' => 'Delete',
        'Voir' => 'View',
        'Rechercher' => 'Search',
        'Recherche' => 'Search',
        'Annuler' => 'Cancel',
        'Confirmer' => 'Confirm',
        'Valider' => 'Validate',
        'Rejeter' => 'Reject',
        'Archiver' => 'Archive',
        'Envoyer' => 'Send',
        'Recevoir' => 'Receive',
        'Télécharger' => 'Download',
        'Imprimer' => 'Print',
        'Exporter' => 'Export',
        'Importer' => 'Import',
        'Sauvegarder' => 'Save',
        'Restaurer' => 'Restore',
        'Réinitialiser' => 'Reset',
        'Actualiser' => 'Refresh',
        
        // Statuts
        'En cours' => 'In Progress',
        'Validé' => 'Validated',
        'Validés' => 'Validated',
        'Rejeté' => 'Rejected',
        'Rejetés' => 'Rejected',
        'Archivé' => 'Archived',
        'Archivés' => 'Archived',
        'Brouillon' => 'Draft',
        'Publié' => 'Published',
        'Suspendu' => 'Suspended',
        'Actif' => 'Active',
        'Inactif' => 'Inactive',
        'Terminé' => 'Completed',
        'Annulé' => 'Cancelled',
        
        // Formulaires
        'Titre' => 'Title',
        'Description' => 'Description',
        'Type' => 'Type',
        'Service' => 'Service',
        'Responsable' => 'Responsible',
        'Email' => 'Email',
        'Mot de passe' => 'Password',
        'Nom' => 'Name',
        'Prénom' => 'First Name',
        'Téléphone' => 'Phone',
        'Adresse' => 'Address',
        'Date' => 'Date',
        'Heure' => 'Time',
        'Commentaire' => 'Comment',
        'Message' => 'Message',
        'Priorité' => 'Priority',
        'Statut' => 'Status',
        'Référence' => 'Reference',
        'Pièces jointes' => 'Attachments',
        
        // Messages
        'Succès' => 'Success',
        'Erreur' => 'Error',
        'Avertissement' => 'Warning',
        'Information' => 'Information',
        'Confirmation' => 'Confirmation',
        'Attention' => 'Warning',
        'Félicitations' => 'Congratulations',
        'Échec' => 'Failure',
        
        // Temps
        'Aujourd\'hui' => 'Today',
        'Demain' => 'Tomorrow',
        'Hier' => 'Yesterday',
        'jours' => 'days',
        'jour' => 'day',
        'semaines' => 'weeks',
        'semaine' => 'week',
        'mois' => 'months',
        'année' => 'year',
        'années' => 'years',
        'heure' => 'hour',
        'heures' => 'hours',
        'minute' => 'minute',
        'minutes' => 'minutes',
        'seconde' => 'second',
        'secondes' => 'seconds',
        'derniers jours' => 'last days',
        
        // Pagination
        'Précédent' => 'Previous',
        'Suivant' => 'Next',
        'Premier' => 'First',
        'Dernier' => 'Last',
        'Page' => 'Page',
        'de' => 'of',
        'résultats' => 'results',
        'résultat' => 'result',
        'éléments' => 'items',
        'élément' => 'item',
        
        // Filtres
        'Tous' => 'All',
        'Tous statuts' => 'All Status',
        'Tous types' => 'All Types',
        'Toutes priorités' => 'All Priorities',
        'Toutes échéances' => 'All Deadlines',
        'Filtrer' => 'Filter',
        'Trier par' => 'Sort by',
        'Ordre' => 'Order',
        'Croissant' => 'Ascending',
        'Décroissant' => 'Descending',
        
        // Dashboard spécifique
        'Total dossiers' => 'Total Files',
        'Dossiers récents' => 'Recent Files',
        'Voir tous' => 'View All',
        'Aucun dossier' => 'No Files',
        'Statistiques Reporting' => 'Reporting Statistics',
        'Créations de dossiers' => 'File Creations',
        'Gestion des Statuts' => 'Status Management',
        'Contrôle des transitions et validation des changements de statut' => 'Control transitions and validate status changes',
        'Statut Actuel' => 'Current Status',
        'Transitions Possibles' => 'Possible Transitions',
        'Rôle Requis' => 'Required Role',
        
        // Échéances
        'Échéance' => 'Deadline',
        'Échéances' => 'Deadlines',
        'Dashboard des Échéances' => 'Deadlines Dashboard',
        'Suivi et gestion des délais critiques' => 'Tracking and management of critical deadlines',
        'Configuration' => 'Configuration',
        'Tous les Dossiers' => 'All Files',
        'Dépassées' => 'Overdue',
        'Expirées' => 'Expired',
        'À venir' => 'Upcoming',
        'Sans échéance' => 'No Deadline',
        'Date de début' => 'Start Date',
        'Date de fin' => 'End Date',
        
        // Formulaires étendus
        'Titre, référence, description...' => 'Title, reference, description...',
        'Étude' => 'Study',
        'Projet' => 'Project',
        'Administratif' => 'Administrative',
        
        // Connexion
        'Connexion' => 'Login',
        'Se connecter' => 'Log In',
        'Identifiants incorrects' => 'Incorrect credentials',
        'Afficher/Masquer le mot de passe' => 'Show/Hide password',
        
        // Profil
        'Profil utilisateur' => 'User Profile',
        'Gérez vos informations et accédez à vos dossiers récents' => 'Manage your information and access your recent files',
        'Accès non autorisé' => 'Unauthorized access',
        
        // Administration
        'Gestion des utilisateurs' => 'User Management',
        'Administration des comptes et permissions' => 'Account and permission administration',
        
        // App
        'MINSANTE - Gestion des Dossiers' => 'MINSANTE - File Management',
        
        // Confirmation
        'Êtes-vous sûr de vouloir vous déconnecter ?' => 'Are you sure you want to log out?',
    ];
    
    // Retourner la traduction si elle existe, sinon le texte original
    return $autoTranslations[$text] ?? $text;
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
        $html = '<div class="language-selector-buttons" style="display:flex;gap:8px;margin:16px 0;">';
        foreach ($languages as $code => $lang) {
            $active = $currentLang === $code ? 'active' : '';
            $bgColor = $currentLang === $code ? '#2980b9' : '#ecf0f1';
            $textColor = $currentLang === $code ? '#fff' : '#2c3e50';
            
            $html .= '<a href="' . langUrl($currentUrl, $code) . '" ';
            $html .= 'style="padding:8px 16px;border-radius:6px;background:' . $bgColor . ';color:' . $textColor . ';text-decoration:none;font-weight:500;transition:all 0.2s;display:flex;align-items:center;gap:6px;" ';
            $html .= 'class="lang-btn ' . $active . '">';
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
