<?php
/**
 * Script pour créer automatiquement les tables nécessaires pour le module Analytics
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';

try {
    global $pdo;
    
    echo "<h1>Création des tables pour le module Analytics</h1>";
    
    // Vérifier la connexion
    if (!$pdo) {
        throw new Exception("Connexion PDO non disponible");
    }
    
    echo "<p>✓ Connexion à la base de données OK</p>";
    
    // Créer la table dossiers
    $createDossiers = "
        CREATE TABLE IF NOT EXISTS `dossiers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL DEFAULT '',
            `description` text,
            `status` varchar(50) NOT NULL DEFAULT 'en_cours',
            `priority` varchar(20) NOT NULL DEFAULT 'medium',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deadline` date DEFAULT NULL,
            `responsable_id` int(11) DEFAULT NULL,
            `service` varchar(100) DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `external_id` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_priority` (`priority`),
            KEY `idx_responsable` (`responsable_id`),
            KEY `idx_created` (`created_at`),
            KEY `idx_deadline` (`deadline`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createDossiers);
    echo "<p>✓ Table 'dossiers' créée ou vérifiée</p>";
    
    // Créer la table users si elle n'existe pas
    $createUsers = "
        CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` varchar(50) NOT NULL DEFAULT 'user',
            `service` varchar(100) DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `prenom` varchar(100) DEFAULT NULL,
            `external_id` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `idx_role` (`role`),
            KEY `idx_active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createUsers);
    echo "<p>✓ Table 'users' créée ou vérifiée</p>";
    
    // Vérifier si nous avons des données
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers");
    $stmt->execute();
    $dossiersCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $usersCount = $stmt->fetchColumn();
    
    echo "<p>📊 Nombre de dossiers : " . $dossiersCount . "</p>";
    echo "<p>👥 Nombre d'utilisateurs : " . $usersCount . "</p>";
    
    // Insérer des données de test si les tables sont vides
    if ($dossiersCount == 0) {
        echo "<p>🔄 Insertion de données de test pour les dossiers...</p>";
        
        $insertDossiers = "
            INSERT INTO `dossiers` (`title`, `description`, `status`, `priority`, `responsable_id`, `service`, `deadline`) VALUES
            ('Dossier Test 1', 'Description du dossier de test 1', 'en_cours', 'high', 1, 'IT', DATE_ADD(NOW(), INTERVAL 7 DAY)),
            ('Dossier Test 2', 'Description du dossier de test 2', 'valide', 'medium', 1, 'RH', DATE_ADD(NOW(), INTERVAL 3 DAY)),
            ('Dossier Test 3', 'Description du dossier de test 3', 'en_cours', 'urgent', 1, 'Finance', DATE_SUB(NOW(), INTERVAL 2 DAY)),
            ('Dossier Test 4', 'Description du dossier de test 4', 'rejete', 'low', 1, 'IT', DATE_ADD(NOW(), INTERVAL 10 DAY)),
            ('Dossier Test 5', 'Description du dossier de test 5', 'en_cours', 'high', 1, 'RH', DATE_ADD(NOW(), INTERVAL 5 DAY))
        ";
        
        $pdo->exec($insertDossiers);
        echo "<p>✓ Données de test insérées pour les dossiers</p>";
    }
    
    if ($usersCount == 0) {
        echo "<p>🔄 Insertion de données de test pour les utilisateurs...</p>";
        
        $insertUsers = "
            INSERT INTO `users` (`name`, `email`, `password`, `role`, `service`, `department`, `prenom`) VALUES
            ('Admin Test', 'admin@test.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'IT', 'Informatique', 'Admin'),
            ('User Test', 'user@test.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'RH', 'Ressources Humaines', 'User')
        ";
        
        $pdo->exec($insertUsers);
        echo "<p>✓ Données de test insérées pour les utilisateurs</p>";
    }
    
    echo "<h2>🎉 Configuration terminée avec succès !</h2>";
    echo "<p><a href='test_analytics.php'>Tester le module Analytics</a></p>";
    echo "<p><a href='index.php'>Accéder au module Analytics</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erreur : " . $e->getMessage() . "</p>";
    echo "<pre>Trace: " . $e->getTraceAsString() . "</pre>";
}
?>
