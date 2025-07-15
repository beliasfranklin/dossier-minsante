<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireAuth();

$pageTitle = "Sécurité - " . t('app_name');
$userId = $_SESSION['user_id'];

// Traitement du changement de mot de passe
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $passwordError = "Le mot de passe doit contenir au moins 8 caractères";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "Les mots de passe ne correspondent pas";
    } else {
        try {
            // Vérifier l'ancien mot de passe
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user && password_verify($currentPassword . PEPPER, $user['password_hash'])) {
                // Hasher le nouveau mot de passe
                $newPasswordHash = password_hash($newPassword . PEPPER, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newPasswordHash, $userId]);

                $passwordSuccess = "Mot de passe modifié avec succès !";
            } else {
                $passwordError = "Mot de passe actuel incorrect";
            }
        } catch (PDOException $e) {
            $passwordError = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// Récupération des informations de sécurité
try {
    $stmt = $pdo->prepare("
        SELECT email, created_at, updated_at, last_login,
               (SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND expires_at > NOW()) as active_sessions
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId, $userId]);
    $securityInfo = $stmt->fetch();
} catch (PDOException $e) {
    $securityInfo = [];
}
?>

<div class="page-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-shield-alt"></i>
            Sécurité
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            Gérez la sécurité de votre compte
        </p>
    </div>
</div>

<div class="container">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
        
        <!-- Changement de mot de passe -->
        <div class="security-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #ffebee); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-key" style="color: #e74c3c;"></i>
                    Mot de passe
                </h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <?php if (isset($passwordSuccess)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #27ae60;">
                        <i class="fas fa-check-circle"></i> <?= $passwordSuccess ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($passwordError)): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i> <?= $passwordError ?>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-lock" style="margin-right: 6px;"></i>
                        Mot de passe actuel
                    </label>
                    <input type="password" name="current_password" required
                           style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-lock" style="margin-right: 6px;"></i>
                        Nouveau mot de passe
                    </label>
                    <input type="password" name="new_password" required minlength="8"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                    <small style="color: #7f8c8d; margin-top: 4px; display: block;">
                        Minimum 8 caractères avec majuscules, minuscules et chiffres
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-lock" style="margin-right: 6px;"></i>
                        Confirmer le mot de passe
                    </label>
                    <input type="password" name="confirm_password" required
                           style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                </div>

                <button type="submit" name="change_password" 
                        style="width: 100%; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Changer le mot de passe
                </button>
            </form>
        </div>

        <!-- Informations de sécurité -->
        <div class="security-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-info-circle" style="color: #3498db;"></i>
                    Informations de sécurité
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f4f8;">
                    <div>
                        <div style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-envelope" style="color: #3498db; margin-right: 8px;"></i>
                            Email
                        </div>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($securityInfo['email'] ?? 'Non défini') ?></small>
                    </div>
                    <span style="background: #e8f4fd; color: #2980b9; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500;">
                        Vérifié
                    </span>
                </div>

                <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f4f8;">
                    <div>
                        <div style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-calendar-plus" style="color: #27ae60; margin-right: 8px;"></i>
                            Compte créé
                        </div>
                        <small style="color: #7f8c8d;">
                            <?= $securityInfo['created_at'] ? date('d/m/Y à H:i', strtotime($securityInfo['created_at'])) : 'Non défini' ?>
                        </small>
                    </div>
                </div>

                <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f4f8;">
                    <div>
                        <div style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-clock" style="color: #f39c12; margin-right: 8px;"></i>
                            Dernière connexion
                        </div>
                        <small style="color: #7f8c8d;">
                            <?= $securityInfo['last_login'] ? date('d/m/Y à H:i', strtotime($securityInfo['last_login'])) : 'Inconnue' ?>
                        </small>
                    </div>
                </div>

                <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                    <div>
                        <div style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-desktop" style="color: #9b59b6; margin-right: 8px;"></i>
                            Sessions actives
                        </div>
                        <small style="color: #7f8c8d;">Appareils connectés actuellement</small>
                    </div>
                    <span style="background: #e8f5e8; color: #27ae60; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 500;">
                        <?= $securityInfo['active_sessions'] ?? 1 ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Authentification à deux facteurs -->
        <div class="security-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #fff3e0); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-mobile-alt" style="color: #f39c12;"></i>
                    Authentification à deux facteurs
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="background: #fff8e1; padding: 1rem; border-radius: 8px; border-left: 4px solid #f39c12; margin-bottom: 1rem;">
                    <p style="margin: 0; color: #f57f17; font-weight: 500;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Fonctionnalité en cours de développement
                    </p>
                </div>
                
                <p style="color: #7f8c8d; margin-bottom: 1.5rem;">
                    L'authentification à deux facteurs ajoute une couche de sécurité supplémentaire à votre compte.
                </p>

                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 2px solid #e1e8ed;">
                    <i class="fas fa-shield-alt" style="color: #95a5a6; font-size: 2rem;"></i>
                    <div>
                        <div style="font-weight: 600; color: #7f8c8d;">2FA non configuré</div>
                        <small style="color: #95a5a6;">Sera disponible prochainement</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Journal de sécurité -->
        <div class="security-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #f3e5f5); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-history" style="color: #9b59b6;"></i>
                    Activité récente
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT action, created_at, ip_address, user_agent 
                        FROM user_activity_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$userId]);
                    $activities = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $activities = [];
                }
                ?>

                <?php if (!empty($activities)): ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0f4f8;">
                            <div style="width: 40px; height: 40px; background: #e8f4fd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-check" style="color: #3498db;"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #2c3e50;"><?= htmlspecialchars($activity['action']) ?></div>
                                <div style="color: #7f8c8d; font-size: 0.9em;">
                                    <?= date('d/m/Y à H:i', strtotime($activity['created_at'])) ?>
                                    <?php if ($activity['ip_address']): ?>
                                        • IP: <?= htmlspecialchars($activity['ip_address']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="<?= BASE_URL ?>modules/users/activity.php" 
                           style="color: #9b59b6; text-decoration: none; font-weight: 500;">
                            <i class="fas fa-arrow-right"></i> Voir toute l'activité
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #7f8c8d;">
                        <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p style="margin: 0;">Aucune activité récente disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions de sécurité -->
    <div class="security-actions" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 2rem; padding: 2rem;">
        <h3 style="color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-tools" style="color: #e67e22;"></i>
            Actions de sécurité
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <button onclick="alert('Fonctionnalité en développement')" 
                    style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-sign-out-alt" style="font-size: 1.5rem;"></i>
                <div style="text-align: left;">
                    <div style="font-weight: 600;">Déconnecter partout</div>
                    <small style="opacity: 0.9;">Fermer toutes les sessions</small>
                </div>
            </button>

            <button onclick="alert('Fonctionnalité en développement')" 
                    style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-download" style="font-size: 1.5rem;"></i>
                <div style="text-align: left;">
                    <div style="font-weight: 600;">Exporter données</div>
                    <small style="opacity: 0.9;">RGPD - Mes données</small>
                </div>
            </button>

            <button onclick="if(confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.')) alert('Fonctionnalité en développement')" 
                    style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border: none; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-trash-alt" style="font-size: 1.5rem;"></i>
                <div style="text-align: left;">
                    <div style="font-weight: 600;">Supprimer compte</div>
                    <small style="opacity: 0.9;">Action irréversible</small>
                </div>
            </button>
        </div>
    </div>
</div>

<style>
.security-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
}

input:focus {
    border-color: #e74c3c !important;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1) !important;
    outline: none !important;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
</style>

<?php require_once '../../includes/footer.php'; ?>
