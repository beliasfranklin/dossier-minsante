<?php
// Diagnostic pour la page de sécurité
require_once 'includes/config.php';

echo "<h2>Diagnostic Page Sécurité</h2>";

// Test de la constante PEPPER
echo "✅ PEPPER défini : " . (defined('PEPPER') ? 'Oui' : 'Non') . "<br>";

// Test des tables nécessaires
$tables_to_check = ['users', 'user_sessions'];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table $table existe<br>";
            
            // Pour user_sessions, vérifier la structure
            if ($table === 'user_sessions') {
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "   Colonnes : " . implode(', ', $columns) . "<br>";
            }
        } else {
            echo "❌ Table $table manquante<br>";
            
            // Créer la table user_sessions si elle manque
            if ($table === 'user_sessions') {
                echo "   🔧 Création de la table user_sessions...<br>";
                $pdo->exec("
                    CREATE TABLE user_sessions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        session_token VARCHAR(255) NOT NULL,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP NOT NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                echo "   ✅ Table user_sessions créée<br>";
            }
        }
    } catch (PDOException $e) {
        echo "❌ Erreur pour la table $table : " . $e->getMessage() . "<br>";
    }
}

// Test de la fonction password_verify
echo "✅ Fonction password_verify disponible : " . (function_exists('password_verify') ? 'Oui' : 'Non') . "<br>";

// Test des fichiers d'inclusion
$files_to_check = [
    'includes/header.php',
    'includes/footer.php',
    'includes/auth.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ Fichier trouvé : $file<br>";
    } else {
        echo "❌ Fichier manquant : $file<br>";
    }
}

echo "<br><a href='modules/users/security.php' style='background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>➡️ Tester la page Sécurité</a>";
?>
