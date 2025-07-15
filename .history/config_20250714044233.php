<?php
// Configuration de l'application
define('APP_NAME', 'Système de Gestion des Dossiers - MINSANTE');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/dossier-minsante/');

// Paramètres de base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'minsante_dossiers');
define('DB_USER', 'root');
define('DB_PASS', '');

// Paramètres de sécurité
define('PEPPER', 'votre_pepper_secret');
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes

// Niveaux d'accès
define('ROLE_ADMIN', 1);
define('ROLE_GESTIONNAIRE', 2);
define('ROLE_CONSULTANT', 3);

// Exigences de mot de passe
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL_CHAR', true);

define('DEBUG_MODE', true); // Mettre à false en production

// Limites d'upload
define('MAX_FILE_UPLOAD_SIZE', 5 * 1024 * 1024); // 5Mo
define('ALLOWED_FILE_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png'
]);


define('REFERENCE_FORMAT', [
    'Etude' => 'ETU{year}{seq}',
    'Projet' => 'PRJ{year}{seq}',
    'Administratif' => 'ADM{year}{seq}'
]);

// Redirection des erreurs
ini_set('log_errors', true);
ini_set('error_log', __DIR__ . '/../logs/error.log');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
?>