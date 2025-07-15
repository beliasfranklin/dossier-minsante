<?php
require_once 'includes/config.php';

echo "=== DIAGNOSTIC PAGE SETTINGS ===\n\n";

// 1. Vérifier la table user_preferences
echo "1. Vérification de la table user_preferences...\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
    if ($result->rowCount() > 0) {
        echo "   ✅ Table user_preferences existe\n";
        $desc = $pdo->query('DESCRIBE user_preferences');
        echo "   Structure:\n";
        while ($row = $desc->fetch(PDO::FETCH_ASSOC)) {
            echo "   - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "   ❌ Table user_preferences n'existe pas\n";
        echo "   Création de la table...\n";
        
        $createTable = "CREATE TABLE user_preferences (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            preference_key varchar(100) NOT NULL,
            preference_value text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_pref (user_id, preference_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($createTable);
        echo "   ✅ Table user_preferences créée\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

// 2. Vérifier les fichiers requis
echo "\n2. Vérification des fichiers requis...\n";
$files = [
    'includes/header.php',
    'includes/auth.php', 
    'includes/db.php',
    'includes/footer.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file existe\n";
    } else {
        echo "   ❌ $file manquant\n";
    }
}

// 3. Test de la fonction d'authentification
echo "\n3. Test de l'authentification...\n";
if (function_exists('requireAuth')) {
    echo "   ✅ Fonction requireAuth() disponible\n";
} else {
    echo "   ❌ Fonction requireAuth() manquante\n";
}

// 4. Test de la fonction de traduction
echo "\n4. Test des fonctions de traduction...\n";
if (function_exists('t')) {
    echo "   ✅ Fonction t() disponible\n";
    echo "   Test: " . t('app_name') . "\n";
} else {
    echo "   ❌ Fonction t() manquante\n";
}

// 5. Vérifier les sessions
echo "\n5. Session...\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Session active\n";
} else {
    echo "   ❌ Session non active\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
?>
