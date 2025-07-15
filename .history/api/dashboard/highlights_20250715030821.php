<?php
/**
 * API pour les éléments importants du dashboard
 * Fournit les données en temps réel pour la section highlights
 */

require_once __DIR__ . '/../../includes/config.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Récupérer les statistiques importantes
    $highlights = [];
    
    // 1. Dossiers en retard
    $retardQuery = "
        SELECT COUNT(*) as count 
        FROM dossiers 
        WHERE deadline < NOW() 
        AND status NOT IN ('archive', 'valide') 
        AND deleted_at IS NULL
    ";
    $retardResult = fetchOne($retardQuery);
    $highlights['retard'] = (int)$retardResult['count'];
    
    // 2. Dossiers urgents (échéances dans les 3 prochains jours)
    $urgentQuery = "
        SELECT COUNT(*) as count 
        FROM dossiers 
        WHERE deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY) 
        AND deadline >= NOW()
        AND status NOT IN ('archive', 'valide') 
        AND deleted_at IS NULL
    ";
    $urgentResult = fetchOne($urgentQuery);
    $highlights['urgent'] = (int)$urgentResult['count'];
    
    // 3. Nouveaux dossiers (créés cette semaine)
    $newQuery = "
        SELECT COUNT(*) as count 
        FROM dossiers 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND deleted_at IS NULL
    ";
    $newResult = fetchOne($newQuery);
    $highlights['nouveaux'] = (int)$newResult['count'];
    
    // 4. Taux de validation du mois
    $validationQuery = "
        SELECT 
            COUNT(CASE WHEN status = 'valide' THEN 1 END) as valides,
            COUNT(*) as total
        FROM dossiers 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND deleted_at IS NULL
    ";
    $validationResult = fetchOne($validationQuery);
    $validationRate = 0;
    if ($validationResult['total'] > 0) {
        $validationRate = round(($validationResult['valides'] / $validationResult['total']) * 100, 1);
    }
    $highlights['validation_rate'] = $validationRate;
    
    // 5. Statistiques additionnelles pour contexte
    $highlights['stats'] = [
        'total_dossiers' => (int)fetchOne("SELECT COUNT(*) as count FROM dossiers WHERE deleted_at IS NULL")['count'],
        'en_cours' => (int)fetchOne("SELECT COUNT(*) as count FROM dossiers WHERE status = 'en_cours' AND deleted_at IS NULL")['count'],
        'archives' => (int)fetchOne("SELECT COUNT(*) as count FROM dossiers WHERE status = 'archive' AND deleted_at IS NULL")['count']
    ];
    
    // 6. Tendances (comparaison avec la semaine précédente)
    $trendsQuery = "
        SELECT 
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as cette_semaine,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as semaine_precedente
        FROM dossiers 
        WHERE deleted_at IS NULL
    ";
    $trendsResult = fetchOne($trendsQuery);
    $tendance = 0;
    if ($trendsResult['semaine_precedente'] > 0) {
        $tendance = round((($trendsResult['cette_semaine'] - $trendsResult['semaine_precedente']) / $trendsResult['semaine_precedente']) * 100, 1);
    }
    $highlights['tendance'] = $tendance;
    
    // 7. Notifications importantes
    if (function_exists('getUnreadNotifications')) {
        $userId = $_SESSION['user_id'] ?? 0;
        $notifications = getUnreadNotifications($userId);
        $highlights['notifications_count'] = count($notifications);
    } else {
        $highlights['notifications_count'] = 0;
    }
    
    // 8. Prochaines échéances critiques (dans les 24h)
    $criticalQuery = "
        SELECT 
            d.id,
            d.numero_dossier,
            d.titre,
            d.deadline,
            TIMESTAMPDIFF(HOUR, NOW(), d.deadline) as heures_restantes
        FROM dossiers d
        WHERE d.deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND d.deadline >= NOW()
        AND d.status NOT IN ('archive', 'valide')
        AND d.deleted_at IS NULL
        ORDER BY d.deadline ASC
        LIMIT 5
    ";
    $highlights['prochaines_echeances'] = fetchAll($criticalQuery);
    
    // Ajouter un timestamp pour le cache
    $highlights['timestamp'] = time();
    $highlights['formatted_time'] = date('Y-m-d H:i:s');
    
    // Log de l'activité (optionnel)
    if (function_exists('logActivity')) {
        logActivity('dashboard_highlights_view', 'API', "Consultation des éléments importants", $_SESSION['user_id'] ?? 0);
    }
    
    echo json_encode($highlights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
    
    // Log de l'erreur
    error_log("Erreur API highlights: " . $e->getMessage());
}
?>
