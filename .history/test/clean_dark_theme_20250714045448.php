<?php
/**
 * Script de nettoyage pour supprimer toutes les préférences de thème sombre
 */

require_once '../config.php';
require_once '../includes/preferences.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Nettoyage - Suppression Mode Sombre</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #007bff; }
        .warning { color: #ffc107; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>";

echo "<div class='container'>";
echo "<h1>🧹 Nettoyage du Mode Sombre</h1>";

// Vérifier la connexion
echo "<div class='card'>";
echo "<h2>1. Vérification de la Connexion</h2>";
try {
    if (isset($pdo)) {
        echo "<p class='success'>✅ Connexion à la base de données établie</p>";
    } else {
        echo "<p class='error'>❌ Impossible de se connecter à la base de données</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Nettoyer les préférences
echo "<div class='card'>";
echo "<h2>2. Nettoyage des Préférences</h2>";

if ($_POST && isset($_POST['clean'])) {
    try {
        // Compter les préférences de thème sombre existantes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferences WHERE preference_key = 'theme' AND preference_value = 'dark'");
        $stmt->execute();
        $darkCount = $stmt->fetchColumn();
        
        echo "<p class='info'>📊 Préférences de thème sombre trouvées: $darkCount</p>";
        
        if ($darkCount > 0) {
            // Mettre à jour toutes les préférences 'dark' vers 'light'
            $stmt = $pdo->prepare("UPDATE user_preferences SET preference_value = 'light' WHERE preference_key = 'theme' AND preference_value = 'dark'");
            $result = $stmt->execute();
            
            if ($result) {
                echo "<p class='success'>✅ $darkCount préférences de thème sombre converties vers le thème clair</p>";
            } else {
                echo "<p class='error'>❌ Erreur lors de la conversion</p>";
            }
        } else {
            echo "<p class='info'>ℹ️ Aucune préférence de thème sombre trouvée</p>";
        }
        
        // Vérifier le résultat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferences WHERE preference_key = 'theme' AND preference_value = 'dark'");
        $stmt->execute();
        $remainingDark = $stmt->fetchColumn();
        
        if ($remainingDark == 0) {
            echo "<p class='success'>✅ Nettoyage terminé ! Aucune préférence de thème sombre restante</p>";
        } else {
            echo "<p class='warning'>⚠️ $remainingDark préférences de thème sombre restantes</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du nettoyage: " . $e->getMessage() . "</p>";
    }
} else {
    // Afficher le formulaire de nettoyage
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferences WHERE preference_key = 'theme' AND preference_value = 'dark'");
        $stmt->execute();
        $darkCount = $stmt->fetchColumn();
        
        echo "<p class='info'>📊 Préférences de thème sombre trouvées: $darkCount</p>";
        
        if ($darkCount > 0) {
            echo "<form method='POST'>";
            echo "<p class='warning'>⚠️ Des préférences de thème sombre ont été trouvées en base de données.</p>";
            echo "<p>Voulez-vous les convertir toutes vers le thème clair ?</p>";
            echo "<button type='submit' name='clean' value='1' class='btn'>🧹 Nettoyer les Préférences Sombres</button>";
            echo "</form>";
        } else {
            echo "<p class='success'>✅ Aucune préférence de thème sombre trouvée</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Statistiques finales
echo "<div class='card'>";
echo "<h2>3. Statistiques des Thèmes</h2>";
try {
    $themes = $pdo->query("
        SELECT preference_value, COUNT(*) as count 
        FROM user_preferences 
        WHERE preference_key = 'theme' 
        GROUP BY preference_value
    ")->fetchAll();
    
    if (empty($themes)) {
        echo "<p class='info'>ℹ️ Aucune préférence de thème enregistrée</p>";
    } else {
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background: #f8f9fa;'><th style='padding: 10px; border: 1px solid #dee2e6;'>Thème</th><th style='padding: 10px; border: 1px solid #dee2e6;'>Nombre d'utilisateurs</th></tr>";
        foreach ($themes as $theme) {
            $icon = $theme['preference_value'] === 'light' ? '🌞' : ($theme['preference_value'] === 'auto' ? '🔄' : '❓');
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>$icon " . ucfirst($theme['preference_value']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: center;'>" . $theme['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Liens utiles
echo "<div class='card'>";
echo "<h2>4. Liens Utiles</h2>";
$baseUrl = 'http://localhost/dossier-minsante/';
echo "<p>🔗 <a href='{$baseUrl}modules/users/activity.php'>Page d'activité (sans mode sombre)</a></p>";
echo "<p>🔗 <a href='{$baseUrl}test/diagnostic_preferences.php'>Diagnostic du système</a></p>";
echo "<p>🔗 <a href='{$baseUrl}'>Retour à l'accueil</a></p>";
echo "</div>";

echo "</div></body></html>";
?>
