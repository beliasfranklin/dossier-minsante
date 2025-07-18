<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireRole(ROLE_GESTIONNAIRE);

// Paramètres de filtrage
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action_type' => $_GET['action_type'] ?? '',
    'dossier_id' => $_GET['dossier_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($filters['user_id'])) {
    $where_conditions[] = "l.user_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['action_type'])) {
    $where_conditions[] = "l.action_type = ?";
    $params[] = $filters['action_type'];
}

if (!empty($filters['dossier_id'])) {
    $where_conditions[] = "l.dossier_id = ?";
    $params[] = $filters['dossier_id'];
}

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(l.details LIKE ? OR d.reference LIKE ? OR d.titre LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_sql = "
    SELECT COUNT(*) 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN dossiers d ON l.dossier_id = d.id
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Récupérer les logs avec pagination
$sql = "
    SELECT 
        l.*,
        u.prenom, u.nom, u.email,
        d.reference, d.titre
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN dossiers d ON l.dossier_id = d.id
    $where_clause
    ORDER BY l.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Récupérer les données pour les filtres
$users_stmt = $pdo->query("SELECT id, prenom, nom FROM users ORDER BY prenom, nom");
$users = $users_stmt->fetchAll();

$actions_stmt = $pdo->query("SELECT DISTINCT action_type FROM logs ORDER BY action_type");
$action_types = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Statistiques rapides
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as users_actifs,
        COUNT(DISTINCT DATE(created_at)) as jours_actifs,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as logs_24h
    FROM logs
");
$stats = $stats_stmt->fetch();

$page_title = "Journal d'Audit";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['total_logs']) ?></h4>
                            <small>Total des logs</small>
                        </div>
                        <i class="fas fa-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['users_actifs'] ?></h4>
                            <small>Utilisateurs actifs</small>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['jours_actifs'] ?></h4>
                            <small>Jours avec activité</small>
                        </div>
                        <i class="fas fa-calendar fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['logs_24h'] ?></h4>
                            <small>Dernières 24h</small>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres de Recherche</h5>
        </div>
        
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-2">
                    <label for="user_id">Utilisateur</label>
                    <select class="form-control form-control-sm" id="user_id" name="user_id">
                        <option value="">-- Tous --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="action_type">Type d'action</label>
                    <select class="form-control form-control-sm" id="action_type" name="action_type">
                        <option value="">-- Toutes --</option>
                        <?php foreach ($action_types as $action): ?>
                            <option value="<?= $action ?>" <?= $filters['action_type'] == $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="dossier_id">ID Dossier</label>
                    <input type="number" class="form-control form-control-sm" id="dossier_id" 
                           name="dossier_id" value="<?= htmlspecialchars($filters['dossier_id']) ?>" 
                           placeholder="ID">
                </div>
                
                <div class="col-md-2">
                    <label for="date_from">Du</label>
                    <input type="date" class="form-control form-control-sm" id="date_from" 
                           name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to">Au</label>
                    <input type="date" class="form-control form-control-sm" id="date_to" 
                           name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="search">Recherche</label>
                    <input type="text" class="form-control form-control-sm" id="search" 
                           name="search" value="<?= htmlspecialchars($filters['search']) ?>" 
                           placeholder="Détails, référence...">
                </div>
                
                <div class="col-md-12 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary btn-sm ml-2">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                    <a href="export.php?<?= http_build_query($filters) ?>" class="btn btn-success btn-sm ml-2">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Résultats -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-history"></i> Journal d'Audit
                <small class="text-muted">(<?= number_format($total_logs) ?> entrées)</small>
            </h5>
            
            <!-- Pagination info -->
            <span class="text-muted small">
                Page <?= $page ?> sur <?= $total_pages ?> 
                (<?= $offset + 1 ?>-<?= min($offset + $per_page, $total_logs) ?> sur <?= number_format($total_logs) ?>)
            </span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Dossier</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2" 
                                             style="width: 30px; height: 30px; font-size: 12px;">
                                            <?= strtoupper(substr($log['prenom'], 0, 1) . substr($log['nom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <small class="font-weight-bold">
                                                <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getActionColor($log['action_type']) ?>">
                                        <?= htmlspecialchars($log['action_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['dossier_id']): ?>
                                        <a href="../../modules/dossiers/view.php?id=<?= $log['dossier_id'] ?>" 
                                           class="text-decoration-none">
                                            <small>
                                                <strong><?= htmlspecialchars($log['reference']) ?></strong>
                                                <br>
                                                <span class="text-muted">
                                                    <?= htmlspecialchars(substr($log['titre'] ?? 'Sans titre', 0, 30)) ?>...
                                                </span>
                                            </small>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($log['details']) ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted font-monospace">
                                        <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Pagination logs">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $total_pages])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-complétion pour la recherche de dossiers
    const dossierInput = document.getElementById('dossier_id');
    if (dossierInput) {
        dossierInput.addEventListener('input', function() {
            // Optionnel : ajouter une auto-complétion AJAX
        });
    }
    
    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
    });
});

// Fonction utilitaire pour les couleurs des actions
<?php
function getActionColor($action) {
    $colors = [
        'LOGIN' => 'success',
        'LOGOUT' => 'secondary',
        'CREATE' => 'primary',
        'UPDATE' => 'warning',
        'DELETE' => 'danger',
        'CHANGE_STATUS' => 'info',
        'UPLOAD' => 'primary',
        'DOWNLOAD' => 'info',
        'EXPORT' => 'success',
        'ARCHIVE' => 'dark'
    ];
    
    foreach ($colors as $pattern => $color) {
        if (strpos($action, $pattern) !== false) {
            return $color;
        }
    }
    
    return 'light';
}
?>
</script>

<?php include '../../includes/footer.php'; ?>
