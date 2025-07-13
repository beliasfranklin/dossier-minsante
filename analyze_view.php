<?php
echo "Analyse et réparation du fichier view.php\n";

$file_path = 'modules/dossiers/view.php';
$content = file_get_contents($file_path);

if ($content === false) {
    echo "Erreur: Impossible de lire le fichier $file_path\n";
    exit(1);
}

$lines = explode("\n", $content);
echo "Total de lignes: " . count($lines) . "\n";

// Analyser autour de la ligne 596
echo "\nContenu autour de la ligne 596:\n";
for ($i = 590; $i < min(605, count($lines)); $i++) {
    echo sprintf("%3d: %s\n", $i + 1, $lines[$i]);
}

// Rechercher les problèmes de syntaxe
echo "\nRecherche de structures non fermées:\n";
$open_braces = 0;
$open_parentheses = 0;
$line_num = 0;

foreach ($lines as $line) {
    $line_num++;
    $open_braces += substr_count($line, '{') - substr_count($line, '}');
    $open_parentheses += substr_count($line, '(') - substr_count($line, ')');
    
    if ($line_num >= 590 && $line_num <= 600) {
        echo sprintf("%3d: braces=%d, parentheses=%d | %s\n", 
                     $line_num, $open_braces, $open_parentheses, trim($line));
    }
}

echo "\nÉtat final: braces=$open_braces, parentheses=$open_parentheses\n";

// Rechercher les elseif orphelins
echo "\nRecherche d'elseif orphelins:\n";
$line_num = 0;
foreach ($lines as $line) {
    $line_num++;
    if (strpos(trim($line), 'elseif') === 0 || strpos(trim($line), '} elseif') !== false) {
        echo sprintf("%3d: %s\n", $line_num, trim($line));
    }
}
?>
