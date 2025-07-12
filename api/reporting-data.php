<?php
/**
 * API POUR LES DONNÉES DE REPORTING AVANCÉ - MINSANTE
 * Fournit les données JSON pour alimenter les graphiques Chart.js
 */

require_once '../../includes/config.php';
requireRole(['admin', 'gestionnaire']);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Récupération et validation des filtres
    $filters = [
        'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
        'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
        'service' => $_GET['service'] ?? '',
        'type' => $_GET['type'] ?? '',
        'user_id' => $_GET['user_id'] ?? ''
    ];
    
    // Validation des dates
    if (!validateDate($filters['start_date']) || !validateDate($filters['end_date'])) {
        throw new Exception('Dates invalides');
    }
    
    // Construction des clauses WHERE
    $whereClause = "WHERE d.created_at BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $filters['start_date'] . ' 00:00:00',
        'end_date' => $filters['end_date'] . ' 23:59:59'
    ];
    
    if (!empty($filters['service'])) {
        $whereClause .= " AND d.service = :service";
        $params['service'] = $filters['service'];
    }
    
    if (!empty($filters['type'])) {
        $whereClause .= " AND d.type_dossier = :type";
        $params['type'] = $filters['type'];
    }
    
    if (!empty($filters['user_id'])) {
        $whereClause .= " AND (d.responsable_id = :user_id OR d.created_by = :user_id)";
        $params['user_id'] = $filters['user_id'];
    }
    
    // Collecte de toutes les données
    $data = [
        'stats' => getGeneralStats($pdo, $whereClause, $params, $filters),
        'evolution' => getEvolutionData($pdo, $whereClause, $params),
        'status' => getStatusData($pdo, $whereClause, $params),
        'services' => getServiceData($pdo, $whereClause, $params),
        'delais' => getDelaisData($pdo, $whereClause, $params),
        'workload' => getWorkloadData($pdo, $whereClause, $params),
        'monthly' => getMonthlyTrends($pdo, $whereClause, $params),
        'deadlines' => getDeadlineAnalysis($pdo, $whereClause, $params)
    ];
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

/**
 * Statistiques générales
 */
function getGeneralStats($pdo, $whereClause, $params, $filters) {
    // Statistiques actuelles
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dossiers,
            COUNT(CASE WHEN status IN ('nouveau', 'en_cours', 'en_attente') THEN 1 END) as en_cours,
            COUNT(CASE WHEN status IN ('valide', 'archive') THEN 1 END) as termines,
            COUNT(CASE WHEN deadline < CURDATE() AND status NOT IN ('valide', 'archive') THEN 1 END) as en_retard
        FROM dossiers d 
        $whereClause
    ");
    $stmt->execute($params);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques de la période précédente pour calculer les changements
    $previousPeriod = getPreviousPeriodParams($filters);
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dossiers,
            COUNT(CASE WHEN status IN ('nouveau', 'en_cours', 'en_attente') THEN 1 END) as en_cours,
            COUNT(CASE WHEN status IN ('valide', 'archive') THEN 1 END) as termines,
            COUNT(CASE WHEN deadline < CURDATE() AND status NOT IN ('valide', 'archive') THEN 1 END) as en_retard
        FROM dossiers d 
        WHERE d.created_at BETWEEN :prev_start AND :prev_end
    ");
    $stmt->execute($previousPeriod);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcul des pourcentages de changement
    return [
        'total_dossiers' => $current['total_dossiers'],
        'en_cours' => $current['en_cours'],
        'termines' => $current['termines'],
        'en_retard' => $current['en_retard'],
        'dossiers_change' => calculatePercentageChange($previous['total_dossiers'], $current['total_dossiers']),
        'en_cours_change' => calculatePercentageChange($previous['en_cours'], $current['en_cours']),
        'termines_change' => calculatePercentageChange($previous['termines'], $current['termines']),
        'retard_change' => calculatePercentageChange($previous['en_retard'], $current['en_retard'])
    ];
}

/**
 * Données d'évolution temporelle
 */
function getEvolutionData($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(d.created_at) as date,
            COUNT(*) as nouveaux,
            COUNT(CASE WHEN d.status IN ('valide', 'archive') THEN 1 END) as termines
        FROM dossiers d 
        $whereClause
        GROUP BY DATE(d.created_at)
        ORDER BY date
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $nouveaux = [];
    $termines = [];
    
    foreach ($results as $row) {
        $labels[] = $row['date'];
        $nouveaux[] = (int)$row['nouveaux'];
        $termines[] = (int)$row['termines'];
    }
    
    return [
        'labels' => $labels,
        'nouveaux' => $nouveaux,
        'termines' => $termines
    ];
}

