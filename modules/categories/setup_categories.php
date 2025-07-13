<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        color VARCHAR(7) DEFAULT '#007bff',
        icon VARCHAR(50) DEFAULT 'fas fa-folder',
        parent_id INT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "Table categories créée avec succès\n";
    
    // Insérer quelques catégories par défaut
    $defaultCategories = [
        ['Administratif', 'Documents administratifs et procédures', '#dc3545', 'fas fa-file-alt'],
        ['Médical', 'Dossiers médicaux et rapports', '#28a745', 'fas fa-user-md'],
        ['Technique', 'Documentation technique et maintenance', '#007bff', 'fas fa-cogs'],
        ['Financier', 'Budgets et documents financiers', '#ffc107', 'fas fa-dollar-sign'],
        ['Ressources Humaines', 'Personnel et formation', '#6f42c1', 'fas fa-users']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
    
    foreach ($defaultCategories as $category) {
        $stmt->execute($category);
    }
    
    echo "Catégories par défaut ajoutées\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
