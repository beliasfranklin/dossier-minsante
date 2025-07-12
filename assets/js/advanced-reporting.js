/**
 * SYSTÈME DE REPORTING AVANCÉ - MINSANTE
 * Graphiques interactifs avec Chart.js
 */

class AdvancedReporting {
    constructor() {
        this.charts = {};
        this.colors = {
            primary: '#3498db',
            success: '#2ecc71',
            warning: '#f39c12',
            danger: '#e74c3c',
            info: '#17a2b8',
            purple: '#6f42c1',
            dark: '#343a40',
            light: '#f8f9fa'
        };
        
        this.gradients = {};
        this.currentFilters = {};
        
        this.init();
    }
    
    init() {
        this.setupChartDefaults();
        this.createGradients();
        this.setupEventListeners();
    }
    
    setupChartDefaults() {
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#6c757d';
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 20;
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0,0,0,0.8)';
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.bodyColor = '#fff';
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
    }
    
    createGradients() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Gradient pour les graphiques en aires
        this.gradients.primary = ctx.createLinearGradient(0, 0, 0, 400);
        this.gradients.primary.addColorStop(0, 'rgba(52, 152, 219, 0.8)');
        this.gradients.primary.addColorStop(1, 'rgba(52, 152, 219, 0.1)');
        
        this.gradients.success = ctx.createLinearGradient(0, 0, 0, 400);
        this.gradients.success.addColorStop(0, 'rgba(46, 204, 113, 0.8)');
        this.gradients.success.addColorStop(1, 'rgba(46, 204, 113, 0.1)');
        
        this.gradients.warning = ctx.createLinearGradient(0, 0, 0, 400);
        this.gradients.warning.addColorStop(0, 'rgba(243, 156, 18, 0.8)');
        this.gradients.warning.addColorStop(1, 'rgba(243, 156, 18, 0.1)');
    }
    
    setupEventListeners() {
        // Écouteur pour le changement de période
        document.getElementById('periodSelect')?.addEventListener('change', (e) => {
            const customRange = document.getElementById('customDateRange');
            if (e.target.value === 'custom') {
                customRange.style.display = 'block';
                this.setDefaultDateRange();
            } else {
                customRange.style.display = 'none';
            }
        });
        
        // Auto-refresh toutes les 5 minutes
        setInterval(() => {
            if (document.hasFocus()) {
                this.updateCharts(false); // Mise à jour silencieuse
            }
        }, 300000);
    }
    
    setDefaultDateRange() {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 30);
        
        document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
        document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
    }
    
    async updateCharts(showLoading = true) {
        if (showLoading) {
            this.showLoading(true);
        }
        
        try {
            this.currentFilters = this.getFilters();
            const data = await this.fetchChartData(this.currentFilters);
            
            await this.updateStatsCards(data.stats);
            await this.updateEvolutionChart(data.evolution);
            await this.updateStatusChart(data.status);
            await this.updateServiceChart(data.services);
            await this.updateDelaisChart(data.delais);
            await this.updateUserWorkloadChart(data.workload);
            await this.updateMonthlyTrendsChart(data.monthly);
            await this.updateDeadlineAnalysisChart(data.deadlines);
            
            if (showLoading) {
                this.showLoading(false);
            }
            
            this.showSuccessToast('Graphiques mis à jour avec succès');
            
        } catch (error) {
            console.error('Erreur lors de la mise à jour des graphiques:', error);
            this.showErrorToast('Erreur lors de la mise à jour des graphiques');
            if (showLoading) {
                this.showLoading(false);
            }
        }
    }
    
    getFilters() {
        const form = document.getElementById('reportFilters');
        const formData = new FormData(form);
        const filters = {};
        
        for (const [key, value] of formData.entries()) {
            filters[key] = value;
        }
        
        // Calculer les dates si période prédéfinie
        if (filters.period && filters.period !== 'custom') {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - parseInt(filters.period));
            
            filters.start_date = startDate.toISOString().split('T')[0];
            filters.end_date = endDate.toISOString().split('T')[0];
        }
        
        return filters;
    }
    
    async fetchChartData(filters) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`api/reporting-data.php?${params}`);
        
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        return await response.json();
    }
    
    async updateStatsCards(stats) {
        const container = document.getElementById('statsCards');
        
        const cards = [
            {
                title: 'Total Dossiers',
                value: stats.total_dossiers || 0,
                icon: 'fas fa-folder',
                color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                change: stats.dossiers_change || 0
            },
            {
                title: 'En Cours',
                value: stats.en_cours || 0,
                icon: 'fas fa-clock',
                color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                change: stats.en_cours_change || 0
            },
            {
                title: 'Terminés',
                value: stats.termines || 0,
                icon: 'fas fa-check-circle',
                color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                change: stats.termines_change || 0
            },
            {
                title: 'En Retard',
                value: stats.en_retard || 0,
                icon: 'fas fa-exclamation-triangle',
                color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                change: stats.retard_change || 0
            }
        ];
        
        container.innerHTML = cards.map((card, index) => `
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="background: ${card.color};" data-aos="fade-up" data-aos-delay="${index * 100}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number">${this.formatNumber(card.value)}</div>
                            <div class="stat-label">${card.title}</div>
                            ${card.change !== 0 ? `
                                <small class="change-indicator ${card.change > 0 ? 'positive' : 'negative'}">
                                    <i class="fas fa-${card.change > 0 ? 'arrow-up' : 'arrow-down'}"></i>
                                    ${Math.abs(card.change)}%
                                </small>
                            ` : ''}
                        </div>
                        <div class="stat-icon">
                            <i class="${card.icon}" style="font-size: 2rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    async updateEvolutionChart(data) {
        const ctx = document.getElementById('evolutionChart');
        if (!ctx) return;
        
        if (this.charts.evolution) {
            this.charts.evolution.destroy();
        }
        
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(52, 152, 219, 0.8)');
        gradient.addColorStop(1, 'rgba(52, 152, 219, 0.1)');
        
        this.charts.evolution = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: 'Nouveaux dossiers',
                        data: data.nouveaux || [],
                        borderColor: this.colors.primary,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: this.colors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    },
                    {
                        label: 'Dossiers terminés',
                        data: data.termines || [],
                        borderColor: this.colors.success,
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: this.colors.success,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function(context) {
                                return 'Date: ' + context[0].label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                day: 'dd/MM',
                                week: 'dd/MM',
                                month: 'MMM yyyy'
                            }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }
    
    async updateStatusChart(data) {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;
        
        if (this.charts.status) {
            this.charts.status.destroy();
        }
        
        this.charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels || [],
                datasets: [{
                    data: data.values || [],
                    backgroundColor: [
                        this.colors.success,
                        this.colors.warning,
                        this.colors.primary,
                        this.colors.danger,
                        this.colors.info,
                        this.colors.purple
                    ],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%',
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                }
            }
        });
    }
    
    async updateServiceChart(data) {
        const ctx = document.getElementById('serviceChart');
        if (!ctx) return;
        
        if (this.charts.service) {
            this.charts.service.destroy();
        }
        
        this.charts.service = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: 'En cours',
                        data: data.en_cours || [],
                        backgroundColor: this.colors.warning,
                        borderRadius: 8,
                        borderSkipped: false
                    },
                    {
                        label: 'Terminés',
                        data: data.termines || [],
                        backgroundColor: this.colors.success,
                        borderRadius: 8,
                        borderSkipped: false
                    },
                    {
                        label: 'En retard',
                        data: data.en_retard || [],
                        backgroundColor: this.colors.danger,
                        borderRadius: 8,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });
    }
    
    async updateDelaisChart(data) {
        const ctx = document.getElementById('delaisChart');
        if (!ctx) return;
        
        if (this.charts.delais) {
            this.charts.delais.destroy();
        }
        
        this.charts.delais = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Délai moyen (jours)',
                    data: data.values || [],
                    borderColor: this.colors.info,
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    pointBackgroundColor: this.colors.info,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        angleLines: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
    }
    
    async updateUserWorkloadChart(data) {
        const ctx = document.getElementById('userWorkloadChart');
        if (!ctx) return;
        
        if (this.charts.userWorkload) {
            this.charts.userWorkload.destroy();
        }
        
        this.charts.userWorkload = new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Dossiers assignés',
                    data: data.values || [],
                    backgroundColor: this.colors.purple,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    async updateMonthlyTrendsChart(data) {
        const ctx = document.getElementById('monthlyTrendsChart');
        if (!ctx) return;
        
        if (this.charts.monthlyTrends) {
            this.charts.monthlyTrends.destroy();
        }
        
        this.charts.monthlyTrends = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: data.labels || [],
                datasets: [{
                    data: data.values || [],
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(52, 73, 94, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    async updateDeadlineAnalysisChart(data) {
        const ctx = document.getElementById('deadlineAnalysisChart');
        if (!ctx) return;
        
        if (this.charts.deadlineAnalysis) {
            this.charts.deadlineAnalysis.destroy();
        }
        
        this.charts.deadlineAnalysis = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: 'Échéances respectées',
                        data: data.respectees || [],
                        borderColor: this.colors.success,
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Échéances dépassées',
                        data: data.depassees || [],
                        borderColor: this.colors.danger,
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                day: 'dd/MM',
                                week: 'dd/MM',
                                month: 'MMM yyyy'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        stacked: true
                    }
                }
            }
        });
    }
    
    showLoading(show) {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = show ? 'block' : 'none';
        }
    }
    
    showSuccessToast(message) {
        if (window.showMobileToast) {
            window.showMobileToast(message, 'success', 2000);
        }
    }
    
    showErrorToast(message) {
        if (window.showMobileToast) {
            window.showMobileToast(message, 'error', 3000);
        }
    }
    
    formatNumber(num) {
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'k';
        }
        return num.toString();
    }
    
    // Fonctions d'export
    exportToPDF() {
        window.open(`api/export-report.php?format=pdf&${new URLSearchParams(this.currentFilters)}`, '_blank');
    }
    
    exportToExcel() {
        window.open(`api/export-report.php?format=excel&${new URLSearchParams(this.currentFilters)}`, '_blank');
    }
    
    exportToCSV() {
        window.open(`api/export-report.php?format=csv&${new URLSearchParams(this.currentFilters)}`, '_blank');
    }
    
    scheduleReport() {
        // Ouvrir modal de programmation
        const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        modal.show();
    }
    
    emailReport() {
        // Ouvrir modal d'envoi par email
        const modal = new bootstrap.Modal(document.getElementById('emailModal'));
        modal.show();
    }
}

// Initialiser le système de reporting
let advancedReporting;

document.addEventListener('DOMContentLoaded', function() {
    advancedReporting = new AdvancedReporting();
});

// Fonction globale pour mettre à jour les graphiques
function updateCharts() {
    if (advancedReporting) {
        advancedReporting.updateCharts();
    }
}

// Fonctions d'export globales
function exportToPDF() {
    if (advancedReporting) {
        advancedReporting.exportToPDF();
    }
}

function exportToExcel() {
    if (advancedReporting) {
        advancedReporting.exportToExcel();
    }
}

function exportToCSV() {
    if (advancedReporting) {
        advancedReporting.exportToCSV();
    }
}

function scheduleReport() {
    if (advancedReporting) {
        advancedReporting.scheduleReport();
    }
}

function emailReport() {
    if (advancedReporting) {
        advancedReporting.emailReport();
    }
}
