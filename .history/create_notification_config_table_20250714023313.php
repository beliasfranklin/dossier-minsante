<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    // Vérifier si la table notification_config existe
    $tableExists = fetchOne("SHOW TABLES LIKE 'notification_config'");
    
    if (!$tableExists) {
        echo "Création de la table notification_config...\n";
        
        $sql = "
        CREATE TABLE `notification_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` varchar(100) NOT NULL,
            `enabled` tinyint(1) DEFAULT 1,
            `email_enabled` tinyint(1) DEFAULT 0,
            `roles` JSON NULL,
            `config_data` JSON NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_type` (`type`),
            KEY `idx_type_enabled` (`type`, `enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        executeQuery($sql);
        echo "✓ Table notification_config créée avec succès\n";
        
        // Insérer des configurations par défaut
        $defaultConfigs = [
            [
                'type' => 'general',
                'enabled' => 1,
                'email_enabled' => 1,
                'config_data' => json_encode([
                    'notification_frequency' => 'immediate',
                    'batch_size' => 50,
                    'email_enabled' => true,
                    'quiet_hours_start' => '22:00',
                    'quiet_hours_end' => '08:00',
                    'weekend_notifications' => false
                ])
            ],
            [
                'type' => 'new_dossier',
                'enabled' => 1,
                'email_enabled' => 1,
                'roles' => json_encode([ROLE_ADMIN, ROLE_GESTIONNAIRE]),
                'config_data' => json_encode(['enabled' => true, 'email' => true])
            ],
            [
                'type' => 'dossier_status_change',
                'enabled' => 1,
                'email_enabled' => 1,
                'roles' => json_encode([ROLE_ADMIN, ROLE_GESTIONNAIRE]),
                'config_data' => json_encode(['enabled' => true, 'email' => true])
            ],
            [
                'type' => 'workflow_assignment',
                'enabled' => 1,
                'email_enabled' => 0,
                'roles' => json_encode([ROLE_ADMIN, ROLE_GESTIONNAIRE, ROLE_CONSULTANT]),
                'config_data' => json_encode(['enabled' => true, 'email' => false])
            ],
            [
                'type' => 'document_upload',
                'enabled' => 1,
                'email_enabled' => 0,
                'roles' => json_encode([ROLE_ADMIN, ROLE_GESTIONNAIRE]),
                'config_data' => json_encode(['enabled' => true, 'email' => false])
            ],
            [
                'type' => 'deadline_reminder',
                'enabled' => 1,
                'email_enabled' => 1,
                'roles' => json_encode([ROLE_ADMIN, ROLE_GESTIONNAIRE]),
                'config_data' => json_encode(['enabled' => true, 'email' => true, 'advance_days' => 3])
            ],
            [
                'type' => 'system_maintenance',
                'enabled' => 1,
                'email_enabled' => 1,
                'roles' => json_encode([ROLE_ADMIN]),
                'config_data' => json_encode(['enabled' => true, 'email' => true])
            ]
        ];
        
        foreach ($defaultConfigs as $config) {
            executeQuery(
                "INSERT INTO notification_config (type, enabled, email_enabled, roles, config_data) 
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $config['type'],
                    $config['enabled'],
                    $config['email_enabled'],
                    $config['roles'] ?? null,
                    $config['config_data']
                ]
            );
        }
        
        echo "✓ Configurations par défaut insérées\n";
    } else {
        echo "✓ Table notification_config existe déjà\n";
    }
    
    echo "\n=== INSTALLATION TERMINÉE ===\n";
    echo "La page de configuration des notifications est maintenant disponible à :\n";
    echo "http://localhost/dossier-minsante/modules/notifications/config.php\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>
