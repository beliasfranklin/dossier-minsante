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
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3>Aucun dossier trouvé</h3>
                <p>Il n'y a aucun dossier correspondant à vos critères de recherche.</p>
                <a href="create.php" class="btn-create-first">
                    <i class="fas fa-plus"></i>
                    Créer le premier dossier
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($dossiers as $dossier): ?>
            <div class="dossier-card" data-id="<?= $dossier['id'] ?>">
                <div class="card-header">
                    <div class="card-ref">
                        <i class="fas fa-hashtag"></i>
                        <?= htmlspecialchars($dossier['reference']) ?>
                    </div>
                    <div class="card-priority priority-<?= $dossier['priorite'] ?? 'medium' ?>">
                        <i class="fas fa-flag"></i>
                        <?= ucfirst($dossier['priorite'] ?? 'Medium') ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <h3 class="card-title"><?= htmlspecialchars($dossier['titre']) ?></h3>
                    
                    <div class="card-meta">
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
                        <?php if (!empty($dossier['deadline'])): ?>
                        <div class="meta-item deadline-item <?= getDeadlineClass($dossier['deadline']) ?>">
                            <i class="fas fa-clock"></i>
                            <span>Échéance: <?= date('d/m/Y', strtotime($dossier['deadline'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($dossier['description'])): ?>
                    <div class="card-description">
                        <?= htmlspecialchars(substr($dossier['description'], 0, 120)) ?>
                        <?= strlen($dossier['description']) > 120 ? '...' : '' ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <div class="status-badge status-<?= $dossier['status'] ?? 'en_cours' ?>">
                        <i class="fas fa-circle"></i>
                        <?= ucfirst(str_replace('_', ' ', $dossier['status'] ?? 'En cours')) ?>
                    </div>
                    
                    <div class="actions-section">
                        <a href="view.php?id=<?= $dossier['id'] ?>" class="action-btn view-btn-action" title="Consulter">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                        <a href="edit.php?id=<?= $dossier['id'] ?>" class="action-btn edit-btn" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= buildPageQuery($i) ?>" class="<?= $i == ($filters['offset'] / $filters['limit'] + 1) ? 'current' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des éléments
    const animatedElements = document.querySelectorAll('.stat-card, .filters-panel, .main-card');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            el.style.transition = 'all 0.6s ease';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Animation des cartes de dossiers
    setTimeout(() => {
        const dossierCards = document.querySelectorAll('.dossier-card');
        dossierCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }, 800);
    
    // Fonction pour basculer l'affichage des filtres
    window.toggleFilters = function() {
        const content = document.getElementById('filtersContent');
        const icon = document.getElementById('filtersIcon');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
            content.style.opacity = '0';
            content.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                content.style.transition = 'all 0.3s ease';
                content.style.opacity = '1';
                content.style.transform = 'translateY(0)';
            }, 10);
        } else {
            content.style.transition = 'all 0.3s ease';
            content.style.opacity = '0';
            content.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
            }, 300);
        }
    };
    
    // Recherche en temps réel
    const quickSearch = document.getElementById('quickSearch');
    if (quickSearch) {
        quickSearch.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const cards = document.querySelectorAll('.dossier-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const shouldShow = text.includes(filter);
                
                if (shouldShow) {
                    card.style.display = '';
                    card.style.animation = 'fadeIn 0.3s ease';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
    
    // Animation des statistiques
    animateStatistics();
    
    // Gestion du hover sur les cartes
    const dossierCards = document.querySelectorAll('.dossier-card');
    dossierCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    console.log('📁 Page de gestion des dossiers initialisée avec succès');
});

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

// Gestion des erreurs d'images
window.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG') {
        e.target.style.display = 'none';
    }
});

// Auto-save des filtres dans le localStorage
const filterForm = document.querySelector('.filters-form');
if (filterForm) {
    filterForm.addEventListener('change', function() {
        const formData = new FormData(this);
        const filters = {};
        formData.forEach((value, key) => {
            if (value.trim()) filters[key] = value;
        });
        localStorage.setItem('dossier_filters', JSON.stringify(filters));
    });
    
    // Restaurer les filtres sauvegardés
    const savedFilters = localStorage.getItem('dossier_filters');
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            Object.keys(filters).forEach(key => {
                const field = filterForm.querySelector(`[name="${key}"]`);
                if (field && !field.value) {
                    field.value = filters[key];
                }
            });
        } catch (e) {
            console.log('Erreur lors de la restauration des filtres');
        }
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>