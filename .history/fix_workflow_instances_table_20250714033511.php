<?php
require_once 'includes/config.php';

try {
    echo "=== CORRECTION DE LA TABLE WORKFLOW_INSTANCES ===\n";
    
    // Vérifier si la colonne started_at existe
    $columns = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE 'started_at'");
    $startedAtExists = $columns->rowCount() > 0;
    
    if (!$startedAtExists) {
        echo "Colonne 'started_at' manquante. Ajout en cours...\n";
        
        // Ajouter la colonne started_at
        $pdo->exec("ALTER TABLE workflow_instances 
                   ADD COLUMN started_at timestamp NULL DEFAULT NULL 
                   AFTER role_requis");
        
        echo "✓ Colonne 'started_at' ajoutée avec succès\n";
    } else {
        echo "✓ Colonne 'started_at' existe déjà\n";
    }
    
    // Vérifier d'autres colonnes potentiellement manquantes
    $requiredColumns = [
        'approved_by' => 'int(11) DEFAULT NULL',
        'approved_at' => 'timestamp NULL DEFAULT NULL',
        'rejected_by' => 'int(11) DEFAULT NULL',
        'rejected_at' => 'timestamp NULL DEFAULT NULL',
        'delegated_to' => 'int(11) DEFAULT NULL',
        'delegated_at' => 'timestamp NULL DEFAULT NULL',
        'comments' => 'text',
        'rejection_reason' => 'text'
    ];
    
    foreach ($requiredColumns as $columnName => $columnDef) {
        $columns = $pdo->query("SHOW COLUMNS FROM workflow_instances LIKE '$columnName'");
        if ($columns->rowCount() == 0) {
            echo "Ajout de la colonne manquante '$columnName'...\n";
            $pdo->exec("ALTER TABLE workflow_instances ADD COLUMN $columnName $columnDef");
            echo "✓ Colonne '$columnName' ajoutée\n";
        }
    }
    
    // Mettre à jour les instances existantes sans started_at
    $updated = $pdo->exec("
        UPDATE workflow_instances 
        SET started_at = created_at 
        WHERE started_at IS NULL AND status IN ('active', 'approved', 'rejected')
    ");
    
    if ($updated > 0) {
        echo "✓ $updated instances mises à jour avec started_at = created_at\n";
    }
    
    echo "\n=== VÉRIFICATION FINALE ===\n";
    $result = $pdo->query("DESCRIBE workflow_instances");
    echo "Structure finale de la table workflow_instances :\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})" . 
             ($row['Null'] == 'YES' ? ' NULL' : ' NOT NULL') . 
             (isset($row['Default']) && $row['Default'] !== null ? " DEFAULT '{$row['Default']}'" : '') . "\n";
    }
    
    echo "\n✅ Correction terminée avec succès !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
?>
