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
    
    private function getUsersConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'total_users',
                    'title' => 'Utilisateurs Actifs',
                    'icon' => 'users',
                    'type' => 'info',
                    'query' => 'active_users',
                    'description' => 'Comptes utilisateurs actifs',
                    'action' => 'modules/users/list.php'
                ],
                [
                    'id' => 'new_registrations',
                    'title' => 'Nouvelles Inscriptions',
                    'icon' => 'user-plus',
                    'type' => 'success',
                    'query' => 'new_registrations',
                    'description' => 'Ce mois-ci',
                    'action' => 'modules/users/list.php?filter=recent'
                ],
                [
                    'id' => 'pending_approvals',
                    'title' => 'En Attente d\'Approbation',
                    'icon' => 'user-clock',
                    'type' => 'important',
                    'query' => 'pending_approvals',
                    'description' => 'Comptes à valider',
                    'action' => 'modules/users/list.php?filter=pending'
                ],
                [
                    'id' => 'online_now',
                    'title' => 'Connectés Maintenant',
                    'icon' => 'circle',
                    'type' => 'success',
                    'query' => 'online_users',
                    'description' => 'Utilisateurs en ligne',
                    'action' => '#'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'user-plus', 'label' => 'Nouvel Utilisateur', 'url' => 'modules/users/create.php'],
                ['icon' => 'users-cog', 'label' => 'Gestion Rôles', 'url' => 'modules/users/roles.php'],
                ['icon' => 'file-export', 'label' => 'Export Utilisateurs', 'url' => 'modules/export/users.php']
            ]
        ];
    }
    
    private function getNotificationsConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'unread_notifications',
                    'title' => 'Non Lues',
                    'icon' => 'bell',
                    'type' => 'important',
                    'query' => 'unread_count',
                    'description' => 'Notifications non lues',
                    'action' => 'modules/notifications/list.php?filter=unread'
                ],
                [
                    'id' => 'sent_today',
                    'title' => 'Envoyées Aujourd\'hui',
                    'icon' => 'paper-plane',
                    'type' => 'info',
                    'query' => 'sent_today',
                    'description' => 'Notifications du jour',
                    'action' => 'modules/notifications/list.php?filter=today'
                ],
                [
                    'id' => 'urgent_notifications',
                    'title' => 'Urgentes',
                    'icon' => 'exclamation-circle',
                    'type' => 'urgent',
                    'query' => 'urgent_notifications',
                    'description' => 'Priorité élevée',
                    'action' => 'modules/notifications/list.php?filter=urgent'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'plus', 'label' => 'Nouvelle Notification', 'url' => 'modules/notifications/create.php'],
                ['icon' => 'broadcast-tower', 'label' => 'Diffusion Générale', 'url' => 'modules/notifications/broadcast.php'],
                ['icon' => 'cog', 'label' => 'Paramètres', 'url' => 'modules/notifications/settings.php']
            ]
        ];
    }
    
    private function getMessagerieConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'unread_messages',
                    'title' => 'Messages Non Lus',
                    'icon' => 'envelope',
                    'type' => 'important',
                    'query' => 'unread_messages',
                    'description' => 'Dans votre boîte de réception',
                    'action' => 'modules/messagerie/list.php?filter=unread'
                ],
                [
                    'id' => 'recent_conversations',
                    'title' => 'Conversations Actives',
                    'icon' => 'comments',
                    'type' => 'info',
                    'query' => 'active_conversations',
                    'description' => 'Dernières 24h',
                    'action' => 'modules/messagerie/list.php?filter=active'
                ],
                [
                    'id' => 'draft_messages',
                    'title' => 'Brouillons',
                    'icon' => 'edit',
                    'type' => 'success',
                    'query' => 'draft_count',
                    'description' => 'Messages en cours',
                    'action' => 'modules/messagerie/list.php?filter=drafts'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'plus', 'label' => 'Nouveau Message', 'url' => 'modules/messagerie/compose.php'],
                ['icon' => 'users', 'label' => 'Message Groupé', 'url' => 'modules/messagerie/group.php'],
                ['icon' => 'archive', 'label' => 'Archives', 'url' => 'modules/messagerie/archive.php']
            ]
        ];
    }
    
    private function getArchivageConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'archived_dossiers',
                    'title' => 'Dossiers Archivés',
                    'icon' => 'archive',
                    'type' => 'info',
                    'query' => 'archived_count',
                    'description' => 'Total des archives',
                    'action' => 'modules/archivage/list.php'
                ],
                [
                    'id' => 'recent_archives',
                    'title' => 'Archivés Récemment',
                    'icon' => 'clock',
                    'type' => 'success',
                    'query' => 'recent_archives',
                    'description' => 'Cette semaine',
                    'action' => 'modules/archivage/list.php?filter=recent'
                ],
                [
                    'id' => 'storage_size',
                    'title' => 'Espace Archives',
                    'icon' => 'hdd',
                    'type' => 'important',
                    'query' => 'storage_size',
                    'description' => 'Espace disque utilisé',
                    'action' => 'modules/archivage/storage.php',
                    'format' => 'size'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'search', 'label' => 'Recherche Archives', 'url' => 'modules/archivage/search.php'],
                ['icon' => 'download', 'label' => 'Export Archives', 'url' => 'modules/export/archives.php'],
                ['icon' => 'cog', 'label' => 'Config Archivage', 'url' => 'modules/archivage/config.php']
            ]
        ];
    }
    
    private function getAnalyticsConfig() {
        return [
            'show' => true,
            'priority' => 'high',
            'elements' => [
                [
                    'id' => 'page_views',
                    'title' => 'Vues de Pages',
                    'icon' => 'eye',
                    'type' => 'info',
                    'query' => 'page_views',
                    'description' => 'Aujourd\'hui',
                    'action' => 'modules/analytics/pages.php'
                ],
                [
                    'id' => 'active_sessions',
                    'title' => 'Sessions Actives',
                    'icon' => 'users',
                    'type' => 'success',
                    'query' => 'active_sessions',
                    'description' => 'Utilisateurs connectés',
                    'action' => 'modules/analytics/sessions.php'
                ],
                [
                    'id' => 'conversion_rate',
                    'title' => 'Taux de Conversion',
                    'icon' => 'chart-line',
                    'type' => 'important',
                    'query' => 'conversion_rate',
                    'description' => 'Dossiers validés/créés',
                    'action' => 'modules/analytics/conversion.php',
                    'format' => 'percentage'
                ],
                [
                    'id' => 'performance_score',
                    'title' => 'Score Performance',
                    'icon' => 'tachometer-alt',
                    'type' => 'info',
                    'query' => 'performance_score',
                    'description' => 'Indice de performance',
                    'action' => 'modules/analytics/performance.php'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'chart-bar', 'label' => 'Rapport Détaillé', 'url' => 'modules/analytics/report.php'],
                ['icon' => 'download', 'label' => 'Export Analytics', 'url' => 'modules/export/analytics.php'],
                ['icon' => 'cog', 'label' => 'Config Tracking', 'url' => 'modules/analytics/config.php']
            ]
        ];
    }
    
    private function getCategoriesConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'total_categories',
                    'title' => 'Catégories Actives',
                    'icon' => 'tags',
                    'type' => 'info',
                    'query' => 'active_categories',
                    'description' => 'Catégories disponibles',
                    'action' => 'modules/categories/index.php'
                ],
                [
                    'id' => 'most_used',
                    'title' => 'Plus Utilisée',
                    'icon' => 'star',
                    'type' => 'success',
                    'query' => 'most_used_category',
                    'description' => 'Catégorie populaire',
                    'action' => 'modules/categories/stats.php'
                ],
                [
                    'id' => 'unused_categories',
                    'title' => 'Non Utilisées',
                    'icon' => 'exclamation-triangle',
                    'type' => 'important',
                    'query' => 'unused_categories',
                    'description' => 'Catégories sans dossiers',
                    'action' => 'modules/categories/cleanup.php'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'plus', 'label' => 'Nouvelle Catégorie', 'url' => 'modules/categories/create.php'],
                ['icon' => 'sort', 'label' => 'Réorganiser', 'url' => 'modules/categories/sort.php'],
                ['icon' => 'chart-pie', 'label' => 'Statistiques', 'url' => 'modules/categories/stats.php']
            ]
        ];
    }
    
    private function getLogsConfig() {
        return [
            'show' => true,
            'priority' => 'high',
            'elements' => [
                [
                    'id' => 'recent_activities',
                    'title' => 'Activités Récentes',
                    'icon' => 'history',
                    'type' => 'info',
                    'query' => 'recent_activities',
                    'description' => 'Dernière heure',
                    'action' => 'modules/logs/index.php?filter=recent'
                ],
                [
                    'id' => 'error_logs',
                    'title' => 'Erreurs Système',
                    'icon' => 'exclamation-circle',
                    'type' => 'urgent',
                    'query' => 'error_count',
                    'description' => 'Erreurs à investiguer',
                    'action' => 'modules/logs/index.php?filter=errors'
                ],
                [
                    'id' => 'security_events',
                    'title' => 'Événements Sécurité',
                    'icon' => 'shield-alt',
                    'type' => 'important',
                    'query' => 'security_events',
                    'description' => 'Alertes de sécurité',
                    'action' => 'modules/logs/security.php'
                ],
                [
                    'id' => 'log_size',
                    'title' => 'Taille des Logs',
                    'icon' => 'database',
                    'type' => 'info',
                    'query' => 'log_size',
                    'description' => 'Espace utilisé',
                    'action' => 'modules/logs/maintenance.php',
                    'format' => 'size'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'search', 'label' => 'Recherche Avancée', 'url' => 'modules/logs/search.php'],
                ['icon' => 'download', 'label' => 'Export Logs', 'url' => 'modules/export/logs.php'],
                ['icon' => 'trash', 'label' => 'Nettoyage', 'url' => 'modules/logs/cleanup.php']
            ]
        ];
    }
    
    private function getSearchConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'search_results',
                    'title' => 'Résultats Trouvés',
                    'icon' => 'search',
                    'type' => 'info',
                    'query' => 'search_results_count',
                    'description' => 'Pour la recherche actuelle',
                    'action' => '#'
                ],
                [
                    'id' => 'search_time',
                    'title' => 'Temps de Recherche',
                    'icon' => 'clock',
                    'type' => 'success',
                    'query' => 'search_time',
                    'description' => 'Millisecondes',
                    'action' => '#',
                    'format' => 'time'
                ],
                [
                    'id' => 'indexed_documents',
                    'title' => 'Documents Indexés',
                    'icon' => 'file-alt',
                    'type' => 'info',
                    'query' => 'indexed_count',
                    'description' => 'Total dans l\'index',
                    'action' => 'modules/search/index_status.php'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'search-plus', 'label' => 'Recherche Avancée', 'url' => 'modules/search/advanced.php'],
                ['icon' => 'save', 'label' => 'Sauvegarder Recherche', 'url' => 'modules/search/save.php'],
                ['icon' => 'history', 'label' => 'Historique', 'url' => 'modules/search/history.php']
            ]
        ];
    }
    
    private function getHelpConfig() {
        return [
            'show' => true,
            'priority' => 'low',
            'elements' => [
                [
                    'id' => 'help_articles',
                    'title' => 'Articles d\'Aide',
                    'icon' => 'book',
                    'type' => 'info',
                    'query' => 'help_articles_count',
                    'description' => 'Articles disponibles',
                    'action' => 'modules/help/index.php'
                ],
                [
                    'id' => 'recent_updates',
                    'title' => 'Mises à Jour',
                    'icon' => 'sync',
                    'type' => 'success',
                    'query' => 'recent_updates',
                    'description' => 'Articles mis à jour',
                    'action' => 'modules/help/updates.php'
                ],
                [
                    'id' => 'popular_topics',
                    'title' => 'Sujets Populaires',
                    'icon' => 'fire',
                    'type' => 'important',
                    'query' => 'popular_topics',
                    'description' => 'Plus consultés',
                    'action' => 'modules/help/popular.php'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'question-circle', 'label' => 'FAQ', 'url' => 'modules/help/faq.php'],
                ['icon' => 'video', 'label' => 'Tutoriels', 'url' => 'modules/help/tutorials.php'],
                ['icon' => 'phone', 'label' => 'Support', 'url' => 'modules/support/contact.php']
            ]
        ];
    }
    
    private function getExportConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'export_queue',
                    'title' => 'Exports en Cours',
                    'icon' => 'spinner',
                    'type' => 'info',
                    'query' => 'pending_exports',
                    'description' => 'Dans la file d\'attente',
                    'action' => 'modules/export/queue.php'
                ],
                [
                    'id' => 'completed_exports',
                    'title' => 'Exports Terminés',
                    'icon' => 'check-circle',
                    'type' => 'success',
                    'query' => 'completed_exports',
                    'description' => 'Prêts au téléchargement',
                    'action' => 'modules/export/completed.php'
                ],
                [
                    'id' => 'export_size',
                    'title' => 'Taille des Exports',
                    'icon' => 'hdd',
                    'type' => 'important',
                    'query' => 'total_export_size',
                    'description' => 'Espace utilisé',
                    'action' => 'modules/export/storage.php',
                    'format' => 'size'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'file-excel', 'label' => 'Export Excel', 'url' => 'modules/export/excel.php'],
                ['icon' => 'file-pdf', 'label' => 'Export PDF', 'url' => 'modules/export/pdf.php'],
                ['icon' => 'database', 'label' => 'Export Base', 'url' => 'modules/export/database.php']
            ]
        ];
    }
    
    private function getFormConfig() {
        return [
            'show' => true,
            'priority' => 'normal',
            'elements' => [
                [
                    'id' => 'form_progress',
                    'title' => 'Progression',
                    'icon' => 'progress-bar',
                    'type' => 'info',
                    'query' => 'form_completion',
                    'description' => 'Formulaire complété',
                    'action' => '#',
                    'format' => 'percentage'
                ],
                [
                    'id' => 'validation_errors',
                    'title' => 'Erreurs de Validation',
                    'icon' => 'exclamation-triangle',
                    'type' => 'urgent',
                    'query' => 'validation_errors',
                    'description' => 'Champs à corriger',
                    'action' => '#'
                ],
                [
                    'id' => 'auto_save',
                    'title' => 'Sauvegarde Auto',
                    'icon' => 'save',
                    'type' => 'success',
                    'query' => 'last_auto_save',
                    'description' => 'Dernière sauvegarde',
                    'action' => '#',
                    'format' => 'time_ago'
                ]
            ],
            'quick_actions' => [
                ['icon' => 'save', 'label' => 'Sauvegarder', 'url' => '#', 'onclick' => 'saveForm()'],
                ['icon' => 'undo', 'label' => 'Annuler', 'url' => '#', 'onclick' => 'resetForm()'],
                ['icon' => 'question', 'label' => 'Aide', 'url' => 'modules/help/forms.php']
            ]
        ];
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
