<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

$userId = $_GET['id'] ?? $_SESSION['user_id'];
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    header("Location: /error.php?code=404");
    exit();
}

// Vérifier les permissions
if ($userId != $_SESSION['user_id'] && !hasPermission(ROLE_ADMIN)) {
    die("Accès non autorisé");
}

$userDossiers = fetchAll("SELECT * FROM dossiers WHERE responsable_id = ? ORDER BY created_at DESC LIMIT 5", [$userId]);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil utilisateur - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE PROFIL MODERNE === */
    .profile-page {
        background: linear-gradient(120deg, #f3f0ff 0%, #e0e7ff 100%);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 2.5rem;
    }
    
    .profile-card {
        background: rgba(255,255,255,0.85);
        border-radius: 24px;
        padding: 2.5rem 2rem;
        box-shadow: 0 8px 32px rgba(102,126,234,0.12);
        border: none;
        text-align: center;
        animation: slideInLeft 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .profile-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 8px;
        background: linear-gradient(90deg, #7f53ac 0%, #647dee 100%);
        border-radius: 24px 24px 0 0;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #7f53ac 0%, #647dee 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        font-weight: 700;
        margin: 0 auto 1.5rem;
        box-shadow: 0 4px 24px rgba(102,126,234,0.18);
        position: relative;
        animation: avatarFloat 3s ease-in-out infinite;
        border: 4px solid #e0e7ff;
    }
    
    .profile-name {
        font-size: 1.7rem;
        font-weight: 700;
        color: #4B2991;
        margin: 0 0 0.5rem 0;
        letter-spacing: 0.02em;
    }
    
    .profile-email {
        color: #647dee;
        font-size: 1.05rem;
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: rgba(100,125,238,0.07);
        border-radius: 8px;
        padding: 0.5rem 1rem;
    }
    
    .role-badge {
        padding: 0.5rem 1.2rem;
        border-radius: 999px;
        font-size: 0.95rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1rem;
        box-shadow: 0 2px 8px rgba(102,126,234,0.08);
        border: none;
    }
    
    .role-admin {
        background: linear-gradient(90deg, #ff758c 0%, #ff7eb3 100%);
        color: #fff;
    }
    .role-gestionnaire {
        background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
        color: #fff;
    }
    .role-consultant {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        color: #fff;
    }
    
    .profile-stats {
        background: rgba(255,255,255,0.85);
        border-radius: 24px;
        padding: 2rem 1.5rem;
        box-shadow: 0 8px 32px rgba(102,126,234,0.10);
        border: none;
        animation: slideInLeft 0.8s ease-out;
    }
    
    .profile-stats h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: #4B2991;
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .stats-grid {
        display: grid;
        gap: 1.2rem;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.85rem 1rem;
        background: rgba(100,125,238,0.07);
        border-radius: 16px;
        transition: box-shadow 0.3s, transform 0.3s;
        box-shadow: 0 2px 8px rgba(102,126,234,0.05);
    }
    
    .stat-item:hover {
        background: #e0e7ff;
        transform: translateX(5px);
        box-shadow: 0 4px 16px rgba(102,126,234,0.10);
    }
    
    .stat-label {
        color: #647dee;
        font-size: 0.95rem;
        font-weight: 600;
    }
    
    .stat-value {
        font-weight: 700;
        color: #4B2991;
        font-size: 1.15rem;
    }
    
    .profile-main {
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
    }
    
    .profile-section {
        background: rgba(255,255,255,0.85);
        border-radius: 24px;
        padding: 2.5rem 2rem;
        box-shadow: 0 8px 32px rgba(102,126,234,0.10);
        border: none;
        animation: slideInRight 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e0e7ff;
    }
    
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #4B2991;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0;
    }
    
    .permissions-grid {
        display: grid;
        gap: 1.2rem;
    }
    
    .permission-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.2rem 1rem;
        background: rgba(100,125,238,0.07);
        border-radius: 16px;
        border-left: 5px solid #43e97b;
        transition: box-shadow 0.3s, transform 0.3s;
        box-shadow: 0 2px 8px rgba(102,126,234,0.05);
    }
    
    .permission-item:hover {
        background: #e0e7ff;
        transform: translateX(5px);
        box-shadow: 0 4px 16px rgba(102,126,234,0.10);
    }
    
    .permission-text {
        color: #22223B;
        font-weight: 600;
        font-size: 1.05rem;
    }
    
    .dossiers-table th,
    .dossiers-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #e0e7ff;
    }
    
    .dossiers-table th {
        background: #e0e7ff;
        font-weight: 700;
        color: #4B2991;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
    }
    
    .dossiers-table td {
        color: #22223B;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .dossiers-table tbody tr:hover {
        background: #f3f0ff;
        transform: translateX(5px);
    }
    
    .ref-badge {
        background: linear-gradient(90deg, #7f53ac 0%, #647dee 100%);
        color: #fff;
        padding: 0.35rem 0.7rem;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    }
    
    .status-badge {
        padding: 0.45rem 1rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        border: none;
        box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    }
    
    .status-en_cours {
        background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
        color: #fff;
    }
    .status-valide {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        color: #fff;
    }
    .status-rejete {
        background: linear-gradient(90deg, #ff758c 0%, #ff7eb3 100%);
        color: #fff;
    }
    
    .priority-badge {
        padding: 0.35rem 0.7rem;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    }
    
    .priority-high {
        background: linear-gradient(90deg, #ff758c 0%, #ff7eb3 100%);
        color: #fff;
    }
    .priority-medium {
        background: linear-gradient(90deg, #ffd86a 0%, #fc6262 100%);
        color: #fff;
    }
    .priority-low {
        background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
        color: #fff;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.7rem;
    }
    
    .btn-sm {
        padding: 0.6rem 1.2rem;
        font-size: 0.95rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        transition: box-shadow 0.3s, transform 0.3s;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        border: none;
        cursor: pointer;
        background: linear-gradient(90deg, #7f53ac 0%, #647dee 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    }
    
    .btn-sm:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(102,126,234,0.18);
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #647dee;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #e0e7ff;
        margin-bottom: 1rem;
    }
    
    .empty-state h4 {
        font-size: 1.25rem;
        color: #4B2991;
        margin-bottom: 1rem;
    }
    
    /* Animations */
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 900px) {
        .profile-container {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .profile-card, .profile-section, .profile-stats {
            padding: 1.5rem;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            font-size: 2rem;
        }
        .dossiers-table {
            font-size: 0.95rem;
        }
        .dossiers-table th,
        .dossiers-table td {
            padding: 0.75rem 0.5rem;
        }
        .action-buttons {
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <div class="profile-page">
        <div class="profile-container">
            <!-- Sidebar profil -->
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    </div>
                    <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                    <p class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <span class="role-badge role-<?= strtolower(getRoleName($user['role'])) ?>">
                        <i class="fas fa-shield-alt"></i>
                        <?= getRoleName($user['role']) ?>
                    </span>
                </div>

                <div class="profile-stats">
                    <h3>
                        <i class="fas fa-chart-bar"></i>
                        Statistiques
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-label">Dossiers créés</span>
                            <span class="stat-value"><?= count($userDossiers) ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Dernière connexion</span>
                            <span class="stat-value">Aujourd'hui</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Compte actif depuis</span>
                            <span class="stat-value"><?= date('Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="profile-main">
                <!-- Permissions -->
                <div class="profile-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-key"></i>
                            Droits et permissions
                        </h3>
                    </div>
                    
                    <div class="permissions-grid">
                        <?php if ($user['role'] == ROLE_ADMIN): ?>
                            <div class="permission-item">
                                <i class="fas fa-check-circle"></i>
                                <span class="permission-text">Accès total à toutes les fonctionnalités</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-users-cog"></i>
                                <span class="permission-text">Gestion des utilisateurs et des droits</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-edit"></i>
                                <span class="permission-text">Suppression et modification de tout dossier</span>
                            </div>
                        <?php elseif ($user['role'] == ROLE_GESTIONNAIRE): ?>
                            <div class="permission-item">
                                <i class="fas fa-folder-plus"></i>
                                <span class="permission-text">Création et gestion des dossiers</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-paperclip"></i>
                                <span class="permission-text">Ajout/suppression de pièces jointes</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-random"></i>
                                <span class="permission-text">Gestion du workflow des dossiers dont il est responsable</span>
                            </div>
                        <?php elseif ($user['role'] == ROLE_CONSULTANT): ?>
                            <div class="permission-item">
                                <i class="fas fa-eye"></i>
                                <span class="permission-text">Consultation des dossiers accessibles</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-comment"></i>
                                <span class="permission-text">Ajout de commentaires</span>
                            </div>
                            <div class="permission-item">
                                <i class="fas fa-download"></i>
                                <span class="permission-text">Téléchargement des pièces jointes autorisées</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dossiers récents -->
                <div class="profile-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-folder-open"></i>
                            Dossiers récents dont responsable
                        </h3>
                        <a href="<?= BASE_URL ?>modules/dossiers/list.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-list"></i>
                            Voir tout
                        </a>
                    </div>

                    <?php if ($userDossiers): ?>
                        <div class="table-responsive">
                            <table class="dossiers-table">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Titre</th>
                                        <th>Statut</th>
                                        <th>Priorité</th>
                                        <th>Créé le</th>
                                        <th>Échéance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userDossiers as $dossier): ?>
                                    <tr>
                                        <td>
                                            <span class="ref-badge">
                                                <i class="fas fa-hashtag"></i>
                                                <?= htmlspecialchars($dossier['reference']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($dossier['titre']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $dossier['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $dossier['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($dossier['priority']): ?>
                                                <span class="priority-badge priority-<?= $dossier['priority'] ?>">
                                                    <?= ucfirst($dossier['priority']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($dossier['created_at'])) ?></td>
                                        <td>
                                            <?php if ($dossier['deadline']): ?>
                                                <?= date('d/m/Y', strtotime($dossier['deadline'])) ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="<?= BASE_URL ?>modules/dossiers/view.php?id=<?= $dossier['id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                    Voir
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h4>Aucun dossier assigné</h4>
                            <p>Vous n'êtes responsable d'aucun dossier pour le moment</p>
                            <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                                <a href="<?= BASE_URL ?>modules/dossiers/create.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i>
                                    Créer un dossier
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des stats
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach((stat, index) => {
                stat.style.opacity = '0';
                setTimeout(() => {
                    stat.style.transition = 'opacity 0.5s ease';
                    stat.style.opacity = '1';
                }, index * 100);
            });
        });
    </script>

                            <!-- ...existing code for table rows... -->
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-dossiers">
                    <i class="fas fa-folder-open"></i>
                    <p>Aucun dossier récent.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>