<?php
echo "Restauration propre du fichier view.php\n";

// Lire la sauvegarde originale
$backup_path = 'modules/dossiers/view_backup.php';
$target_path = 'modules/dossiers/view.php';

if (!file_exists($backup_path)) {
    echo "Erreur: Fichier de sauvegarde non trouvé\n";
    exit(1);
}

$content = file_get_contents($backup_path);
$lines = explode("\n", $content);

echo "Analyse du fichier de sauvegarde: " . count($lines) . " lignes\n";

// Trouver et supprimer complètement la fonction formatFileSize
$in_function = false;
$function_start = -1;
$function_end = -1;
$brace_count = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    
    // Début de la fonction formatFileSize
    if (strpos($line, 'function formatFileSize') !== false) {
        $in_function = true;
        $function_start = $i;
        $brace_count = 0;
        echo "Début de fonction trouvé à la ligne " . ($i + 1) . "\n";
    }
    
    if ($in_function) {
        // Compter les accolades
        $brace_count += substr_count($lines[$i], '{') - substr_count($lines[$i], '}');
        
        // Si on revient à 0, c'est la fin de la fonction
        if ($brace_count <= 0 && strpos($lines[$i], '}') !== false) {
            $function_end = $i;
            echo "Fin de fonction trouvée à la ligne " . ($i + 1) . "\n";
            break;
        }
    }
}

if ($function_start >= 0 && $function_end >= 0) {
    // Supprimer les lignes de la fonction
    echo "Suppression des lignes " . ($function_start + 1) . " à " . ($function_end + 1) . "\n";
    
    $new_lines = array_merge(
        array_slice($lines, 0, $function_start),
        array_slice($lines, $function_end + 1)
    );
    
    echo "Nouvelles lignes: " . count($new_lines) . "\n";
    
    // Reconstruire le contenu
    $new_content = implode("\n", $new_lines);
    
    // Sauvegarder
    if (file_put_contents($target_path, $new_content) !== false) {
        echo "Fichier restauré avec succès\n";
    } else {
        echo "Erreur lors de la sauvegarde\n";
        exit(1);
    }
    
    // Vérifier la syntaxe
    echo "\nVérification de la syntaxe PHP...\n";
    exec("php -l \"$target_path\" 2>&1", $output, $return_code);
    if ($return_code === 0) {
        echo "✅ Syntaxe PHP correcte\n";
    } else {
        echo "❌ Erreur de syntaxe:\n";
        echo implode("\n", $output) . "\n";
    }
} else {
    echo "Fonction formatFileSize non trouvée dans le fichier de sauvegarde\n";
}
?>
