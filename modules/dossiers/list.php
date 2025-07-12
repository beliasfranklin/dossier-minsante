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

<!-- Interface utilisateur -->
<div class="dossier-section" style="display:flex;align-items:center;justify-content:space-between;align-items:flex-end;">
    <h2 class="section-title" style="color:#2980b9;"><i class="fas fa-folder-open"></i> Liste des dossiers</h2>
    <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Créer un dossier</a>
</div>
<div class="filter-container">
    <div class="filter-header">
        <h3><i class="fas fa-filter"></i> Filtres de recherche</h3>
        <button type="button" class="filter-toggle" onclick="toggleFilters()">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <form method="get" class="filter-form" id="filter-form">
        <div class="filter-row">
            <div class="filter-group">
                <label><i class="fas fa-info-circle"></i> Statut</label>
                <select name="status" class="status-select">
                    <option value="">Tous statuts</option>
                    <option value="en_cours" <?= $filters['status'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="valide" <?= $filters['status'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                    <option value="rejete" <?= $filters['status'] === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                    <option value="archive" <?= $filters['status'] === 'archive' ? 'selected' : '' ?>>Archivé</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-file-alt"></i> Type</label>
                <select name="type" class="type-select">
                    <option value="">Tous types</option>
                    <option value="Etude" <?= $filters['type'] === 'Etude' ? 'selected' : '' ?>>Étude</option>
                    <option value="Projet" <?= $filters['type'] === 'Projet' ? 'selected' : '' ?>>Projet</option>
                    <option value="Administratif" <?= $filters['type'] === 'Administratif' ? 'selected' : '' ?>>Administratif</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-flag"></i> Priorité</label>
                <select name="priority" class="priority-select">
                    <option value="">Toutes priorités</option>
                    <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>Haute</option>
                    <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Moyenne</option>
                    <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Basse</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div class="filter-group search-group">
                <label><i class="fas fa-search"></i> Recherche</label>
                <input type="text" name="search" placeholder="Titre, référence, description..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" class="search-input">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-clock"></i> Échéance</label>
                <select name="deadline" class="deadline-select">
                    <option value="">Toutes échéances</option>
                    <option value="expired" <?= $filters['deadline'] === 'expired' ? 'selected' : '' ?>>Expirées</option>
                    <option value="today" <?= $filters['deadline'] === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                    <option value="upcoming" <?= $filters['deadline'] === 'upcoming' ? 'selected' : '' ?>>À venir (7j)</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Date de début</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="date-input">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Date de fin</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="date-input">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> Réinitialiser
                </button>
            </div>
        </div>
    </form>
</div>
<!-- Liste moderne des dossiers avec design premium -->
<div class="dossiers-grid-container">
    <div class="dossiers-header-stats">
        <div class="stats-container">
            <div class="stats-item premium-stat">
                <div class="stat-icon">
                    <i class="fas fa-files"></i>
                </div>
                <div class="stat-content">
                    <span class="stats-number" data-count="<?= count($dossiers) ?>"><?= count($dossiers) ?></span>
                    <span class="stats-label">Dossiers affichés</span>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: <?= $total > 0 ? (count($dossiers) / $total * 100) : 0 ?>%"></div>
                </div>
            </div>
            <div class="stats-item premium-stat">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <span class="stats-number" data-count="<?= $total ?>"><?= $total ?></span>
                    <span class="stats-label">Total général</span>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar full" style="width: 100%"></div>
                </div>
            </div>
            <div class="stats-item premium-stat">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <span class="stats-number"><?= $totalPages ?></span>
                    <span class="stats-label">Pages</span>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: 75%"></div>
                </div>
            </div>
        </div>
        <div class="view-mode-toggle premium-toggle">
            <button class="view-btn active premium-btn" data-view="grid" title="Vue cartes">
                <i class="fas fa-th-large"></i>
                <span>Cartes</span>
            </button>
            <button class="view-btn premium-btn" data-view="table" title="Vue tableau">
                <i class="fas fa-table"></i>
                <span>Tableau</span>
            </button>
            <button class="view-btn premium-btn" data-view="kanban" title="Vue Kanban">
                <i class="fas fa-columns"></i>
                <span>Kanban</span>
            </button>
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
        <div class="dossier-card" data-id="<?= $dossier['id'] ?>" data-priority="<?= $dossier['priority'] ?>">
            <div class="card-header">
                <div class="card-ref">
                    <i class="fas fa-hashtag"></i>
                    <?= $dossier['reference'] ?>
                </div>
                <div class="card-priority priority-<?= $dossier['priority'] ?>">
                    <i class="fas fa-flag"></i>
                    <?= ucfirst($dossier['priority']) ?>
                </div>
            </div>
            
            <div class="card-body">
                <h3 class="card-title"><?= htmlspecialchars($dossier['titre']) ?></h3>
                <div class="card-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Créé le <?= date('d/m/Y', strtotime($dossier['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($dossier['deadline'])): ?>
                    <div class="meta-item deadline-item <?= getDeadlineClass($dossier['deadline']) ?>">
                        <i class="fas fa-clock"></i>
                        <span>Échéance: <?= date('d/m/Y', strtotime($dossier['deadline'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="status-section">
                    <span class="status-badge status-<?= $dossier['status'] ?>">
                        <i class="fas fa-circle"></i>
                        <?= ucfirst(str_replace('_', ' ', $dossier['status'])) ?>
                    </span>
                </div>
                <div class="actions-section">
                    <a href="view.php?id=<?= $dossier['id'] ?>" class="action-btn view-btn" title="Voir le dossier">
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

    <!-- Vue tableau (masquée par défaut) -->
    <div class="dossiers-table-container" id="table-view" style="display: none;">
        <?php if (empty($dossiers)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-table"></i>
            </div>
            <h3>Aucun dossier à afficher</h3>
            <p>La liste des dossiers est vide. Modifiez vos filtres ou créez un nouveau dossier.</p>
        </div>
        <?php else: ?>
        <div class="modern-table-wrapper">
            <table class="modern-dossiers-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> Référence</th>
                        <th><i class="fas fa-file-alt"></i> Titre</th>
                        <th><i class="fas fa-info-circle"></i> Statut</th>
                        <th><i class="fas fa-flag"></i> Priorité</th>
                        <th><i class="fas fa-calendar"></i> Créé le</th>
                        <th><i class="fas fa-clock"></i> Échéance</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                    <tr class="table-row" data-id="<?= $dossier['id'] ?>">
                        <td>
                            <div class="ref-cell">
                                <span class="ref-badge"><?= $dossier['reference'] ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="title-cell">
                                <span class="title-text"><?= htmlspecialchars($dossier['titre']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $dossier['status'] ?>">
                                <i class="fas fa-circle"></i>
                                <?= ucfirst(str_replace('_', ' ', $dossier['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="priority-badge priority-<?= $dossier['priority'] ?>">
                                <i class="fas fa-flag"></i>
                                <?= ucfirst($dossier['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="date-cell">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('d/m/Y', strtotime($dossier['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div class="deadline-cell <?= getDeadlineClass($dossier['deadline']) ?>">
                                <i class="fas fa-clock"></i>
                                <?= !empty($dossier['deadline']) ? date('d/m/Y', strtotime($dossier['deadline'])) : 'N/A' ?>
                            </div>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="view.php?id=<?= $dossier['id'] ?>" class="action-btn view-btn" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                                <a href="edit.php?id=<?= $dossier['id'] ?>" class="action-btn edit-btn" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
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

<!-- Pagination -->
<div class="pagination">
    <?php if ($totalPages > 1): ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= buildPageQuery($i) ?>" class="<?= $i == ($filters['offset'] / $filters['limit'] + 1) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
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
    // Gestion du changement de vue
    const viewButtons = document.querySelectorAll('.view-btn');
    const gridView = document.getElementById('grid-view');
    const tableView = document.getElementById('table-view');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            viewButtons.forEach(b => b.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');
            
            // Changer la vue avec animation
            if (this.dataset.view === 'grid') {
                tableView.style.opacity = '0';
                setTimeout(() => {
                    gridView.style.display = 'grid';
                    tableView.style.display = 'none';
                    gridView.style.opacity = '1';
                }, 200);
            } else {
                gridView.style.opacity = '0';
                setTimeout(() => {
                    gridView.style.display = 'none';
                    tableView.style.display = 'block';
                    tableView.style.opacity = '1';
                }, 200);
            }
        });
    });
    
    // Animation d'apparition décalée pour les cartes
    const cards = document.querySelectorAll('.dossier-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
    
    // Animation au survol des cartes
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Gestion des filtres
    const filterForm = document.getElementById('filter-form');
    const filterInputs = filterForm.querySelectorAll('select, input');
    
    // Sauvegarde automatique des filtres
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            localStorage.setItem(`filter_${this.name}`, this.value);
        });
    });
    
    // Restauration des filtres depuis localStorage
    filterInputs.forEach(input => {
        const savedValue = localStorage.getItem(`filter_${input.name}`);
        if (savedValue && !input.value) {
            input.value = savedValue;
        }
    });
    
    // Animation des boutons au clic
    const actionBtns = document.querySelectorAll('.action-btn');
    actionBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Effet de ripple
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Indicateur de chargement pour les actions
    const form = document.querySelector('.filter-form');
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('.btn-info');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recherche...';
        submitBtn.disabled = true;
        
        // Restaurer le bouton après 3 secondes (sécurité)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });
    
    // Compteur de résultats en temps réel
    updateResultsCounter();
});

// Fonction pour basculer l'affichage des filtres
function toggleFilters() {
    const filterForm = document.getElementById('filter-form');
    const toggleBtn = document.querySelector('.filter-toggle i');
    
    if (filterForm.style.display === 'none') {
        filterForm.style.display = 'flex';
        toggleBtn.classList.remove('fa-chevron-down');
        toggleBtn.classList.add('fa-chevron-up');
    } else {
        filterForm.style.display = 'none';
        toggleBtn.classList.remove('fa-chevron-up');
        toggleBtn.classList.add('fa-chevron-down');
    }
}

// Fonction pour réinitialiser les filtres
function resetFilters() {
    const form = document.querySelector('.filter-form');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
        // Supprimer de localStorage
        localStorage.removeItem(`filter_${input.name}`);
    });
    
    // Animation de réinitialisation
    form.style.transform = 'scale(0.98)';
    setTimeout(() => {
        form.style.transform = 'scale(1)';
        // Soumettre automatiquement après réinitialisation
        form.submit();
    }, 200);
}

// Fonction pour mettre à jour le compteur de résultats
function updateResultsCounter() {
    const cards = document.querySelectorAll('.dossier-card');
    const rows = document.querySelectorAll('.table-row');
    const activeView = document.querySelector('.view-btn.active').dataset.view;
    const count = activeView === 'grid' ? cards.length : rows.length;
    
    const statsNumber = document.querySelector('.stats-number');
    if (statsNumber) {
        // Animation du compteur
        let currentCount = 0;
        const increment = count / 30;
        const timer = setInterval(() => {
            currentCount += increment;
            if (currentCount >= count) {
                statsNumber.textContent = count;
                clearInterval(timer);
            } else {
                statsNumber.textContent = Math.floor(currentCount);
            }
        }, 50);
    }
}

// Fonction de recherche en temps réel (optionnelle)
function setupLiveSearch() {
    const searchInput = document.querySelector('.search-input');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const query = this.value.toLowerCase();
            const cards = document.querySelectorAll('.dossier-card');
            const rows = document.querySelectorAll('.table-row');
            
            // Filtrer les cartes
            cards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const ref = card.querySelector('.card-ref').textContent.toLowerCase();
                
                if (title.includes(query) || ref.includes(query)) {
                    card.style.display = 'block';
                    card.style.opacity = '1';
                } else {
                    card.style.opacity = '0';
                    setTimeout(() => {
                        if (card.style.opacity === '0') {
                            card.style.display = 'none';
                        }
                    }, 300);
                }
            });
            
            // Filtrer les lignes du tableau
            rows.forEach(row => {
                const title = row.querySelector('.title-text').textContent.toLowerCase();
                const ref = row.querySelector('.ref-badge').textContent.toLowerCase();
                
                if (title.includes(query) || ref.includes(query)) {
                    row.style.display = 'table-row';
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateResultsCounter();
        }, 300);
    });
}

// Activer la recherche en temps réel si souhaité
// setupLiveSearch();
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