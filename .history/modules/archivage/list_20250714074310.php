<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();
requirePermission(ROLE_ADMIN);

// Dossiers archivés
$dossiers = fetchAll("
    SELECT d.*, u.name as responsable_name 
    FROM dossiers d
    JOIN users u ON d.responsable_id = u.id
    WHERE d.status = 'archive'
    ORDER BY d.updated_at DESC
");

include __DIR__ . '/../../includes/header.php';
?>

<style>
:root {
    --primary-color: #2980b9;
    --primary-dark: #1f5f8b;
    --success-color: #27AE60;
    --warning-color: #F39C12;
    --danger-color: #E74C3C;
    --info-color: #3498DB;
    --archive-color: #8E44AD;
    --text-primary: #2C3E50;
    --text-secondary: #7F8C8D;
    --background-light: #F8F9FA;
    --border-light: #E1E8ED;
    --shadow-light: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --gradient-archive: linear-gradient(135deg, #8E44AD, #9B59B6);
    --gradient-export: linear-gradient(135deg, #E74C3C, #C0392B);
}

.archive-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 120px);
    padding: 2rem;
    position: relative;
}

.archive-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/><circle cx="30" cy="30" r="1" fill="white" opacity="0.1"/><circle cx="70" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="90" cy="80" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    z-index: 0;
}

.archive-header {
    background: white;
    padding: 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-medium);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
    background: var(--gradient-archive);
    color: white;
}

.archive-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100"><polygon fill="rgba(255,255,255,0.1)" points="0,0 1000,0 1000,80 0,100"/></svg>');
    z-index: 1;
}

.header-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.archive-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.3);
}

.archive-icon i {
    font-size: 2.5rem;
    color: white;
}

.header-text h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0;
}

