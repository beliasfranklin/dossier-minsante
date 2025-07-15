<?php
/**
 * Gestionnaire intelligent des éléments importants
 * Adapte l'affichage selon le contexte de la page
 */

class ModuleHighlightsManager {
    private $currentPage;
    private $userRole;
    private $userId;
    
    public function __construct() {
        $this->currentPage = $this->getCurrentPage();
        $this->userRole = $_SESSION['user_role'] ?? 'user';
        $this->userId = $_SESSION['user_id'] ?? 0;
    }
    
    private function getCurrentPage() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $pathInfo = pathinfo($scriptName);
        return $pathInfo['filename'] ?? 'unknown';
    }
    
    /**
     * Retourne la configuration des éléments importants selon le contexte
     */
    public function getHighlightsConfig() {
        $config = [
            'show' => true,
            'priority' => 'normal',
            'elements' => [],
            'quick_actions' => []
        ];
        
        switch ($this->currentPage) {
            case 'dashboard':
                $config = $this->getDashboardConfig();
                break;
                
            case 'list':
                if (strpos($_SERVER['REQUEST_URI'], 'dossiers') !== false) {
                    $config = $this->getDossiersConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'users') !== false) {
                    $config = $this->getUsersConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'notifications') !== false) {
                    $config = $this->getNotificationsConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'messagerie') !== false) {
                    $config = $this->getMessagerieConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'archivage') !== false) {
                    $config = $this->getArchivageConfig();
                }
                break;
                
            case 'stats':
            case 'advanced':
                $config = $this->getReportingConfig();
                break;
                
            case 'index':
                if (strpos($_SERVER['REQUEST_URI'], 'analytics') !== false) {
                    $config = $this->getAnalyticsConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'categories') !== false) {
                    $config = $this->getCategoriesConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'logs') !== false) {
                    $config = $this->getLogsConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'search') !== false) {
                    $config = $this->getSearchConfig();
                } elseif (strpos($_SERVER['REQUEST_URI'], 'help') !== false) {
                    $config = $this->getHelpConfig();
                }
                break;
                
            case 'export':
                $config = $this->getExportConfig();
                break;
                
            case 'create':
            case 'edit':
            case 'view':
                $config = $this->getFormConfig();
                break;
                
            case 'admin':
                $config = $this->getAdminConfig();
                break;
                
            default:
                $config = $this->getDefaultConfig();
                break;
        }
        
        // Filtrer selon les permissions
        $config = $this->filterByPermissions($config);
        
        return $config;
    }
    
    private function getDashboardConfig() {
        return [
            'show' => true,
            'priority' => 'high',
            'elements' => [
                [
                    'id' => 'retard',
                    'title' => 'Dossiers en Retard',
                    'icon' => 'exclamation-triangle',
                    'type' => 'urgent',
                    'query' => 'retard_count',
                    'description' => 'Nécessitent une attention immédiate',
                    'action' => 'modules/dossiers/list.php?filter=retard'
                ],
                [
                    'id' => 'urgent',
                    'title' => 'Échéances Prochaines',
                    'icon' => 'clock',
                    'type' => 'important',
                    'query' => 'urgent_count',
                    'description' => 'À traiter dans les 3 prochains jours',
                    'action' => 'modules/echeances/dashboard.php'
                ],
                [
                    'id' => 'nouveaux',
                    'title' => 'Nouveaux Dossiers',
                    'icon' => 'file-plus',
                    'type' => 'success',
                    'query' => 'new_count',
                    'description' => 'Créés cette semaine',
                    'action' => 'modules/dossiers/list.php?filter=nouveaux'
                ],
                [
                    'id' => 'validation',
                    'title' => 'Taux de Validation',
                    'icon' => 'chart-line',
                    'type' => 'info',
                    'query' => 'validation_rate',
                    'description' => 'Performance mensuelle',
                    'action' => 'modules/reporting/stats.php',
                    'format' => 'percentage'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'plus', 'label' => 'Nouveau Dossier', 'url' => 'modules/dossiers/create.php'],
                ['icon' => 'download', 'label' => 'Export Rapide', 'url' => 'modules/export/export.php'],
                ['icon' => 'chart-bar', 'label' => 'Rapports Avancés', 'url' => 'modules/reporting/advanced.php'],
                ['icon' => 'search', 'label' => 'Recherche Avancée', 'url' => 'modules/search/index.php']
            ]
        ];
    }
    
    private function getDossiersConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'total_visible',
                    'title' => 'Dossiers Visibles',
                    'icon' => 'folder-open',
                    'type' => 'info',
                    'query' => 'visible_count',
                    'description' => 'Dans la vue actuelle',
                    'action' => '#'
                ],
                [
                    'id' => 'selection',
                    'title' => 'Sélectionnés',
                    'icon' => 'check-square',
                    'type' => 'success',
                    'query' => 'selected_count',
                    'description' => 'Prêts pour action groupée',
                    'action' => '#'
                ],
                [
                    'id' => 'filtres_actifs',
                    'title' => 'Filtres Actifs',
                    'icon' => 'filter',
                    'type' => 'important',
                    'query' => 'filters_count',
                    'description' => 'Critères appliqués',
                    'action' => '#'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'plus', 'label' => 'Nouveau Dossier', 'url' => 'create.php'],
                ['icon' => 'download', 'label' => 'Exporter Sélection', 'url' => '#', 'onclick' => 'exportSelected()'],
                ['icon' => 'archive', 'label' => 'Archiver Sélection', 'url' => '#', 'onclick' => 'archiveSelected()']
            ]
        ];
    }
    
    private function getReportingConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'periode',
                    'title' => 'Période Analysée',
                    'icon' => 'calendar-alt',
                    'type' => 'info',
                    'query' => 'period_days',
                    'description' => 'Jours inclus dans le rapport',
                    'action' => '#',
                    'format' => 'days'
                ],
                [
                    'id' => 'tendance',
                    'title' => 'Tendance Générale',
                    'icon' => 'trending-up',
                    'type' => 'success',
                    'query' => 'general_trend',
                    'description' => 'Évolution par rapport à la période précédente',
                    'action' => '#',
                    'format' => 'percentage_trend'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'file-pdf', 'label' => 'Export PDF', 'url' => '#', 'onclick' => 'exportPDF()'],
                ['icon' => 'file-excel', 'label' => 'Export Excel', 'url' => '#', 'onclick' => 'exportExcel()'],
                ['icon' => 'share', 'label' => 'Partager', 'url' => '#', 'onclick' => 'shareReport()']
            ]
        ];
    }
    
    private function getAdminConfig() {
        return [
            'show' => true,
            'priority' => 'high',
            'elements' => [
                [
                    'id' => 'users_online',
                    'title' => 'Utilisateurs Connectés',
                    'icon' => 'users',
                    'type' => 'success',
                    'query' => 'online_users',
                    'description' => 'Actifs dans les dernières 15 minutes',
                    'action' => 'modules/users/list.php'
                ],
                [
                    'id' => 'system_health',
                    'title' => 'État Système',
                    'icon' => 'server',
                    'type' => 'info',
                    'query' => 'system_status',
                    'description' => 'Performance et disponibilité',
                    'action' => 'modules/system/health.php'
                ],
                [
                    'id' => 'storage_usage',
                    'title' => 'Espace Disque',
                    'icon' => 'hdd',
                    'type' => 'important',
                    'query' => 'storage_percent',
                    'description' => 'Utilisation du stockage',
                    'action' => 'modules/system/storage.php',
                    'format' => 'percentage'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'user-plus', 'label' => 'Nouvel Utilisateur', 'url' => 'modules/users/create.php'],
                ['icon' => 'database', 'label' => 'Sauvegarde', 'url' => 'modules/system/backup.php'],
                ['icon' => 'cog', 'label' => 'Configuration', 'url' => 'modules/system/config.php']
            ]
        ];
    }
    
    private function getDefaultConfig() {
        return [
            'show' => true,
            'priority' => 'low',
            'elements' => [
                [
                    'id' => 'notifications',
                    'title' => 'Notifications',
                    'icon' => 'bell',
                    'type' => 'info',
                    'query' => 'notifications_count',
                    'description' => 'Messages non lus',
                    'action' => 'modules/notifications/list.php'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'home', 'label' => 'Accueil', 'url' => 'dashboard.php'],
                ['icon' => 'folder', 'label' => 'Mes Dossiers', 'url' => 'modules/dossiers/list.php']
            ]
        ];
    }
    
    private function filterByPermissions($config) {
        // Filtrer les éléments selon les permissions utilisateur
        if ($this->userRole !== 'admin') {
            // Retirer certains éléments pour les non-admin
            $config['elements'] = array_filter($config['elements'], function($element) {
                $adminOnly = ['system_health', 'storage_usage', 'users_online'];
                return !in_array($element['id'], $adminOnly);
            });
            
            $config['quick_actions'] = array_filter($config['quick_actions'], function($action) {
                $adminUrls = ['modules/system/', 'modules/users/create.php'];
                foreach ($adminUrls as $adminUrl) {
                    if (strpos($action['url'], $adminUrl) !== false) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        return $config;
    }
    
    /**
     * Génère le HTML pour les éléments importants
     */
    public function generateHighlightsHTML() {
        $config = $this->getHighlightsConfig();
        
        if (!$config['show']) {
            return '';
        }
        
        $priorityClass = 'priority-' . $config['priority'];
        
        ob_start();
        ?>
        <div class="module-highlights <?php echo $priorityClass; ?>" id="moduleHighlights">
            <div class="module-highlights-header">
                <h3 class="module-highlights-title">
                    <i class="fas fa-star"></i>
                    Éléments Importants
                </h3>
                <button class="highlights-toggle-btn" onclick="toggleHighlights()" id="highlightsToggle">
                    <i class="fas fa-eye-slash"></i>
                    Masquer
                </button>
            </div>
            
            <div class="highlights-grid">
                <?php foreach ($config['elements'] as $element): ?>
                <div class="highlight-card <?php echo $element['type']; ?>">
                    <div class="highlight-icon">
                        <i class="fas fa-<?php echo $element['icon']; ?>"></i>
                    </div>
                    <div class="highlight-title"><?php echo htmlspecialchars($element['title']); ?></div>
                    <div class="highlight-value" id="<?php echo $element['id']; ?>">--</div>
                    <div class="highlight-description"><?php echo htmlspecialchars($element['description']); ?></div>
                    <a href="<?php echo BASE_URL . $element['action']; ?>" class="highlight-action">
                        <i class="fas fa-arrow-right"></i>
                        Voir plus
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($config['quick_actions'])): ?>
            <div class="highlights-quick-actions">
                <?php foreach ($config['quick_actions'] as $action): ?>
                <a href="<?php echo BASE_URL . $action['url']; ?>" 
                   class="quick-action-btn"
                   <?php echo isset($action['onclick']) ? 'onclick="' . $action['onclick'] . '; return false;"' : ''; ?>>
                    <i class="fas fa-<?php echo $action['icon']; ?>"></i>
                    <?php echo htmlspecialchars($action['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}

/**
 * Fonction utilitaire pour afficher les éléments importants
 */
function displayModuleHighlights() {
    $manager = new ModuleHighlightsManager();
    return $manager->generateHighlightsHTML();
}
?>
