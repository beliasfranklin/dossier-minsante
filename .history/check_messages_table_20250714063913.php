<?php
require_once 'includes/config.php';

try {
    // Vérifier la structure de la table messages
    $result = $pdo->query('DESCRIBE messages');
    echo "Structure de la table messages:\n";
    echo "Field - Type - Null - Key - Default - Extra\n";
    echo "----------------------------------------------\n";
    while ($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Key'] . ' - ' . ($row['Default'] ?? 'NULL') . ' - ' . $row['Extra'] . "\n";
    }
    
    echo "\n";
    
    // Vérifier si la table existe
    $tables = $pdo->query("SHOW TABLES LIKE 'messages'")->fetchAll();
    if (empty($tables)) {
        echo "La table 'messages' n'existe pas!\n";
    } else {
        echo "La table 'messages' existe.\n";
    }
    
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
}
?>
