<?php
require_once 'includes/config.php';
requireAuth();

include __DIR__ . '/includes/header.php';

// Récupérer les statistiques des dossiers
$stats = fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
        SUM(CASE WHEN status = 'rejete' THEN 1 ELSE 0 END) as rejetes,
        SUM(CASE WHEN status = 'archive' THEN 1 ELSE 0 END) as archives
    FROM dossiers
");

// Dossiers récents
$recentDossiers = fetchAll("
    SELECT d.*, u.name as responsable_name 
    FROM dossiers d
    LEFT JOIN users u ON d.responsable_id = u.id
    ORDER BY created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('dashboard') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container dashboard-modern">
        <div class="dashboard-header" style="display:flex;align-items:center;gap:16px;margin:16px 0;">
            <div class="dashboard-icon"><i class="fas fa-tachometer-alt" style="font-size:24px;color:#2980b9;"></i></div>
            <h1 class="dashboard-title" style="color:#2980b9;margin:0;font-size:1.2em;"><?= t('dashboard') ?></h1>
        </div>
        <main>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:24px;margin-bottom:48px;">
                <div class="stat-card stat-blue" style="background:linear-gradient(135deg,#3498db,#2980b9);border-radius:16px;padding:24px;color:#fff;box-shadow:0 4px 20px rgba(41,128,185,0.15);transition:transform 0.2s;">
                    <h3 style="margin:0 0 16px 0;font-size:1.1em;opacity:0.9;"><i class="fas fa-folder"></i> Total dossiers</h3>
                    <p class="stat-value" data-target="<?= $stats['total'] ?>" style="margin:0;font-size:2.2em;font-weight:700;">0</p>
                </div>
                <div class="stat-card stat-cyan" style="background:linear-gradient(135deg,#00bcd4,#00acc1);border-radius:16px;padding:24px;color:#fff;box-shadow:0 4px 20px rgba(0,188,212,0.15);transition:transform 0.2s;">
                    <h3 style="margin:0 0 16px 0;font-size:1.1em;opacity:0.9;"><i class="fas fa-clock"></i> En cours</h3>
                    <p class="stat-value" data-target="<?= $stats['en_cours'] ?>" style="margin:0;font-size:2.2em;font-weight:700;">0</p>
                </div>
                <div class="stat-card stat-green" style="background:linear-gradient(135deg,#2ecc71,#27ae60);border-radius:16px;padding:24px;color:#fff;box-shadow:0 4px 20px rgba(46,204,113,0.15);transition:transform 0.2s;">
                    <h3 style="margin:0 0 16px 0;font-size:1.1em;opacity:0.9;"><i class="fas fa-check"></i> Validés</h3>
                    <p class="stat-value" data-target="<?= $stats['valides'] ?>" style="margin:0;font-size:2.2em;font-weight:700;">0</p>
                </div>
                <div class="stat-card stat-red" style="background:linear-gradient(135deg,#e74c3c,#c0392b);border-radius:16px;padding:24px;color:#fff;box-shadow:0 4px 20px rgba(231,76,60,0.15);transition:transform 0.2s;">
                    <h3 style="margin:0 0 16px 0;font-size:1.1em;opacity:0.9;"><i class="fas fa-times"></i> Rejetés</h3>
                    <p class="stat-value" data-target="<?= $stats['rejetes'] ?>" style="margin:0;font-size:2.2em;font-weight:700;">0</p>
                </div>
                <div class="stat-card stat-grey" style="background:linear-gradient(135deg,#636e72,#2d3436);border-radius:16px;padding:24px;color:#fff;box-shadow:0 4px 20px rgba(99,110,114,0.15);transition:transform 0.2s;">
                    <h3 style="margin:0 0 16px 0;font-size:1.1em;opacity:0.9;"><i class="fas fa-archive"></i> Archivés</h3>
                    <p class="stat-value" data-target="<?= $stats['archives'] ?>" style="margin:0;font-size:2.2em;font-weight:700;">0</p>
                </div>
            </div>
            <section class="recent-dossiers dossier-section">
                <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
                    <h2 class="section-title" style="color:#2c3e50;font-size:1.8em;margin:0;display:flex;align-items:center;gap:12px;font-weight:600;">
                        <div class="icon-wrapper" style="background:linear-gradient(135deg,#667eea,#764ba2);padding:12px;border-radius:12px;color:white;">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        Dossiers récents
                    </h2>
                    <a href="modules/dossiers/list.php" class="btn-view-all" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 20px;border-radius:25px;text-decoration:none;font-weight:500;transition:all 0.3s;box-shadow:0 4px 15px rgba(102,126,234,0.3);">
                        <i class="fas fa-list"></i> Voir tous
                    </a>
                </div>
                
                <div class="dossier-table-container" style="background:white;border-radius:20px;box-shadow:0 10px 40px rgba(0,0,0,0.1);overflow:hidden;border:1px solid #e8ecf3;">
                    <?php if (count($recentDossiers) > 0): ?>
                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="dossier-table-modern" style="width:100%;border-collapse:collapse;background:white;">
                            <thead>
                                <tr style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;">
                                    <th style="padding:20px 24px;text-align:left;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-hashtag" style="margin-right:8px;opacity:0.8;"></i>Référence
                                    </th>
                                    <th style="padding:20px 24px;text-align:left;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-file-alt" style="margin-right:8px;opacity:0.8;"></i>Titre
                                    </th>
                                    <th style="padding:20px 24px;text-align:center;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-info-circle" style="margin-right:8px;opacity:0.8;"></i>Statut
                                    </th>
                                    <th style="padding:20px 24px;text-align:center;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-flag" style="margin-right:8px;opacity:0.8;"></i>Priorité
                                    </th>
                                    <th style="padding:20px 24px;text-align:left;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-user" style="margin-right:8px;opacity:0.8;"></i>Responsable
                                    </th>
                                    <th style="padding:20px 24px;text-align:center;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-calendar-plus" style="margin-right:8px;opacity:0.8;"></i>Création
                                    </th>
                                    <th style="padding:20px 24px;text-align:center;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-clock" style="margin-right:8px;opacity:0.8;"></i>Échéance
                                    </th>
                                    <th style="padding:20px 24px;text-align:center;font-weight:600;font-size:0.95em;letter-spacing:0.5px;text-transform:uppercase;border:none;">
                                        <i class="fas fa-cogs" style="margin-right:8px;opacity:0.8;"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDossiers as $index => $dossier): ?>
                                <tr class="table-row-hover" style="border-bottom:1px solid #f1f5f9;transition:all 0.3s ease;animation:fadeInUp 0.6s ease forwards;animation-delay:<?= $index * 0.1 ?>s;opacity:0;transform:translateY(20px);">
                                    <td style="padding:20px 24px;vertical-align:middle;">
                                        <div class="reference-cell" style="display:flex;align-items:center;gap:12px;">
                                            <div class="ref-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:0.9em;flex-shrink:0;">
                                                <?= strtoupper(substr($dossier['reference'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="ref-number" style="font-weight:600;color:#2c3e50;font-size:1em;"><?= htmlspecialchars($dossier['reference']) ?></div>
                                                <div class="ref-type" style="font-size:0.8em;color:#64748b;margin-top:2px;"><?= ucfirst($dossier['type'] ?? 'Standard') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:20px 24px;vertical-align:middle;">
                                        <div class="title-cell">
                                            <div class="title-main" style="font-weight:600;color:#2c3e50;font-size:1.05em;line-height:1.4;margin-bottom:4px;">
                                                <?= htmlspecialchars(strlen($dossier['titre']) > 50 ? substr($dossier['titre'], 0, 47) . '...' : $dossier['titre']) ?>
                                            </div>
                                            <?php if (!empty($dossier['service'])): ?>
                                            <div class="title-service" style="font-size:0.8em;color:#64748b;display:flex;align-items:center;gap:4px;">
                                                <i class="fas fa-building" style="opacity:0.7;"></i>
                                                <?= htmlspecialchars($dossier['service']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding:20px 24px;text-align:center;vertical-align:middle;">
                                        <?php
                                        $statusConfig = [
                                            'en_cours' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'icon' => 'fa-spinner', 'text' => 'En cours'],
                                            'valide' => ['color' => '#10b981', 'bg' => '#d1fae5', 'icon' => 'fa-check-circle', 'text' => 'Validé'],
                                            'rejete' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'icon' => 'fa-times-circle', 'text' => 'Rejeté'],
                                            'archive' => ['color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => 'fa-archive', 'text' => 'Archivé'],
                                            'brouillon' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => 'fa-edit', 'text' => 'Brouillon']
                                        ];
                                        $status = $statusConfig[$dossier['status']] ?? $statusConfig['en_cours'];
                                        ?>
                                        <span class="modern-badge" style="background:<?= $status['bg'] ?>;color:<?= $status['color'] ?>;padding:8px 16px;border-radius:20px;font-size:0.9em;font-weight:600;display:inline-flex;align-items:center;gap:6px;border:1px solid <?= $status['color'] ?>20;">
                                            <i class="fas <?= $status['icon'] ?>" style="font-size:0.8em;"></i>
                                            <?= $status['text'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:20px 24px;text-align:center;vertical-align:middle;">
                                        <?php
                                        $priorityConfig = [
                                            'haute' => ['color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-exclamation-triangle', 'text' => 'Haute'],
                                            'moyenne' => ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-minus', 'text' => 'Moyenne'],
                                            'basse' => ['color' => '#059669', 'bg' => '#ecfdf5', 'icon' => 'fa-arrow-down', 'text' => 'Basse'],
                                            'normale' => ['color' => '#6b7280', 'bg' => '#f9fafb', 'icon' => 'fa-equals', 'text' => 'Normale']
                                        ];
                                        $priority = $priorityConfig[$dossier['priority'] ?? 'normale'] ?? $priorityConfig['normale'];
                                        ?>
                                        <span class="priority-badge-modern" style="background:<?= $priority['bg'] ?>;color:<?= $priority['color'] ?>;padding:6px 12px;border-radius:15px;font-size:0.85em;font-weight:600;display:inline-flex;align-items:center;gap:4px;border:1px solid <?= $priority['color'] ?>30;">
                                            <i class="fas <?= $priority['icon'] ?>" style="font-size:0.75em;"></i>
                                            <?= $priority['text'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:20px 24px;vertical-align:middle;">
                                        <div class="user-info" style="display:flex;align-items:center;gap:10px;">
                                            <div class="user-avatar" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:0.9em;flex-shrink:0;">
                                                <?= strtoupper(substr($dossier['responsable_name'] ?? 'N/A', 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="user-name" style="font-weight:500;color:#2c3e50;font-size:0.95em;"><?= htmlspecialchars($dossier['responsable_name'] ?? 'Non assigné') ?></div>
                                                <div class="user-role" style="font-size:0.8em;color:#64748b;">Responsable</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:20px 24px;text-align:center;vertical-align:middle;">
                                        <div class="date-info">
                                            <div class="date-main" style="font-weight:500;color:#2c3e50;font-size:0.95em;">
                                                <?= date('d/m/Y', strtotime($dossier['created_at'])) ?>
                                            </div>
                                            <div class="date-relative" style="font-size:0.8em;color:#64748b;margin-top:2px;">
                                                <?php
                                                $diff = (new DateTime())->diff(new DateTime($dossier['created_at']));
                                                if ($diff->days == 0) echo "Aujourd'hui";
                                                elseif ($diff->days == 1) echo "Hier";
                                                else echo "Il y a " . $diff->days . " jours";
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:20px 24px;text-align:center;vertical-align:middle;">
                                        <?php if (!empty($dossier['deadline'])): ?>
                                            <?php
                                            $deadlineDate = new DateTime($dossier['deadline']);
                                            $today = new DateTime();
                                            $diffDays = $today->diff($deadlineDate)->days;
                                            $isExpired = $deadlineDate < $today;
                                            $isUrgent = $diffDays <= 3 && !$isExpired;
                                            $isWarning = $diffDays <= 7 && !$isExpired;
                                            
                                            if ($isExpired) {
                                                $deadlineClass = "color:#dc2626;background:#fef2f2;border:1px solid #dc262630;";
                                                $icon = "fa-exclamation-triangle";
                                            } elseif ($isUrgent) {
                                                $deadlineClass = "color:#f59e0b;background:#fffbeb;border:1px solid #f59e0b30;";
                                                $icon = "fa-clock";
                                            } elseif ($isWarning) {
                                                $deadlineClass = "color:#f59e0b;background:#fffbeb;border:1px solid #f59e0b30;";
                                                $icon = "fa-clock";
                                            } else {
                                                $deadlineClass = "color:#059669;background:#ecfdf5;border:1px solid #05966930;";
                                                $icon = "fa-calendar-check";
                                            }
                                            ?>
                                            <div class="deadline-info" style="<?= $deadlineClass ?>padding:8px 12px;border-radius:12px;font-size:0.9em;font-weight:500;">
                                                <div style="display:flex;align-items:center;justify-content:center;gap:4px;margin-bottom:2px;">
                                                    <i class="fas <?= $icon ?>" style="font-size:0.8em;"></i>
                                                    <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                                </div>
                                                <div style="font-size:0.8em;opacity:0.8;">
                                                    <?php
                                                    if ($isExpired) echo "Expiré";
                                                    elseif ($diffDays == 0) echo "Aujourd'hui";
                                                    elseif ($diffDays == 1) echo "Demain";
                                                    else echo "Dans " . $diffDays . " jours";
                                                    ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-size:0.9em;font-style:italic;">
                                                <i class="fas fa-minus" style="margin-right:4px;"></i>
                                                Aucune échéance
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:20px 24px;text-align:center;vertical-align:middle;">
                                        <div class="action-buttons" style="display:flex;gap:8px;justify-content:center;">
                                            <a href="modules/dossiers/view.php?id=<?= $dossier['id'] ?>" 
                                               class="action-btn view-btn" 
                                               style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 12px;border-radius:10px;text-decoration:none;transition:all 0.3s;box-shadow:0 4px 12px rgba(102,126,234,0.3);display:flex;align-items:center;justify-content:center;"
                                               title="Voir le dossier">
                                                <i class="fas fa-eye" style="font-size:0.9em;"></i>
                                            </a>
                                            <a href="modules/dossiers/edit.php?id=<?= $dossier['id'] ?>" 
                                               class="action-btn edit-btn" 
                                               style="background:linear-gradient(135deg,#10b981,#059669);color:white;padding:10px 12px;border-radius:10px;text-decoration:none;transition:all 0.3s;box-shadow:0 4px 12px rgba(16,185,129,0.3);display:flex;align-items:center;justify-content:center;"
                                               title="Modifier le dossier">
                                                <i class="fas fa-edit" style="font-size:0.9em;"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="text-align:center;padding:60px 20px;color:#64748b;">
                        <div class="empty-icon" style="background:#f1f5f9;width:80px;height:80px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;">
                            <i class="fas fa-folder-open" style="font-size:2em;color:#94a3b8;"></i>
                        </div>
                        <h3 style="color:#475569;margin:0 0 8px 0;font-size:1.2em;">Aucun dossier récent</h3>
                        <p style="margin:0;font-size:0.95em;">Les dossiers récemment créés apparaîtront ici.</p>
                        <a href="modules/dossiers/create.php" class="btn-create-first" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:12px 24px;border-radius:25px;text-decoration:none;font-weight:500;margin-top:20px;display:inline-block;transition:all 0.3s;">
                            <i class="fas fa-plus" style="margin-right:8px;"></i>Créer le premier dossier
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
        <style>
        /* Animations */
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

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Cartes statistiques */
        .stat-card:hover { 
            transform: translateY(-8px) scale(1.02); 
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card-feedback {
            animation: pulse 0.4s ease-in-out;
        }

        /* En-tête de section */
        .btn-view-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }

        /* Tableau moderne */
        .table-row-hover:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9) !important;
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .dossier-table-modern thead tr {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Badges modernisés */
        .modern-badge {
            transition: all 0.3s ease;
            cursor: default;
        }

        .modern-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .priority-badge-modern {
            transition: all 0.3s ease;
            cursor: default;
        }

        .priority-badge-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Cellule de référence */
        .reference-cell:hover .ref-icon {
            transform: rotate(5deg);
            transition: transform 0.3s ease;
        }

        /* Info utilisateur */
        .user-info:hover .user-avatar {
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }

        /* Boutons d'action */
        .action-btn {
            transition: all 0.3s ease;
        }

        .view-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }

        .edit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(16,185,129,0.4);
        }

        /* État vide */
        .empty-state .empty-icon {
            transition: all 0.3s ease;
        }

        .empty-state:hover .empty-icon {
            transform: scale(1.1);
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .empty-state:hover .empty-icon i {
            color: white !important;
        }

        .btn-create-first:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .stats-grid { 
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            }
            .dossier-table-modern th, 
            .dossier-table-modern td { 
                padding: 16px 12px !important;
                font-size: 0.9em !important;
            }
        }

        @media (max-width: 768px) {
            .stats-grid { 
                grid-template-columns: 1fr; 
            }
            
            .section-header {
                flex-direction: column !important;
                gap: 16px !important;
                align-items: flex-start !important;
            }
            
            .section-title {
                font-size: 1.4em !important;
            }
            
            .dossier-table-modern th, 
            .dossier-table-modern td { 
                padding: 12px 8px !important;
                font-size: 0.85em !important;
            }
            
            .reference-cell,
            .user-info {
                flex-direction: column !important;
                gap: 8px !important;
                text-align: center !important;
            }
            
            .action-buttons {
                flex-direction: column !important;
                gap: 4px !important;
            }
            
            .action-btn {
                padding: 8px 10px !important;
                font-size: 0.8em !important;
            }
        }

        @media (max-width: 576px) {
            .table-wrapper {
                font-size: 0.8em;
            }
            
            .dossier-table-modern th:nth-child(4),
            .dossier-table-modern td:nth-child(4),
            .dossier-table-modern th:nth-child(5),
            .dossier-table-modern td:nth-child(5) {
                display: none;
            }
            
            .title-main {
                font-size: 0.9em !important;
            }
            
            .modern-badge,
            .priority-badge-modern {
                font-size: 0.75em !important;
                padding: 4px 8px !important;
            }
        }

        /* Statuts et priorités legacy */
        .status-badge.status-en_cours { background:#2980b9;color:#fff; }
        .status-badge.status-valide { background:#27ae60;color:#fff; }
        .status-badge.status-rejete { background:#e74c3c;color:#fff; }
        .status-badge.status-archive { background:#636e72;color:#fff; }
        .priority-badge.priority-haute { background:#e74c3c;color:#fff; }
        .priority-badge.priority-moyenne { background:#f39c12;color:#fff; }
        .priority-badge.priority-basse { background:#27ae60;color:#fff; }
        .btn-view:hover { opacity:0.9;transform:translateY(-2px); }
        .deadline-expired { color:#e74c3c !important;font-weight:600 !important; }
        .deadline-urgent { color:#f39c12 !important;font-weight:600 !important; }
        .deadline-warning { color:#f1c40f !important;font-weight:600 !important; }
        </style>
        <?php include __DIR__ . '/includes/footer.php'; ?>
    </div>
    
    <script src="assets/js/script.js"></script>
    <script>
    // Animation compteur façon "chronomètre" sur les stats (quelques secondes)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.stat-value').forEach(function(el) {
            const target = +el.getAttribute('data-target');
            let count = 0;
            const duration = 2500; // 2,5 secondes
            const step = Math.max(1, Math.floor(target / (duration / 16)));
            function update() {
                count += step;
                if (count >= target) {
                    el.textContent = target;
                } else {
                    el.textContent = count;
                    requestAnimationFrame(update);
                }
            }
            update();
        });

        // Gestion du clic sur les stats : si pas de lien, feedback visuel
        document.querySelectorAll('.stat-card').forEach(function(card) {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function(e) {
                // Vérifie si le card contient un lien direct
                if (!card.querySelector('a')) {
                    card.classList.add('stat-card-feedback');
                    setTimeout(function() {
                        card.classList.remove('stat-card-feedback');
                    }, 400);
                }
            });
        });
    });
    </script>
</body>
</html>