.header-text p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0.5rem 0 0 0;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-warning {
    background: var(--warning-color);
    color: white;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-info {
    background: var(--info-color);
    color: white;
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    z-index: 1;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 20px;
    text-align: center;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--archive-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
}

.alert-info {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.2);
    color: var(--info-color);
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.alert-info i {
    font-size: 1.5rem;
    margin-top: 0.25rem;
}

.main-card {
    background: white;
    border-radius: 24px;
    box-shadow: var(--shadow-medium);
    border: 1px solid var(--border-light);
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.card-header {
    background: linear-gradient(135deg, var(--archive-color), #9B59B6);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-info {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
}

.card-body {
    padding: 0;
}

.table-container {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table th {
    background: var(--background-light);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1.25rem 1.5rem;
    text-align: left;
    font-size: 0.9rem;
    border-bottom: 2px solid var(--border-light);
}

.modern-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f8f9fa;
    vertical-align: middle;
}

.table-row-hover {
    transition: all 0.3s ease;
}

.table-row-hover:hover {
    background: rgba(142, 68, 173, 0.05);
    transform: translateX(5px);
}

.archive-ref {
    color: var(--archive-color);
    font-weight: 700;
    font-size: 1rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
}

.user-info i {
    color: var(--primary-color);
}

.archive-date {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.export-group {
    display: flex;
    gap: 0.25rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--archive-color);
}

.empty-state h3 {
    margin: 1rem 0 0.5rem 0;
    color: var(--text-primary);
}

@media (max-width: 1024px) {
    .archive-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .header-info {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header-actions {
        justify-content: center;
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .export-group {
        justify-content: center;
    }
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.animate-fade-in {
    opacity: 0;
    transform: translateY(20px);
    animation: slideInUp 0.6s ease forwards;
}

.stat-card, .main-card, .alert-info {
    animation: slideInUp 0.6s ease forwards;
}

/* Scrollbar personnalisée pour la table */
.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-container::-webkit-scrollbar-thumb {
    background: var(--archive-color);
    border-radius: 10px;
}

/* Effet de chargement pour les boutons d'export */
.btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.btn.loading::after {
    content: '';
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 0.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="archive-container">
    <!-- En-tête Archivage -->
    <div class="archive-header">
        <div class="header-content">
            <div class="header-info">
                <div class="archive-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="header-text">
                    <h1>Archivage Sécurisé</h1>
                    <p>Gestion des dossiers archivés et exports permanents</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="install_pdf.php" class="btn btn-warning">
                    <i class="fas fa-cog"></i> Config PDF
                </a>
                <a href="test_export.php" class="btn btn-primary">
                    <i class="fas fa-vial"></i> Test Export
                </a>
                <a href="test_pdf_advanced.php" class="btn btn-secondary">
                    <i class="fas fa-flask"></i> Test Avancé
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($dossiers) ?></div>
            <div class="stat-label">Dossiers archivés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?= count(array_filter($dossiers, fn($d) => strtotime($d['updated_at']) > strtotime('-30 days'))) ?>
            </div>
            <div class="stat-label">Archivés ce mois</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?= count(array_unique(array_column($dossiers, 'service'))) ?>
            </div>
            <div class="stat-label">Services concernés</div>
        </div>
    </div>

    <!-- Information sur l'archivage -->
    <div class="alert-info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Archivage sécurisé</strong><br>
            Les dossiers archivés sont conservés de manière sécurisée et immuable. 
            Utilisez les boutons d'export pour générer des rapports PDF ou HTML.
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-folder-open"></i> 
                Dossiers archivés
            </h3>
            <div class="badge badge-info">
                <?= count($dossiers) ?> dossier<?= count($dossiers) > 1 ? 's' : '' ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($dossiers)): ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>Aucun dossier archivé</h3>
                    <p>Les dossiers archivés apparaîtront ici</p>
                    <a href="../dossiers/list.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-folder"></i> Voir les dossiers actifs
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>
                                    <i class="fas fa-hashtag" style="margin-right: 0.5rem;"></i>
                                    Référence
                                </th>
                                <th>
                                    <i class="fas fa-file-alt" style="margin-right: 0.5rem;"></i>
                                    Titre
                                </th>
                                <th>
                                    <i class="fas fa-user" style="margin-right: 0.5rem;"></i>
                                    Responsable
                                </th>
                                <th>
                                    <i class="fas fa-calendar" style="margin-right: 0.5rem;"></i>
                                    Date archivage
                                </th>
                                <th>
                                    <i class="fas fa-cogs" style="margin-right: 0.5rem;"></i>
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dossiers as $d): ?>
                            <tr class="table-row-hover">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 8px; height: 8px; background: var(--archive-color); border-radius: 50%;"></div>
                                        <strong class="archive-ref"><?= htmlspecialchars($d['reference']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 300px;">
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= htmlspecialchars($d['titre']) ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                            Type: <?= htmlspecialchars($d['type'] ?? 'Non défini') ?> • 
                                            Service: <?= htmlspecialchars($d['service'] ?? 'Non défini') ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($d['responsable_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="archive-date">
                                        <?= formatDate($d['updated_at']) ?>
                                    </span>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                        <?php 
                                        $days = round((time() - strtotime($d['updated_at'])) / 86400);
                                        echo "Il y a $days jour" . ($days > 1 ? 's' : '');
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../dossiers/view.php?id=<?= $d['id'] ?>" 
                                           class="btn btn-info action-btn"
                                           title="Consulter le dossier">
                                            <i class="fas fa-eye"></i> Consulter
                                        </a>
                                        <div class="export-group">
                                            <a href="export.php?id=<?= $d['id'] ?>&format=pdf" 
                                               class="btn btn-danger action-btn export-btn" 
                                               title="Exporter en PDF"
                                               data-format="PDF">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                            <a href="export.php?id=<?= $d['id'] ?>&format=html" 
                                               class="btn btn-secondary action-btn export-btn" 
                                               title="Exporter en HTML"
                                               data-format="HTML">
                                                <i class="fas fa-file-alt"></i> HTML
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des éléments
    const animatedElements = document.querySelectorAll('.stat-card, .alert-info, .main-card');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            el.style.transition = 'all 0.6s ease';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Animation des lignes du tableau
    setTimeout(() => {
        const tableRows = document.querySelectorAll('.table-row-hover');
        tableRows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                row.style.transition = 'all 0.4s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 100);
        });
    }, 800);
    
    // Gestion des boutons d'export avec feedback
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const format = this.dataset.format;
            const originalContent = this.innerHTML;
            
            // Animation de chargement
            this.classList.add('loading');
            this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Export...`;
            
            // Simulation du délai d'export
            setTimeout(() => {
                // Redirection vers l'export
                window.location.href = this.href;
                
                // Restaurer le bouton après un délai
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.innerHTML = originalContent;
                }, 2000);
            }, 500);
        });
    });
    
    // Amélioration de l'accessibilité avec des tooltips
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.position = 'relative';
        });
    });
    
    // Recherche en temps réel dans le tableau
    createSearchFilter();
    
    // Tri du tableau
    createTableSort();
    
    // Statistiques animées
    animateStatistics();
    
    console.log('📊 Page d\'archivage initialisée avec succès');
});

function createSearchFilter() {
    // Créer un champ de recherche
    const cardHeader = document.querySelector('.card-header');
    if (cardHeader && document.querySelector('.modern-table')) {
        const searchContainer = document.createElement('div');
        searchContainer.style.cssText = `
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        `;
        
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Rechercher...';
        searchInput.style.cssText = `
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 0.9rem;
            min-width: 200px;
        `;
        
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row-hover');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const shouldShow = text.includes(filter);
                
                row.style.display = shouldShow ? '' : 'none';
                
                if (shouldShow) {
                    row.style.animation = 'fadeIn 0.3s ease';
                }
            });
            
            // Mettre à jour le compteur
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            updateRowCounter(visibleRows.length, rows.length);
        });
        
        searchContainer.appendChild(searchInput);
        cardHeader.appendChild(searchContainer);
    }
}

function createTableSort() {
    const headers = document.querySelectorAll('.modern-table th');
    headers.forEach((header, index) => {
        if (index < 4) { // Exclure la colonne Actions
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.addEventListener('click', () => sortTable(index));
            
            // Ajouter un indicateur de tri
            const sortIcon = document.createElement('i');
            sortIcon.className = 'fas fa-sort';
            sortIcon.style.marginLeft = '0.5rem';
            sortIcon.style.opacity = '0.5';
            header.appendChild(sortIcon);
        }
    });
}

function sortTable(columnIndex) {
    const table = document.querySelector('.modern-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const isAscending = table.dataset.sortOrder !== 'asc';
    table.dataset.sortOrder = isAscending ? 'asc' : 'desc';
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        let comparison = 0;
        
        // Tri numérique pour les références
        if (columnIndex === 0) {
            const aNum = parseInt(aValue.match(/\d+/) || 0);
            const bNum = parseInt(bValue.match(/\d+/) || 0);
            comparison = aNum - bNum;
        }
        // Tri par date pour la colonne date
        else if (columnIndex === 3) {
            const aDate = new Date(aValue);
            const bDate = new Date(bValue);
            comparison = aDate - bDate;
        }
        // Tri alphabétique pour les autres
        else {
            comparison = aValue.localeCompare(bValue);
        }
        
        return isAscending ? comparison : -comparison;
    });
    
    // Réorganiser les lignes
    rows.forEach(row => tbody.appendChild(row));
    
    // Mettre à jour les icônes de tri
    const headers = document.querySelectorAll('.modern-table th i.fa-sort, .modern-table th i.fa-sort-up, .modern-table th i.fa-sort-down');
    headers.forEach((icon, index) => {
        if (index === columnIndex) {
            icon.className = isAscending ? 'fas fa-sort-up' : 'fas fa-sort-down';
            icon.style.opacity = '1';
        } else {
            icon.className = 'fas fa-sort';
            icon.style.opacity = '0.5';
        }
    });
}

function updateRowCounter(visible, total) {
    const badge = document.querySelector('.badge-info');
    if (badge) {
        badge.textContent = visible === total ? 
            `${total} dossier${total > 1 ? 's' : ''}` : 
            `${visible}/${total} dossier${total > 1 ? 's' : ''}`;
    }
}

function animateStatistics() {
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((stat, index) => {
        const finalValue = parseInt(stat.textContent);
        stat.textContent = '0';
        
        setTimeout(() => {
            let current = 0;
            const increment = Math.ceil(finalValue / 20);
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    current = finalValue;
                    clearInterval(timer);
                }
                stat.textContent = current;
            }, 50);
        }, index * 300);
    });
}

// Gestion des erreurs d'export
window.addEventListener('error', function(e) {
    if (e.target.classList && e.target.classList.contains('export-btn')) {
        const btn = e.target;
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erreur';
        btn.style.background = 'var(--danger-color)';
        
        setTimeout(() => {
            btn.innerHTML = btn.dataset.format === 'PDF' ? 
                '<i class="fas fa-file-pdf"></i> PDF' : 
                '<i class="fas fa-file-alt"></i> HTML';
            btn.style.background = '';
        }, 2000);
    }
});

// Notification de succès pour les exports
function showExportSuccess(format) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--success-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: var(--shadow-medium);
        z-index: 1000;
        animation: slideInRight 0.3s ease;
    `;
    notification.innerHTML = `
        <i class="fas fa-check-circle"></i>
        Export ${format} généré avec succès
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// CSS pour les animations de notification
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>