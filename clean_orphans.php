<?php
echo "Nettoyage des fragments orphelins dans view.php\n";

$file_path = 'modules/dossiers/view.php';
$content = file_get_contents($file_path);

if ($content === false) {
    echo "Erreur: Impossible de lire le fichier $file_path\n";
    exit(1);
}

$lines = explode("\n", $content);
echo "Lignes originales: " . count($lines) . "\n";

// Identifier les lignes à supprimer (fragments orphelins de formatFileSize)
$lines_to_remove = [];
for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    
    // Supprimer les fragments orphelins de la fonction formatFileSize supprimée
    if (
        strpos($line, 'elseif ($bytes >= 1048576)') !== false ||
        strpos($line, 'return number_format($bytes / 1048576, 2) . \' MB\';') !== false ||
        strpos($line, '} elseif ($bytes >= 1024)') !== false ||
        strpos($line, 'return number_format($bytes / 1024, 2) . \' KB\';') !== false ||
        (strpos($line, '} else {') !== false && $i < count($lines) - 1 && strpos($lines[$i+1], 'return $bytes . \' B\';') !== false) ||
        strpos($line, 'return $bytes . \' B\';') !== false
    ) {
        $lines_to_remove[] = $i;
        echo "Ligne à supprimer " . ($i + 1) . ": " . $line . "\n";
    }
}

// Supprimer les lignes identifiées
foreach (array_reverse($lines_to_remove) as $line_index) {
    unset($lines[$line_index]);
}

// Réindexer le tableau
$lines = array_values($lines);

echo "Lignes après nettoyage: " . count($lines) . "\n";

// Reconstruire le contenu
$new_content = implode("\n", $lines);

// Sauvegarder
if (file_put_contents($file_path, $new_content) !== false) {
    echo "Fichier nettoyé et sauvegardé avec succès\n";
} else {
    echo "Erreur lors de la sauvegarde\n";
    exit(1);
}

// Vérifier la syntaxe
echo "\nVérification de la syntaxe PHP...\n";
exec("php -l \"$file_path\" 2>&1", $output, $return_code);
if ($return_code === 0) {
    echo "✅ Syntaxe PHP correcte\n";
} else {
    echo "❌ Erreur de syntaxe:\n";
    echo implode("\n", $output) . "\n";
}
?>
