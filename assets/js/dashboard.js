/**
 * Dashboard Personnalisable - Script JavaScript principal
 * Gère l'interface utilisateur, les widgets et l'actualisation temps réel
 */

class PersonalizableDashboard {
    constructor(config) {
        this.config = config;
        this.grid = null;
        this.editMode = false;
        this.autoRefreshInterval = null;
        this.autoRefreshTimer = null;
        this.notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LNeSMFl0');
        
        this.init();
    }
    
    init() {
        this.initGridStack();
        this.initEventListeners();
        this.loadUserPreferences();
        this.loadWidgets();
        this.startAutoRefresh();
        this.loadNotifications();
        
        console.log('Dashboard personnalisable initialisé');
    }
    
    initGridStack() {
        this.grid = GridStack.init({
            cellHeight: 120,
            verticalMargin: 10,
            animate: true,
            disableDrag: true,
            disableResize: true,
            removable: false,
            acceptWidgets: true,
            column: 12,
            margin: 10,
            float: false
        });
        
        // Événement de changement de layout
        this.grid.on('change', (event, items) => {
            if (this.editMode && items) {
                items.forEach(item => {
                    this.saveWidgetLayout(item.el.dataset.widgetId, item.x, item.y, item.w, item.h);
                });
            }
        });
    }
    
