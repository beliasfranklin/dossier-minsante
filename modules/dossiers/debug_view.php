<?php
/**
 * Script de débogage pour view.php
 */

require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
echo "=== Débogage de view.php ===\n";

echo "1. Vérification de la session...\n";
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID: " . $_SESSION['user_id'] . "\n";
    echo "✅ User role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'NON DÉFINI') . "\n";
} else {
    echo "❌ Session utilisateur non trouvée\n";
    echo "Redirection vers login...\n";
    exit;
}

// Récupérer l'ID du dossier
$dossierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "2. ID du dossier demandé: {$dossierId}\n";

if (!$dossierId) {
    echo "❌ ID de dossier invalide\n";
    exit;
}

// Vérifier l'existence du dossier
echo "3. Vérification du dossier...\n";
try {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               u1.prenom as created_by_prenom, u1.nom as created_by_nom,
               u2.prenom as responsable_prenom, u2.nom as responsable_nom,
               c.nom as category_name, c.couleur as category_color, c.icone as category_icon
        FROM dossiers d
        LEFT JOIN users u1 ON d.created_by = u1.id
        LEFT JOIN users u2 ON d.responsable_id = u2.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.id = ?
    ");
    $stmt->execute([$dossierId]);
    $dossier = $stmt->fetch();
    
    if (!$dossier) {
        echo "❌ Dossier avec ID {$dossierId} non trouvé dans la base\n";
        
        // Lister les dossiers disponibles
        echo "4. Dossiers disponibles:\n";
        $stmt = $pdo->query("SELECT id, titre FROM dossiers LIMIT 5");
        $dossiers = $stmt->fetchAll();
        
        if ($dossiers) {
            foreach ($dossiers as $d) {
                echo "   - ID: {$d['id']} - Titre: {$d['titre']}\n";
            }
        } else {
            echo "   Aucun dossier dans la base de données\n";
        }
        exit;
    }
    
    echo "✅ Dossier trouvé: {$dossier['titre']}\n";
    echo "   Responsable ID: {$dossier['responsable_id']}\n";
    echo "   Créé par: {$dossier['created_by']}\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur de base de données: " . $e->getMessage() . "\n";
    exit;
}

// Vérifier les permissions
echo "4. Vérification des permissions...\n";

if (!function_exists('canViewDossier')) {
    echo "❌ Fonction canViewDossier non trouvée\n";
    
    // Fonction de base pour test
    function canViewDossier($user_id, $user_role, $responsable_id) {
        // Admin et gestionnaire peuvent tout voir
        if ($user_role <= 2) return true;
        // Responsable peut voir son dossier
        if ($user_id == $responsable_id) return true;
        return false;
    }
    echo "✅ Fonction canViewDossier créée temporairement\n";
}

$canView = canViewDossier($_SESSION['user_id'], $_SESSION['user_role'], $dossier['responsable_id']);
echo "   Peut voir le dossier: " . ($canView ? "OUI" : "NON") . "\n";

if (!$canView) {
    echo "❌ Permissions insuffisantes\n";
    echo "   User ID: {$_SESSION['user_id']}\n";
    echo "   User role: {$_SESSION['user_role']}\n";
    echo "   Responsable ID: {$dossier['responsable_id']}\n";
    exit;
}

echo "\n✅ Tous les tests passés - Le dossier devrait s'afficher\n";
echo "🔗 URL de test: http://localhost/dossier-minsante/modules/dossiers/view.php?id={$dossierId}\n";

echo "\n=== Fin du débogage ===\n";
