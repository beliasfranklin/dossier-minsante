<?php
require_once 'includes/config.php';

try {
    $result = $pdo->query('DESCRIBE workflow_instances');
    echo "Structure de la table workflow_instances:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n--- Échantillon de données ---\n";
    $sample = $pdo->query('SELECT * FROM workflow_instances LIMIT 3');
    while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Colonnes: " . implode(', ', array_keys($row)) . "\n";
        break; // Juste pour voir les colonnes
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
