/**
 * OPTIMISATIONS MOBILE JAVASCRIPT - MINSANTE
 * Améliore l'expérience utilisateur sur mobile et tablette
 */

class MobileOptimizer {
    constructor() {
        this.isMobile = window.innerWidth <= 767;
        this.isTablet = window.innerWidth >= 768 && window.innerWidth <= 991;
        this.touchStartY = 0;
        this.touchEndY = 0;
        
        this.init();
    }
    
    init() {
        this.setupMobileDetection();
        this.setupResponsiveTables();
        this.setupMobileNavigation();
        this.setupTouchGestures();
        this.setupMobileModals();
        this.setupMobileForms();
        this.setupMobileDataTables();
        this.setupMobileSearch();
        this.setupPullToRefresh();
        
        // Écouteur de redimensionnement
        window.addEventListener('resize', () => this.handleResize());
    }
    
    setupMobileDetection() {
        // Ajouter des classes CSS basées sur le type d'appareil
        document.body.classList.toggle('is-mobile', this.isMobile);
        document.body.classList.toggle('is-tablet', this.isTablet);
        document.body.classList.toggle('is-desktop', !this.isMobile && !this.isTablet);
        
        // Détecter l'orientation
        this.updateOrientation();
        window.addEventListener('orientationchange', () => {
            setTimeout(() => this.updateOrientation(), 100);
        });
    }
    
    updateOrientation() {
        const isLandscape = window.innerHeight < window.innerWidth;
        document.body.classList.toggle('landscape', isLandscape);
        document.body.classList.toggle('portrait', !isLandscape);
    }
    
    setupResponsiveTables() {
        if (!this.isMobile) return;
        
        // Convertir les tables en mode liste sur mobile
        const tables = document.querySelectorAll('.table:not(.table-mobile-list)');
        tables.forEach(table => {
            if (table.offsetWidth > window.innerWidth - 40) {
                this.convertTableToMobileList(table);
            }
        });
    }
    
