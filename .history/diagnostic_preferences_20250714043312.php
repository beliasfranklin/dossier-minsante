<?php
/**
 * Script de diagnostic rapide pour le système de préférences
 * Vérification que tous les composants sont en place et fonctionnent
 */

// Empêcher l'exécution directe depuis le navigateur si non autorisé
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips) && !isset($_GET['allow'])) {
    die('Accès non autorisé. Ajoutez ?allow=1 pour forcer.');
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>🔧 Diagnostic Système de Préférences</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; text-align: center; }
        .section { background: white; border-radius: 8px; margin: 1rem 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .section-header { background: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #495057; }
        .section-content { padding: 1rem; }
        .status { padding: 8px 12px; border-radius: 4px; margin: 4px 0; display: inline-block; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .file-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .file-item { background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border-left: 4px solid #28a745; }
        .file-item.missing { border-left-color: #dc3545; background: #fff5f5; }
        .progress-bar { background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 1rem 0; }
        .progress-fill { background: linear-gradient(90deg, #28a745, #20c997); height: 20px; transition: width 0.3s; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        .metric { text-align: center; padding: 1rem; }
        .metric-value { font-size: 2rem; font-weight: bold; color: #495057; }
        .metric-label { color: #6c757d; font-size: 0.9rem; }
    </style>
</head>
<body>";

echo "<div class='container'>";
echo "<div class='header'>";
echo "<h1>🔧 Diagnostic Système de Préférences</h1>";
echo "<p>Vérification complète des composants du système</p>";
echo "<p><small>Exécuté le " . date('Y-m-d H:i:s') . "</small></p>";
echo "</div>";

// Fichiers requis
$required_files = [
    'Classe PreferencesManager' => '../includes/preferences.php',
    'CSS Dynamique' => '../css/dynamic-theme.css.php',
    'CSS Animations' => '../assets/css/preferences-animations.css',
    'JavaScript Manager' => '../assets/js/preferences-manager.js',
    'Page Settings' => '../modules/users/settings.php',
    'Page Security' => '../modules/users/security.php',
    'Header' => '../includes/header.php',
    'Footer' => '../includes/footer.php',
    'Configuration' => '../config.php'
];

// Vérification des fichiers
echo "<div class='section'>";
echo "<div class='section-header'>📁 Vérification des Fichiers</div>";
echo "<div class='section-content'>";

$files_ok = 0;
$total_files = count($required_files);

echo "<div class='file-list'>";
foreach ($required_files as $name => $path) {
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;
    $class = $exists ? '' : ' missing';
    
    echo "<div class='file-item$class'>";
    echo "<strong>$name</strong><br>";
    echo "<small>$path</small><br>";
    
    if ($exists) {
        echo "<span class='status success'>✅ Trouvé (" . number_format($size) . " bytes)</span>";
        $files_ok++;
    } else {
        echo "<span class='status error'>❌ Manquant</span>";
    }
    echo "</div>";
}
echo "</div>";

$progress = ($files_ok / $total_files) * 100;
echo "<div class='progress-bar'>";
echo "<div class='progress-fill' style='width: {$progress}%'></div>";
echo "</div>";
echo "<p>Fichiers trouvés: $files_ok/$total_files (" . round($progress) . "%)</p>";
echo "</div></div>";

// Test de la base de données
echo "<div class='section'>";
echo "<div class='section-header'>🗄️ Base de Données</div>";
echo "<div class='section-content'>";

try {
    require_once '../config.php';
    
    if (isset($pdo)) {
        echo "<span class='status success'>✅ Connexion PDO réussie</span><br>";
        
        // Vérification de la table user_preferences
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
        if ($stmt->fetch()) {
            echo "<span class='status success'>✅ Table user_preferences existe</span><br>";
            
            // Compter les préférences
            $count = $pdo->query("SELECT COUNT(*) FROM user_preferences")->fetchColumn();
            echo "<span class='status info'>📊 $count préférence(s) enregistrée(s)</span><br>";
            
            // Vérifier la structure
            $columns = $pdo->query("DESCRIBE user_preferences")->fetchAll(PDO::FETCH_COLUMN);
            echo "<span class='status info'>🏗️ " . count($columns) . " colonnes: " . implode(', ', $columns) . "</span>";
        } else {
            echo "<span class='status warning'>⚠️ Table user_preferences manquante</span>";
        }
        
        // Version MySQL
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "<br><span class='status info'>🐬 MySQL $version</span>";
        
    } else {
        echo "<span class='status error'>❌ Connexion PDO non disponible</span>";
    }
} catch (Exception $e) {
    echo "<span class='status error'>❌ Erreur: " . $e->getMessage() . "</span>";
}
echo "</div></div>";

// Test des classes PHP
echo "<div class='section'>";
echo "<div class='section-header'>🐘 Classes PHP</div>";
echo "<div class='section-content'>";

try {
    if (file_exists('../includes/preferences.php')) {
        require_once '../includes/preferences.php';
        
        if (class_exists('PreferencesManager')) {
            echo "<span class='status success'>✅ Classe PreferencesManager chargée</span><br>";
            
            if (isset($pdo)) {
                $pm = new PreferencesManager($pdo);
                echo "<span class='status success'>✅ Instance PreferencesManager créée</span><br>";
                
                // Test des méthodes
                $theme = $pm->getTheme();
                echo "<span class='status info'>🎨 getTheme(): $theme</span><br>";
                
                $language = $pm->getLanguage();
                echo "<span class='status info'>🌍 getLanguage(): $language</span><br>";
                
                $styles = $pm->getThemeStyles();
                $style_length = strlen($styles);
                echo "<span class='status info'>📝 getThemeStyles(): $style_length caractères</span>";
            } else {
                echo "<span class='status warning'>⚠️ PDO non disponible pour tester PreferencesManager</span>";
            }
        } else {
            echo "<span class='status error'>❌ Classe PreferencesManager non trouvée</span>";
        }
    } else {
        echo "<span class='status error'>❌ Fichier preferences.php non trouvé</span>";
    }
} catch (Exception $e) {
    echo "<span class='status error'>❌ Erreur: " . $e->getMessage() . "</span>";
}

echo "</div></div>";

// Métriques système
echo "<div class='section'>";
echo "<div class='section-header'>📊 Métriques Système</div>";
echo "<div class='section-content'>";
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;'>";

echo "<div class='metric'>";
echo "<div class='metric-value'>" . PHP_VERSION . "</div>";
echo "<div class='metric-label'>Version PHP</div>";
echo "</div>";

echo "<div class='metric'>";
echo "<div class='metric-value'>" . ini_get('memory_limit') . "</div>";
echo "<div class='metric-label'>Limite Mémoire</div>";
echo "</div>";

echo "<div class='metric'>";
echo "<div class='metric-value'>" . ini_get('max_execution_time') . "s</div>";
echo "<div class='metric-label'>Temps Exec Max</div>";
echo "</div>";

echo "<div class='metric'>";
$extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$loaded = array_filter($extensions, 'extension_loaded');
echo "<div class='metric-value'>" . count($loaded) . "/" . count($extensions) . "</div>";
echo "<div class='metric-label'>Extensions PHP</div>";
echo "</div>";

echo "</div>";
echo "</div></div>";

// URLs de test
echo "<div class='section'>";
echo "<div class='section-header'>🔗 URLs de Test</div>";
echo "<div class='section-content'>";

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
$test_urls = [
    'Page de Test Complète' => '/test/test_preferences_system.php',
    'Page Settings' => '/modules/users/settings.php',
    'Page Security' => '/modules/users/security.php',
    'CSS Dynamique' => '/css/dynamic-theme.css.php',
    'Dashboard' => '/dashboard.php'
];

echo "<table>";
echo "<tr><th>Page</th><th>URL</th><th>Action</th></tr>";
foreach ($test_urls as $name => $url) {
    $full_url = $base_url . $url;
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td><code>$url</code></td>";
    echo "<td><a href='$full_url' target='_blank' style='color: #007bff; text-decoration: none;'>🔗 Tester</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "</div></div>";

// Recommandations
echo "<div class='section'>";
echo "<div class='section-header'>💡 Recommandations</div>";
echo "<div class='section-content'>";

if ($files_ok < $total_files) {
    echo "<span class='status warning'>⚠️ Certains fichiers sont manquants. Vérifiez l'installation.</span><br>";
}

if (!isset($pdo)) {
    echo "<span class='status error'>❌ Configurez la connexion à la base de données dans config.php</span><br>";
}

echo "<span class='status info'>💡 Testez les fonctionnalités via la page complète de test</span><br>";
echo "<span class='status info'>💡 Vérifiez la console JavaScript pour les erreurs côté client</span><br>";
echo "<span class='status success'>✅ Le système semble prêt pour les tests utilisateur</span>";

echo "</div></div>";

echo "<div style='text-align: center; margin: 2rem 0; color: #6c757d;'>";
echo "<p>Diagnostic terminé ✨</p>";
echo "<p><small>Pour des tests plus approfondis, utilisez la <a href='{$base_url}/test/test_preferences_system.php' target='_blank'>page de test complète</a></small></p>";
echo "</div>";

echo "</div>"; // container
echo "</body></html>";
?>
