<?php
/**
 * Script pour créer quelques attachments de test
 */

require_once '../../includes/db.php';

try {
    // Créer quelques données de test dans attachments
    echo "Ajout de données de test pour attachments...\n";
    
    // Vérifier si la table attachments existe maintenant
    $stmt = $pdo->query("SHOW TABLES LIKE 'attachments'");
    if ($stmt->fetch()) {
        // Ajouter quelques données de test
        $testData = [
            [1, 'test_document.pdf', 'doc_12345.pdf', 245760, 'application/pdf'],
            [2, 'rapport_medical.docx', 'rapport_67890.docx', 1024000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            [3, 'analyse_resultats.xlsx', 'analyse_11111.xlsx', 512000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO attachments (dossier_id, original_name, file_name, file_size, mime_type, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($testData as $data) {
            $stmt->execute($data);
        }
        
        echo "✅ Données de test ajoutées à la table attachments\n";
        
        // Vérifier les données
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM attachments");
        $count = $stmt->fetchColumn();
        echo "📊 Total d'attachments: {$count}\n";
        
    } else {
        echo "⚠️  Table 'attachments' n'existe pas. Utilisez d'abord create_attachments_table.php\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Fin de l'ajout ===\n";
