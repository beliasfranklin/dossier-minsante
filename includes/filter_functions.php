<?php
function buildDossierQuery($filters) {
    $baseQuery = "SELECT d.*, u.name as responsable_name 
                 FROM dossiers d
                 LEFT JOIN users u ON d.responsable_id = u.id";
    
    $where = [];
    $params = [];
    
    // Filtre par statut
    if (!empty($filters['status'])) {
        $where[] = "d.status = ?";
        $params[] = $filters['status'];
    }
    
    // Filtre par type
    if (!empty($filters['type'])) {
        $where[] = "d.type = ?";
        $params[] = $filters['type'];
    }
    
    // Filtre par service
    if (!empty($filters['service'])) {
        $where[] = "d.service = ?";
        $params[] = $filters['service'];
    }
    
    // Filtre par priorité
    if (!empty($filters['priority'])) {
        $where[] = "d.priority = ?";
        $params[] = $filters['priority'];
    }
    
    // Filtre par date de création
    if (!empty($filters['date_from'])) {
        $where[] = "d.created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "d.created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Filtre par échéance
    if (!empty($filters['deadline'])) {
        if ($filters['deadline'] === 'expired') {
            $where[] = "d.deadline < CURDATE()";
        } elseif ($filters['deadline'] === 'today') {
            $where[] = "d.deadline = CURDATE()";
        } elseif ($filters['deadline'] === 'upcoming') {
            $where[] = "d.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        }
    }
    
    // Filtre par responsable
    if (!empty($filters['responsable_id'])) {
        $where[] = "d.responsable_id = ?";
        $params[] = $filters['responsable_id'];
    }
    
    // Recherche texte
    if (!empty($filters['search'])) {
        $where[] = "(d.reference LIKE ? OR d.titre LIKE ? OR d.description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Construction de la requête
    if (!empty($where)) {
        $baseQuery .= " WHERE " . implode(" AND ", $where);
    }
    
    // Tri
    $sortField = in_array($filters['sort'] ?? '', ['reference', 'titre', 'created_at', 'deadline', 'priority']) 
        ? $filters['sort'] 
        : 'created_at';
    
    $sortOrder = ($filters['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $baseQuery .= " ORDER BY " . $sortField . " " . $sortOrder;
    
    // Pagination
    if (isset($filters['limit'])) {
        $limit = (int)$filters['limit'];
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $baseQuery .= " LIMIT $limit OFFSET $offset";
        // Ne pas ajouter dans $params !
    }
    
    return [
        'query' => $baseQuery,
        'params' => $params
    ];
}
function getCachedDossiers($filters) {
    $cacheKey = md5(json_encode($filters));
    $cacheFile = __DIR__.'/../cache/dossiers_'.$cacheKey.'.json';
    
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) { // 5 min cache
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $queryData = buildDossierQuery($filters);
    $dossiers = fetchAll($queryData['query'], $queryData['params']);
    
    file_put_contents($cacheFile, json_encode($dossiers));
    return $dossiers;
}
?>