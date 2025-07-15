<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireAuth();

$pageTitle = "Préférences Utilisateur - " . t('app_name');
$userId = $_SESSION['user_id'];

// Traitement du formulaire
if ($_POST) {
    try {
        $preferences = [
            'theme' => $_POST['theme'] ?? 'light',
            'language' => $_POST['language'] ?? 'fr',
            'notifications_email' => isset($_POST['notifications_email']) ? 1 : 0,
            'notifications_browser' => isset($_POST['notifications_browser']) ? 1 : 0,
            'dashboard_layout' => $_POST['dashboard_layout'] ?? 'grid',
            'timezone' => $_POST['timezone'] ?? 'Africa/Douala',
            'items_per_page' => (int)($_POST['items_per_page'] ?? 20)
        ];

        foreach ($preferences as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
            ");
            $stmt->execute([$userId, $key, $value]);
        }

        $success = "Préférences mises à jour avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération des préférences actuelles
$preferences = [];
try {
    $stmt = $pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch()) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
} catch (PDOException $e) {
    // Valeurs par défaut
    $preferences = [
        'theme' => 'light',
        'language' => 'fr',
        'notifications_email' => 1,
        'notifications_browser' => 1,
        'dashboard_layout' => 'grid',
        'timezone' => 'Africa/Douala',
        'items_per_page' => 20
    ];
}
?>

<div class="page-header" style="background: linear-gradient(135deg, #16a085, #138d75); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-cog"></i>
            Préférences
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            Personnalisez votre expérience utilisateur
        </p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid #27ae60;">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid #e74c3c;">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="preferences-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
        
        <!-- Apparence -->
        <div class="preference-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-palette" style="color: #9b59b6;"></i>
                    Apparence
                </h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-moon" style="margin-right: 6px;"></i>
                        Thème
                    </label>
                    <select name="theme" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                        <option value="light" <?= ($preferences['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>🌞 Clair</option>
                        <option value="dark" <?= ($preferences['theme'] ?? 'light') === 'dark' ? 'selected' : '' ?>>🌙 Sombre</option>
                        <option value="auto" <?= ($preferences['theme'] ?? 'light') === 'auto' ? 'selected' : '' ?>>🔄 Automatique</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-th" style="margin-right: 6px;"></i>
                        Disposition du tableau de bord
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; cursor: pointer; transition: all 0.2s;" onclick="this.style.borderColor='#16a085'">
                            <input type="radio" name="dashboard_layout" value="grid" <?= ($preferences['dashboard_layout'] ?? 'grid') === 'grid' ? 'checked' : '' ?> style="accent-color: #16a085;">
                            <span>📊 Grille</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; cursor: pointer; transition: all 0.2s;" onclick="this.style.borderColor='#16a085'">
                            <input type="radio" name="dashboard_layout" value="list" <?= ($preferences['dashboard_layout'] ?? 'grid') === 'list' ? 'checked' : '' ?> style="accent-color: #16a085;">
                            <span>📋 Liste</span>
                        </label>
                    </div>
                </div>

                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #16a085, #138d75); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>

        <!-- Localisation -->
        <div class="preference-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-globe" style="color: #3498db;"></i>
                    Localisation
                </h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-language" style="margin-right: 6px;"></i>
                        Langue
                    </label>
                    <select name="language" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                        <option value="fr" <?= ($preferences['language'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>🇫🇷 Français</option>
                        <option value="en" <?= ($preferences['language'] ?? 'fr') === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-clock" style="margin-right: 6px;"></i>
                        Fuseau horaire
                    </label>
                    <select name="timezone" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                        <option value="Africa/Douala" <?= ($preferences['timezone'] ?? 'Africa/Douala') === 'Africa/Douala' ? 'selected' : '' ?>>🇨🇲 Douala (GMT+1)</option>
                        <option value="Europe/Paris" <?= ($preferences['timezone'] ?? 'Africa/Douala') === 'Europe/Paris' ? 'selected' : '' ?>>🇫🇷 Paris (GMT+1/+2)</option>
                        <option value="UTC" <?= ($preferences['timezone'] ?? 'Africa/Douala') === 'UTC' ? 'selected' : '' ?>>🌍 UTC (GMT+0)</option>
                    </select>
                </div>

                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>

        <!-- Notifications -->
        <div class="preference-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-bell" style="color: #f39c12;"></i>
                    Notifications
                </h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: #f8fafc; border-radius: 8px; border: 2px solid #e1e8ed;">
                        <input type="checkbox" name="notifications_email" value="1" 
                               <?= ($preferences['notifications_email'] ?? 1) ? 'checked' : '' ?>
                               style="width: 20px; height: 20px; accent-color: #f39c12;">
                        <div>
                            <div style="font-weight: 600; color: #2c3e50;">
                                <i class="fas fa-envelope" style="color: #f39c12; margin-right: 6px;"></i>
                                Notifications par email
                            </div>
                            <small style="color: #7f8c8d;">Recevoir les alertes importantes par email</small>
                        </div>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: #f8fafc; border-radius: 8px; border: 2px solid #e1e8ed;">
                        <input type="checkbox" name="notifications_browser" value="1" 
                               <?= ($preferences['notifications_browser'] ?? 1) ? 'checked' : '' ?>
                               style="width: 20px; height: 20px; accent-color: #f39c12;">
                        <div>
                            <div style="font-weight: 600; color: #2c3e50;">
                                <i class="fas fa-desktop" style="color: #f39c12; margin-right: 6px;"></i>
                                Notifications navigateur
                            </div>
                            <small style="color: #7f8c8d;">Afficher les notifications dans le navigateur</small>
                        </div>
                    </label>
                </div>

                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>

        <!-- Affichage -->
        <div class="preference-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-list" style="color: #27ae60;"></i>
                    Affichage
                </h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-sort-numeric-down" style="margin-right: 6px;"></i>
                        Éléments par page
                    </label>
                    <select name="items_per_page" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem;">
                        <option value="10" <?= ($preferences['items_per_page'] ?? 20) == 10 ? 'selected' : '' ?>>10 éléments</option>
                        <option value="20" <?= ($preferences['items_per_page'] ?? 20) == 20 ? 'selected' : '' ?>>20 éléments</option>
                        <option value="50" <?= ($preferences['items_per_page'] ?? 20) == 50 ? 'selected' : '' ?>>50 éléments</option>
                        <option value="100" <?= ($preferences['items_per_page'] ?? 20) == 100 ? 'selected' : '' ?>>100 éléments</option>
                    </select>
                </div>

                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #27ae60, #229954); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="quick-actions" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 2rem; padding: 2rem;">
        <h3 style="color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-bolt" style="color: #f39c12;"></i>
            Actions Rapides
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="<?= BASE_URL ?>modules/users/profile.php" 
               style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #16a085, #138d75); color: white; text-decoration: none; border-radius: 12px; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-user-edit" style="font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: 600;">Mon Profil</div>
                    <small style="opacity: 0.9;">Modifier mes informations</small>
                </div>
            </a>

            <a href="<?= BASE_URL ?>modules/users/security.php" 
               style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; text-decoration: none; border-radius: 12px; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-shield-alt" style="font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: 600;">Sécurité</div>
                    <small style="opacity: 0.9;">Mot de passe & 2FA</small>
                </div>
            </a>

            <a href="<?= BASE_URL ?>modules/users/activity.php" 
               style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #3498db, #2980b9); color: white; text-decoration: none; border-radius: 12px; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-history" style="font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: 600;">Activité</div>
                    <small style="opacity: 0.9;">Historique des actions</small>
                </div>
            </a>

            <a href="<?= BASE_URL ?>logout.php" 
               onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')"
               style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; text-decoration: none; border-radius: 12px; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-sign-out-alt" style="font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: 600;">Déconnexion</div>
                    <small style="opacity: 0.9;">Quitter l'application</small>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
.preference-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
}

input:focus, select:focus {
    border-color: #16a085 !important;
    box-shadow: 0 0 0 3px rgba(22, 160, 133, 0.1) !important;
    outline: none !important;
}

.checkbox-label:hover {
    border-color: #16a085 !important;
    background: #e8f8f5 !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
