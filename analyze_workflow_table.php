<?php
require_once 'includes/config.php';

try {
    echo "=== ANALYSE DE LA TABLE WORKFLOW_INSTANCES ===\n\n";
    
    // Vérifier si la table existe
    $tables = $pdo->query("SHOW TABLES LIKE 'workflow_instances'");
    if ($tables->rowCount() == 0) {
        echo "❌ Table 'workflow_instances' n'existe pas !\n";
        echo "Création de la table...\n";
        
        // Créer la table complète
        $createTable = "CREATE TABLE workflow_instances (
            id int(11) NOT NULL AUTO_INCREMENT,
            dossier_id int(11) NOT NULL,
            workflow_step_id int(11) DEFAULT NULL,
            status enum('pending','active','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            ordre int(11) DEFAULT 1,
            role_requis int(11) DEFAULT NULL,
            started_at timestamp NULL DEFAULT NULL,
            approved_by int(11) DEFAULT NULL,
            approved_at timestamp NULL DEFAULT NULL,
            comments text,
            rejected_by int(11) DEFAULT NULL,
            rejected_at timestamp NULL DEFAULT NULL,
            rejection_reason text,
            delegated_to int(11) DEFAULT NULL,
            delegated_at timestamp NULL DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            data longtext,
            assigned_to int(11) DEFAULT NULL,
            created_by int(11) DEFAULT NULL,
            completed_at timestamp NULL DEFAULT NULL,
            completed_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_dossier_id (dossier_id),
            KEY idx_status (status),
            KEY idx_workflow_step_id (workflow_step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTable);
        echo "✓ Table créée avec succès\n\n";
    }
    
    // Afficher la structure actuelle
    $result = $pdo->query("DESCRIBE workflow_instances");
    echo "Structure actuelle de la table workflow_instances :\n";
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
        echo "- {$row['Field']} ({$row['Type']})" . 
             ($row['Null'] == 'YES' ? ' NULL' : ' NOT NULL') . 
             ($row['Default'] !== null ? " DEFAULT '{$row['Default']}'" : '') . "\n";
    }
    
    echo "\n=== VÉRIFICATION DES COLONNES REQUISES ===\n";
    
    // Colonnes requises pour les requêtes SQL
    $requiredColumns = [
        'started_at' => 'timestamp NULL DEFAULT NULL',
        'approved_at' => 'timestamp NULL DEFAULT NULL', 
        'rejected_at' => 'timestamp NULL DEFAULT NULL',
        'approved_by' => 'int(11) DEFAULT NULL',
        'rejected_by' => 'int(11) DEFAULT NULL',
        'workflow_step_id' => 'int(11) DEFAULT NULL'
    ];
    
    $missingColumns = [];
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $columns)) {
            $missingColumns[] = $columnName;
            echo "❌ Colonne manquante : $columnName\n";
        } else {
            echo "✓ Colonne présente : $columnName\n";
        }
    }
    
    // Ajouter les colonnes manquantes
    if (!empty($missingColumns)) {
        echo "\n=== AJOUT DES COLONNES MANQUANTES ===\n";
        foreach ($missingColumns as $columnName) {
            try {
                $columnDef = $requiredColumns[$columnName];
                $sql = "ALTER TABLE workflow_instances ADD COLUMN $columnName $columnDef";
                echo "Ajout de $columnName...\n";
                $pdo->exec($sql);
                echo "✓ Colonne $columnName ajoutée\n";
            } catch (Exception $e) {
                echo "❌ Erreur lors de l'ajout de $columnName : " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Analyse terminée !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur générale : " . $e->getMessage() . "\n";
}
?>
