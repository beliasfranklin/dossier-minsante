/**
 * Gestionnaire des préférences utilisateur côté client
 * Gère les changements de thème en temps réel et la synchronisation
 */

class PreferencesManager {
    constructor() {
        this.init();
    }
    
    init() {
        // Détecter le thème initial
        this.currentTheme = document.body.getAttribute('data-theme') || 'light';
        this.currentLanguage = document.body.getAttribute('data-language') || 'fr';
        
        // Écouter les changements de thème automatique
        if (this.currentTheme === 'auto') {
            this.setupAutoTheme();
        }
        
        // Écouter les changements dans les formulaires de préférences
        this.setupFormListeners();
        
        // Appliquer les animations
        this.setupAnimations();
        
        console.log('🎨 PreferencesManager initialisé - Thème:', this.currentTheme);
    }
    
    /**
     * Configurer le thème automatique selon l'heure
     */
    setupAutoTheme() {
        const updateAutoTheme = () => {
            const hour = new Date().getHours();
            const isDark = hour >= 18 || hour <= 6;
            const autoTheme = isDark ? 'dark' : 'light';
            
            if (autoTheme !== this.getEffectiveTheme()) {
                this.applyTheme(autoTheme);
                console.log('🔄 Thème auto mis à jour:', autoTheme);
            }
        };
        
        // Vérifier toutes les heures
        setInterval(updateAutoTheme, 60000); // 1 minute pour les tests
        updateAutoTheme();
    }
    
    /**
     * Obtenir le thème effectif (résolu si auto)
     */
    getEffectiveTheme() {
        if (this.currentTheme === 'auto') {
            const hour = new Date().getHours();
            return (hour >= 18 || hour <= 6) ? 'dark' : 'light';
        }
        return this.currentTheme;
    }
    
    /**
     * Écouter les changements dans les formulaires
     */
    setupFormListeners() {
        // Écouter les changements de thème
        document.addEventListener('change', (e) => {
            if (e.target.name === 'theme') {
                this.handleThemeChange(e.target.value);
            }
            
            if (e.target.name === 'dashboard_layout') {
                this.handleLayoutChange(e.target.value);
            }
            
            if (e.target.name === 'language') {
                this.handleLanguageChange(e.target.value);
            }
        });
        
        // Écouter les soumissions de formulaires de préférences
        document.addEventListener('submit', (e) => {
            if (e.target.closest('.preference-card')) {
                this.handlePreferenceSubmit(e);
            }
        });
    }
    
    /**
     * Gérer le changement de thème
     */
    handleThemeChange(newTheme) {
        console.log('🎨 Changement de thème détecté:', newTheme);
        this.currentTheme = newTheme;
        
        // Appliquer immédiatement le nouveau thème
        const effectiveTheme = this.getEffectiveTheme();
        this.applyTheme(effectiveTheme);
        
        // Configurer le thème auto si nécessaire
        if (newTheme === 'auto') {
            this.setupAutoTheme();
        }
        
        // Mettre à jour l'indicateur
        this.updateThemeIndicator(effectiveTheme);
        
        // Animation de transition
        this.animateThemeChange();
    }
    
    /**
     * Appliquer un thème
     */
    applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        document.body.className = document.body.className.replace(/theme-\w+/, `theme-${theme}`);
        
        // Recharger le CSS dynamique
        this.reloadDynamicCSS();
        
