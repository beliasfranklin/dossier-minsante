<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

$suggestions = [];

try {
    // Recherche dans les dossiers
    $stmt = $pdo->prepare("
        SELECT id, titre, numero_dossier, status 
        FROM dossiers 
        WHERE (titre LIKE ? OR numero_dossier LIKE ? OR description LIKE ?) 
        AND deleted_at IS NULL
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $dossiers = $stmt->fetchAll();

    foreach ($dossiers as $dossier) {
        $suggestions[] = [
            'title' => $dossier['titre'],
            'description' => "Dossier #{$dossier['numero_dossier']} - " . ucfirst($dossier['status']),
            'url' => BASE_URL . 'modules/dossiers/view.php?id=' . $dossier['id'],
            'type' => 'Dossier',
            'icon' => 'fas fa-folder'
        ];
    }

    // Recherche dans les utilisateurs (si gestionnaire+)
    if (hasPermission(ROLE_GESTIONNAIRE)) {
        $stmt = $pdo->prepare("
            SELECT id, nom, prenom, email, role 
            FROM users 
            WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?) 
            AND deleted_at IS NULL
            ORDER BY nom, prenom 
            LIMIT 3
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            $suggestions[] = [
                'title' => $user['prenom'] . ' ' . $user['nom'],
                'description' => $user['email'] . ' - ' . getRoleName($user['role']),
                'url' => BASE_URL . 'modules/users/profile.php?id=' . $user['id'],
                'type' => 'Utilisateur',
                'icon' => 'fas fa-user'
            ];
        }
    }

    // Recherche dans les catégories
    if (function_exists('fetchAll')) {
        $categories = fetchAll("
            SELECT id, nom, description 
            FROM categories 
            WHERE nom LIKE ? OR description LIKE ?
            LIMIT 2
        ", [$searchTerm, $searchTerm]);

        foreach ($categories as $category) {
            $suggestions[] = [
                'title' => $category['nom'],
                'description' => $category['description'] ? substr($category['description'], 0, 50) . '...' : 'Catégorie',
                'url' => BASE_URL . 'modules/dossiers/list.php?category=' . $category['id'],
                'type' => 'Catégorie',
                'icon' => 'fas fa-tag'
            ];
        }
    }

    // Si aucun résultat spécifique, proposer une recherche globale
    if (empty($suggestions)) {
        $suggestions[] = [
            'title' => "Rechercher \"$query\"",
            'description' => 'Recherche globale dans tous les modules',
            'url' => BASE_URL . 'modules/search/global.php?q=' . urlencode($query),
            'type' => 'Recherche',
            'icon' => 'fas fa-search'
        ];
    }

} catch (PDOException $e) {
    error_log("Erreur recherche suggestions: " . $e->getMessage());
    $suggestions = [];
}

header('Content-Type: application/json');
echo json_encode(['suggestions' => $suggestions]);
?>
