<?php
require_once '../includes/config.php';

try {
    // Vérifier si les colonnes existent déjà
    $result = executeQuery("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($result->rowCount() == 0) {
        // Ajouter les colonnes de récupération de mot de passe
        executeQuery("ALTER TABLE `users` 
                      ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `updated_at`,
                      ADD COLUMN `reset_expires` DATETIME NULL DEFAULT NULL AFTER `reset_token`");
        echo "✅ Colonnes reset_token et reset_expires ajoutées avec succès à la table users.\n";
    } else {
        echo "ℹ️ Les colonnes reset_token et reset_expires existent déjà.\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
}
?>
