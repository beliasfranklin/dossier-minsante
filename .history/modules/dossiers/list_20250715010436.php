<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/filter_functions.php';
requireAuth();

// Récupération des paramètres
$filters = [
    'status' => $_GET['status'] ?? null,
    'type' => $_GET['type'] ?? null,
    'service' => $_GET['service'] ?? null,
    'priority' => $_GET['priority'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'deadline' => $_GET['deadline'] ?? null,
    'search' => $_GET['search'] ?? null,
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'desc',
    'limit' => 20,
    'offset' => ($_GET['page'] ?? 1) * 20 - 20
];

// Construction et exécution de la requête
$queryData = buildDossierQuery($filters);
$dossiers = fetchAll($queryData['query'], $queryData['params']);

// Calcul du total pour la pagination
$totalQuery = "SELECT COUNT(*) as total FROM dossiers d " . 
              (strpos($queryData['query'], 'WHERE') !== false ? 
               substr($queryData['query'], strpos($queryData['query'], 'WHERE')) : '');
$totalQuery = preg_replace('/ORDER BY.*/', '', $totalQuery);
$totalQuery = preg_replace('/LIMIT.*/', '', $totalQuery);

$total = fetchOne($totalQuery, $queryData['params'])['total'];
$totalPages = ceil($total / $filters['limit']);

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
    --dossier-color: #9B59B6;
    --text-primary: #2C3E50;
    --text-secondary: #7F8C8D;
    --background-light: #F8F9FA;
    --border-light: #E1E8ED;
    --shadow-light: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --gradient-primary: linear-gradient(135deg, #3498db, #2980b9);
    --gradient-dossier: linear-gradient(135deg, #9B59B6, #8E44AD);
}

.dossiers-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 120px);
    padding: 2rem;
    position: relative;
}

.dossiers-container::before {
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

.dossiers-header {
    background: var(--gradient-dossier);
    color: white;
    padding: 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-medium);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
}

