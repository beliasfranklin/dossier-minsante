<?php
/**
 * Module d'Analytics Avancées
 * Génère des KPI, graphiques et rapports en temps réel
 */

class AnalyticsManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
        
        // Vérifier que la connexion est valide
        if (!$this->db) {
            throw new Exception("Erreur: Connexion à la base de données non disponible");
        }
    }
    
    /**
     * Retourne des KPI par défaut en cas d'erreur
     */
    private function getDefaultKPIs() {
        return [
            'total_dossiers' => 0,
            'dossiers_period' => 0,
            'dossiers_valides' => 0,
            'dossiers_en_cours' => 0,
            'dossiers_urgent' => 0,
            'dossiers_retard' => 0,
            'temps_moyen_traitement' => 0,
            'utilisateurs_actifs' => 0,
            'taux_validation' => 0,
            'taux_retard' => 0,
            'tendances' => [
                'dossiers' => 0,
                'validations' => 0
            ]
        ];
    }
    
    /**
     * Récupère les KPI principaux
     */
    public function getMainKPIs($dateRange = '30d') {
        try {
            $days = (int) substr($dateRange, 0, -1);
            
            // Vérifier si la table dossiers existe
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'dossiers'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                // Si la table n'existe pas, retourner des valeurs par défaut
                return $this->getDefaultKPIs();
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_dossiers,
                    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as dossiers_period,
                    COUNT(CASE WHEN status = 'valide' THEN 1 END) as dossiers_valides,
                    COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as dossiers_en_cours,
                    COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as dossiers_urgent,
                    COUNT(CASE WHEN deadline < NOW() AND status != 'valide' THEN 1 END) as dossiers_retard,
                    AVG(CASE WHEN status = 'valide' 
                        THEN DATEDIFF(updated_at, created_at) 
                        END) as temps_moyen_traitement,
                    COUNT(DISTINCT responsable_id) as utilisateurs_actifs
                FROM dossiers
            ");
            $stmt->execute([$days]);
            $kpis = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            // En cas d'erreur, retourner des valeurs par défaut
            error_log("Erreur Analytics KPI: " . $e->getMessage());
            return $this->getDefaultKPIs();
        }
        
        // Calculer les pourcentages et tendances
        $kpis['taux_validation'] = $kpis['total_dossiers'] > 0 ? 
            round(($kpis['dossiers_valides'] / $kpis['total_dossiers']) * 100, 1) : 0;
            
        $kpis['taux_retard'] = $kpis['total_dossiers'] > 0 ? 
            round(($kpis['dossiers_retard'] / $kpis['total_dossiers']) * 100, 1) : 0;
            
        $kpis['temps_moyen_traitement'] = round($kpis['temps_moyen_traitement'] ?? 0, 1);
        
        // Tendances par rapport à la période précédente
        $kpis['tendances'] = $this->calculateTrends($days);
        
        return $kpis;
    }
    
    /**
     * Calcule les tendances par rapport à la période précédente
     */
    private function calculateTrends($days) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as current_period,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND DATE(created_at) < DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as previous_period,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND status = 'valide' THEN 1 END) as current_valides,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND DATE(created_at) < DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND status = 'valide' THEN 1 END) as previous_valides
            FROM dossiers
        ");
        $stmt->execute([$days, $days * 2, $days, $days * 2, $days, $days * 2, $days]);
        $trends = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'dossiers' => $this->calculatePercentageChange($trends['previous_period'], $trends['current_period']),
            'validations' => $this->calculatePercentageChange($trends['previous_valides'], $trends['current_valides'])
        ];
    }
    
    /**
     * Calcule le pourcentage de changement
     */
    private function calculatePercentageChange($old, $new) {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }
        return round((($new - $old) / $old) * 100, 1);
    }
    
    /**
     * Données pour graphique d'évolution temporelle
     */
    public function getTimeSeriesData($metric = 'created', $period = '30d', $groupBy = 'day') {
        $days = (int) substr($period, 0, -1);
        
        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $metricField = match($metric) {
            'created' => 'created_at',
            'updated' => 'updated_at',
            'validated' => 'CASE WHEN status = "valide" THEN updated_at END',
            default => 'created_at'
        };
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT($metricField, ?) as period_label,
                DATE($metricField) as date,
                COUNT(*) as count,
                COUNT(CASE WHEN status = 'valide' THEN 1 END) as validated,
                COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as in_progress,
                COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent
            FROM dossiers 
            WHERE $metricField >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND $metricField IS NOT NULL
            GROUP BY DATE_FORMAT($metricField, ?)
            ORDER BY DATE($metricField)
        ");
        $stmt->execute([$dateFormat, $days, $dateFormat]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Répartition par statut
     */
    public function getStatusDistribution() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dossiers)), 1) as percentage
                FROM dossiers 
                GROUP BY status
                ORDER BY count DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getStatusDistribution: " . $e->getMessage());
            return [
                ['status' => 'en_cours', 'count' => 0, 'percentage' => 0],
                ['status' => 'valide', 'count' => 0, 'percentage' => 0],
                ['status' => 'rejete', 'count' => 0, 'percentage' => 0],
                ['status' => 'archive', 'count' => 0, 'percentage' => 0]
            ];
        }
    }
    
    /**
     * Répartition par priorité
     */
    public function getPriorityDistribution() {
        $stmt = $this->db->prepare("
            SELECT 
                priority,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dossiers)), 1) as percentage
            FROM dossiers 
            GROUP BY priority
            ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low')
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Performance par utilisateur
     */
    public function getUserPerformance($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT 
                u.name,
                u.email,
                COUNT(d.id) as total_dossiers,
                COUNT(CASE WHEN d.status = 'valide' THEN 1 END) as dossiers_valides,
                COUNT(CASE WHEN d.status = 'en_cours' THEN 1 END) as dossiers_en_cours,
                COUNT(CASE WHEN d.deadline < NOW() AND d.status != 'valide' THEN 1 END) as dossiers_retard,
                AVG(CASE WHEN d.status = 'valide' 
                    THEN DATEDIFF(d.updated_at, d.created_at) 
                    END) as temps_moyen,
                ROUND((COUNT(CASE WHEN d.status = 'valide' THEN 1 END) * 100.0 / 
                       NULLIF(COUNT(d.id), 0)), 1) as taux_validation
            FROM users u
            LEFT JOIN dossiers d ON u.id = d.responsable_id
            WHERE u.role IN ('user', 'manager')
            GROUP BY u.id, u.name, u.email
            HAVING total_dossiers > 0
            ORDER BY taux_validation DESC, total_dossiers DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Performance par service/département
     */
    public function getServicePerformance() {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(service, 'Non spécifié') as service,
                COUNT(*) as total_dossiers,
                COUNT(CASE WHEN status = 'valide' THEN 1 END) as dossiers_valides,
                COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as dossiers_en_cours,
                AVG(CASE WHEN status = 'valide' 
                    THEN DATEDIFF(updated_at, created_at) 
                    END) as temps_moyen,
                ROUND((COUNT(CASE WHEN status = 'valide' THEN 1 END) * 100.0 / COUNT(*)), 1) as taux_validation
            FROM dossiers 
            GROUP BY service
            HAVING total_dossiers > 0
            ORDER BY total_dossiers DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analyse des échéances
     */
    public function getDeadlineAnalysis() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN deadline < NOW() AND status != 'valide' THEN 1 END) as en_retard,
                COUNT(CASE WHEN deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) 
                           AND status != 'valide' THEN 1 END) as echeance_3j,
                COUNT(CASE WHEN deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) 
                           AND status != 'valide' THEN 1 END) as echeance_7j,
                COUNT(CASE WHEN deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) 
                           AND status != 'valide' THEN 1 END) as echeance_30j,
                AVG(CASE WHEN deadline IS NOT NULL AND status = 'valide'
                    THEN DATEDIFF(deadline, updated_at)
                    END) as marge_moyenne_respectee
            FROM dossiers
            WHERE deadline IS NOT NULL
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Top des dossiers les plus consultés ou modifiés
     */
    public function getTopDossiers($type = 'recent', $limit = 10) {
        $orderBy = match($type) {
            'recent' => 'updated_at DESC',
            'urgent' => 'FIELD(priority, "urgent", "high", "medium", "low"), created_at DESC',
            'overdue' => 'deadline ASC',
            default => 'updated_at DESC'
        };
        
        $whereClause = match($type) {
            'urgent' => 'WHERE priority IN ("urgent", "high")',
            'overdue' => 'WHERE deadline < NOW() AND status != "valide"',
            default => ''
        };
        
        $stmt = $this->db->prepare("
            SELECT 
                d.*,
                u.name as responsable_name,
                CASE 
                    WHEN d.deadline < NOW() AND d.status != 'valide' THEN 'retard'
                    WHEN d.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'urgent'
                    WHEN d.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'attention'
                    ELSE 'normal'
                END as urgency_level
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            $whereClause
            ORDER BY $orderBy
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données de performance mensuelle
     */
    public function getMonthlyPerformance($months = 12) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                MONTHNAME(created_at) as month_name,
                YEAR(created_at) as year,
                COUNT(*) as total_created,
                COUNT(CASE WHEN status = 'valide' THEN 1 END) as total_validated,
                AVG(CASE WHEN status = 'valide' 
                    THEN DATEDIFF(updated_at, created_at) 
                    END) as avg_processing_time,
                COUNT(CASE WHEN deadline < updated_at AND status = 'valide' THEN 1 END) as delivered_late
            FROM dossiers 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$months]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Génère un rapport d'activité personnalisé
     */
    public function generateCustomReport($filters = []) {
        $whereConditions = ['1=1'];
        $params = [];
        
        // Filtres de date
        if (!empty($filters['date_start'])) {
            $whereConditions[] = 'created_at >= ?';
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $whereConditions[] = 'created_at <= ?';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }
        
        // Filtres de statut
        if (!empty($filters['status'])) {
            $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
            $whereConditions[] = "status IN ($placeholders)";
            $params = array_merge($params, $filters['status']);
        }
        
        // Filtres de priorité
        if (!empty($filters['priority'])) {
            $placeholders = str_repeat('?,', count($filters['priority']) - 1) . '?';
            $whereConditions[] = "priority IN ($placeholders)";
            $params = array_merge($params, $filters['priority']);
        }
        
        // Filtres de responsable
        if (!empty($filters['responsable_id'])) {
            $whereConditions[] = 'responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        
        // Filtres de service
        if (!empty($filters['service'])) {
            $whereConditions[] = 'service = ?';
            $params[] = $filters['service'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->db->prepare("
            SELECT 
                d.*,
                u.name as responsable_name,
                DATEDIFF(COALESCE(d.updated_at, NOW()), d.created_at) as days_in_system,
                CASE 
                    WHEN d.deadline IS NOT NULL AND d.status = 'valide' 
                    THEN DATEDIFF(d.deadline, d.updated_at)
                    WHEN d.deadline IS NOT NULL AND d.deadline < NOW() AND d.status != 'valide'
                    THEN DATEDIFF(NOW(), d.deadline) * -1
                    ELSE NULL
                END as deadline_performance
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            WHERE $whereClause
            ORDER BY d.created_at DESC
        ");
        
        $stmt->execute($params);
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques du rapport
        $stats = [
            'total_dossiers' => count($dossiers),
            'par_statut' => [],
            'par_priorite' => [],
            'temps_moyen' => 0,
            'respecte_delais' => 0,
            'en_retard' => 0
        ];
        
        $totalDays = 0;
        $validatedCount = 0;
        
        foreach ($dossiers as $dossier) {
            // Comptage par statut
            $stats['par_statut'][$dossier['status']] = ($stats['par_statut'][$dossier['status']] ?? 0) + 1;
            
            // Comptage par priorité
            $stats['par_priorite'][$dossier['priority']] = ($stats['par_priorite'][$dossier['priority']] ?? 0) + 1;
            
            // Temps de traitement
            if ($dossier['status'] === 'valide') {
                $totalDays += $dossier['days_in_system'];
                $validatedCount++;
            }
            
            // Performance des délais
            if ($dossier['deadline_performance'] !== null) {
                if ($dossier['deadline_performance'] >= 0) {
                    $stats['respecte_delais']++;
                } else {
                    $stats['en_retard']++;
                }
            }
        }
        
        $stats['temps_moyen'] = $validatedCount > 0 ? round($totalDays / $validatedCount, 1) : 0;
        
        return [
            'dossiers' => $dossiers,
            'statistiques' => $stats,
            'filtres_appliques' => $filters
        ];
    }
    
    /**
     * Données temps réel pour le dashboard
     */
    public function getRealtimeData() {
        return [
            'timestamp' => time(),
            'kpis' => $this->getMainKPIs('7d'),
            'status_distribution' => $this->getStatusDistribution(),
            'recent_activity' => $this->getTopDossiers('recent', 5),
            'urgent_items' => $this->getTopDossiers('urgent', 5),
            'deadline_analysis' => $this->getDeadlineAnalysis()
        ];
    }
    
    /**
     * Exporte les données au format CSV
     */
    public function exportToCsv($data, $filename = 'export.csv') {
        $output = fopen('php://output', 'w');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        if (!empty($data)) {
            // En-têtes
            fputcsv($output, array_keys($data[0]));
            
            // Données
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
    }
}
