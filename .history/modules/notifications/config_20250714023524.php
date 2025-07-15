<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérifier les permissions - seuls les admins peuvent configurer les notifications
if (!hasPermission(ROLE_ADMIN)) {
    header("Location: " . BASE_URL . "error.php?code=403");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = '';
    
    try {
        // Configuration des notifications par type
        $notificationTypes = [
            'new_dossier' => [
                'enabled' => isset($_POST['new_dossier_enabled']),
                'email' => isset($_POST['new_dossier_email']),
                'roles' => $_POST['new_dossier_roles'] ?? []
            ],
            'dossier_status_change' => [
                'enabled' => isset($_POST['status_change_enabled']),
                'email' => isset($_POST['status_change_email']),
                'roles' => $_POST['status_change_roles'] ?? []
            ],
            'workflow_assignment' => [
                'enabled' => isset($_POST['workflow_enabled']),
                'email' => isset($_POST['workflow_email']),
                'roles' => $_POST['workflow_roles'] ?? []
            ],
            'document_upload' => [
                'enabled' => isset($_POST['document_enabled']),
                'email' => isset($_POST['document_email']),
                'roles' => $_POST['document_roles'] ?? []
            ],
            'deadline_reminder' => [
                'enabled' => isset($_POST['deadline_enabled']),
                'email' => isset($_POST['deadline_email']),
                'advance_days' => (int)($_POST['deadline_advance_days'] ?? 3),
                'roles' => $_POST['deadline_roles'] ?? []
            ],
            'system_maintenance' => [
                'enabled' => isset($_POST['maintenance_enabled']),
                'email' => isset($_POST['maintenance_email']),
                'roles' => $_POST['maintenance_roles'] ?? []
            ]
        ];
        
        // Configuration générale
        $generalConfig = [
            'notification_frequency' => $_POST['notification_frequency'] ?? 'immediate',
            'batch_size' => (int)($_POST['batch_size'] ?? 50),
            'email_enabled' => isset($_POST['global_email_enabled']),
            'quiet_hours_start' => $_POST['quiet_hours_start'] ?? '',
            'quiet_hours_end' => $_POST['quiet_hours_end'] ?? '',
            'weekend_notifications' => isset($_POST['weekend_notifications'])
        ];
        
        // Sauvegarder les configurations
        foreach ($notificationTypes as $type => $config) {
            // Vérifier si une configuration existe déjà
            $existing = fetchOne("SELECT id FROM notification_config WHERE type = ?", [$type]);
            
            if ($existing) {
                executeQuery(
                    "UPDATE notification_config SET 
                     enabled = ?, email_enabled = ?, roles = ?, config_data = ?, updated_at = NOW()
                     WHERE type = ?",
                    [
                        $config['enabled'] ? 1 : 0,
                        $config['email'] ? 1 : 0,
                        json_encode($config['roles']),
                        json_encode($config),
                        $type
                    ]
                );
            } else {
                executeQuery(
                    "INSERT INTO notification_config (type, enabled, email_enabled, roles, config_data, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $type,
                        $config['enabled'] ? 1 : 0,
                        $config['email'] ? 1 : 0,
                        json_encode($config['roles']),
                        json_encode($config)
                    ]
                );
            }
        }
        
        // Sauvegarder la configuration générale
        $existingGeneral = fetchOne("SELECT id FROM notification_config WHERE type = 'general'");
        if ($existingGeneral) {
            executeQuery(
                "UPDATE notification_config SET config_data = ?, updated_at = NOW() WHERE type = 'general'",
                [json_encode($generalConfig)]
            );
        } else {
            executeQuery(
                "INSERT INTO notification_config (type, enabled, config_data, created_at, updated_at)
                 VALUES ('general', 1, ?, NOW(), NOW())",
                [json_encode($generalConfig)]
            );
        }
        
        logAction($_SESSION['user_id'], 'notification_config_updated', null, "Configuration des notifications mise à jour");
        $success = "Configuration des notifications mise à jour avec succès";
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la sauvegarde : " . $e->getMessage();
    }
}

