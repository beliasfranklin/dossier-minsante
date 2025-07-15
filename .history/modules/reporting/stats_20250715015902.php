<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_ADMIN);

// Statistiques des dossiers par statut
$stats_dossiers = $pdo->query("SELECT status, COUNT(*) as count FROM dossiers GROUP BY status")->fetchAll();

// Statistiques des utilisateurs par rôle
$stats_users = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();

// Timeline des créations (30 derniers jours)
$stats_timeline = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM dossiers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Statistiques avancées
$advanced_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_dossiers,
        COUNT(DISTINCT created_by) as users_actifs,
        AVG(DATEDIFF(updated_at, created_at)) as duree_moyenne,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as dossiers_valides,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as nouveaux_7j,
        COUNT(CASE WHEN deadline < NOW() AND status != 'archive' THEN 1 END) as en_retard
    FROM dossiers
")->fetch();

// Activité récente par mois
$monthly_activity = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as nouveaux,
        COUNT(CASE WHEN status = 'valide' THEN 1 END) as valides
    FROM dossiers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mois DESC
    LIMIT 12
")->fetchAll();

$page_title = "Statistiques et Reporting";
include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="container stats-section" style="max-width:900px;margin:auto;">
    <div class="stats-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="stats-icon"><i class="fas fa-chart-bar" style="font-size:48px;color:#2980b9;"></i></div>
        <h1 class="section-title" style="color:#2980b9;">Statistiques Reporting</h1>
    </div>
    <div class="stats-cards" style="display:flex;gap:24px;margin-bottom:32px;flex-wrap:wrap;">
        <?php foreach ($stats['dossiers'] as $row): ?>
        <div class="stat-card stat-<?= strtolower($row['status']) ?>" style="flex:1 1 160px;min-width:160px;background:#eaf6fb;border-radius:12px;padding:18px 16px;box-shadow:0 2px 8px #2980b922;">
            <div style="font-size:1.1em;font-weight:600;color:#2980b9;">Statut : <?= htmlspecialchars($row['status']) ?></div>
            <div style="font-size:2.2em;font-weight:700;color:#27ae60;"><?= $row['count'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <h2 style="color:#2980b9;margin-top:32px;">Utilisateurs par rôle</h2>
    <div class="dossier-table-responsive">
    <table class="dossier-table">
        <thead>
            <tr>
                <th>Rôle</th>
                <th>Nombre</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['users'] as $row): ?>
            <tr>
                <td><?= getRoleName($row['role']) ?></td>
                <td><?= $row['count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <h2 style="color:#2980b9;margin-top:32px;">Créations de dossiers (30 derniers jours)</h2>
    <div class="dossier-table-responsive">
    <table class="dossier-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Nombre</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['timeline'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= $row['count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>