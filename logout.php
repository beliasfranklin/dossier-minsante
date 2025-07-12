<?php
declare(strict_types=1);
/**
 * Fichier de déconnexion - Ministère de la Santé Publique
 * Sécurité renforcée avec journalisation et protection CSRF
 */

// Affichage des erreurs PHP pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Chargement de la configuration
require_once __DIR__ . '/includes/config.php';

// Vérification de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,    // Enforce HTTPS
        'cookie_httponly' => true,  // Prevent JavaScript access
        'cookie_samesite' => 'Strict' // Protection CSRF
    ]);
}

// Journalisation de la déconnexion
if (isset($_SESSION['user_id'])) {
    // Récupération des infos utilisateur avant déconnexion
    $user = fetchOne("SELECT name, email FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Journalisation détaillée
    logAction(
        $_SESSION['user_id'], 
        'user_logout', 
        null, 
        "Déconnexion de " . ($user['name'] ?? 'inconnu') . " (" . ($user['email'] ?? 'inconnu') . ")"
    );
}

// Protection CSRF (si vous utilisez des tokens)
if (isset($_SESSION['csrf_token'])) {
    unset($_SESSION['csrf_token']);
}

// Destruction complète de la session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// Redémarre une session propre pour le message flash
session_start();
$_SESSION['flash']['success'] = "Vous avez été déconnecté avec succès.";

header("Location: /dossier-minsante/login.php", true, 303);
exit();