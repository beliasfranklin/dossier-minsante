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
if (!defined('MIN_PASSWORD_LENGTH')) {
    define('MIN_PASSWORD_LENGTH', 8);
}
if (!defined('PASSWORD_REQUIRE_UPPERCASE')) {
    define('PASSWORD_REQUIRE_UPPERCASE', true);
}
if (!defined('PASSWORD_REQUIRE_NUMBER')) {
    define('PASSWORD_REQUIRE_NUMBER', true);
}
if (!defined('PASSWORD_REQUIRE_SPECIAL_CHAR')) {
    define('PASSWORD_REQUIRE_SPECIAL_CHAR', true);
}

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true); // Mettre à false en production
}

// Limites d'upload
if (!defined('MAX_FILE_UPLOAD_SIZE')) {
    define('MAX_FILE_UPLOAD_SIZE', 5 * 1024 * 1024); // 5Mo
}
if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ]);
}

if (!defined('REFERENCE_FORMAT')) {
    define('REFERENCE_FORMAT', [
        'Etude' => 'ETU{year}{seq}',
        'Projet' => 'PRJ{year}{seq}',
        'Administratif' => 'ADM{year}{seq}'
    ]);
}

// Configuration email/SMTP pour MINSANTE
// Configuration principale - serveur SMTP du MINSANTE (PRODUCTION)
/*
define('SMTP_HOST', 'mail.minsante.gov.cm'); // Serveur SMTP officiel du MINSANTE
define('SMTP_PORT', 587); // Port SMTP avec STARTTLS
define('SMTP_SECURITY', 'tls'); // 'tls' ou 'ssl'
define('SMTP_USER', 'dossiers@minsante.gov.cm'); // Compte email système
define('SMTP_PASS', 'mot_de_passe_securise'); // À configurer avec le vrai mot de passe
define('SMTP_FROM', 'dossiers@minsante.gov.cm'); // Adresse expéditeur
define('SMTP_FROM_NAME', 'Système de Gestion des Dossiers - MINSANTE');
*/

// Configuration alternative pour Gmail (test/développement) - ACTIVÉE
// Remplacez les valeurs par vos vraies informations Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'beliasfranklin@gmail.com'); // Votre email Gmail
define('SMTP_PASS', 'votre-mot-de-passe-app-gmail'); // MOT DE PASSE D'APPLICATION Gmail (16 caractères)
define('SMTP_FROM', 'beliasfranklin@gmail.com'); // Votre email Gmail
define('SMTP_FROM_NAME', 'MINSANTE - Test');
define('SMTP_TIMEOUT', 30); // Timeout de connexion SMTP
define('SMTP_DEBUG', true); // Activer pour diagnostiquer les problèmes SMTP

// Configuration alternative pour Office365 (si utilisé par le MINSANTE)
/*
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'dossiers@minsante.gov.cm');
define('SMTP_PASS', 'mot_de_passe');
define('SMTP_FROM', 'dossiers@minsante.gov.cm');
define('SMTP_FROM_NAME', 'MINSANTE - Gestion des Dossiers');
*/

// Configuration email pour les notifications et administration
define('EMAIL_ADMIN', 'admin.dossiers@minsante.gov.cm'); // Email administrateur principal
define('EMAIL_SUPPORT', 'support.dossiers@minsante.gov.cm'); // Email support technique
define('EMAIL_DIRECTOR', 'directeur.etudes@minsante.gov.cm'); // Email directeur des études
define('EMAIL_NOTIFICATIONS_ENABLED', true); // Activer les notifications par email
define('EMAIL_DEADLINE_ALERTS', true); // Activer les alertes d'échéance
define('EMAIL_USER_REGISTRATION', true); // Notifier les nouvelles inscriptions
define('EMAIL_WORKFLOW_UPDATES', true); // Notifier les changements de statut

// Configuration WhatsApp (API Business)
define('WHATSAPP_ENABLED', false); // À activer quand l'API est configurée
define('WHATSAPP_TOKEN', ''); // Token de l'API WhatsApp Business
define('WHATSAPP_PHONE_ID', ''); // ID du numéro de téléphone WhatsApp Business
define('WHATSAPP_VERIFY_TOKEN', ''); // Token de vérification webhook

// Templates d'emails professionnels
define('EMAIL_RESET_SUBJECT', '[MINSANTE] Réinitialisation de votre mot de passe');
define('EMAIL_NOTIFICATION_SUBJECT', '[MINSANTE] Notification de dossier');
define('EMAIL_DEADLINE_SUBJECT', '[MINSANTE] Alerte d\'échéance de dossier');
define('EMAIL_WELCOME_SUBJECT', '[MINSANTE] Bienvenue dans le système de gestion des dossiers');
define('EMAIL_STATUS_UPDATE_SUBJECT', '[MINSANTE] Mise à jour du statut de votre dossier');
define('EMAIL_NEW_MESSAGE_SUBJECT', '[MINSANTE] Nouveau message dans votre dossier');

// Configuration des notifications système
define('NOTIFY_DEADLINE_DAYS', [30, 15, 7, 3, 1]); // Jours avant échéance pour notification
define('NOTIFY_ADMIN_NEW_USER', true); // Notifier l'admin des nouvelles inscriptions
define('NOTIFY_WORKFLOW_CHANGES', true); // Notifier les changements de workflow
define('NOTIFY_FILE_UPLOADS', false); // Notifier les uploads de fichiers

// Redirection des erreurs
ini_set('log_errors', true);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// IMPORTANT: Inclure le gestionnaire de langues en premier
require_once __DIR__ . '/language_manager.php';

// Inclure les autres fichiers de configuration (sauf db.php pour éviter les dépendances circulaires)
require_once 'functions.php';
require_once 'auth.php';

// Configuration finale
define('NOTIFICATION_EMAIL_ENABLED', true);
?>