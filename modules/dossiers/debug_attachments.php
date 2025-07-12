<?php
/**
 * Script de débogage pour les attachments
 */

require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$dossierId = 1; // Tester avec un ID de dossier spécifique

echo "=== Débogage des attachments ===\n";

// Tester les différentes tables
$possibleTables = ['attachments', 'pieces_jointes', 'dossier_attachments'];

foreach ($possibleTables as $table) {
    echo "\n--- Test de la table: {$table} ---\n";
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE dossier_id = ? LIMIT 1");
        $stmt->execute([$dossierId]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "✅ Table trouvée avec données:\n";
            foreach ($result as $key => $value) {
                if (!is_numeric($key)) { // Ignorer les index numériques
                    echo "  - {$key}: " . (is_string($value) ? substr($value, 0, 50) : $value) . "\n";
                }
            }
        } else {
            echo "⚠️  Table existe mais pas de données pour dossier {$dossierId}\n";
        }
    } catch (PDOException $e) {
        echo "❌ Table n'existe pas: " . $e->getMessage() . "\n";
    }
}

// Tester la fonction getDossierAttachments
echo "\n--- Test de getDossierAttachments() ---\n";
try {
    if (function_exists('getDossierAttachments')) {
        $attachments = getDossierAttachments($dossierId);
        if (!empty($attachments)) {
            echo "✅ Fonction trouve " . count($attachments) . " attachments:\n";
            $first = $attachments[0];
            foreach ($first as $key => $value) {
                if (!is_numeric($key)) {
                    echo "  - {$key}: " . (is_string($value) ? substr($value, 0, 50) : $value) . "\n";
                }
            }
        } else {
            echo "⚠️  Fonction existe mais aucun attachment trouvé\n";
        }
    } else {
        echo "❌ Fonction getDossierAttachments n'existe pas\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur fonction: " . $e->getMessage() . "\n";
}

// Lister tous les dossiers disponibles
echo "\n--- Dossiers disponibles ---\n";
try {
    $stmt = $pdo->query("SELECT id, titre FROM dossiers LIMIT 5");
    $dossiers = $stmt->fetchAll();
    foreach ($dossiers as $d) {
        echo "  - ID: {$d['id']} - {$d['titre']}\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Fin du débogage ===\n";
