<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
requireAuth();
hasPermission(ROLE_ADMIN) or die("Accès refusé");

$stats = [
    'dossiers' => fetchAll("SELECT status, COUNT(*) as count FROM dossiers GROUP BY status"),
    'users' => fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role"),
    'timeline' => fetchAll("SELECT DATE(created_at) as date, COUNT(*) as count 
                          FROM dossiers 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                          GROUP BY DATE(created_at)")
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Statistiques Workflow Modernisées -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<div class="container mt-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-2 d-none d-md-block">
            <img src="/assets/img/admin-users.svg" alt="Statistiques" style="width:100%">
        </div>
        <div class="col-md-10">
            <h1 class="mb-1"><i class="fa-solid fa-chart-line text-primary"></i> Statistiques Workflow</h1>
            <p class="text-muted">Vue d'ensemble de l'activité des dossiers et des utilisateurs sur la plateforme.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Dossiers par statut -->
        <?php foreach ($stats['dossiers'] as $row): ?>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fa-solid fa-folder-open fa-2x text-info"></i>
                        </div>
                        <h5 class="card-title mb-1">
                            <span class="badge bg-info text-white" style="font-size:1rem;">
                                <?= htmlspecialchars($row['statut']) ?>
                            </span>
                        </h5>
                        <p class="display-6 fw-bold mb-0"><?= $row['count'] ?></p>
                        <small class="text-muted">Dossiers</small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <!-- Utilisateurs par rôle -->
        <?php foreach ($stats['users'] as $row): ?>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fa-solid fa-user-shield fa-2x text-success"></i>
                        </div>
                        <h5 class="card-title mb-1">
                            <span class="badge bg-success text-white" style="font-size:1rem;">
                                <?= getRoleName($row['role']) ?>
                            </span>
                        </h5>
                        <p class="display-6 fw-bold mb-0"><?= $row['count'] ?></p>
                        <small class="text-muted">Utilisateurs</small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="card-title mb-3"><i class="fa-solid fa-calendar-days text-primary"></i> Créations de dossiers (30 derniers jours)</h4>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Date</th><th>Nombre</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stats['timeline'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['date']) ?></td>
                                    <td><span class="badge bg-primary fs-6"><?= $row['count'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 1rem;
    transition: box-shadow 0.2s;
}
.card:hover {
    box-shadow: 0 0 0.5rem #007bff33;
}
.badge {
    font-size: 1rem;
    padding: 0.5em 1em;
    border-radius: 1em;
}
.display-6 {
    font-size: 2.5rem;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>