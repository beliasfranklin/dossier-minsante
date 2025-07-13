<?php
/**
 * Script pour vérifier et corriger la base de données
 */

require_once 'includes/config.php';

try {
    echo "🔍 Vérification de la base de données...\n\n";
    
    // Vérifier la connexion
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "📊 Base de données actuelle: " . $result['current_db'] . "\n\n";
    
    // Lister toutes les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables existantes:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
    
    // Vérifier spécifiquement la table attachments
    $stmt = $pdo->query("SHOW TABLES LIKE 'attachments'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'attachments' existe.\n";
        
        try {
            $stmt = $pdo->query("DESCRIBE attachments");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\n📋 Structure de la table 'attachments':\n";
            foreach ($columns as $column) {
                echo "  - {$column['Field']} ({$column['Type']})\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur lors de la lecture de la structure: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Table 'attachments' n'existe pas.\n";
        echo "🔨 Tentative de création...\n";
        
        $sql = "CREATE TABLE attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dossier_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            INDEX idx_dossier (dossier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Table 'attachments' créée avec succès.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur PDO: " . $e->getMessage() . "\n";
    echo "🔍 Code d'erreur: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
}
?>
