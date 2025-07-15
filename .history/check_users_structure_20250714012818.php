<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h2>Structure de la table users</h2>";

try {
    $stmt = $pdo->query('DESCRIBE users');
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Nom de colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Tester quelques requêtes pour voir ce qui fonctionne
    echo "<h3>Test de requêtes de base</h3>";
    
    // Test simple avec colonnes de base
    try {
        $users = fetchAll("SELECT id, name, email FROM users LIMIT 3");
        echo "<p style='color: green;'>✅ Requête basique fonctionne - " . count($users) . " utilisateurs</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur requête basique: " . $e->getMessage() . "</p>";
    }
    
    // Test avec toutes les colonnes
    try {
        $users = fetchAll("SELECT * FROM users LIMIT 1");
        if ($users) {
            echo "<h4>Exemple d'enregistrement (toutes colonnes):</h4>";
            echo "<pre>";
            print_r($users[0]);
            echo "</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur SELECT * : " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>
