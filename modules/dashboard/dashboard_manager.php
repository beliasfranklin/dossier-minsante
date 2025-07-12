<?php
/**
 * Module de gestion du dashboard personnalisable
 * Gère les widgets, les préférences utilisateur et l'actualisation en temps réel
 */

class DashboardManager {
    private $db;
    private $userId;
    
    public function __construct($userId) {
        global $conn;
        $this->db = $conn;
        $this->userId = $userId;
        $this->initDashboardTables();
        $this->ensureUserDashboard();
    }
    
    /**
     * Initialise les tables du dashboard si elles n'existent pas
     */
    private function initDashboardTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS user_dashboards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT 'Mon Dashboard',
            layout JSON,
            is_default BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS dashboard_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dashboard_id INT NOT NULL,
            widget_type VARCHAR(100) NOT NULL,
            position_x INT DEFAULT 0,
            position_y INT DEFAULT 0,
            width INT DEFAULT 4,
            height INT DEFAULT 3,
            config JSON,
            is_visible BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (dashboard_id) REFERENCES user_dashboards(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS widget_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            default_config JSON,
            required_permissions JSON,
            category VARCHAR(100) DEFAULT 'general',
            icon VARCHAR(100),
            is_active BOOLEAN DEFAULT 1
        );
        
        CREATE TABLE IF NOT EXISTS user_dashboard_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            auto_refresh_interval INT DEFAULT 30,
            theme VARCHAR(50) DEFAULT 'light',
            notifications_enabled BOOLEAN DEFAULT 1,
            sound_alerts BOOLEAN DEFAULT 0,
            show_tooltips BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";
        
