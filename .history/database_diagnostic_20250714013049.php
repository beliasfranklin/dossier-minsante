<?php
/**
 * Diagnostic complet de la base de données
 * Vérifie les colonnes manquantes et propose des corrections
 */
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h2>🔍 Diagnostic de la base de données - Table users</h2>";

try {
    // 1. Vérifier la structure actuelle
    echo "<h3>📋 Structure actuelle de la table users</h3>";
    $stmt = $pdo->query('DESCRIBE users');
    $actualColumns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    
    $existingColumns = [];
    foreach ($actualColumns as $column) {
        $existingColumns[] = $column['Field'];
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Colonnes attendues vs existantes
    echo "<h3>🔍 Analyse des colonnes</h3>";
    $expectedColumns = [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'external_id' => 'VARCHAR(50) - ID externe du système RH',
        'name' => 'VARCHAR(255) NOT NULL - Nom de famille',
        'prenom' => 'VARCHAR(255) - Prénom',
        'email' => 'VARCHAR(255) NOT NULL UNIQUE - Email',
        'password' => 'VARCHAR(255) - Mot de passe hashé',
        'role' => 'INT - Rôle utilisateur (1=Admin, 2=Gestionnaire, 3=Consultant)',
        'department' => 'VARCHAR(255) - Département/Service',
        'active' => 'TINYINT(1) DEFAULT 1 - Statut actif',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP - Date de création',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'last_login' => 'TIMESTAMP NULL - Dernière connexion',
        'reset_token' => 'VARCHAR(255) NULL - Token de reset mot de passe',
        'reset_expires' => 'TIMESTAMP NULL - Expiration du token'
    ];
    
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>";
    
    // Colonnes existantes
    echo "<div>";
    echo "<h4 style='color: green;'>✅ Colonnes existantes</h4>";
    echo "<ul>";
    foreach ($existingColumns as $col) {
        echo "<li style='color: green;'><strong>$col</strong>";
        if (isset($expectedColumns[$col])) {
            echo " - " . $expectedColumns[$col];
        }
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Colonnes manquantes
    echo "<div>";
    echo "<h4 style='color: red;'>❌ Colonnes manquantes</h4>";
    $missingColumns = array_diff(array_keys($expectedColumns), $existingColumns);
    if (empty($missingColumns)) {
        echo "<p style='color: green;'>✅ Toutes les colonnes attendues sont présentes!</p>";
    } else {
        echo "<ul>";
        foreach ($missingColumns as $col) {
            echo "<li style='color: red;'><strong>$col</strong> - " . $expectedColumns[$col] . "</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    echo "</div>";
    
    // 3. Script de correction automatique
    if (!empty($missingColumns)) {
        echo "<h3>🔧 Script de correction SQL</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<p><strong>Exécutez ces commandes SQL pour ajouter les colonnes manquantes :</strong></p>";
        echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
        
        $sqlCommands = [
            'external_id' => "ALTER TABLE users ADD COLUMN external_id VARCHAR(50) NULL COMMENT 'ID externe du système RH';",
            'prenom' => "ALTER TABLE users ADD COLUMN prenom VARCHAR(255) NULL COMMENT 'Prénom de l\'utilisateur';",
            'department' => "ALTER TABLE users ADD COLUMN department VARCHAR(255) NULL COMMENT 'Département/Service';",
            'active' => "ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1 COMMENT 'Statut actif';",
            'created_at' => "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création';",
            'updated_at' => "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de mise à jour';",
            'last_login' => "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL COMMENT 'Dernière connexion';",
            'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL COMMENT 'Token de reset mot de passe';",
            'reset_expires' => "ALTER TABLE users ADD COLUMN reset_expires TIMESTAMP NULL COMMENT 'Expiration du token';"
        ];
        
        foreach ($missingColumns as $col) {
            if (isset($sqlCommands[$col])) {
                echo $sqlCommands[$col] . "\n";
            }
        }
        echo "</pre>";
        
        // Bouton pour exécuter automatiquement
        echo "<form method='post' style='margin-top: 15px;'>";
        echo "<button type='submit' name='fix_columns' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
        echo "🔧 Exécuter les corrections automatiquement";
        echo "</button>";
        echo "<p style='color: #dc3545; font-size: 0.9em; margin-top: 10px;'>";
        echo "⚠️ <strong>Attention :</strong> Cette action modifiera la structure de la base de données. Assurez-vous d'avoir une sauvegarde.";
        echo "</p>";
        echo "</form>";
    }
    
    // 4. Test de données d'exemple
    echo "<h3>📊 Données d'exemple</h3>";
    try {
        $sampleUsers = fetchAll("SELECT * FROM users LIMIT 3");
        if ($sampleUsers) {
            echo "<div style='overflow-x: auto;'>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%; font-size: 0.9em;'>";
            echo "<tr style='background: #f8f9fa;'>";
            foreach (array_keys($sampleUsers[0]) as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>";
            
            foreach ($sampleUsers as $user) {
                echo "<tr>";
                foreach ($user as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        } else {
            echo "<p style='color: orange;'>⚠️ Aucune donnée utilisateur trouvée.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la récupération des données: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
}

// Traitement de la correction automatique
if (isset($_POST['fix_columns'])) {
    echo "<h3>🔧 Exécution des corrections...</h3>";
    
    $sqlCommands = [
        'external_id' => "ALTER TABLE users ADD COLUMN external_id VARCHAR(50) NULL COMMENT 'ID externe du système RH'",
        'prenom' => "ALTER TABLE users ADD COLUMN prenom VARCHAR(255) NULL COMMENT 'Prénom de l\'utilisateur'",
        'department' => "ALTER TABLE users ADD COLUMN department VARCHAR(255) NULL COMMENT 'Département/Service'",
        'active' => "ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1 COMMENT 'Statut actif'",
        'created_at' => "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création'",
        'updated_at' => "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de mise à jour'",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL COMMENT 'Dernière connexion'",
        'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL COMMENT 'Token de reset mot de passe'",
        'reset_expires' => "ALTER TABLE users ADD COLUMN reset_expires TIMESTAMP NULL COMMENT 'Expiration du token'"
    ];
    
    foreach ($sqlCommands as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur pour la colonne '$column': " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<p><strong>🔄 <a href=''>Actualiser la page</a> pour voir les changements.</strong></p>";
}

echo "<hr>";
echo "<h3>🔗 Actions rapides</h3>";
echo "<p>";
echo "<a href='modules/users/list.php' style='background: #007bff; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; margin-right: 10px;'>👥 Liste des utilisateurs</a>";
echo "<a href='test_database_functions.php' style='background: #28a745; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; margin-right: 10px;'>🧪 Test des fonctions</a>";
echo "<a href='check_users_structure.php' style='background: #6f42c1; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none;'>🔍 Structure détaillée</a>";
echo "</p>";
?>
