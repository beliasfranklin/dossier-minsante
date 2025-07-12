<?php
// Script de diagnostic pour les configurations d'échéances
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';

echo "<h2>🔍 Diagnostic Configurations Échéances</h2>";

try {
    // Vérifier si la table existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'echeances_config'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 10px 0;'>";
        echo "❌ <strong>La table 'echeances_config' n'existe pas !</strong>";
        echo "</div>";
        
        // Créer la table
        echo "<h3>Création de la table...</h3>";
        $createTable = "
        CREATE TABLE echeances_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_dossier VARCHAR(50) NOT NULL,
            service VARCHAR(50) NOT NULL,
            delai_jours INT NOT NULL,
            alertes JSON,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_type_service (type_dossier, service)
        )";
        
        $pdo->exec($createTable);
        echo "✅ Table créée avec succès<br>";
        
        // Insérer des données de base
        echo "<h3>Insertion de configurations par défaut...</h3>";
        $defaultConfigs = [
            ['Etude', 'DEP', 30, '[7, 3, 0]'],
            ['Projet', 'DEP', 45, '[14, 7, 3, 0]'],
            ['Administratif', 'RH', 15, '[7, 3, 1, 0]'],
            ['Autre', 'Finance', 20, '[7, 3, 0]']
        ];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO echeances_config (type_dossier, service, delai_jours, alertes) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($defaultConfigs as $config) {
            $insertStmt->execute($config);
            echo "✅ Ajouté: {$config[0]} / {$config[1]} - {$config[2]} jours<br>";
        }
        
    } else {
        echo "✅ La table 'echeances_config' existe";
    }
    
    // Vérifier le contenu
    echo "<h3>📋 Contenu de la table</h3>";
    $stmt = $pdo->query("SELECT * FROM echeances_config ORDER BY type_dossier, service");
    $configs = $stmt->fetchAll();
    
    if (empty($configs)) {
        echo "<div style='color: orange; background: #fff3cd; padding: 10px; margin: 10px 0;'>";
        echo "⚠️ <strong>La table existe mais est vide !</strong>";
        echo "</div>";
        
        // Insérer des données de base
        echo "<h4>Insertion de configurations par défaut...</h4>";
        $defaultConfigs = [
            ['Etude', 'DEP', 30, '[7, 3, 0]'],
            ['Projet', 'DEP', 45, '[14, 7, 3, 0]'],
            ['Administratif', 'RH', 15, '[7, 3, 1, 0]'],
            ['Autre', 'Finance', 20, '[7, 3, 0]']
        ];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO echeances_config (type_dossier, service, delai_jours, alertes) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($defaultConfigs as $config) {
            $insertStmt->execute($config);
            echo "✅ Ajouté: {$config[0]} / {$config[1]} - {$config[2]} jours<br>";
        }
        
        // Re-vérifier le contenu
        $stmt = $pdo->query("SELECT * FROM echeances_config ORDER BY type_dossier, service");
        $configs = $stmt->fetchAll();
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>Type</th>";
    echo "<th style='padding: 8px;'>Service</th>";
    echo "<th style='padding: 8px;'>Délai (jours)</th>";
    echo "<th style='padding: 8px;'>Alertes</th>";
    echo "<th style='padding: 8px;'>Actif</th>";
    echo "</tr>";
    
    foreach ($configs as $config) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$config['id']}</td>";
        echo "<td style='padding: 8px;'>{$config['type_dossier']}</td>";
        echo "<td style='padding: 8px;'>{$config['service']}</td>";
        echo "<td style='padding: 8px;'>{$config['delai_jours']}</td>";
        echo "<td style='padding: 8px;'>{$config['alertes']}</td>";
        echo "<td style='padding: 8px;'>" . ($config['actif'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>✅ Total: " . count($configs) . " configuration(s) trouvée(s)</strong>";
    echo "</div>";
    
    // Test de la requête utilisée dans config.php
    echo "<h3>🧪 Test de la requête config.php</h3>";
    
    $stmt = $pdo->prepare("SELECT * FROM echeances_config ORDER BY type_dossier, service");
    $stmt->execute();
    $configs_test = $stmt->fetchAll();
    
    echo "<p>Requête optimisée retourne: <strong>" . count($configs_test) . " résultat(s)</strong></p>";
    
    if (count($configs_test) > 0) {
        echo "<div style='color: green;'>✅ La requête fonctionne correctement</div>";
    } else {
        echo "<div style='color: red;'>❌ Problème avec la requête</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 10px 0;'>";
    echo "❌ <strong>Erreur PDO:</strong> " . $e->getMessage();
    echo "</div>";
    
    if (strpos($e->getMessage(), 'echeances_config') !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<p>La table n'existe pas. Créons-la...</p>";
        // Script de création sera ajouté ici
    }
}

echo "<hr>";
echo "<p><strong>Diagnostic terminé le " . date('Y-m-d H:i:s') . "</strong></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { text-align: left; }
</style>