    initEventListeners() {
        // Toggle mode édition
        document.getElementById('editModeToggle')?.addEventListener('click', () => {
            this.toggleEditMode();
        });
        
        // Bouton ajouter widget
        document.getElementById('addWidgetBtn')?.addEventListener('click', () => {
            this.showAddWidgetModal();
        });
        
        // Bouton préférences
        document.getElementById('preferencesBtn')?.addEventListener('click', () => {
            this.showPreferencesModal();
        });
        
        // Toggle notifications
        document.getElementById('notificationToggle')?.addEventListener('click', () => {
            this.toggleNotifications();
        });
        
        // Marquer toutes les notifications comme lues
        document.getElementById('markAllRead')?.addEventListener('click', () => {
            this.markAllNotificationsRead();
        });
        
        // Fermeture des modales
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.closeModal(e.target.closest('.modal'));
            });
        });
        
        // Fermeture des modales en cliquant à l'extérieur
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        });
        
        // Onglets de catégories de widgets
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.switchWidgetCategory(tab.dataset.category);
            });
        });
        
        // Boutons d'ajout de widget
        document.querySelectorAll('.add-widget-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.addWidget(btn.dataset.widgetType);
            });
        });
        
        // Formulaire de préférences
        document.getElementById('preferencesForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.savePreferences();
        });
        
        // Bouton annuler préférences
        document.getElementById('cancelPreferences')?.addEventListener('click', () => {
            this.closeModal(document.getElementById('preferencesModal'));
        });
        
        // Raccourcis clavier
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                this.toggleEditMode();
            }
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }
    
    loadUserPreferences() {
        const prefs = this.config.preferences;
        if (!prefs) return;
        
        // Appliquer le thème
        document.body.setAttribute('data-theme', prefs.theme || 'light');
        
        // Configurer l'auto-refresh
        if (prefs.auto_refresh_interval > 0) {
            this.autoRefreshInterval = prefs.auto_refresh_interval * 1000;
        }
        
        // Pré-remplir le formulaire de préférences
        if (document.getElementById('autoRefreshInterval')) {
            document.getElementById('autoRefreshInterval').value = prefs.auto_refresh_interval || 30;
            document.getElementById('theme').value = prefs.theme || 'light';
            document.getElementById('notificationsEnabled').checked = prefs.notifications_enabled;
            document.getElementById('soundAlerts').checked = prefs.sound_alerts;
            document.getElementById('showTooltips').checked = prefs.show_tooltips;
        }
    }
    
    loadWidgets() {
        const widgets = this.config.widgets || [];
        
        widgets.forEach(widget => {
            this.createWidgetElement(widget);
        });
        
        // Si aucun widget, afficher message d'accueil
        if (widgets.length === 0) {
            this.showWelcomeMessage();
        }
    }
    
    createWidgetElement(widget) {
        const template = document.getElementById('widgetTemplate');
        if (!template) return;
        
        let html = template.innerHTML;
        html = html.replace(/{{id}}/g, widget.id);
        html = html.replace(/{{type}}/g, widget.widget_type);
        html = html.replace(/{{icon}}/g, widget.icon || 'fa-square');
        html = html.replace(/{{name}}/g, widget.widget_name || widget.widget_type);
        
        // Créer l'élément widget
        const widgetEl = document.createElement('div');
        widgetEl.innerHTML = html;
        const gridItem = widgetEl.firstElementChild;
        
        // Ajouter au grid
        this.grid.addWidget(gridItem, {
            x: widget.position_x || 0,
            y: widget.position_y || 0,
            w: widget.width || 4,
            h: widget.height || 3
        });
        
        // Charger le contenu du widget
        this.loadWidgetData(widget.id, widget.widget_type, JSON.parse(widget.config || '{}'));
        
        // Ajouter les événements aux actions du widget
        this.attachWidgetEvents(gridItem);
    }
    
    attachWidgetEvents(widgetEl) {
        const widgetId = widgetEl.dataset.widgetId;
        const widgetType = widgetEl.dataset.widgetType;
        
        // Bouton actualiser
        const refreshBtn = widgetEl.querySelector('.refresh-widget');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshWidget(widgetId, widgetType);
            });
        }
        
        // Bouton configurer
        const configBtn = widgetEl.querySelector('.config-widget');
        if (configBtn) {
            configBtn.addEventListener('click', () => {
                this.configureWidget(widgetId, widgetType);
            });
        }
        
        // Bouton supprimer
        const removeBtn = widgetEl.querySelector('.remove-widget');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                this.removeWidget(widgetId, widgetEl);
            });
        }
    }
    
    loadWidgetData(widgetId, widgetType, config = {}) {
        const contentEl = document.getElementById(`widget-content-${widgetId}`);
        if (!contentEl) return;
        
        // Afficher l'indicateur de chargement
        contentEl.innerHTML = `
            <div class="widget-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Chargement...</span>
            </div>
        `;
        
        // Requête AJAX pour récupérer les données
        fetch(`modules/dashboard/api.php?action=get_widget_data&widget_type=${widgetType}&config=${encodeURIComponent(JSON.stringify(config))}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderWidgetContent(widgetId, widgetType, data.data);
                } else {
                    this.showWidgetError(contentEl, data.error || 'Erreur de chargement');
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement du widget:', error);
                this.showWidgetError(contentEl, 'Erreur de connexion');
            });
    }
    
    renderWidgetContent(widgetId, widgetType, data) {
        const contentEl = document.getElementById(`widget-content-${widgetId}`);
        if (!contentEl) return;
        
        let html = '';
        
        switch (widgetType) {
            case 'stats_counter':
                html = this.renderStatsCounter(data);
                break;
            case 'recent_dossiers':
                html = this.renderRecentDossiers(data);
                break;
            case 'priority_alerts':
                html = this.renderPriorityAlerts(data);
                break;
            case 'deadline_tracker':
                html = this.renderDeadlineTracker(data);
                break;
            case 'activity_feed':
                html = this.renderActivityFeed(data);
                break;
            case 'performance_chart':
                html = this.renderPerformanceChart(widgetId, data);
                break;
            case 'quick_actions':
                html = this.renderQuickActions(data);
                break;
            case 'team_workload':
                html = this.renderTeamWorkload(data);
                break;
            default:
                html = '<p class="text-muted">Type de widget non supporté</p>';
        }
        
        contentEl.innerHTML = html;
        
        // Initialiser les graphiques si nécessaire
        if (widgetType === 'performance_chart') {
            this.initChart(widgetId, data);
        }
    }
    
    renderStatsCounter(data) {
        return `
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">${data.total || 0}</div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${data.en_cours || 0}</div>
                    <div class="stat-label">En cours</div>
                </div>
                <div class="stat-item success">
                    <div class="stat-number">${data.valides || 0}</div>
                    <div class="stat-label">Validés</div>
                </div>
                <div class="stat-item danger">
                    <div class="stat-number">${data.rejetes || 0}</div>
                    <div class="stat-label">Rejetés</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${data.archives || 0}</div>
                    <div class="stat-label">Archivés</div>
                </div>
                <div class="stat-item warning">
                    <div class="stat-number">${data.urgent || 0}</div>
                    <div class="stat-label">Urgent</div>
                </div>
            </div>
        `;
    }
    
    renderRecentDossiers(data) {
        if (!data || data.length === 0) {
            return '<p class="text-muted text-center">Aucun dossier récent</p>';
        }
        
        return data.map(dossier => `
            <div class="recent-item">
                <div class="recent-info">
                    <div class="recent-title">
                        <strong>${this.escapeHtml(dossier.reference)}</strong>
                        <span class="status-badge status-${dossier.status}">${dossier.status}</span>
                    </div>
                    <div class="recent-subtitle">${this.escapeHtml(dossier.titre || '')}</div>
                    <div class="recent-meta">
                        <span><i class="fas fa-user"></i> ${this.escapeHtml(dossier.responsable_name || 'Non assigné')}</span>
                        <span><i class="fas fa-clock"></i> ${this.formatDate(dossier.updated_at)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    renderPriorityAlerts(data) {
        if (!data || data.length === 0) {
            return `
                <div class="text-center text-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <p class="mt-2 mb-0">Aucune alerte prioritaire</p>
                </div>
            `;
        }
        
        return data.map(dossier => `
            <div class="alert-item ${dossier.priority === 'urgent' ? 'alert-danger' : 'alert-warning'}">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <div class="alert-title">
                        <strong>${this.escapeHtml(dossier.reference)}</strong>
                        <span class="priority-badge priority-${dossier.priority}">${dossier.priority}</span>
                    </div>
                    <div class="alert-description">${this.escapeHtml(dossier.titre || '')}</div>
                    <div class="alert-meta">
                        <span>${this.escapeHtml(dossier.responsable_name || 'Non assigné')}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    renderDeadlineTracker(data) {
        if (!data || data.length === 0) {
            return '<p class="text-muted text-center">Aucune échéance proche</p>';
        }
        
        return data.map(dossier => {
            const daysRemaining = dossier.days_remaining;
            const urgencyClass = daysRemaining <= 1 ? 'danger' : daysRemaining <= 3 ? 'warning' : 'info';
            
            return `
                <div class="deadline-item ${urgencyClass}">
                    <div class="deadline-info">
                        <div class="deadline-title">
                            <strong>${this.escapeHtml(dossier.reference)}</strong>
                            <span class="days-remaining ${urgencyClass}">
                                ${daysRemaining} jour(s)
                            </span>
                        </div>
                        <div class="deadline-description">${this.escapeHtml(dossier.titre || '')}</div>
                        <div class="deadline-date">
                            <i class="fas fa-calendar"></i> ${this.formatDate(dossier.deadline)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    renderActivityFeed(data) {
        if (!data || data.length === 0) {
            return '<p class="text-muted text-center">Aucune activité récente</p>';
        }
        
        return data.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${this.escapeHtml(activity.item_title || '')}</div>
                    <div class="activity-meta">
                        <span>${this.escapeHtml(activity.user_name || 'Système')}</span>
                        <span>${this.formatDate(activity.activity_date)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    renderQuickActions(data) {
        if (!data || data.length === 0) {
            return '<p class="text-muted text-center">Aucune action configurée</p>';
        }
        
        return `
            <div class="quick-actions-grid">
                ${data.map(action => `
                    <a href="${action.url}" class="quick-action" style="background: ${action.color}">
                        <i class="fas ${action.icon}"></i>
                        <span>${this.escapeHtml(action.title)}</span>
                    </a>
                `).join('')}
            </div>
        `;
    }
    
    renderTeamWorkload(data) {
        if (!data || data.length === 0) {
            return '<p class="text-muted text-center">Aucune donnée d\'équipe</p>';
        }
        
        const maxDossiers = Math.max(...data.map(member => member.total_dossiers));
        
        return data.map(member => {
            const percentage = maxDossiers > 0 ? (member.active_dossiers / maxDossiers) * 100 : 0;
            
            return `
                <div class="team-member">
                    <div class="member-info">
                        <div class="member-name">${this.escapeHtml(member.user_name)}</div>
                        <div class="member-stats">
                            <span class="active-count">${member.active_dossiers} actifs</span>
                            ${member.urgent_dossiers > 0 ? `<span class="urgent-count">${member.urgent_dossiers} urgent(s)</span>` : ''}
                        </div>
                    </div>
                    <div class="workload-bar">
                        <div class="workload-fill" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    renderPerformanceChart(widgetId, data) {
        return `
            <div class="chart-container">
                <canvas id="chart-${widgetId}" width="400" height="200"></canvas>
            </div>
        `;
    }
    
    initChart(widgetId, data) {
        const canvas = document.getElementById(`chart-${widgetId}`);
        if (!canvas || !data || data.length === 0) return;
        
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => this.formatDate(item.date)),
                datasets: [
                    {
                        label: 'Créés',
                        data: data.map(item => item.created_count),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Validés',
                        data: data.map(item => item.validated_count),
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    showWidgetError(contentEl, message) {
        contentEl.innerHTML = `
            <div class="widget-error">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <p class="text-muted">${this.escapeHtml(message)}</p>
                <button class="btn-retry" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Réessayer
                </button>
            </div>
        `;
    }
    
    showWelcomeMessage() {
        const grid = document.getElementById('dashboardGrid');
        if (!grid) return;
        
        grid.innerHTML = `
            <div class="welcome-message">
                <div class="welcome-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h2>Bienvenue sur votre dashboard personnalisé!</h2>
                <p>Commencez par ajouter des widgets pour personnaliser votre espace de travail.</p>
                <button class="btn-primary" id="startCustomization">
                    <i class="fas fa-plus"></i> Ajouter mon premier widget
                </button>
            </div>
        `;
        
        document.getElementById('startCustomization')?.addEventListener('click', () => {
            this.showAddWidgetModal();
        });
    }
    
    toggleEditMode() {
        this.editMode = !this.editMode;
        
        const toggleBtn = document.getElementById('editModeToggle');
        const widgets = document.querySelectorAll('.grid-stack-item');
        
        if (this.editMode) {
            this.grid.enableMove(true);
            this.grid.enableResize(true);
            toggleBtn.classList.add('active');
            toggleBtn.querySelector('span').textContent = 'Terminer';
            widgets.forEach(widget => widget.classList.add('edit-mode'));
            
            this.showToast('Mode édition activé', 'info');
        } else {
            this.grid.enableMove(false);
            this.grid.enableResize(false);
            toggleBtn.classList.remove('active');
            toggleBtn.querySelector('span').textContent = 'Éditer';
            widgets.forEach(widget => widget.classList.remove('edit-mode'));
            
            this.showToast('Mode édition désactivé', 'success');
        }
    }
    
    showAddWidgetModal() {
        const modal = document.getElementById('addWidgetModal');
        if (!modal) return;
        
        // Activer la première catégorie par défaut
        const firstTab = modal.querySelector('.category-tab');
        if (firstTab) {
            this.switchWidgetCategory(firstTab.dataset.category);
            firstTab.classList.add('active');
        }
        
        this.showModal(modal);
    }
    
    switchWidgetCategory(category) {
        // Désactiver tous les onglets et catégories
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.widget-category').forEach(cat => {
            cat.classList.remove('active');
        });
        
        // Activer l'onglet et la catégorie sélectionnés
        document.querySelector(`[data-category="${category}"]`).classList.add('active');
        document.querySelector(`.widget-category[data-category="${category}"]`).classList.add('active');
    }
    
    addWidget(widgetType) {
        const dashboardId = this.config.dashboardId;
        
        // Fermer la modal
        this.closeModal(document.getElementById('addWidgetModal'));
        
        // Requête pour ajouter le widget
        fetch('modules/dashboard/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_widget&dashboard_id=${dashboardId}&widget_type=${widgetType}&x=0&y=0&width=4&height=3`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showToast('Widget ajouté avec succès', 'success');
                // Recharger la page pour afficher le nouveau widget
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showToast(data.error || 'Erreur lors de l\'ajout du widget', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        });
    }
    
    removeWidget(widgetId, widgetEl) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce widget ?')) {
            return;
        }
        
        fetch('modules/dashboard/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove_widget&widget_id=${widgetId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.grid.removeWidget(widgetEl);
                this.showToast('Widget supprimé', 'success');
            } else {
                this.showToast(data.error || 'Erreur lors de la suppression', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        });
    }
    
    refreshWidget(widgetId, widgetType) {
        this.loadWidgetData(widgetId, widgetType);
    }
    
    configureWidget(widgetId, widgetType) {
        // TODO: Implémenter la configuration des widgets
        this.showToast('Configuration des widgets en développement', 'info');
    }
    
    saveWidgetLayout(widgetId, x, y, width, height) {
        fetch('modules/dashboard/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_widget_layout&widget_id=${widgetId}&x=${x}&y=${y}&width=${width}&height=${height}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Erreur lors de la sauvegarde du layout:', data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
    
    showPreferencesModal() {
        const modal = document.getElementById('preferencesModal');
        if (!modal) return;
        
        this.showModal(modal);
    }
    
    savePreferences() {
        const form = document.getElementById('preferencesForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'update_preferences');
        
        fetch('modules/dashboard/api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showToast('Préférences sauvegardées', 'success');
                this.closeModal(document.getElementById('preferencesModal'));
                
                // Recharger pour appliquer les changements
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showToast(data.error || 'Erreur lors de la sauvegarde', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        });
    }
    
    startAutoRefresh() {
        if (!this.autoRefreshInterval || this.autoRefreshInterval <= 0) {
            return;
        }
        
        this.autoRefreshTimer = setInterval(() => {
            this.refreshAllWidgets();
            this.loadNotifications();
        }, this.autoRefreshInterval);
        
        // Indicateur visuel du timer
        this.startRefreshTimer();
    }
    
    startRefreshTimer() {
        const timerEl = document.getElementById('refreshTimer');
        const indicatorEl = document.getElementById('autoRefreshIndicator');
        
        if (!timerEl || !this.autoRefreshInterval) return;
        
        let remaining = this.autoRefreshInterval / 1000;
        
        const countdown = setInterval(() => {
            remaining--;
            timerEl.textContent = `${remaining}s`;
            
            if (remaining <= 0) {
                clearInterval(countdown);
                indicatorEl.classList.add('active');
                
                setTimeout(() => {
                    indicatorEl.classList.remove('active');
                    this.startRefreshTimer();
                }, 2000);
            }
        }, 1000);
    }
    
    refreshAllWidgets() {
        const widgets = document.querySelectorAll('.grid-stack-item');
        widgets.forEach(widget => {
            const widgetId = widget.dataset.widgetId;
            const widgetType = widget.dataset.widgetType;
            if (widgetId && widgetType) {
                this.loadWidgetData(widgetId, widgetType);
            }
        });
    }
    
    loadNotifications() {
        fetch('modules/dashboard/api.php?action=get_notifications')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateNotifications(data.data);
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des notifications:', error);
            });
    }
    
    updateNotifications(notifications) {
        const badge = document.getElementById('notificationBadge');
        const list = document.getElementById('notificationList');
        
        if (badge) {
            badge.textContent = notifications.length;
            badge.style.display = notifications.length > 0 ? 'flex' : 'none';
        }
        
        if (list) {
            if (notifications.length === 0) {
                list.innerHTML = '<div class="no-notifications">Aucune notification</div>';
            } else {
                list.innerHTML = notifications.map(notif => `
                    <div class="notification-item" data-id="${notif.id}">
                        <div class="notification-content">
                            <div class="notification-title">${this.escapeHtml(notif.title || 'Notification')}</div>
                            <div class="notification-message">${this.escapeHtml(notif.message || '')}</div>
                            <div class="notification-time">${this.formatDate(notif.created_at)}</div>
                        </div>
                        <button class="mark-read-btn" onclick="dashboard.markNotificationRead(${notif.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                `).join('');
            }
        }
        
        // Jouer un son si nouvelles notifications
        if (notifications.length > 0 && this.config.preferences?.sound_alerts) {
            this.playNotificationSound();
        }
    }
    
    toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;
        
        dropdown.classList.toggle('show');
    }
    
    markNotificationRead(notificationId) {
        fetch('modules/dashboard/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_notification_read&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadNotifications();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
    
    markAllNotificationsRead() {
        const notifications = document.querySelectorAll('.notification-item');
        notifications.forEach(notif => {
            const id = notif.dataset.id;
            if (id) {
                this.markNotificationRead(id);
            }
        });
    }
    
    playNotificationSound() {
        try {
            this.notificationSound.play();
        } catch (error) {
            console.log('Son de notification non disponible');
        }
    }
    
    showModal(modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    closeModal(modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            this.closeModal(modal);
        });
    }
    
    showToast(message, type = 'info') {
        // Créer l'élément toast s'il n'existe pas
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${this.getToastIcon(type)}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
            <button class="toast-close">&times;</button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Animation d'entrée
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Fermeture automatique
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
        
        // Fermeture manuelle
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });
    }
    
    getToastIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 1) {
            return 'Aujourd\'hui';
        } else if (diffDays === 2) {
            return 'Hier';
        } else if (diffDays <= 7) {
            return `Il y a ${diffDays - 1} jours`;
        } else {
            return date.toLocaleDateString('fr-FR');
        }
    }
}

// Styles CSS pour les toasts
const toastStyles = `
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 3000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.3s ease;
    border-left: 4px solid #3498db;
}

.toast.show {
    transform: translateX(0);
    opacity: 1;
}

.toast-success { border-left-color: #27ae60; }
.toast-error { border-left-color: #e74c3c; }
.toast-warning { border-left-color: #f39c12; }
.toast-info { border-left-color: #3498db; }

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toast-content i {
    font-size: 1.2rem;
}

.toast-success i { color: #27ae60; }
.toast-error i { color: #e74c3c; }
.toast-warning i { color: #f39c12; }
.toast-info i { color: #3498db; }

.toast-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-close:hover {
    color: #343a40;
}
`;

// Injecter les styles
const styleSheet = document.createElement('style');
styleSheet.textContent = toastStyles;
document.head.appendChild(styleSheet);