// Récupérer les configurations existantes
$configs = [];
$configRows = fetchAll("SELECT * FROM notification_config");
foreach ($configRows as $row) {
    $configs[$row['type']] = [
        'enabled' => $row['enabled'],
        'email_enabled' => $row['email_enabled'],
        'roles' => json_decode($row['roles'] ?? '[]', true),
        'config_data' => json_decode($row['config_data'] ?? '{}', true)
    ];
}

// Configuration par défaut si aucune n'existe
$defaultConfig = [
    'enabled' => true,
    'email_enabled' => false,
    'roles' => [],
    'config_data' => []
];

// Récupérer tous les rôles disponibles
$roles = [
    ROLE_ADMIN => 'Administrateur',
    ROLE_GESTIONNAIRE => 'Gestionnaire',
    ROLE_CONSULTANT => 'Consultant'
];

// Messages de session
$sessionSuccess = $_SESSION['success'] ?? '';
$sessionError = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Notifications - MINSANTE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* === PAGE CONFIGURATION NOTIFICATIONS - STYLE ADMIN MODERNE === */
    .notifications-config-page {
        background: var(--gray-50);
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    
    .notifications-config-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        color: var(--gray-600);
        font-size: 0.875rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.3s ease-out;
    }
    
    .breadcrumb a {
        color: var(--primary-600);
        text-decoration: none;
        transition: var(--transition-all);
    }
    
    .breadcrumb a:hover {
        color: var(--primary-800);
    }
    
    .page-header {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInDown 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .header-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .header-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: var(--shadow-lg);
        animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-6px) rotate(2deg); }
    }
    
    .header-details h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0 0 0.5rem 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .header-details .subtitle {
        color: var(--gray-600);
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .config-form {
        background: white;
        border-radius: var(--radius-2xl);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        animation: slideInUp 0.6s ease-out;
    }
    
    .form-sections {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .form-section {
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        padding: 2rem;
        border: 1px solid var(--gray-200);
        position: relative;
        overflow: hidden;
        transition: var(--transition-all);
    }
    
    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.5s ease;
        transform-origin: left;
    }
    
    .form-section:hover::before {
        transform: scaleX(1);
    }
    
    .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-200);
    }
    
    .section-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
    }
    
    .section-title {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
    }
    
    .section-description {
        color: var(--gray-600);
        font-size: 0.875rem;
        margin: 0;
        line-height: 1.5;
    }
    
    .notification-type {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 2px solid var(--gray-200);
        margin-bottom: 1rem;
        transition: var(--transition-all);
        position: relative;
    }
    
    .notification-type:hover {
        border-color: var(--primary-300);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .notification-type.enabled {
        border-color: var(--success-300);
        background: var(--success-50);
    }
    
    .type-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .type-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .type-icon {
        width: 40px;
        height: 40px;
        background: var(--primary-100);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-600);
        font-size: 1rem;
    }
    
    .type-details h4 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0 0 0.25rem 0;
    }
    
    .type-details p {
        color: var(--gray-600);
        font-size: 0.875rem;
        margin: 0;
    }
    
    .type-toggle {
        position: relative;
    }
    
    .toggle-switch {
        position: relative;
        width: 60px;
        height: 30px;
        background: var(--gray-300);
        border-radius: 15px;
        cursor: pointer;
        transition: var(--transition-all);
    }
    
    .toggle-switch.active {
        background: var(--success-500);
    }
    
    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 24px;
        height: 24px;
        background: white;
        border-radius: 50%;
        transition: var(--transition-all);
        box-shadow: var(--shadow-sm);
    }
    
    .toggle-switch.active::after {
        transform: translateX(30px);
    }
    
    .type-options {
        display: none;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-200);
    }
    
    .type-options.show {
        display: grid;
    }
    
    .option-group {
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        padding: 1rem;
        border: 1px solid var(--gray-200);
    }
    
    .option-group h5 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-700);
        margin: 0 0 0.75rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group:last-child {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--primary-500);
        width: 16px;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        background: white;
        transition: var(--transition-all);
        box-sizing: border-box;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }
    
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        transition: var(--transition-all);
    }
    
    .checkbox-item:hover {
        background: var(--gray-100);
    }
    
    .checkbox-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-500);
    }
    
    .checkbox-item label {
        font-size: 0.875rem;
        color: var(--gray-700);
        cursor: pointer;
        margin: 0;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-lg);
        font-weight: 500;
        transition: var(--transition-all);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
    }
    
    .btn-secondary:hover {
        background: var(--gray-200);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid var(--gray-200);
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        animation: slideInDown 0.5s ease-out;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-700);
        border: 1px solid var(--success-200);
        border-left: 4px solid var(--success-500);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-700);
        border: 1px solid var(--danger-200);
        border-left: 4px solid var(--danger-500);
    }
    
    .alert i {
        font-size: 1.125rem;
    }
    
    /* Animations */
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .type-options {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .header-info {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .header-details h1 {
            font-size: 1.5rem;
        }
        
        .type-header {
            flex-direction: column;
            align-items: start;
            gap: 1rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <div class="notifications-config-page">
        <div class="notifications-config-container">
            <!-- Fil d'Ariane -->
            <nav class="breadcrumb">
                <a href="<?= BASE_URL ?>dashboard.php">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= BASE_URL ?>modules/notifications/list.php">
                    <i class="fas fa-bell"></i>
                    Notifications
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Configuration</span>
            </nav>

            <!-- En-tête -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <div class="header-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="header-details">
                            <h1>Configuration des Notifications</h1>
                            <p class="subtitle">
                                <i class="fas fa-sliders-h"></i>
                                Paramétrage des alertes et notifications système
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="<?= BASE_URL ?>modules/notifications/list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour aux notifications
                        </a>
                        <button form="configForm" type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Sauvegarder
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages d'alerte -->
            <?php if (!empty($success) || !empty($sessionSuccess)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success ?: $sessionSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors) || !empty($sessionError)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php if (!empty($errors)): ?>
                        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($sessionError) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de configuration -->
            <form id="configForm" method="POST" class="config-form">
                <div class="form-sections">
                    <!-- Configuration générale -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Configuration Générale</h3>
                                <p class="section-description">Paramètres globaux du système de notifications</p>
                            </div>
                        </div>

                        <div class="type-options show">
                            <div class="option-group">
                                <h5><i class="fas fa-clock"></i> Fréquence des notifications</h5>
                                <div class="form-group">
                                    <select name="notification_frequency" class="form-select">
                                        <option value="immediate" <?= ($configs['general']['config_data']['notification_frequency'] ?? 'immediate') === 'immediate' ? 'selected' : '' ?>>
                                            Immédiate
                                        </option>
                                        <option value="hourly" <?= ($configs['general']['config_data']['notification_frequency'] ?? '') === 'hourly' ? 'selected' : '' ?>>
                                            Toutes les heures
                                        </option>
                                        <option value="daily" <?= ($configs['general']['config_data']['notification_frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>
                                            Quotidienne
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="option-group">
                                <h5><i class="fas fa-envelope"></i> Configuration Email</h5>
                                <div class="form-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="global_email_enabled" name="global_email_enabled" 
                                               <?= ($configs['general']['config_data']['email_enabled'] ?? false) ? 'checked' : '' ?>>
                                        <label for="global_email_enabled">Activer les notifications par email</label>
                                    </div>
                                </div>
                            </div>

                            <div class="option-group">
                                <h5><i class="fas fa-moon"></i> Heures silencieuses</h5>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-clock"></i>
                                        Début
                                    </label>
                                    <input type="time" name="quiet_hours_start" class="form-input" 
                                           value="<?= htmlspecialchars($configs['general']['config_data']['quiet_hours_start'] ?? '22:00') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-clock"></i>
                                        Fin
                                    </label>
                                    <input type="time" name="quiet_hours_end" class="form-input" 
                                           value="<?= htmlspecialchars($configs['general']['config_data']['quiet_hours_end'] ?? '08:00') ?>">
                                </div>
                            </div>

                            <div class="option-group">
                                <h5><i class="fas fa-calendar-weekend"></i> Options avancées</h5>
                                <div class="form-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="weekend_notifications" name="weekend_notifications" 
                                               <?= ($configs['general']['config_data']['weekend_notifications'] ?? false) ? 'checked' : '' ?>>
                                        <label for="weekend_notifications">Notifications le week-end</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-layer-group"></i>
                                        Taille des lots de traitement
                                    </label>
                                    <input type="number" name="batch_size" class="form-input" min="1" max="100" 
                                           value="<?= htmlspecialchars($configs['general']['config_data']['batch_size'] ?? 50) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Types de notifications -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-list-ul"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Types de Notifications</h3>
                                <p class="section-description">Configuration spécifique pour chaque type d'événement</p>
                            </div>
                        </div>

                        <!-- Nouveau dossier -->
                        <?php
                        $newDossierConfig = $configs['new_dossier'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $newDossierConfig['enabled'] ? 'enabled' : '' ?>" data-type="new_dossier">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-plus-circle"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Nouveau Dossier</h4>
                                        <p>Notification lors de la création d'un nouveau dossier</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $newDossierConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('new_dossier')"></div>
                                    <input type="hidden" name="new_dossier_enabled" value="<?= $newDossierConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $newDossierConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="new_dossier_email" 
                                               <?= $newDossierConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="new_dossier_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $newDossierConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Changement de statut -->
                        <?php
                        $statusConfig = $configs['dossier_status_change'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $statusConfig['enabled'] ? 'enabled' : '' ?>" data-type="status_change">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Changement de Statut</h4>
                                        <p>Notification lors du changement de statut d'un dossier</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $statusConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('status_change')"></div>
                                    <input type="hidden" name="status_change_enabled" value="<?= $statusConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $statusConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="status_change_email" 
                                               <?= $statusConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="status_change_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $statusConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attribution de workflow -->
                        <?php
                        $workflowConfig = $configs['workflow_assignment'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $workflowConfig['enabled'] ? 'enabled' : '' ?>" data-type="workflow">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Attribution de Workflow</h4>
                                        <p>Notification lors de l'attribution d'une tâche workflow</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $workflowConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('workflow')"></div>
                                    <input type="hidden" name="workflow_enabled" value="<?= $workflowConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $workflowConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="workflow_email" 
                                               <?= $workflowConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="workflow_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $workflowConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload de document -->
                        <?php
                        $documentConfig = $configs['document_upload'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $documentConfig['enabled'] ? 'enabled' : '' ?>" data-type="document">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Upload de Document</h4>
                                        <p>Notification lors de l'ajout d'un nouveau document</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $documentConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('document')"></div>
                                    <input type="hidden" name="document_enabled" value="<?= $documentConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $documentConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="document_email" 
                                               <?= $documentConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="document_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $documentConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rappel d'échéance -->
                        <?php
                        $deadlineConfig = $configs['deadline_reminder'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $deadlineConfig['enabled'] ? 'enabled' : '' ?>" data-type="deadline">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Rappel d'Échéance</h4>
                                        <p>Notification avant les dates d'échéance importantes</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $deadlineConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('deadline')"></div>
                                    <input type="hidden" name="deadline_enabled" value="<?= $deadlineConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $deadlineConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="deadline_email" 
                                               <?= $deadlineConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-clock"></i> Délai d'avance</h5>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-calendar-day"></i>
                                            Jours avant échéance
                                        </label>
                                        <input type="number" name="deadline_advance_days" class="form-input" min="1" max="30" 
                                               value="<?= htmlspecialchars($deadlineConfig['config_data']['advance_days'] ?? 3) ?>">
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="deadline_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $deadlineConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance système -->
                        <?php
                        $maintenanceConfig = $configs['system_maintenance'] ?? $defaultConfig;
                        ?>
                        <div class="notification-type <?= $maintenanceConfig['enabled'] ? 'enabled' : '' ?>" data-type="maintenance">
                            <div class="type-header">
                                <div class="type-info">
                                    <div class="type-icon">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="type-details">
                                        <h4>Maintenance Système</h4>
                                        <p>Notifications concernant la maintenance et les mises à jour</p>
                                    </div>
                                </div>
                                <div class="type-toggle">
                                    <div class="toggle-switch <?= $maintenanceConfig['enabled'] ? 'active' : '' ?>" 
                                         onclick="toggleNotificationType('maintenance')"></div>
                                    <input type="hidden" name="maintenance_enabled" value="<?= $maintenanceConfig['enabled'] ? '1' : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="type-options <?= $maintenanceConfig['enabled'] ? 'show' : '' ?>">
                                <div class="option-group">
                                    <h5><i class="fas fa-envelope"></i> Notification par email</h5>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="maintenance_email" 
                                               <?= $maintenanceConfig['email_enabled'] ? 'checked' : '' ?>>
                                        <label>Envoyer par email</label>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <h5><i class="fas fa-users"></i> Rôles concernés</h5>
                                    <div class="checkbox-group">
                                        <?php foreach ($roles as $roleId => $roleName): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="maintenance_roles[]" value="<?= $roleId ?>" 
                                                       <?= in_array($roleId, $maintenanceConfig['roles']) ? 'checked' : '' ?>>
                                                <label><?= htmlspecialchars($roleName) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions du formulaire -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>modules/notifications/list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Sauvegarder la configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fonction pour basculer l'état d'un type de notification
        function toggleNotificationType(type) {
            const container = document.querySelector(`[data-type="${type}"]`);
            const toggle = container.querySelector('.toggle-switch');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const options = container.querySelector('.type-options');
            
            const isActive = toggle.classList.contains('active');
            
            if (isActive) {
                toggle.classList.remove('active');
                container.classList.remove('enabled');
                options.classList.remove('show');
                hiddenInput.value = '0';
            } else {
                toggle.classList.add('active');
                container.classList.add('enabled');
                options.classList.add('show');
                hiddenInput.value = '1';
            }
            
            // Animation pour l'affichage/masquage des options
            if (!isActive) {
                options.style.maxHeight = options.scrollHeight + 'px';
                setTimeout(() => {
                    options.style.maxHeight = 'none';
                }, 300);
            } else {
                options.style.maxHeight = options.scrollHeight + 'px';
                setTimeout(() => {
                    options.style.maxHeight = '0';
                }, 10);
            }
        }
        
        // Animation des éléments au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 200);
            });
            
            // Animation des notifications types
            const notificationTypes = document.querySelectorAll('.notification-type');
            notificationTypes.forEach((type, index) => {
                type.style.opacity = '0';
                type.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    type.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    type.style.opacity = '1';
                    type.style.transform = 'translateX(0)';
                }, 800 + (index * 100));
            });
        });
        
        // Validation du formulaire
        document.getElementById('configForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
            submitBtn.disabled = true;
            
            // Permettre la soumission normale du formulaire
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });
        
        // Amélioration UX : confirmation avant changements importants
        const importantToggles = document.querySelectorAll('[data-type="new_dossier"] .toggle-switch, [data-type="deadline"] .toggle-switch');
        importantToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                const type = this.closest('.notification-type').dataset.type;
                const isActive = this.classList.contains('active');
                
                if (isActive && (type === 'new_dossier' || type === 'deadline')) {
                    const confirmMessage = type === 'new_dossier' ? 
                        'Désactiver les notifications de nouveaux dossiers ?' :
                        'Désactiver les rappels d\'échéance ?';
                    
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                }
            });
        });
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('configForm').submit();
            }
        });
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