    convertTableToMobileList(table) {
        table.classList.add('table-mobile-list');
        
        // Récupérer les headers
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        
        // Ajouter data-label à chaque cellule
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });
    }
    
    setupMobileNavigation() {
        // Menu hamburger amélioré
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', (e) => {
                e.preventDefault();
                navbarCollapse.classList.toggle('show');
                navbarToggler.classList.toggle('active');
                
                // Ajouter overlay pour fermer le menu
                this.toggleMobileMenuOverlay(navbarCollapse.classList.contains('show'));
            });
        }
        
        // Fermer le menu en cliquant sur un lien
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (this.isMobile) {
                    navbarCollapse.classList.remove('show');
                    navbarToggler.classList.remove('active');
                    this.toggleMobileMenuOverlay(false);
                }
            });
        });
    }
    
    toggleMobileMenuOverlay(show) {
        let overlay = document.querySelector('.mobile-menu-overlay');
        
        if (show && !overlay) {
            overlay = document.createElement('div');
            overlay.className = 'mobile-menu-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            document.body.appendChild(overlay);
            
            // Fade in
            requestAnimationFrame(() => {
                overlay.style.opacity = '1';
            });
            
            // Fermer en cliquant sur l'overlay
            overlay.addEventListener('click', () => {
                document.querySelector('.navbar-collapse').classList.remove('show');
                document.querySelector('.navbar-toggler').classList.remove('active');
                this.toggleMobileMenuOverlay(false);
            });
        } else if (!show && overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
            }, 300);
        }
    }
    
    setupTouchGestures() {
        // Swipe gestures pour navigation
        document.addEventListener('touchstart', (e) => {
            this.touchStartY = e.changedTouches[0].screenY;
        });
        
        document.addEventListener('touchend', (e) => {
            this.touchEndY = e.changedTouches[0].screenY;
            this.handleSwipe();
        });
    }
    
    handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = this.touchEndY - this.touchStartY;
        
        if (Math.abs(swipeDistance) > swipeThreshold) {
            // Swipe vers le bas pour actualiser (si pull-to-refresh activé)
            if (swipeDistance > 0 && this.touchStartY < 100) {
                this.triggerPullToRefresh();
            }
        }
    }
    
    setupMobileModals() {
        if (!this.isMobile) return;
        
        // Améliorer les modales sur mobile
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', () => {
                document.body.style.overflow = 'hidden';
                
                // Ajuster la hauteur de la modale
                const modalDialog = modal.querySelector('.modal-dialog');
                const modalBody = modal.querySelector('.modal-body');
                
                if (modalBody) {
                    const maxHeight = window.innerHeight * 0.8;
                    modalBody.style.maxHeight = maxHeight + 'px';
                    modalBody.style.overflowY = 'auto';
                }
            });
            
            modal.addEventListener('hide.bs.modal', () => {
                document.body.style.overflow = '';
            });
        });
    }
    
    setupMobileForms() {
        if (!this.isMobile) return;
        
        // Améliorer les formulaires sur mobile
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Grouper les boutons sur mobile
            const buttons = form.querySelectorAll('.btn');
            if (buttons.length > 1) {
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'btn-group-mobile mt-3';
                
                buttons.forEach(btn => {
                    if (!btn.classList.contains('btn-block')) {
                        btn.classList.add('btn-sm');
                        buttonContainer.appendChild(btn);
                    }
                });
                
                if (buttonContainer.children.length > 0) {
                    form.appendChild(buttonContainer);
                }
            }
        });
        
        // Optimiser les champs de recherche
        const searchInputs = document.querySelectorAll('input[type="search"], .search-input');
        searchInputs.forEach(input => {
            input.addEventListener('focus', () => {
                // Scroll vers l'input pour éviter que le clavier le cache
                setTimeout(() => {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    }
    
    setupMobileDataTables() {
        if (!this.isMobile || !window.jQuery || !$.fn.DataTable) return;
        
        // Configuration DataTables pour mobile
        const mobileConfig = {
            responsive: true,
            scrollX: false,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 10,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            language: {
                search: '',
                searchPlaceholder: 'Rechercher...',
                lengthMenu: '_MENU_ résultats',
                info: '_START_ à _END_ sur _TOTAL_',
                paginate: {
                    previous: '‹',
                    next: '›'
                }
            }
        };
        
        // Appliquer la configuration aux tables existantes
        $('.table').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().destroy();
            }
            $(this).DataTable(mobileConfig);
        });
    }
    
    setupMobileSearch() {
        // Améliorer l'expérience de recherche sur mobile
        const searchInputs = document.querySelectorAll('.dataTables_filter input');
        searchInputs.forEach(input => {
            input.style.width = '100%';
            input.setAttribute('placeholder', 'Rechercher...');
            
            // Ajouter une icône de recherche
            const wrapper = input.parentElement;
            wrapper.style.position = 'relative';
            
            const icon = document.createElement('i');
            icon.className = 'fas fa-search';
            icon.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: #6c757d;
                pointer-events: none;
            `;
            wrapper.appendChild(icon);
            
            input.style.paddingRight = '35px';
        });
    }
    
    setupPullToRefresh() {
        if (!this.isMobile) return;
        
        let startY = 0;
        let currentY = 0;
        let isRefreshing = false;
        let pullThreshold = 80;
        
        const refreshIndicator = this.createRefreshIndicator();
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (window.scrollY === 0 && !isRefreshing) {
                currentY = e.touches[0].clientY;
                const pullDistance = currentY - startY;
                
                if (pullDistance > 0 && pullDistance < pullThreshold * 2) {
                    e.preventDefault();
                    
                    const opacity = Math.min(pullDistance / pullThreshold, 1);
                    refreshIndicator.style.opacity = opacity;
                    refreshIndicator.style.transform = `translateY(${Math.min(pullDistance, pullThreshold)}px)`;
                }
            }
        });
        
        document.addEventListener('touchend', () => {
            if (window.scrollY === 0) {
                const pullDistance = currentY - startY;
                
                if (pullDistance > pullThreshold && !isRefreshing) {
                    this.triggerPullToRefresh();
                } else {
                    refreshIndicator.style.opacity = '0';
                    refreshIndicator.style.transform = 'translateY(0)';
                }
            }
        });
    }
    
    createRefreshIndicator() {
        const indicator = document.createElement('div');
        indicator.innerHTML = '<i class="fas fa-sync-alt"></i> Tirer pour actualiser';
        indicator.style.cssText = `
            position: fixed;
            top: -50px;
            left: 50%;
            transform: translateX(-50%);
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(indicator);
        return indicator;
    }
    
    triggerPullToRefresh() {
        if (typeof window.pullToRefreshCallback === 'function') {
            window.pullToRefreshCallback();
        } else {
            window.location.reload();
        }
    }
    
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 767;
        this.isTablet = window.innerWidth >= 768 && window.innerWidth <= 991;
        
        // Réinitialiser si le statut mobile a changé
        if (wasMobile !== this.isMobile) {
            this.setupMobileDetection();
            this.setupResponsiveTables();
            this.setupMobileDataTables();
        }
        
        this.updateOrientation();
    }
    
    // Utilitaires publics
    static showMobileToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `mobile-toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#3498db'};
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            max-width: 90%;
            text-align: center;
        `;
        
        document.body.appendChild(toast);
        
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
        });
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    static vibrate(pattern = [100]) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
}

// Initialiser les optimisations mobile au chargement
document.addEventListener('DOMContentLoaded', () => {
    window.mobileOptimizer = new MobileOptimizer();
});

// Fonctions utilitaires globales
window.showMobileToast = MobileOptimizer.showMobileToast;
window.vibrate = MobileOptimizer.vibrate;
