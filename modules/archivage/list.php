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

<div class="container archivage-section" style="max-width:900px;margin:auto;">
    <div class="archivage-header-modern" style="display:flex;align-items:center;gap:24px;margin-bottom:24px;">
        <div class="archivage-icon"><i class="fas fa-archive" style="font-size:48px;color:#636e72;"></i></div>
        <div style="flex:1;">
            <h1 class="section-title" style="color:#636e72;">Archivage Sécurisé</h1>
        </div>
        <div>
            <a href="install_pdf.php" style="background:#f39c12;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-right:8px;"><i class="fas fa-cog"></i> Config PDF</a>
            <a href="test_export.php" style="background:#2980b9;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-right:8px;"><i class="fas fa-vial"></i> Test Export</a>
            <a href="test_pdf_advanced.php" style="background:#8e44ad;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;"><i class="fas fa-flask"></i> Test Avancé</a>
        </div>
    </div>
    <div class="alert alert-info" style="background:#eaf6fb;color:#2980b9;border-radius:8px;padding:14px 18px;margin-bottom:24px;">
        <i class="fas fa-info-circle"></i> Les dossiers archivés sont conservés de manière sécurisée et immuable. Utilisez les boutons d'export pour générer des rapports PDF ou HTML.
    </div>
    <table class="table archivage-table-modern" style="background:#fff;border-radius:16px;box-shadow:0 2px 16px #636e7222;overflow:hidden;margin-bottom:32px;">
        <thead style="background:#fff;box-shadow:0 2px 8px #636e7222;">
            <tr>
                <th style="color:#636e72;font-size:1.08em;font-weight:700;text-align:center;padding:18px 0;border-top-left-radius:16px;">Référence</th>
                <th style="color:#222;text-align:center;">Titre</th>
                <th style="color:#2980b9;text-align:center;">Responsable</th>
                <th style="color:#27ae60;text-align:center;">Date archivage</th>
                <th style="text-align:center;border-top-right-radius:16px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dossiers as $d): ?>
            <tr class="archivage-row-modern" style="background:#f8fafc;border-radius:14px;box-shadow:0 1px 6px #636e7222;margin-bottom:14px;vertical-align:middle;">
                <td style="text-align:center;font-weight:600;letter-spacing:0.5px;"> <?= $d['reference'] ?> </td>
                <td style="text-align:center;color:#222;"> <?= htmlspecialchars($d['titre']) ?> </td>
                <td style="text-align:center;color:#2980b9;font-weight:500;"> <?= htmlspecialchars($d['responsable_name']) ?> </td>
                <td style="text-align:center;color:#27ae60;"> <?= formatDate($d['updated_at']) ?> </td>
                <td style="text-align:center;">
                    <a href="../dossiers/view.php?id=<?= $d['id'] ?>" class="btn btn-info" style="border-radius:8px;padding:8px 18px;margin-right:8px;background:#eaf6fb;color:#2980b9;font-weight:600;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-eye"></i> Consulter</a>
                    <div style="display:inline-flex;gap:4px;">
                        <a href="export.php?id=<?= $d['id'] ?>&format=pdf" class="btn btn-danger" style="border-radius:8px;padding:8px 16px;background:#e74c3c;color:#fff;font-weight:600;display:inline-flex;align-items:center;gap:6px;" title="Export PDF"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="export.php?id=<?= $d['id'] ?>&format=html" class="btn btn-secondary" style="border-radius:8px;padding:8px 16px;background:#636e72;color:#fff;font-weight:600;display:inline-flex;align-items:center;gap:6px;" title="Export HTML"><i class="fas fa-file-alt"></i> HTML</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <style>
    .archivage-table-modern thead tr { box-shadow:0 2px 8px #636e7222; }
    .archivage-row-modern { margin-bottom:14px; transition:background 0.18s, box-shadow 0.18s; }
    .archivage-row-modern:hover { background:#eaf6fb !important; box-shadow:0 4px 16px #2980b922; }
    @media (max-width: 700px) { .archivage-table-modern th, .archivage-table-modern td { font-size:0.97em;padding:7px 2px; } }
    </style>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>