        $this->db->exec($sql);
        $this->insertDefaultTemplates();
    }
    
    /**
     * Insère les templates de widgets par défaut
     */
    private function insertDefaultTemplates() {
        $templates = [
            [
                'type' => 'stats_counter',
                'name' => 'Compteurs statistiques',
                'description' => 'Affiche les statistiques principales des dossiers',
                'default_config' => json_encode(['metrics' => ['total', 'en_cours', 'valides', 'rejetes']]),
                'category' => 'statistics',
                'icon' => 'fa-chart-bar'
            ],
            [
                'type' => 'recent_dossiers',
                'name' => 'Dossiers récents',
                'description' => 'Liste des derniers dossiers créés ou modifiés',
                'default_config' => json_encode(['limit' => 5, 'show_status' => true]),
                'category' => 'dossiers',
                'icon' => 'fa-folder-open'
            ],
            [
                'type' => 'priority_alerts',
                'name' => 'Alertes priorité',
                'description' => 'Dossiers nécessitant une attention immédiate',
                'default_config' => json_encode(['priority_levels' => ['urgent', 'high']]),
                'category' => 'alerts',
                'icon' => 'fa-exclamation-triangle'
            ],
            [
                'type' => 'deadline_tracker',
                'name' => 'Suivi des échéances',
                'description' => 'Dossiers approchant de leur date limite',
                'default_config' => json_encode(['days_ahead' => 7]),
                'category' => 'deadlines',
                'icon' => 'fa-calendar-check'
            ],
            [
                'type' => 'activity_feed',
                'name' => 'Flux d\'activité',
                'description' => 'Dernières actions et modifications',
                'default_config' => json_encode(['limit' => 10, 'show_user' => true]),
                'category' => 'activity',
                'icon' => 'fa-stream'
            ],
            [
                'type' => 'performance_chart',
                'name' => 'Graphique performance',
                'description' => 'Évolution des performances sur la période',
                'default_config' => json_encode(['period' => '30d', 'chart_type' => 'line']),
                'category' => 'analytics',
                'icon' => 'fa-chart-line'
            ],
            [
                'type' => 'quick_actions',
                'name' => 'Actions rapides',
                'description' => 'Raccourcis vers les actions fréquentes',
                'default_config' => json_encode(['actions' => ['create_dossier', 'search', 'reports']]),
                'category' => 'tools',
                'icon' => 'fa-bolt'
            ],
            [
                'type' => 'team_workload',
                'name' => 'Charge équipe',
                'description' => 'Répartition de la charge de travail par membre',
                'default_config' => json_encode(['show_percentage' => true]),
                'category' => 'team',
                'icon' => 'fa-users'
            ]
        ];
        
        foreach ($templates as $template) {
            $stmt = $this->db->prepare("INSERT IGNORE INTO widget_templates (type, name, description, default_config, category, icon) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $template['type'],
                $template['name'],
                $template['description'],
                $template['default_config'],
                $template['category'],
                $template['icon']
            ]);
        }
    }
    
    /**
     * S'assure qu'un dashboard par défaut existe pour l'utilisateur
     */
    private function ensureUserDashboard() {
        $stmt = $this->db->prepare("SELECT id FROM user_dashboards WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$this->userId]);
        
        if (!$stmt->fetch()) {
            $this->createDefaultDashboard();
        }
        
        // S'assurer que les préférences existent
        $stmt = $this->db->prepare("SELECT id FROM user_dashboard_preferences WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        
        if (!$stmt->fetch()) {
            $this->createDefaultPreferences();
        }
    }
    
    /**
     * Crée un dashboard par défaut pour l'utilisateur
     */
    private function createDefaultDashboard() {
        $layout = json_encode([
            'columns' => 12,
            'row_height' => 120,
            'margin' => [10, 10]
        ]);
        
        $stmt = $this->db->prepare("INSERT INTO user_dashboards (user_id, name, layout, is_default) VALUES (?, ?, ?, 1)");
        $stmt->execute([$this->userId, 'Dashboard Principal', $layout]);
        
        $dashboardId = $this->db->lastInsertId();
        
        // Ajouter les widgets par défaut
        $defaultWidgets = [
            ['type' => 'stats_counter', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
            ['type' => 'recent_dossiers', 'x' => 0, 'y' => 2, 'w' => 8, 'h' => 4],
            ['type' => 'priority_alerts', 'x' => 8, 'y' => 2, 'w' => 4, 'h' => 4],
            ['type' => 'deadline_tracker', 'x' => 0, 'y' => 6, 'w' => 6, 'h' => 3],
            ['type' => 'activity_feed', 'x' => 6, 'y' => 6, 'w' => 6, 'h' => 3]
        ];
        
        foreach ($defaultWidgets as $widget) {
            $this->addWidget($dashboardId, $widget['type'], $widget['x'], $widget['y'], $widget['w'], $widget['h']);
        }
    }
    
    /**
     * Crée les préférences par défaut pour l'utilisateur
     */
    private function createDefaultPreferences() {
        $stmt = $this->db->prepare("INSERT INTO user_dashboard_preferences (user_id) VALUES (?)");
        $stmt->execute([$this->userId]);
    }
    
    /**
     * Récupère le dashboard de l'utilisateur
     */
    public function getUserDashboard($dashboardId = null) {
        if ($dashboardId) {
            $stmt = $this->db->prepare("SELECT * FROM user_dashboards WHERE id = ? AND user_id = ?");
            $stmt->execute([$dashboardId, $this->userId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM user_dashboards WHERE user_id = ? AND is_default = 1");
            $stmt->execute([$this->userId]);
        }
        
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dashboard) {
            $dashboard['widgets'] = $this->getDashboardWidgets($dashboard['id']);
        }
        
        return $dashboard;
    }
    
    /**
     * Récupère les widgets d'un dashboard
     */
    public function getDashboardWidgets($dashboardId) {
        $stmt = $this->db->prepare("
            SELECT w.*, t.name as widget_name, t.icon, t.category 
            FROM dashboard_widgets w 
            LEFT JOIN widget_templates t ON w.widget_type = t.type 
            WHERE w.dashboard_id = ? AND w.is_visible = 1 
            ORDER BY w.position_y, w.position_x
        ");
        $stmt->execute([$dashboardId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ajoute un widget au dashboard
     */
    public function addWidget($dashboardId, $widgetType, $x = 0, $y = 0, $width = 4, $height = 3, $config = null) {
        $stmt = $this->db->prepare("
            INSERT INTO dashboard_widgets (dashboard_id, widget_type, position_x, position_y, width, height, config) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $dashboardId,
            $widgetType,
            $x,
            $y,
            $width,
            $height,
            $config ? json_encode($config) : null
        ]);
    }
    
    /**
     * Met à jour la position et taille d'un widget
     */
    public function updateWidgetLayout($widgetId, $x, $y, $width, $height) {
        $stmt = $this->db->prepare("
            UPDATE dashboard_widgets 
            SET position_x = ?, position_y = ?, width = ?, height = ? 
            WHERE id = ? AND dashboard_id IN (SELECT id FROM user_dashboards WHERE user_id = ?)
        ");
        
        return $stmt->execute([$x, $y, $width, $height, $widgetId, $this->userId]);
    }
    
    /**
     * Supprime un widget
     */
    public function removeWidget($widgetId) {
        $stmt = $this->db->prepare("
            DELETE FROM dashboard_widgets 
            WHERE id = ? AND dashboard_id IN (SELECT id FROM user_dashboards WHERE user_id = ?)
        ");
        
        return $stmt->execute([$widgetId, $this->userId]);
    }
    
    /**
     * Récupère les templates de widgets disponibles
     */
    public function getAvailableWidgets() {
        $stmt = $this->db->prepare("SELECT * FROM widget_templates WHERE is_active = 1 ORDER BY category, name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les préférences utilisateur
     */
    public function getUserPreferences() {
        $stmt = $this->db->prepare("SELECT * FROM user_dashboard_preferences WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Met à jour les préférences utilisateur
     */
    public function updatePreferences($preferences) {
        $allowedFields = ['auto_refresh_interval', 'theme', 'notifications_enabled', 'sound_alerts', 'show_tooltips'];
        $updateFields = [];
        $values = [];
        
        foreach ($preferences as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            return false;
        }
        
        $values[] = $this->userId;
        $sql = "UPDATE user_dashboard_preferences SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Génère les données pour un widget spécifique
     */
    public function getWidgetData($widgetType, $config = []) {
        switch ($widgetType) {
            case 'stats_counter':
                return $this->getStatsCounterData($config);
            case 'recent_dossiers':
                return $this->getRecentDossiersData($config);
            case 'priority_alerts':
                return $this->getPriorityAlertsData($config);
            case 'deadline_tracker':
                return $this->getDeadlineTrackerData($config);
            case 'activity_feed':
                return $this->getActivityFeedData($config);
            case 'performance_chart':
                return $this->getPerformanceChartData($config);
            case 'quick_actions':
                return $this->getQuickActionsData($config);
            case 'team_workload':
                return $this->getTeamWorkloadData($config);
            default:
                return ['error' => 'Widget type not found'];
        }
    }
    
    /**
     * Données pour le widget compteurs statistiques
     */
    private function getStatsCounterData($config) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN status = 'rejete' THEN 1 ELSE 0 END) as rejetes,
                SUM(CASE WHEN status = 'archive' THEN 1 ELSE 0 END) as archives,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
            FROM dossiers
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget dossiers récents
     */
    private function getRecentDossiersData($config) {
        $limit = $config['limit'] ?? 5;
        $stmt = $this->db->prepare("
            SELECT d.*, u.name as responsable_name 
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            ORDER BY d.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget alertes priorité
     */
    private function getPriorityAlertsData($config) {
        $priorities = $config['priority_levels'] ?? ['urgent', 'high'];
        $placeholders = str_repeat('?,', count($priorities) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT d.*, u.name as responsable_name 
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            WHERE d.priority IN ($placeholders) AND d.status != 'archive'
            ORDER BY FIELD(d.priority, 'urgent', 'high', 'medium', 'low'), d.updated_at DESC
            LIMIT 10
        ");
        $stmt->execute($priorities);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget suivi des échéances
     */
    private function getDeadlineTrackerData($config) {
        $daysAhead = $config['days_ahead'] ?? 7;
        $stmt = $this->db->prepare("
            SELECT d.*, u.name as responsable_name,
                   DATEDIFF(d.deadline, NOW()) as days_remaining
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            WHERE d.deadline IS NOT NULL 
                  AND d.deadline >= NOW() 
                  AND d.deadline <= DATE_ADD(NOW(), INTERVAL ? DAY)
                  AND d.status NOT IN ('valide', 'archive')
            ORDER BY d.deadline ASC
        ");
        $stmt->execute([$daysAhead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget flux d'activité
     */
    private function getActivityFeedData($config) {
        $limit = $config['limit'] ?? 10;
        // Si une table d'audit existe, l'utiliser, sinon simuler avec les dernières modifications
        $stmt = $this->db->prepare("
            SELECT 'dossier_updated' as activity_type, 
                   d.titre as item_title,
                   d.reference as item_reference,
                   u.name as user_name,
                   d.updated_at as activity_date
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            ORDER BY d.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget graphique performance
     */
    private function getPerformanceChartData($config) {
        $period = $config['period'] ?? '30d';
        $days = (int) substr($period, 0, -1);
        
        $stmt = $this->db->prepare("
            SELECT DATE(created_at) as date,
                   COUNT(*) as created_count,
                   SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as validated_count
            FROM dossiers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Données pour le widget actions rapides
     */
    private function getQuickActionsData($config) {
        $actions = $config['actions'] ?? ['create_dossier', 'search', 'reports'];
        
        $actionData = [
            'create_dossier' => [
                'title' => 'Nouveau dossier',
                'icon' => 'fa-plus',
                'url' => 'modules/dossiers/create.php',
                'color' => '#3498db'
            ],
            'search' => [
                'title' => 'Recherche avancée',
                'icon' => 'fa-search',
                'url' => 'modules/dossiers/search.php',
                'color' => '#9b59b6'
            ],
            'reports' => [
                'title' => 'Rapports',
                'icon' => 'fa-chart-pie',
                'url' => 'modules/reporting/index.php',
                'color' => '#e67e22'
            ]
        ];
        
        $result = [];
        foreach ($actions as $action) {
            if (isset($actionData[$action])) {
                $result[] = $actionData[$action];
            }
        }
        
        return $result;
    }
    
    /**
     * Données pour le widget charge équipe
     */
    private function getTeamWorkloadData($config) {
        $stmt = $this->db->prepare("
            SELECT u.name as user_name,
                   COUNT(d.id) as total_dossiers,
                   SUM(CASE WHEN d.status = 'en_cours' THEN 1 ELSE 0 END) as active_dossiers,
                   SUM(CASE WHEN d.priority = 'urgent' THEN 1 ELSE 0 END) as urgent_dossiers
            FROM users u
            LEFT JOIN dossiers d ON u.id = d.responsable_id AND d.status != 'archive'
            WHERE u.role IN ('user', 'manager')
            GROUP BY u.id, u.name
            ORDER BY active_dossiers DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
