<?php
/**
 * Fichier d'authentification sécurisée
 */

// Démarrer la session seulement si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les fonctions de base de données si elles ne sont pas déjà disponibles
if (!function_exists('fetchOne')) {
    require_once __DIR__ . '/db.php';
}

// Vérification de l'authentification
function isAuthenticated() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Vérification du timeout de session
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        logout();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// Fonction de login
function login($email, $password) {
    global $pdo;
    
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    
    if ($user && password_verify($password . PEPPER, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];          // Nouvelle convention
        $_SESSION['user_role'] = $user['role'];     // Ancienne convention pour compatibilité
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    return false;
}

// Fonction de logout
function logout() {
    session_unset();
    session_destroy();
    session_start();
}

function hasPermission($requiredRole) {
    // 1. Vérifie si la session est valide
    if (!isset($_SESSION['user_id'])) {
        error_log("Session incomplète pour la vérification des permissions");
        return false;
    }

    // Utiliser role ou user_role selon ce qui est disponible
    $sessionRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    
    if (!$sessionRole) {
        error_log("Aucun rôle défini en session pour l'utilisateur: ".$_SESSION['user_id']);
        return false;
    }

    // 2. Vérifie en base de données (cache bypass)
    $actualRole = fetchOne("SELECT role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    if (!$actualRole) {
        error_log("Utilisateur introuvable en BDD: ".$_SESSION['user_id']);
        return false;
    }

    // 3. Compare les rôles
    return ($actualRole['role'] <= $requiredRole);
}

// Redirection si non authentifié
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

// Ajout de la fonction requirePermission si elle n'existe pas déjà
if (!function_exists('requirePermission')) {
    function requirePermission($requiredRole) {
        if (!hasPermission($requiredRole)) {
            $_SESSION['flash']['warning'] = "Demande d'élévation envoyée aux administrateurs";
            logAction($_SESSION['user_id'], 'permission_request', null, "Accès refusé (requirePermission)");
            header("Location: /dossier-minsante/request_access.php?resource=" . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
    }
}

// Ajout de la fonction requireRole (alias pour requirePermission)
if (!function_exists('requireRole')) {
    function requireRole($requiredRole) {
        // Vérifier d'abord l'authentification
        requireAuth();
        
        // Puis vérifier les permissions
        requirePermission($requiredRole);
    }
}

// Alias pour la compatibilité
function isLoggedIn() {
    return isAuthenticated();
}

// Fonction pour vérifier si l'utilisateur est administrateur
function isAdmin() {
    return isAuthenticated() && hasPermission(ROLE_ADMIN);
}

// Fonction pour vérifier si l'utilisateur est gestionnaire ou plus
function isManager() {
    return isAuthenticated() && (hasPermission(ROLE_ADMIN) || hasPermission(ROLE_GESTIONNAIRE));
}
?>