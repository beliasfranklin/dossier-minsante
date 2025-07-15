<?php
// Script pour forcer l'insertion de préférences de test
require_once 'includes/config.php';
session_start();

echo "<h2>🔧 Insertion forcée de préférences de test</h2>";

// ID utilisateur de test
$testUserId = 1;

// Préférences de test très visibles
$testPreferences = [
    'theme' => 'dark',
    'language' => 'en',
    'dashboard_layout' => 'list',
    'timezone' => 'Europe/Paris',
    'items_per_page' => 50,
    'notifications_email' => 0,
    'notifications_browser' => 1
];

echo "<h3>1. Suppression des anciennes préférences</h3>";
try {
    $stmt = $pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    echo "✅ Anciennes préférences supprimées<br>";
} catch (PDOException $e) {
    echo "❌ Erreur suppression : " . $e->getMessage() . "<br>";
}

echo "<h3>2. Insertion des nouvelles préférences</h3>";
foreach ($testPreferences as $key => $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$testUserId, $key, $value]);
        echo "✅ Inséré : $key = $value<br>";
    } catch (PDOException $e) {
        echo "❌ Erreur pour $key : " . $e->getMessage() . "<br>";
    }
}

echo "<h3>3. Vérification de l'insertion</h3>";
try {
    $stmt = $pdo->prepare("SELECT preference_key, preference_value, updated_at FROM user_preferences WHERE user_id = ? ORDER BY preference_key");
    $stmt->execute([$testUserId]);
    
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #2c3e50; color: white;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Clé</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Valeur</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Horodatage</th>";
    echo "</tr>";
    
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>" . htmlspecialchars($row['preference_key']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; background: #e8f5e8;'>" . htmlspecialchars($row['preference_value']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "❌ Erreur vérification : " . $e->getMessage() . "<br>";
}

echo "<h3>4. Test de la page settings avec ces valeurs</h3>";
echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
echo "<p><strong>Valeurs de test insérées :</strong></p>";
echo "<ul>";
echo "<li>🌙 <strong>Thème :</strong> Sombre (dark)</li>";
echo "<li>🇬🇧 <strong>Langue :</strong> Anglais (en)</li>";
echo "<li>📋 <strong>Layout :</strong> Liste (list)</li>";
echo "<li>🇫🇷 <strong>Timezone :</strong> Paris</li>";
echo "<li>📄 <strong>Items/page :</strong> 50</li>";
echo "<li>✉️ <strong>Email notif :</strong> Désactivées (0)</li>";
echo "<li>🖥️ <strong>Browser notif :</strong> Activées (1)</li>";
echo "</ul>";
echo "</div>";

// Simuler une session pour le test
$_SESSION['user_id'] = $testUserId;
$_SESSION['authenticated'] = true;
$_SESSION['last_activity'] = time();

echo "<div style='margin: 20px 0;'>";
echo "<a href='modules/users/settings.php' style='background: #16a085; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-right: 10px; font-weight: bold;'>🎯 TESTER LA PAGE SETTINGS</a>";
echo "<a href='modules/users/settings.php?debug=1' style='background: #e74c3c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-right: 10px;'>🐛 Settings mode debug</a>";
echo "<a href='test_preferences_apply.php' style='background: #9b59b6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>🔄 Re-diagnostiquer</a>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;'>";
echo "<h4>📋 Instructions de vérification :</h4>";
echo "<ol>";
echo "<li>Cliquez sur 'TESTER LA PAGE SETTINGS' ci-dessus</li>";
echo "<li>Vérifiez que les valeurs suivantes sont sélectionnées :</li>";
echo "<ul>";
echo "<li>Thème = <strong>Sombre</strong> (pas Clair)</li>";
echo "<li>Langue = <strong>English</strong> (pas Français)</li>";
echo "<li>Layout = <strong>Liste</strong> (pas Grille)</li>";
echo "<li>Timezone = <strong>Paris</strong> (pas Douala)</li>";
echo "<li>Items = <strong>50</strong> (pas 20)</li>";
echo "<li>Email notif = <strong>Décochée</strong></li>";
echo "<li>Browser notif = <strong>Cochée</strong></li>";
echo "</ul>";
echo "<li>Si ces valeurs ne s'affichent pas, il y a un problème dans le code</li>";
echo "</ol>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
h3 { color: #34495e; margin-top: 20px; }
h4 { color: #2c3e50; }
table { margin: 10px 0; }
</style>