/**
 * Répartition par statut
 */
function getStatusData($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN status = 'nouveau' THEN 'Nouveau'
                WHEN status = 'en_cours' THEN 'En cours'
                WHEN status = 'en_attente' THEN 'En attente'
                WHEN status = 'valide' THEN 'Validé'
                WHEN status = 'archive' THEN 'Archivé'
                WHEN status = 'rejete' THEN 'Rejeté'
                ELSE 'Autre'
            END as status_label,
            COUNT(*) as count
        FROM dossiers d 
        $whereClause
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'labels' => array_column($results, 'status_label'),
        'values' => array_map('intval', array_column($results, 'count'))
    ];
}

/**
 * Performance par service
 */
function getServiceData($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(d.service, 'Non défini') as service,
            COUNT(CASE WHEN status IN ('nouveau', 'en_cours', 'en_attente') THEN 1 END) as en_cours,
            COUNT(CASE WHEN status IN ('valide', 'archive') THEN 1 END) as termines,
            COUNT(CASE WHEN deadline < CURDATE() AND status NOT IN ('valide', 'archive') THEN 1 END) as en_retard
        FROM dossiers d 
        $whereClause
        GROUP BY d.service
        ORDER BY (en_cours + termines + en_retard) DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'labels' => array_column($results, 'service'),
        'en_cours' => array_map('intval', array_column($results, 'en_cours')),
        'termines' => array_map('intval', array_column($results, 'termines')),
        'en_retard' => array_map('intval', array_column($results, 'en_retard'))
    ];
}

/**
 * Analyse des délais
 */
function getDelaisData($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(d.type_dossier, 'Non défini') as type,
            AVG(DATEDIFF(COALESCE(d.updated_at, NOW()), d.created_at)) as delai_moyen
        FROM dossiers d 
        $whereClause
        AND d.status IN ('valide', 'archive')
        GROUP BY d.type_dossier
        HAVING COUNT(*) >= 3
        ORDER BY delai_moyen DESC
        LIMIT 8
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'labels' => array_column($results, 'type'),
        'values' => array_map(function($val) { return round($val, 1); }, array_column($results, 'delai_moyen'))
    ];
}

/**
 * Charge de travail par utilisateur
 */
function getWorkloadData($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(u.name, 'Non assigné') as user_name,
            COUNT(*) as dossiers_count
        FROM dossiers d 
        LEFT JOIN users u ON d.responsable_id = u.id
        $whereClause
        AND d.status NOT IN ('archive')
        GROUP BY d.responsable_id, u.name
        ORDER BY dossiers_count DESC
        LIMIT 15
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'labels' => array_column($results, 'user_name'),
        'values' => array_map('intval', array_column($results, 'dossiers_count'))
    ];
}

/**
 * Tendances mensuelles
 */
function getMonthlyTrends($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(d.created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM dossiers d 
        WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(d.created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

/**
 * Analyse des échéances
 */
function getDeadlineAnalysis($pdo, $whereClause, $params) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(d.deadline) as deadline_date,
            COUNT(CASE WHEN d.deadline >= d.updated_at OR d.status NOT IN ('valide', 'archive') THEN 1 END) as respectees,
            COUNT(CASE WHEN d.deadline < d.updated_at AND d.status IN ('valide', 'archive') THEN 1 END) as depassees
        FROM dossiers d 
        $whereClause
        AND d.deadline IS NOT NULL
        GROUP BY DATE(d.deadline)
        ORDER BY deadline_date
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'labels' => array_column($results, 'deadline_date'),
        'respectees' => array_map('intval', array_column($results, 'respectees')),
        'depassees' => array_map('intval', array_column($results, 'depassees'))
    ];
}

/**
 * Fonctions utilitaires
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function getPreviousPeriodParams($filters) {
    $start = new DateTime($filters['start_date']);
    $end = new DateTime($filters['end_date']);
    $diff = $start->diff($end)->days;
    
    $prevEnd = clone $start;
    $prevEnd->modify('-1 day');
    $prevStart = clone $prevEnd;
    $prevStart->modify("-{$diff} days");
    
    return [
        'prev_start' => $prevStart->format('Y-m-d') . ' 00:00:00',
        'prev_end' => $prevEnd->format('Y-m-d') . ' 23:59:59'
    ];
}

function calculatePercentageChange($old, $new) {
    if ($old == 0) {
        return $new > 0 ? 100 : 0;
    }
    return round((($new - $old) / $old) * 100, 1);
}
?>
