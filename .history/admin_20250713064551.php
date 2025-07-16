<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

// Vérification des droits admin
if (!hasPermission(ROLE_ADMIN)) {
    header("Location: /error.php?code=403");
    exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_role'])) {
        $userId = (int)$_POST['user_id'];
        $newRole = (int)$_POST['new_role'];
        
        // Validation du rôle
        $allowedRoles = [ROLE_ADMIN, ROLE_GESTIONNAIRE, ROLE_CONSULTANT];
        if (in_array($newRole, $allowedRoles)) {
            executeQuery("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId]);
            $_SESSION['flash']['success'] = "Rôle mis à jour avec succès";
        }
    }
    
    if (isset($_POST['create_user'])) {
        // Validation des données
        $name = cleanInput($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $role = (int)$_POST['role'];
        $tempPassword = bin2hex(random_bytes(4)); // Mot de passe temporaire
        
        if ($email) {
            $hashedPassword = generateSecurePassword($tempPassword);
            executeQuery(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)",
                [$name, $email, $hashedPassword, $role]
            );
            
            // Envoyer le mot de passe par email (à implémenter)
            $_SESSION['flash']['success'] = "Utilisateur créé. Mot de passe temporaire : $tempPassword";
        }
    }
}

// Récupération des utilisateurs
$users = fetchAll("SELECT id, name, email, role FROM users ORDER BY name");
$roles = [
    ROLE_ADMIN => "Administrateur",
    ROLE_GESTIONNAIRE => "Gestionnaire",
    ROLE_CONSULTANT => "Consultant"
];

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="admin-container" style="max-width:1200px;margin:32px auto;padding:0 24px;">
    <div class="admin-header" style="background:linear-gradient(135deg,#fff,#f8fafc);border-radius:16px;padding:32px;box-shadow:0 4px 20px rgba(41,128,185,0.1);display:flex;align-items:center;gap:30px;margin-bottom:32px;">
        <div class="header-icon" style="background:linear-gradient(135deg,#2980b9,#6dd5fa);padding:24px;border-radius:50%;box-shadow:0 4px 20px rgba(41,128,185,0.2);">
            <i class="fas fa-users-cog" style="font-size:42px;color:#fff;"></i>
        </div>
        <div>
            <h1 style="color:#2980b9;font-size:2.2em;margin:0 0 8px 0;"><?= t('user_management') ?></h1>
            <div style="color:#636e72;font-size:1.1em;"><?= t('user_management_desc') ?></div>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
            <?php 
            $bgColor = $type === 'success' ? '#e8f8f5' : ($type === 'warning' ? '#fef5e7' : '#fde9e8');
            $textColor = $type === 'success' ? '#27ae60' : ($type === 'warning' ? '#f39c12' : '#e74c3c');
            $icon = $type === 'success' ? 'check-circle' : ($type === 'warning' ? 'exclamation-circle' : 'times-circle');
            ?>
            <div style="background:<?= $bgColor ?>;color:<?= $textColor ?>;padding:16px 24px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;animation:slideIn 0.3s ease-out;">
                <i class="fas fa-<?= $icon ?>" style="font-size:24px;"></i>
                <div style="flex:1;"><?= htmlspecialchars(is_array($msg) ? implode('<br>', $msg) : $msg) ?></div>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Outils d'Administration Système -->
    <section class="admin-tools" style="background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(41,128,185,0.1);padding:32px;margin-bottom:32px;">
        <h2 style="color:#2980b9;font-size:1.5em;margin:0 0 24px 0;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-tools"></i> <?= t('system_stats') ?>
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
            
            <!-- Configuration Email -->
            <div style="background:linear-gradient(135deg,#e3f2fd,#f3e5f5);border-radius:12px;padding:20px;border:1px solid #e1bee7;transition:transform 0.3s ease;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="background:linear-gradient(135deg,#2196f3,#9c27b0);padding:12px;border-radius:50%;color:#fff;">
                        <i class="fas fa-envelope-open-text" style="font-size:20px;"></i>
                    </div>
                    <h3 style="color:#2980b9;margin:0;font-size:1.2em;">Configuration Email</h3>
                </div>
                <p style="color:#636e72;margin-bottom:16px;font-size:0.95em;">Configurez et testez les paramètres SMTP pour l'envoi d'emails automatiques.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="setup_email_quickstart.php" style="background:#4caf50;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-rocket"></i> Démarrage Rapide
                    </a>
                    <a href="setup_email_advanced.php" style="background:#2196f3;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-cog"></i> Configuration
                    </a>
                    <a href="test_email_config.php" style="background:#ff9800;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-stethoscope"></i> Diagnostic
                    </a>
                </div>
            </div>

            <!-- Installation PHPMailer -->
            <div style="background:linear-gradient(135deg,#fff3e0,#fce4ec);border-radius:12px;padding:20px;border:1px solid #f8bbd9;transition:transform 0.3s ease;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="background:linear-gradient(135deg,#ff9800,#e91e63);padding:12px;border-radius:50%;color:#fff;">
                        <i class="fas fa-download" style="font-size:20px;"></i>
                    </div>
                    <h3 style="color:#2980b9;margin:0;font-size:1.2em;">PHPMailer</h3>
                </div>
                <p style="color:#636e72;margin-bottom:16px;font-size:0.95em;">Installez PHPMailer pour un envoi d'emails plus robuste et sécurisé.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="install_phpmailer.php" style="background:#ff9800;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-download"></i> Installer
                    </a>
                    <a href="docs/EMAIL_CONFIGURATION.md" target="_blank" style="background:#9c27b0;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-book"></i> Documentation
                    </a>
                </div>
            </div>

            <!-- Gestion des Logs -->
            <div style="background:linear-gradient(135deg,#f1f8e9,#fff8e1);border-radius:12px;padding:20px;border:1px solid #dcedc1;transition:transform 0.3s ease;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="background:linear-gradient(135deg,#8bc34a,#ffc107);padding:12px;border-radius:50%;color:#fff;">
                        <i class="fas fa-database" style="font-size:20px;"></i>
                    </div>
                    <h3 style="color:#2980b9;margin:0;font-size:1.2em;">Base de Données</h3>
                </div>
                <p style="color:#636e72;margin-bottom:16px;font-size:0.95em;">Testez la connexion et consultez les logs du système.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="test_db_connection.php" style="background:#8bc34a;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-check-circle"></i> Test DB
                    </a>
                    <a href="test_db_auth_fix.php" style="background:#17a2b8;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-cogs"></i> Test Auth/DB
                    </a>
                    <a href="logs/error.log" target="_blank" style="background:#ffc107;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-eye"></i> Voir Logs
                    </a>
                    <a href="repair_permissions.php" style="background:#ff9800;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-wrench"></i> Permissions
                    </a>
                    <a href="repair_auth.php" style="background:#dc3545;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.9em;display:flex;align-items:center;gap:6px;transition:background 0.3s ease;">
                        <i class="fas fa-shield-alt"></i> Auth/Session
                    </a>
                </div>
            </div>

        </div>
    </section>

    <section class="user-list" style="background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(41,128,185,0.1);padding:32px;margin-bottom:32px;">
        <h2 style="color:#2980b9;font-size:1.5em;margin:0 0 24px 0;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-list"></i> Liste des Utilisateurs
        </h2>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:16px;text-align:left;color:#2980b9;font-weight:600;"><i class="fas fa-user"></i> Nom</th>
                        <th style="padding:16px;text-align:left;color:#2980b9;"><i class="fas fa-envelope"></i> Email</th>
                        <th style="padding:16px;text-align:left;color:#2980b9;"><i class="fas fa-user-shield"></i> Rôle actuel</th>
                        <th style="padding:16px;text-align:left;color:#2980b9;"><i class="fas fa-cogs"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr style="border-bottom:1px solid #f0f4f8;transition:background 0.2s;">
                        <td style="padding:16px;"><?= htmlspecialchars($user['name']) ?></td>
                        <td style="padding:16px;color:#636e72;"><?= htmlspecialchars($user['email']) ?></td>
                        <td style="padding:16px;">
                            <span class="role-badge" style="background:#eaf6fb;color:#2980b9;padding:6px 12px;border-radius:50px;font-size:0.9em;">
                                <i class="fas fa-user-tag"></i> <?= $roles[$user['role']] ?? 'Inconnu' ?>
                            </span>
                        </td>
                        <td style="padding:16px;">
                            <form method="post" class="form-inline" style="display:flex;gap:8px;align-items:center;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="new_role" class="form-control" style="padding:8px 12px;border:1.5px solid #e0eafc;border-radius:8px;background:#f8fafc;">
                                    <?php foreach ($roles as $id => $label): ?>
                                        <option value="<?= $id ?>" <?= $id == $user['role'] ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-primary" style="background:linear-gradient(135deg,#2980b9,#3498db);color:#fff;border:none;padding:8px 16px;border-radius:8px;display:flex;align-items:center;gap:8px;cursor:pointer;transition:transform 0.2s;">
                                    <i class="fas fa-sync-alt"></i> Mettre à jour
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="create-user" style="background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(41,128,185,0.1);padding:32px;">
        <h2 style="color:#27ae60;font-size:1.5em;margin:0 0 24px 0;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-user-plus"></i> Créer un Nouvel Utilisateur
        </h2>
        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:24px;">
            <div class="form-group" style="display:flex;flex-direction:column;gap:8px;">
                <label style="color:#2d3436;font-weight:600;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user"></i> Nom complet
                </label>
                <input type="text" name="name" required class="form-control" 
                    style="padding:12px;border:1.5px solid #e0eafc;border-radius:8px;font-size:1em;transition:border-color 0.2s;">
            </div>
            <div class="form-group" style="display:flex;flex-direction:column;gap:8px;">
                <label style="color:#2d3436;font-weight:600;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-envelope"></i> Email
                </label>
                <input type="email" name="email" required class="form-control"
                    style="padding:12px;border:1.5px solid #e0eafc;border-radius:8px;font-size:1em;transition:border-color 0.2s;">
            </div>
            <div class="form-group" style="display:flex;flex-direction:column;gap:8px;">
                <label style="color:#2d3436;font-weight:600;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user-shield"></i> Rôle
                </label>
                <select name="role" required class="form-control"
                    style="padding:12px;border:1.5px solid #e0eafc;border-radius:8px;font-size:1em;transition:border-color 0.2s;">
                    <?php foreach ($roles as $id => $label): ?>
                        <option value="<?= $id ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;margin-top:16px;">
                <button type="submit" name="create_user" class="btn btn-success" 
                    style="background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:1.1em;display:flex;align-items:center;gap:8px;cursor:pointer;transition:transform 0.2s;">
                    <i class="fas fa-user-plus"></i> Créer l'utilisateur
                </button>
            </div>
        </form>
    </section>
</div>

<style>
input.form-control:focus, select.form-control:focus {
    border-color: #2980b9;
    outline: none;
    box-shadow: 0 0 0 3px rgba(41,128,185,0.1);
}
button:hover {
    transform: translateY(-2px);
}
tr:hover {
    background: #f8fafc;
}
@keyframes slideIn {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        text-align: center;
        padding: 24px 16px;
    }
    .form-inline {
        flex-direction: column;
        align-items: stretch;
    }
    th, td { font-size: 0.9em; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>