document.addEventListener('DOMContentLoaded', function() {
    // Gestion des dates
    const dateFrom = document.querySelector('input[name="date_from"]');
    const dateTo = document.querySelector('input[name="date_to"]');
    
    dateFrom.addEventListener('change', function() {
        if (this.value && dateTo.value && this.value > dateTo.value) {
            dateTo.value = this.value;
        }
    });
    
    dateTo.addEventListener('change', function() {
        if (this.value && dateFrom.value && this.value < dateFrom.value) {
            dateFrom.value = this.value;
        }
    });
    
    // Réinitialisation des filtres
    window.resetFilters = function() {
        const form = document.querySelector('.filter-form');
        form.reset();
        window.location = window.location.pathname;
    };
    
    // Sauvegarde des filtres dans localStorage
    const filterForm = document.querySelector('.filter-form');
    if (filterForm) {
        // Restaurer les filtres
        const savedFilters = localStorage.getItem('dossierFilters');
        if (savedFilters) {
            const filters = JSON.parse(savedFilters);
            for (const [name, value] of Object.entries(filters)) {
                const input = filterForm.querySelector(`[name="${name}"]`);
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = true;
                    } else {
                        input.value = value;
                    }
                }
            }
        }
        
        // Sauvegarder à la soumission
        filterForm.addEventListener('submit', function() {
            const formData = new FormData(this);
            const filters = {};
            for (const [name, value] of formData.entries()) {
                filters[name] = value;
            }
            localStorage.setItem('dossierFilters', JSON.stringify(filters));
        });
    }
});