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

<!-- Affichage du profil utilisateur -->
<div class="container">
    <div class="profile-header">
        <h1>Profil utilisateur</h1>
        <p>Gérez vos informations et accédez à vos dossiers récents</p>
    </div>

    <div class="profile-container">
        <div class="profile-card-modern">
            <div class="profile-avatar">
                <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            </div>
            <div class="profile-info">
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                <span class="role-badge role-<?= strtolower(getRoleName($user['role'])) ?>">
                    <i class="fas fa-shield-alt"></i> <?= getRoleName($user['role']) ?>
                </span>
            </div>
        </div>

        <div class="profile-section">
            <h3>
                <i class="fas fa-key"></i> Mes droits et permissions
            </h3>
            <div class="profile-permissions">
                <?php if ($user['role'] == ROLE_ADMIN): ?>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Accès total à toutes les fonctionnalités</li>
                        <li><i class="fas fa-users-cog"></i> Gestion des utilisateurs et des droits</li>
                        <li><i class="fas fa-edit"></i> Suppression et modification de tout dossier</li>
                    </ul>
                <?php elseif ($user['role'] == ROLE_GESTIONNAIRE): ?>
                    <ul>
                        <li><i class="fas fa-folder-plus"></i> Création et gestion des dossiers</li>
                        <li><i class="fas fa-paperclip"></i> Ajout/suppression de pièces jointes</li>
                        <li><i class="fas fa-random"></i> Gestion du workflow des dossiers dont il est responsable</li>
                    </ul>
                <?php elseif ($user['role'] == ROLE_CONSULTANT): ?>
                    <ul>
                        <li><i class="fas fa-eye"></i> Consultation des dossiers accessibles</li>
                        <li><i class="fas fa-comment"></i> Ajout de commentaires</li>
                        <li><i class="fas fa-download"></i> Téléchargement des pièces jointes autorisées</li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-section">
            <h3>
                <i class="fa fa-folder-open"></i> 
                Dossiers récents dont responsable
            </h3>

            <?php if ($userDossiers): ?>
                <div class="dossier-table-responsive">
                    <table class="dossier-table">
                        <thead>
                            <tr>
                                <th>Réf.</th>
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
                                        <i class="fa fa-hashtag"></i> <?= $dossier['reference'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span><?= htmlspecialchars($dossier['titre']) ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $dossier['status'] ?>">
                                        <i class="fa fa-circle"></i> <?= ucfirst($dossier['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?= $dossier['priority'] ?>">
                                        <i class="fa fa-flag"></i> <?= ucfirst($dossier['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span>
                                        <i class="fa fa-calendar"></i> <?= date('d/m/Y', strtotime($dossier['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="<?php if (!empty($dossier['deadline'])) { 
                                    $deadlineDate = new DateTime($dossier['deadline']); 
                                    $today = new DateTime(); 
                                    if ($deadlineDate < $today) { 
                                        echo 'deadline-expired'; 
                                    } elseif ($deadlineDate->diff($today)->days <= 3) { 
                                        echo 'deadline-urgent'; 
                                    } elseif ($deadlineDate->diff($today)->days <= 7) { 
                                        echo 'deadline-warning'; 
                                    } 
                                } ?>">
                                    <span>
                                        <i class="fa fa-clock"></i> <?= !empty($dossier['deadline']) ? date('d/m/Y', strtotime($dossier['deadline'])) : 'N/A' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>" 
                                           class="btn btn-view" 
                                           title="Voir">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <?php if (hasPermission(ROLE_GESTIONNAIRE)): ?>
                                        <a href="../dossiers/edit.php?id=<?= $dossier['id'] ?>" 
                                           class="btn btn-edit" 
                                           title="Modifier">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
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