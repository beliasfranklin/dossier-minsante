<?php
// Script pour créer la table echeances_config si elle n'existe pas
require_once '../../config.php';
require_once '../../includes/db.php';

function createEcheancesTable() {
    global $pdo;
    
    try {
        // Vérifier si la table existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'echeances_config'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            // Créer la table
            $createTable = "
            CREATE TABLE echeances_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_dossier VARCHAR(50) NOT NULL,
                service VARCHAR(50) NOT NULL,
                delai_jours INT NOT NULL,
                alertes JSON,
                actif BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_type_service (type_dossier, service)
            ) ENGINE=InnoDB";
            
            $pdo->exec($createTable);
            
            // Insérer des configurations par défaut
            $defaultConfigs = [
                ['Etude', 'DEP', 30, '[7, 3, 0]'],
                ['Projet', 'DEP', 45, '[14, 7, 3, 0]'],
                ['Administratif', 'RH', 15, '[7, 3, 1, 0]'],
                ['Autre', 'Finance', 20, '[7, 3, 0]']
            ];
            
            $insertStmt = $pdo->prepare("
                INSERT INTO echeances_config (type_dossier, service, delai_jours, alertes) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($defaultConfigs as $config) {
                $insertStmt->execute($config);
            }
            
            return true;
        }
        
        // Vérifier si la table est vide
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM echeances_config");
        $count = $stmt->fetch()['total'];
        
        if ($count == 0) {
            // Insérer des configurations par défaut
            $defaultConfigs = [
                ['Etude', 'DEP', 30, '[7, 3, 0]'],
                ['Projet', 'DEP', 45, '[14, 7, 3, 0]'],
                ['Administratif', 'RH', 15, '[7, 3, 1, 0]'],
                ['Autre', 'Finance', 20, '[7, 3, 0]']
            ];
            
            $insertStmt = $pdo->prepare("
                INSERT INTO echeances_config (type_dossier, service, delai_jours, alertes) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($defaultConfigs as $config) {
                $insertStmt->execute($config);
            }
            
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Erreur création table echeances_config: " . $e->getMessage());
        return false;
    }
}

// Exécuter la création/initialisation
$created = createEcheancesTable();

if ($created) {
    echo json_encode(['success' => true, 'message' => 'Table initialisée avec succès']);
} else {
    echo json_encode(['success' => true, 'message' => 'Table déjà présente']);
}
?>
