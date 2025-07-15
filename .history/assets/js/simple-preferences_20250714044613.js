/**
 * Gestionnaire simple des préférences côté client
 * Version légère et logique
 */

class SimplePreferencesManager {
    constructor() {
        this.init();
    }
    
    init() {
        console.log('🎨 SimplePreferencesManager initialisé');
        this.setupFormHandlers();
        this.setupAnimations();
    }
    
    /**
     * Configurer les gestionnaires de formulaires
     */
    setupFormHandlers() {
        // Auto-submit pour les sélecteurs de thème
        document.addEventListener('change', (e) => {
            if (e.target.name === 'theme' && e.target.tagName === 'SELECT') {
                this.showLoadingIndicator(e.target);
                setTimeout(() => e.target.form.submit(), 300);
            }
        });
        
        // Confirmation pour les modifications importantes
        document.addEventListener('submit', (e) => {
            if (e.target.querySelector('input[name="action"][value="reset_preferences"]')) {
                if (!confirm('Êtes-vous sûr de vouloir réinitialiser toutes vos préférences ?')) {
                    e.preventDefault();
                }
            }
        });
    }
    
    /**
     * Afficher un indicateur de chargement
     */
    showLoadingIndicator(element) {
        const indicator = document.createElement('div');
        indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Application...';
        indicator.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
        `;
        
        element.style.position = 'relative';
        element.appendChild(indicator);
        
        setTimeout(() => indicator.remove(), 2000);
    }
    
    /**
     * Configurer les animations
     */
    setupAnimations() {
        // Animation d'apparition des éléments
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });
        
        document.querySelectorAll('.stat-card, .preference-card, .activity-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });
    }
    
    /**
     * Basculer le thème
     */
    toggleTheme() {
        const themeSelects = document.querySelectorAll('select[name="theme"]');
        if (themeSelects.length > 0) {
            const currentValue = themeSelects[0].value;
            const newValue = currentValue === 'dark' ? 'light' : 'dark';
            
            themeSelects.forEach(select => {
                select.value = newValue;
                select.dispatchEvent(new Event('change'));
            });
        }
    }
    
    /**
     * Appliquer un thème temporairement (prévisualisation)
     */
    previewTheme(theme) {
        document.body.setAttribute('data-theme-preview', theme);
        
        // Supprimer la prévisualisation après 3 secondes
        setTimeout(() => {
            document.body.removeAttribute('data-theme-preview');
        }, 3000);
    }
}

// Fonctions utilitaires globales
window.toggleTheme = function() {
    if (window.preferencesManager) {
        window.preferencesManager.toggleTheme();
    }
};

window.previewTheme = function(theme) {
    if (window.preferencesManager) {
        window.preferencesManager.previewTheme(theme);
    }
};

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    window.preferencesManager = new SimplePreferencesManager();
});

console.log('📝 Simple Preferences Manager chargé');
