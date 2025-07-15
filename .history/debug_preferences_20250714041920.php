<?php
// Diagnostic des préférences utilisateur
require_once 'includes/config.php';
session_start();

echo "<h2>🔍 Diagnostic des préférences utilisateur</h2>";

// Simuler une session pour les tests
$_SESSION['user_id'] = 1;
$_SESSION['authenticated'] = true;
$_SESSION['last_activity'] = time();

echo "<h3>1. Test de la base de données</h3>";

// Vérifier la table user_preferences
try {
    $stmt = $pdo->query("DESCRIBE user_preferences");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Table user_preferences existe<br>";
    echo "<strong>Structure :</strong><br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur table user_preferences : " . $e->getMessage() . "<br>";
    
    // Créer la table si elle n'existe pas
    echo "🔧 Création de la table...<br>";
    try {
        $pdo->exec("
            CREATE TABLE user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preference_key VARCHAR(50) NOT NULL,
                preference_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_preference (user_id, preference_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "✅ Table créée avec succès<br>";
    } catch (PDOException $e2) {
        echo "❌ Erreur création table : " . $e2->getMessage() . "<br>";
    }
}

echo "<h3>2. Test d'insertion de préférences</h3>";

// Test d'insertion manuelle
$testUserId = 1;
$testPreferences = [
    'theme' => 'dark',
    'language' => 'fr',
    'notifications_email' => '1',
    'dashboard_layout' => 'grid'
];

foreach ($testPreferences as $key => $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
        ");
        $stmt->execute([$testUserId, $key, $value]);
        echo "✅ Préférence '$key' = '$value' insérée<br>";
    } catch (PDOException $e) {
        echo "❌ Erreur insertion '$key' : " . $e->getMessage() . "<br>";
    }
}

echo "<h3>3. Test de récupération</h3>";

try {
    $stmt = $pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $preferences = [];
    while ($row = $stmt->fetch()) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
    
    echo "✅ Préférences récupérées :<br>";
    foreach ($preferences as $key => $value) {
        echo "- $key = $value<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur récupération : " . $e->getMessage() . "<br>";
}

echo "<h3>4. Test de simulation POST</h3>";

// Simuler une soumission de formulaire
$_POST = [
    'theme' => 'light',
    'language' => 'en',
    'notifications_email' => '1',
    'notifications_browser' => '1',
    'dashboard_layout' => 'list',
    'timezone' => 'Europe/Paris',
    'items_per_page' => '50'
];

echo "Données POST simulées :<br>";
foreach ($_POST as $key => $value) {
    echo "- $key = $value<br>";
}

// Test du traitement comme dans settings.php
$userId = $testUserId;
$preferences = [
    'theme' => $_POST['theme'] ?? 'light',
    'language' => $_POST['language'] ?? 'fr',
    'notifications_email' => isset($_POST['notifications_email']) ? 1 : 0,
    'notifications_browser' => isset($_POST['notifications_browser']) ? 1 : 0,
    'dashboard_layout' => $_POST['dashboard_layout'] ?? 'grid',
    'timezone' => $_POST['timezone'] ?? 'Africa/Douala',
    'items_per_page' => (int)($_POST['items_per_page'] ?? 20)
];

echo "<br><strong>Préférences traitées :</strong><br>";
foreach ($preferences as $key => $value) {
    echo "- $key = $value<br>";
}

try {
    foreach ($preferences as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
        ");
        $stmt->execute([$userId, $key, $value]);
    }
    echo "<br>✅ Toutes les préférences ont été sauvegardées !<br>";
} catch (PDOException $e) {
    echo "<br>❌ Erreur sauvegarde : " . $e->getMessage() . "<br>";
}

// Vérifier la sauvegarde
echo "<h3>5. Vérification finale</h3>";
try {
    $stmt = $pdo->prepare("SELECT preference_key, preference_value, updated_at FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    echo "Préférences après sauvegarde :<br>";
    while ($row = $stmt->fetch()) {
        echo "- " . $row['preference_key'] . " = " . $row['preference_value'] . " (MAJ: " . $row['updated_at'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur vérification : " . $e->getMessage() . "<br>";
}

echo "<br><div style='margin: 20px 0;'>";
echo "<a href='modules/users/settings.php' style='background: #16a085; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Tester la page Settings</a>";
echo "</div>";

// Nettoyer la session
unset($_SESSION['user_id']);
unset($_SESSION['authenticated']);
unset($_POST);
?>
