<?php
echo "<h2>🔧 Guide de test des préférences</h2>";

echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #27ae60;'>";
echo "<h3>✅ Corrections apportées :</h3>";
echo "<ol>";
echo "<li><strong>Traitement sélectif</strong> : Seuls les champs soumis sont mis à jour</li>";
echo "<li><strong>Conservation des valeurs</strong> : Les autres préférences restent intactes</li>";
echo "<li><strong>Gestion des checkboxes</strong> : Traitement correct des notifications</li>";
echo "<li><strong>Messages informatifs</strong> : Indication du nombre de paramètres modifiés</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;'>";
echo "<h3>📋 Instructions de test :</h3>";
echo "<ol>";
echo "<li>Connectez-vous à votre compte</li>";
echo "<li>Allez sur la page des préférences</li>";
echo "<li>Modifiez une section à la fois (ex: changez le thème)</li>";
echo "<li>Cliquez sur 'Enregistrer' de cette section</li>";
echo "<li>Vérifiez que le message de succès s'affiche</li>";
echo "<li>Modifiez une autre section et vérifiez que la première reste sauvegardée</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #17a2b8;'>";
echo "<h3>🔍 Mode debug :</h3>";
echo "<p>Ajoutez <code>?debug=1</code> à l'URL pour voir quels champs sont traités :</p>";
echo "<p><code>modules/users/settings.php?debug=1</code></p>";
echo "</div>";

echo "<div style='margin: 20px 0;'>";
echo "<a href='login.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-right: 10px;'>🔑 Se connecter</a>";
echo "<a href='modules/users/settings.php' style='background: #16a085; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-right: 10px;'>⚙️ Page Préférences</a>";
echo "<a href='modules/users/settings.php?debug=1' style='background: #9b59b6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>🐛 Mode Debug</a>";
echo "</div>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #dee2e6;'>";
echo "<h3>🧪 Tests disponibles :</h3>";
echo "<ul>";
echo "<li><a href='test_settings_forms.php'>Formulaires de test</a> - Tests directs des modifications</li>";
echo "<li><a href='debug_preferences.php'>Diagnostic complet</a> - Vérification de la base de données</li>";
echo "<li><a href='test_security_fixes.php'>Test sécurité</a> - Vérification des corrections</li>";
echo "</ul>";
echo "</div>";

session_start();
if (isset($_SESSION['user_id'])) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745;'>";
    echo "✅ <strong>Session active</strong> - Utilisateur ID: " . $_SESSION['user_id'];
    echo "<br><a href='modules/users/settings.php' style='color: #155724; font-weight: bold;'>Aller directement aux préférences →</a>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545;'>";
    echo "❌ <strong>Non connecté</strong> - Vous devez vous connecter pour tester les préférences";
    echo "<br><a href='login.php' style='color: #721c24; font-weight: bold;'>Se connecter →</a>";
    echo "</div>";
}
?>

<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 30px; background: #f8f9fa; }
h2 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
h3 { color: #34495e; margin-top: 15px; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
a { text-decoration: none; }
a:hover { opacity: 0.8; }
</style>