.dossiers-header::before {
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

.dossiers-icon {
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

.dossiers-icon i {
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

.btn-primary {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-primary:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
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
    color: var(--dossier-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
}

.filters-panel {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
}

.filters-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--background-light);
}

.filters-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.filters-title i {
    color: var(--dossier-color);
}

.filters-toggle {
    background: none;
    border: none;
    color: var(--dossier-color);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.filters-toggle:hover {
    background: rgba(155, 89, 182, 0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group {
    margin-bottom: 1rem;
}

.filter-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-light);
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: #FAFBFC;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--dossier-color);
    background: white;
    box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.1);
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn-filter {
    background: var(--gradient-dossier);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(155, 89, 182, 0.4);
}

.btn-reset {
    background: #95a5a6;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    background: #7f8c8d;
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
    background: var(--gradient-dossier);
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

.search-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    max-width: 300px;
}

.search-input {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 20px;
    background: rgba(255,255,255,0.2);
    color: white;
    font-size: 0.9rem;
    width: 100%;
}

.search-input::placeholder {
    color: rgba(255,255,255,0.7);
}

.search-input:focus {
    outline: none;
    background: rgba(255,255,255,0.3);
}

.dossiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.dossier-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.dossier-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--dossier-color);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.dossier-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.dossier-card:hover::before {
    transform: scaleY(1);
}

.dossier-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.dossier-ref {
    background: rgba(155, 89, 182, 0.1);
    color: var(--dossier-color);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-draft { background: rgba(149, 165, 166, 0.2); color: #7f8c8d; }
.status-review { background: rgba(243, 156, 18, 0.2); color: #e67e22; }
.status-active { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
.status-archive { background: rgba(155, 89, 182, 0.2); color: #8e44ad; }

.dossier-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0.5rem 0;
    line-height: 1.3;
}

.dossier-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.meta-item i {
    color: var(--dossier-color);
}

.dossier-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 1.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.dossier-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    border-radius: 8px;
}

.btn-info {
    background: var(--info-color);
    color: white;
}

.btn-warning {
    background: var(--warning-color);
    color: white;
}

.btn-sm:hover {
    transform: translateY(-1px);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    background: var(--background-light);
}

.pagination a, .pagination span {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination a {
    color: var(--text-primary);
    background: white;
    border: 1px solid var(--border-light);
}

.pagination a:hover {
    background: var(--dossier-color);
    color: white;
}

.pagination .current {
    background: var(--dossier-color);
    color: white;
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
    color: var(--dossier-color);
}

.empty-state h3 {
    margin: 1rem 0 0.5rem 0;
    color: var(--text-primary);
}

@media (max-width: 1024px) {
    .dossiers-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .header-info {
        flex-direction: column;
    }
    
    .dossiers-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .dossier-meta {
        flex-direction: column;
        gap: 0.5rem;
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

.stat-card, .filters-panel, .main-card {
    animation: slideInUp 0.6s ease forwards;
}

.dossier-card {
    animation: slideInUp 0.4s ease forwards;
}

/* Loading states */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    border: 2px solid var(--dossier-color);
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    transform: translate(-50%, -50%);
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}
</style>
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: var(--gradient-success);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
}

.filters-panel {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--background-light);
}

.filters-header h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filter-toggle {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.filter-toggle:hover {
    background: rgba(52, 152, 219, 0.1);
}

.filter-form {
    display: grid;
    gap: 1.5rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label i {
    color: var(--primary-color);
}

.filter-group select,
.filter-group input {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #FAFBFC;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: white;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.filter-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

.btn-info {
    background: var(--gradient-primary);
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
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

.stat-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
    font-size: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
}

.view-controls {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.view-toggle {
    display: flex;
    background: var(--background-light);
    border-radius: 12px;
    padding: 0.25rem;
    gap: 0.25rem;
}

.view-btn {
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-btn.active,
.view-btn:hover {
    background: var(--primary-color);
    color: white;
}

.dossiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.dossier-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
    position: relative;
}

.dossier-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.card-header {
    background: var(--background-light);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-light);
}

.card-ref {
    background: var(--gradient-primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
}

.card-priority {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.priority-high {
    background: var(--gradient-danger);
    color: white;
}

.priority-medium {
    background: var(--gradient-warning);
    color: white;
}

.priority-low {
    background: var(--gradient-success);
    color: white;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    line-height: 1.4;
}

.card-meta {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.meta-item i {
    color: var(--primary-color);
    width: 16px;
}

.card-footer {
    padding: 1rem 1.5rem;
    background: var(--background-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border-light);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-en_cours {
    background: var(--gradient-warning);
    color: white;
}

.status-valide {
    background: var(--gradient-success);
    color: white;
}

.status-rejete {
    background: var(--gradient-danger);
    color: white;
}

.status-archive {
    background: #95a5a6;
    color: white;
}

.actions-section {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.view-btn-action {
    background: var(--gradient-primary);
    color: white;
}

.edit-btn {
    background: var(--gradient-warning);
    color: white;
}

.action-btn:hover {
    transform: translateY(-2px) scale(1.1);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
    background: white;
    border-radius: 20px;
    box-shadow: var(--shadow-light);
    border: 1px solid var(--border-light);
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--primary-color);
}

.empty-state h3 {
    margin: 1rem 0 0.5rem 0;
    color: var(--text-primary);
}

.pagination {
    margin-top: 2rem;
    text-align: center;
    position: relative;
    z-index: 1;
}

.pagination a {
    display: inline-block;
    margin: 0 0.25rem;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    background: white;
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid var(--border-light);
}

.pagination a.active,
.pagination a:hover {
    background: var(--gradient-primary);
    color: white;
    transform: translateY(-2px);
}

@media (max-width: 1024px) {
    .dossiers-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .dossiers-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .dossiers-grid {
        grid-template-columns: 1fr;
    }
    
    .view-controls {
        flex-direction: column;
        gap: 1rem;
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

.dossier-card, .stat-card, .filters-panel {
    animation: slideInUp 0.6s ease forwards;
}

/* Scrollbar personnalisée */
.dossiers-container::-webkit-scrollbar {
    width: 8px;
}

.dossiers-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.dossiers-container::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}
</style>

<div class="dossiers-container">
    <!-- En-tête moderne -->
    <div class="dossiers-header">
        <div class="header-content">
            <div class="header-info">
                <div class="dossiers-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="header-text">
                    <h1>Gestion des Dossiers</h1>
                    <p>Administration complète des dossiers du MinSanté</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Créer un dossier
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($dossiers) ?></div>
            <div class="stat-label">Dossiers affichés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total général</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?= count(array_filter($dossiers, fn($d) => $d['status'] === 'active')) ?>
            </div>
            <div class="stat-label">En cours</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?= count(array_filter($dossiers, fn($d) => $d['status'] === 'archive')) ?>
            </div>
            <div class="stat-label">Archivés</div>
        </div>
    </div>

    <!-- Panel de filtres moderne -->
    <div class="filters-panel">
        <div class="filters-header">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filtres de recherche
            </div>
            <button type="button" class="filters-toggle" onclick="toggleFilters()">
                <i class="fas fa-chevron-down" id="filtersIcon"></i>
            </button>
        </div>
        
        <div class="filters-content" id="filtersContent" style="display: none;">
            <form method="GET" class="filters-form">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Recherche globale</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Titre, référence, description..." 
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="status" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="draft" <?= selected('draft', $filters['status']) ?>>Brouillon</option>
                            <option value="review" <?= selected('review', $filters['status']) ?>>En révision</option>
                            <option value="active" <?= selected('active', $filters['status']) ?>>Actif</option>
                            <option value="archive" <?= selected('archive', $filters['status']) ?>>Archivé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select name="type" class="filter-select">
                            <option value="">Tous les types</option>
                            <option value="Etude" <?= selected('Etude', $filters['type']) ?>>Étude</option>
                            <option value="Projet" <?= selected('Projet', $filters['type']) ?>>Projet</option>
                            <option value="Administratif" <?= selected('Administratif', $filters['type']) ?>>Administratif</option>
                            <option value="Autre" <?= selected('Autre', $filters['type']) ?>>Autre</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Service</label>
                        <select name="service" class="filter-select">
                            <option value="">Tous les services</option>
                            <option value="DEP" <?= selected('DEP', $filters['service']) ?>>DEP</option>
                            <option value="Finance" <?= selected('Finance', $filters['service']) ?>>Finance</option>
                            <option value="RH" <?= selected('RH', $filters['service']) ?>>RH</option>
                            <option value="Logistique" <?= selected('Logistique', $filters['service']) ?>>Logistique</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date de début</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?= $filters['date_from'] ?? '' ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date de fin</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?= $filters['date_to'] ?? '' ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="list.php" class="btn-reset">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-list"></i>
                Liste des dossiers
            </h3>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Recherche rapide..." id="quickSearch">
                <i class="fas fa-search" style="margin-left: -2rem; pointer-events: none; color: rgba(255,255,255,0.7);"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-plus"></i>
            </div>
            <div class="stat-value">
                <?= count(array_filter($dossiers, fn($d) => $d['status'] === 'en_cours')) ?>
            </div>
            <div class="stat-label">En cours</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value">
                <?= count(array_filter($dossiers, fn($d) => $d['status'] === 'valide')) ?>
            </div>
            <div class="stat-label">Validés</div>
        </div>
    </div>
    <!-- Panel de filtres moderne -->
    <div class="filters-panel">
        <div class="filters-header">
            <h3>
                <i class="fas fa-filter"></i>
                Filtres de recherche
            </h3>
            <button type="button" class="filter-toggle" onclick="toggleFilters()">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        
        <form method="get" class="filter-form" id="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label>
                        <i class="fas fa-info-circle"></i>
                        Statut
                    </label>
                    <select name="status">
                        <option value="">Tous statuts</option>
                        <option value="en_cours" <?= $filters['status'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="valide" <?= $filters['status'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                        <option value="rejete" <?= $filters['status'] === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                        <option value="archive" <?= $filters['status'] === 'archive' ? 'selected' : '' ?>>Archivé</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>
                        <i class="fas fa-file-alt"></i>
                        Type
                    </label>
                    <select name="type">
                        <option value="">Tous types</option>
                        <option value="Etude" <?= $filters['type'] === 'Etude' ? 'selected' : '' ?>>Étude</option>
                        <option value="Projet" <?= $filters['type'] === 'Projet' ? 'selected' : '' ?>>Projet</option>
                        <option value="Administratif" <?= $filters['type'] === 'Administratif' ? 'selected' : '' ?>>Administratif</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>
                        <i class="fas fa-flag"></i>
                        Priorité
                    </label>
                    <select name="priority">
                        <option value="">Toutes priorités</option>
                        <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>Haute</option>
                        <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Moyenne</option>
                        <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Basse</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>
                        <i class="fas fa-search"></i>
                        Recherche
                    </label>
                    <input type="text" 
                           name="search" 
                           placeholder="Titre, référence, description..." 
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar"></i>
                        Date de début
                    </label>
                    <input type="date" 
                           name="date_from" 
                           value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar"></i>
                        Date de fin
                    </label>
                    <input type="date" 
                           name="date_to" 
                           value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> Réinitialiser
                </button>
            </div>
        </form>
    </div>

    <!-- Contrôles de vue -->
    <div class="view-controls">
        <div class="view-toggle">
            <button class="view-btn active" data-view="grid" onclick="switchView('grid')">
                <i class="fas fa-th-large"></i>
                <span>Cartes</span>
            </button>
            <button class="view-btn" data-view="table" onclick="switchView('table')">
                <i class="fas fa-table"></i>
                <span>Tableau</span>
            </button>
        </div>
        <div style="color: var(--text-secondary); font-weight: 600;">
            Page <?= ($filters['offset'] / $filters['limit'] + 1) ?> sur <?= $totalPages ?>
        </div>
    </div>

    <!-- Vue cartes (par défaut) -->
    <div class="dossiers-grid" id="grid-view">
        <?php if (empty($dossiers)): ?>
        
        <?php if (empty($dossiers)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>Aucun dossier trouvé</h3>
                <p>Il n'y a aucun dossier correspondant à vos critères de recherche.</p>
                <a href="create.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i>
                    Créer le premier dossier
                </a>
            </div>
        <?php else: ?>
            <div class="dossiers-grid">
                <?php foreach ($dossiers as $dossier): ?>
                <div class="dossier-card" data-id="<?= $dossier['id'] ?>" onclick="window.location.href='view.php?id=<?= $dossier['id'] ?>'">
                    <div class="dossier-header">
                        <div class="dossier-ref"><?= htmlspecialchars($dossier['reference']) ?></div>
                        <div class="status-badge status-<?= $dossier['status'] ?? 'draft' ?>">
                            <?= ucfirst(str_replace('_', ' ', $dossier['status'] ?? 'draft')) ?>
                        </div>
                    </div>
                    
                    <h3 class="dossier-title"><?= htmlspecialchars($dossier['titre']) ?></h3>
                    
                    <div class="dossier-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar-plus"></i>
                            <span><?= date('d/m/Y', strtotime($dossier['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($dossier['service'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-building"></i>
                            <span><?= htmlspecialchars($dossier['service']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dossier['type'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?= htmlspecialchars($dossier['type']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($dossier['description'])): ?>
                    <div class="dossier-description">
                        <?= htmlspecialchars(substr($dossier['description'], 0, 150)) ?>
                        <?= strlen($dossier['description']) > 150 ? '...' : '' ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dossier-actions" onclick="event.stopPropagation()">
                        <a href="view.php?id=<?= $dossier['id'] ?>" class="btn btn-sm btn-info" title="Consulter">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                        <a href="edit.php?id=<?= $dossier['id'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= buildPageQuery($i) ?>" class="<?= $i == ($filters['offset'] / $filters['limit'] + 1) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* === FILTRES === */
.filter-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px 32px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid rgba(102, 126, 234, 0.1);
}

.filter-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-toggle {
    background: none;
    border: none;
    color: #667eea;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.filter-toggle:hover {
    background: rgba(102, 126, 234, 0.1);
    transform: scale(1.1);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 180px;
}

.filter-group.search-group {
    flex: 1;
    min-width: 300px;
}

.filter-group label {
    font-weight: 600;
    color: #475569;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group label i {
    color: #667eea;
    width: 16px;
}

.filter-row select,
.filter-row input[type="text"],
.filter-row input[type="date"] {
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 50px;
    background: #ffffff;
    color: #334155;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-row select:focus,
.filter-row input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.filter-actions {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    margin-left: auto;
}

.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.btn-info {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.btn-secondary {
    background: linear-gradient(135deg, #94a3b8, #64748b);
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(148, 163, 184, 0.4);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(148, 163, 184, 0.6);
}

/* === ÉTAT VIDE === */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 32px;
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-radius: 20px;
    border: 2px dashed #cbd5e1;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 3rem;
    animation: pulse 2s ease-in-out infinite;
}

.empty-state h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #334155;
    margin: 0 0 16px 0;
}

.empty-state p {
    font-size: 1.1rem;
    color: #64748b;
    margin: 0 0 32px 0;
    line-height: 1.6;
}

.btn-create-first {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    padding: 16px 32px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-create-first:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(16, 185, 129, 0.6);
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}
.dossier-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    margin-bottom: 32px;
    padding: 32px;
    position: relative;
    overflow: hidden;
    animation: slideInDown 0.8s ease-out;
}

.dossier-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    pointer-events: none;
}

.dossier-section h2 {
    margin: 0;
    font-size: 2rem;
    font-weight: 800;
    color: #ffffff;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
    z-index: 1;
}

.btn-primary {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: #fff;
    padding: 14px 28px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(238, 90, 82, 0.4);
    border: none;
    position: relative;
    z-index: 1;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(238, 90, 82, 0.6);
}

/* === HEADER STATS === */
.dossiers-header-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px 32px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stats-item {
    text-align: center;
}

.stats-number {
    display: block;
    font-size: 2.5rem;
    font-weight: 900;
    color: #667eea;
    text-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
}

.stats-label {
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.view-mode-toggle {
    display: flex;
    background: #f1f5f9;
    border-radius: 50px;
    padding: 4px;
    gap: 4px;
}

.view-btn {
    padding: 12px 16px;
    border: none;
    border-radius: 50px;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
}

.view-btn.active,
.view-btn:hover {
    background: #667eea;
    color: #ffffff;
    transform: scale(1.05);
}

/* === VUE CARTES === */
.dossiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 24px;
    padding: 0;
}

.dossier-card {
    background: #ffffff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    animation: fadeInUp 0.6s ease-out;
}

.dossier-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px 16px;
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
}

.card-ref {
    background: #667eea;
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.card-priority {
    padding: 6px 14px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
}

.card-priority.priority-high {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: #ffffff;
}

.card-priority.priority-medium {
    background: linear-gradient(135deg, #feca57, #ff9ff3);
    color: #ffffff;
}

.card-priority.priority-low {
    background: linear-gradient(135deg, #48cae4, #023e8a);
    color: #ffffff;
}

.card-body {
    padding: 24px;
}

.card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 16px 0;
    line-height: 1.4;
}

.card-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #64748b;
    font-size: 0.95rem;
}

.meta-item i {
    color: #667eea;
    width: 16px;
}

.deadline-item.deadline-expired {
    color: #ef4444;
    font-weight: 600;
}

.deadline-item.deadline-urgent {
    color: #f97316;
    font-weight: 600;
}

.deadline-item.deadline-warning {
    color: #eab308;
    font-weight: 600;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: #f8fafc;
    border-top: 1px solid rgba(226, 232, 240, 0.5);
}

/* === VUE TABLEAU === */
.modern-table-wrapper {
    background: #ffffff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.modern-dossiers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.modern-dossiers-table thead {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.modern-dossiers-table th {
    padding: 20px 16px;
    color: #ffffff;
    font-weight: 700;
    text-align: left;
    border: none;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 0.85rem;
}

.modern-dossiers-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f1f5f9;
}

.modern-dossiers-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    transform: scale(1.01);
}

.modern-dossiers-table td {
    padding: 20px 16px;
    vertical-align: middle;
}

/* === BADGES === */
.ref-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-en_cours {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #ffffff;
}

.status-badge.status-valide {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
}

.status-badge.status-rejete {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
}

.status-badge.status-archive {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: #ffffff;
}

.priority-badge {
    padding: 6px 14px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
}

.priority-badge.priority-high {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: #ffffff;
}

.priority-badge.priority-medium {
    background: linear-gradient(135deg, #feca57, #ff9ff3);
    color: #ffffff;
}

.priority-badge.priority-low {
    background: linear-gradient(135deg, #48cae4, #023e8a);
    color: #ffffff;
}

/* === BOUTONS D'ACTION === */
.actions-section,
.actions-cell {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.action-btn.view-btn {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #ffffff;
}

.action-btn.edit-btn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #ffffff;
}

.action-btn:hover {
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

/* === CELLULES PERSONNALISÉES === */
.date-cell,
.deadline-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-weight: 500;
}

.deadline-cell.deadline-expired {
    color: #ef4444;
    font-weight: 700;
}

.deadline-cell.deadline-urgent {
    color: #f97316;
    font-weight: 700;
}

.deadline-cell.deadline-warning {
    color: #eab308;
    font-weight: 600;
}

.title-text {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
}

/* === PAGINATION === */
.pagination {
    margin: 32px 0 0 0;
    text-align: center;
}

.pagination a {
    display: inline-block;
    margin: 0 6px;
    padding: 12px 18px;
    border-radius: 50px;
    background: #ffffff;
    color: #667eea;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1rem;
    border: 2px solid #e2e8f0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.pagination a.active,
.pagination a:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #ffffff;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* === ANIMATIONS === */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* === RESPONSIVE === */
@media (max-width: 1200px) {
    .dossiers-grid {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .dossier-section {
        padding: 20px;
    }
    
    .dossiers-header-stats {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 20px;
    }
    
    .stats-number {
        font-size: 2rem;
    }
    
    .filter-container {
        padding: 20px;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }
    
    .filter-group {
        min-width: unset;
    }
    
    .filter-actions {
        margin-left: 0;
        justify-content: center;
    }
    
    .dossiers-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .modern-dossiers-table {
        font-size: 0.85rem;
    }
    
    .modern-dossiers-table th,
    .modern-dossiers-table td {
        padding: 12px 8px;
    }
    
    .action-btn {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .dossier-section {
        padding: 16px;
        margin-bottom: 20px;
    }
    
    .dossier-section h2 {
        font-size: 1.5rem;
    }
    
    .filter-container {
        padding: 16px;
    }
    
    .filter-header h3 {
        font-size: 1.2rem;
    }
    
    .card-body {
        padding: 16px;
    }
    
    .card-footer {
        padding: 16px;
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }
    
    .actions-section {
        justify-content: center;
    }
    
    .stats-number {
        font-size: 1.8rem;
    }
    
    .btn {
        padding: 12px 20px;
        font-size: 0.85rem;
    }
    
    .modern-dossiers-table th,
    .modern-dossiers-table td {
        padding: 10px 6px;
        font-size: 0.8rem;
    }
}

/* === EFFETS SPÉCIAUX === */
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    animation: ripple-effect 0.6s ease-out;
    pointer-events: none;
}

@keyframes ripple-effect {
    from {
        opacity: 1;
        transform: scale(0);
    }
    to {
        opacity: 0;
        transform: scale(2);
    }
}

/* === TRANSITIONS FLUIDES === */
.dossiers-grid,
.dossiers-table-container {
    transition: opacity 0.3s ease-in-out;
}

.filter-form {
    transition: all 0.3s ease;
}

/* === AMÉLIORATIONS VISUELLES === */
.card-title {
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.action-btn {
    position: relative;
    overflow: hidden;
}

.status-badge,
.priority-badge {
    position: relative;
    overflow: hidden;
}

.status-badge::before,
.priority-badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.status-badge:hover::before,
.priority-badge:hover::before {
    left: 100%;
}

/* === SCROLLBAR PERSONNALISÉE === */
.modern-table-wrapper {
    overflow-x: auto;
}

.modern-table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.modern-table-wrapper::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.modern-table-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
}

.modern-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
}

/* === INDICATEURS DE CHARGEMENT === */
.loading {
    opacity: 0.7;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #667eea;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des éléments
    const animatedElements = document.querySelectorAll('.stat-card, .filters-panel, .dossier-card');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            el.style.transition = 'all 0.6s ease';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animation des statistiques
    setTimeout(() => {
        animateStatistics();
    }, 500);
    
    // Gestion de la recherche en temps réel
    setupLiveSearch();
    
    // Sauvegarde des filtres
    saveFiltersState();
    
    console.log('📁 Page des dossiers initialisée avec succès');
});

// Fonction pour basculer l'affichage des filtres
function toggleFilters() {
    const filterForm = document.getElementById('filter-form');
    const toggleBtn = document.querySelector('.filter-toggle i');
    
    if (filterForm.style.display === 'none') {
        filterForm.style.display = 'grid';
        filterForm.style.opacity = '0';
        setTimeout(() => {
            filterForm.style.opacity = '1';
        }, 50);
        toggleBtn.classList.remove('fa-chevron-down');
        toggleBtn.classList.add('fa-chevron-up');
    } else {
        filterForm.style.opacity = '0';
        setTimeout(() => {
            filterForm.style.display = 'none';
        }, 300);
        toggleBtn.classList.remove('fa-chevron-up');
        toggleBtn.classList.add('fa-chevron-down');
    }
}

// Fonction pour réinitialiser les filtres
function resetFilters() {
    const form = document.getElementById('filter-form');
    const inputs = form.querySelectorAll('input, select');
    
    // Animation de réinitialisation
    form.style.transform = 'scale(0.98)';
    
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
        // Supprimer de localStorage
        localStorage.removeItem(`dossier_filter_${input.name}`);
    });
    
    setTimeout(() => {
        form.style.transform = 'scale(1)';
        // Redirection pour effacer l'URL
        window.location.href = window.location.pathname;
    }, 200);
}

// Fonction pour changer de vue
function switchView(viewType) {
    const buttons = document.querySelectorAll('.view-btn');
    const gridView = document.getElementById('grid-view');
    
    // Mettre à jour les boutons
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.view === viewType) {
            btn.classList.add('active');
        }
    });
    
    // Pour l'instant, on garde seulement la vue grille
    // La vue tableau peut être ajoutée plus tard si nécessaire
    localStorage.setItem('dossier_view_preference', viewType);
    
    // Animation de transition
    gridView.style.opacity = '0';
    setTimeout(() => {
        gridView.style.opacity = '1';
    }, 200);
}

// Animation des statistiques
function animateStatistics() {
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((stat, index) => {
        const finalValue = parseInt(stat.textContent);
        stat.textContent = '0';
        
        setTimeout(() => {
            let current = 0;
            const increment = Math.max(1, Math.ceil(finalValue / 30));
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    current = finalValue;
                    clearInterval(timer);
                }
                stat.textContent = current;
            }, 50);
        }, index * 200);
    });
}

// Recherche en temps réel
function setupLiveSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.toLowerCase().trim();
        
        if (query.length === 0) {
            showAllCards();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            filterCards(query);
        }, 300);
    });
}

function filterCards(query) {
    const cards = document.querySelectorAll('.dossier-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        const ref = card.querySelector('.card-ref').textContent.toLowerCase();
        const meta = card.querySelector('.card-meta').textContent.toLowerCase();
        
        const isVisible = title.includes(query) || ref.includes(query) || meta.includes(query);
        
        if (isVisible) {
            card.style.display = 'block';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
            visibleCount++;
        } else {
            card.style.opacity = '0';
            card.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 300);
        }
    });
    
    updateVisibleCount(visibleCount);
}

function showAllCards() {
    const cards = document.querySelectorAll('.dossier-card');
    cards.forEach(card => {
        card.style.display = 'block';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    });
    updateVisibleCount(cards.length);
}

function updateVisibleCount(count) {
    const statValue = document.querySelector('.stat-value');
    if (statValue) {
        statValue.textContent = count;
    }
}

// Sauvegarde de l'état des filtres
function saveFiltersState() {
    const form = document.getElementById('filter-form');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        // Restaurer les valeurs sauvegardées
        const savedValue = localStorage.getItem(`dossier_filter_${input.name}`);
        if (savedValue && !input.value) {
            input.value = savedValue;
        }
        
        // Sauvegarder lors des changements
        input.addEventListener('change', function() {
            if (this.value) {
                localStorage.setItem(`dossier_filter_${this.name}`, this.value);
            } else {
                localStorage.removeItem(`dossier_filter_${this.name}`);
            }
        });
    });
    
    // Restaurer la préférence de vue
    const savedView = localStorage.getItem('dossier_view_preference');
    if (savedView) {
        switchView(savedView);
    }
}

// Gestion des interactions avec les cartes
document.addEventListener('click', function(e) {
    // Effet de ripple sur les boutons d'action
    if (e.target.closest('.action-btn')) {
        const btn = e.target.closest('.action-btn');
        createRipple(btn, e);
    }
    
    // Navigation rapide en cliquant sur la carte (sauf sur les boutons)
    if (e.target.closest('.dossier-card') && !e.target.closest('.action-btn')) {
        const card = e.target.closest('.dossier-card');
        const dossierId = card.dataset.id;
        const viewLink = card.querySelector('.view-btn-action');
        if (viewLink) {
            window.location.href = viewLink.href;
        }
    }
});

function createRipple(element, event) {
    const ripple = document.createElement('span');
    ripple.classList.add('ripple');
    
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    `;
    
    element.style.position = 'relative';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// CSS pour l'animation de ripple
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Gestion du chargement du formulaire
document.getElementById('filter-form').addEventListener('submit', function() {
    const submitBtn = this.querySelector('.btn-info');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recherche...';
    submitBtn.disabled = true;
    
    // Animation de la grille
    const cards = document.querySelectorAll('.dossier-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0.7';
            card.style.transform = 'scale(0.98)';
        }, index * 50);
    });
});

// Restaurer les filtres depuis l'URL au chargement
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const form = document.getElementById('filter-form');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        const value = urlParams.get(input.name);
        if (value) {
            input.value = value;
            localStorage.setItem(`dossier_filter_${input.name}`, value);
        }
    });
});

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K pour focus sur la recherche
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape pour effacer la recherche
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            showAllCards();
        }
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Fonctions helper
function buildSortQuery($field) {
    global $filters;
    $query = [];
    foreach ($filters as $key => $value) {
        if ($key !== 'sort' && $key !== 'order' && $value !== null) {
            $query[] = "$key=" . urlencode($value);
        }
    }
    $query[] = "sort=$field";
    $query[] = "order=" . ($filters['sort'] === $field && $filters['order'] === 'asc' ? 'desc' : 'asc');
    return implode('&', $query);
}

function sortArrow($field) {
    global $filters;
    if ($filters['sort'] === $field) {
        return $filters['order'] === 'asc' ? '↑' : '↓';
    }
    return '';
}

function buildPageQuery($page) {
    global $filters;
    $query = [];
    foreach ($filters as $key => $value) {
        if ($key !== 'offset' && $key !== 'page' && $value !== null) {
            $query[] = "$key=" . urlencode($value);
        }
    }
    $query[] = "page=$page";
    return implode('&', $query);
}

function getDeadlineClass($deadline) {
    if (!$deadline) return '';
    $deadlineDate = new DateTime($deadline);
    $today = new DateTime();
    
    if ($deadlineDate < $today) {
        return 'deadline-expired';
    } elseif ($deadlineDate->diff($today)->days <= 3) {
        return 'deadline-urgent';
    } elseif ($deadlineDate->diff($today)->days <= 7) {
        return 'deadline-warning';
    }
    return '';
}
?>