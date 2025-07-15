<?php
require_once 'includes/config.php';

try {
    echo "Mise à jour de la structure de la table messages...\n";
    
    // Ajouter la colonne deleted_by_recipient si elle n'existe pas
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS deleted_by_recipient TINYINT(1) DEFAULT 0");
    echo "✓ Colonne deleted_by_recipient ajoutée\n";
    
    // Ajouter la colonne deleted_by_sender si elle n'existe pas
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS deleted_by_sender TINYINT(1) DEFAULT 0");
    echo "✓ Colonne deleted_by_sender ajoutée\n";
    
    // Ajouter un index sur recipient_id si pas déjà présent
    try {
        $pdo->exec("ALTER TABLE messages ADD INDEX idx_recipient_id (recipient_id)");
        echo "✓ Index sur recipient_id ajouté\n";
    } catch (Exception $e) {
        echo "Index sur recipient_id déjà présent ou erreur: " . $e->getMessage() . "\n";
    }
    
    // Ajouter un index sur sender_id si pas déjà présent
    try {
        $pdo->exec("ALTER TABLE messages ADD INDEX idx_sender_id (sender_id)");
        echo "✓ Index sur sender_id ajouté\n";
    } catch (Exception $e) {
        echo "Index sur sender_id déjà présent ou erreur: " . $e->getMessage() . "\n";
    }
    
    // Vérifier la nouvelle structure
    echo "\nNouvelle structure de la table messages:\n";
    $result = $pdo->query('DESCRIBE messages');
    echo "Field - Type - Null - Key - Default - Extra\n";
    echo "----------------------------------------------\n";
    while ($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Key'] . ' - ' . ($row['Default'] ?? 'NULL') . ' - ' . $row['Extra'] . "\n";
    }
    
    echo "\n✅ Mise à jour terminée avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la mise à jour: " . $e->getMessage() . "\n";
}
?>
