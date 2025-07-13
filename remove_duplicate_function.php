<?php
echo "Script pour supprimer la fonction formatFileSize du fichier view.php\n";

$file_path = 'modules/dossiers/view.php';
$content = file_get_contents($file_path);

if ($content === false) {
    echo "Erreur: Impossible de lire le fichier $file_path\n";
    exit(1);
}

echo "Taille originale du fichier: " . strlen($content) . " bytes\n";
echo "Nombre de lignes: " . substr_count($content, "\n") . "\n";

// Rechercher et supprimer la fonction formatFileSize
$pattern = '/function formatFileSize\([^}]*\{[^}]*\}/s';
$new_content = preg_replace($pattern, '', $content);

if ($new_content === null) {
    echo "Erreur lors de la suppression de la fonction\n";
    exit(1);
}

if ($new_content !== $content) {
    echo "Fonction formatFileSize trouvée et supprimée\n";
    
    // Nettoyer les lignes vides consécutives
    $new_content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $new_content);
    
    // Sauvegarder le fichier
    if (file_put_contents($file_path, $new_content) !== false) {
        echo "Fichier sauvegardé avec succès\n";
        echo "Nouvelle taille: " . strlen($new_content) . " bytes\n";
        echo "Nouvelles lignes: " . substr_count($new_content, "\n") . "\n";
    } else {
        echo "Erreur lors de la sauvegarde du fichier\n";
        exit(1);
    }
} else {
    echo "Aucune fonction formatFileSize trouvée dans le fichier\n";
}

// Vérifier qu'il n'y a plus de fonction formatFileSize
if (strpos(file_get_contents($file_path), 'function formatFileSize') === false) {
    echo "✅ Confirmation: Aucune fonction formatFileSize trouvée après suppression\n";
} else {
    echo "❌ Attention: La fonction formatFileSize est encore présente\n";
}
?>