        // Événement personnalisé
        window.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme, effective: theme } 
        }));
    }
    
    /**
     * Recharger le CSS dynamique
     */
    reloadDynamicCSS() {
        const dynamicCSS = document.getElementById('dynamic-theme');
        if (dynamicCSS) {
            const newHref = dynamicCSS.href.split('?')[0] + '?v=' + Date.now();
            dynamicCSS.href = newHref;
        }
    }
    
    /**
     * Mettre à jour l'indicateur de thème
     */
    updateThemeIndicator(theme) {
        const indicator = document.querySelector('.theme-indicator');
        if (indicator) {
            const icon = theme === 'dark' ? '🌙' : '🌞';
            indicator.innerHTML = `${icon} ${theme.charAt(0).toUpperCase() + theme.slice(1)}`;
            indicator.title = `Thème actuel: ${theme}`;
        }
    }
    
    /**
     * Animation de changement de thème
     */
    animateThemeChange() {
        document.body.style.transition = 'all 0.5s ease';
        
        // Créer un effet de fondu
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.1);
            z-index: 9999;
            pointer-events: none;
            opacity: 1;
            transition: opacity 0.3s ease;
        `;
        
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                document.body.style.transition = '';
            }, 300);
        }, 100);
    }
    
    /**
     * Gérer le changement de layout
     */
    handleLayoutChange(newLayout) {
        console.log('📊 Changement de layout détecté:', newLayout);
        
        // Appliquer le nouveau layout
        const dashboardGrid = document.querySelector('.dashboard-grid, .preferences-container');
        if (dashboardGrid) {
            dashboardGrid.setAttribute('data-layout', newLayout);
            
            if (newLayout === 'list') {
                dashboardGrid.style.display = 'flex';
                dashboardGrid.style.flexDirection = 'column';
            } else {
                dashboardGrid.style.display = 'grid';
                dashboardGrid.style.flexDirection = '';
            }
        }
        
        // Animation de transition
        this.animateLayoutChange();
    }
    
    /**
     * Animation de changement de layout
     */
    animateLayoutChange() {
        const cards = document.querySelectorAll('.preference-card, .module-card, .dashboard-item');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }
    
    /**
     * Gérer le changement de langue
     */
    handleLanguageChange(newLanguage) {
        console.log('🌍 Changement de langue détecté:', newLanguage);
        this.currentLanguage = newLanguage;
        document.body.setAttribute('data-language', newLanguage);
        document.documentElement.lang = newLanguage;
    }
    
    /**
     * Gérer la soumission des préférences
     */
    handlePreferenceSubmit(e) {
        // Ajouter un indicateur de chargement
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
            submitBtn.disabled = true;
            
            // Restaurer après 2 secondes (la page se rechargera normalement)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        }
    }
    
    /**
     * Configurer les animations
     */
    setupAnimations() {
        // Animation d'apparition des éléments
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);
        
        // Observer les cartes et éléments importants
        document.querySelectorAll('.preference-card, .module-card, .alert').forEach(el => {
            observer.observe(el);
        });
    }
    
    /**
     * Méthodes utilitaires publiques
     */
    
    /**
     * Changer le thème programmatiquement
     */
    setTheme(theme) {
        this.handleThemeChange(theme);
        
        // Mettre à jour le formulaire si présent
        const themeSelect = document.querySelector('select[name="theme"]');
        if (themeSelect) {
            themeSelect.value = theme;
        }
    }
    
    /**
     * Obtenir le thème actuel
     */
    getTheme() {
        return this.currentTheme;
    }
    
    /**
     * Obtenir la langue actuelle
     */
    getLanguage() {
        return this.currentLanguage;
    }
    
    /**
     * Basculer entre thème clair et sombre
     */
    toggleTheme() {
        const newTheme = this.getEffectiveTheme() === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }
}

// Initialiser le gestionnaire quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    window.preferencesManager = new PreferencesManager();
    
    // Ajouter des raccourcis clavier
    document.addEventListener('keydown', (e) => {
        // Ctrl+Shift+T pour basculer le thème
        if (e.ctrlKey && e.shiftKey && e.key === 'T') {
            e.preventDefault();
            window.preferencesManager.toggleTheme();
            console.log('🎨 Thème basculé via raccourci');
        }
    });
});

// Exposer les fonctions globalement pour les tests
window.setTheme = (theme) => window.preferencesManager?.setTheme(theme);
window.toggleTheme = () => window.preferencesManager?.toggleTheme();
window.getTheme = () => window.preferencesManager?.getTheme();

console.log('🚀 preferences-manager.js chargé');
