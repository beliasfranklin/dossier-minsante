<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérification des permissions admin
if (!hasPermission(ROLE_ADMIN)) {
    die('Accès refusé - Permissions administrateur requises');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_rh_integration':
            $result = testRHIntegration();
            $_SESSION['flash']['info'] = $result['message'];
            break;
            
        case 'sync_rh_data':
            $result = syncRHData();
            $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
            break;
            
        case 'test_financial_integration':
            $result = testFinancialIntegration();
            $_SESSION['flash']['info'] = $result['message'];
            break;
            
        case 'save_integration_config':
            $result = saveIntegrationConfig($_POST);
            $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
            break;
            
        case 'test_archive_sync':
            $result = testArchiveSync();
            $_SESSION['flash']['info'] = $result['message'];
            break;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * Teste la connexion avec le système RH
 */
function testRHIntegration() {
    try {
        $apiUrl = defined('RH_API_URL') ? RH_API_URL : 'https://rh.minsante.cm/api/status';
        $apiKey = defined('RH_API_KEY') ? RH_API_KEY : '';
        
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Clé API RH non configurée'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'Erreur de connexion RH: ' . $error];
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return ['success' => true, 'message' => 'Connexion RH réussie - ' . ($data['status'] ?? 'OK')];
        } else {
            return ['success' => false, 'message' => 'Erreur HTTP RH: ' . $httpCode];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Exception RH: ' . $e->getMessage()];
    }
}

/**
 * Teste la connexion avec le système financier
 */
function testFinancialIntegration() {
    try {
        $apiUrl = defined('FINANCE_API_URL') ? FINANCE_API_URL : 'https://finance.minsante.cm/api/status';
        $apiKey = defined('FINANCE_API_KEY') ? FINANCE_API_KEY : '';
        
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Clé API Finance non configurée'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'Erreur de connexion Finance: ' . $error];
        }
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Connexion Finance réussie'];
        } else {
            return ['success' => false, 'message' => 'Erreur HTTP Finance: ' . $httpCode];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Exception Finance: ' . $e->getMessage()];
    }
}

/**
 * Teste la synchronisation avec le système d'archives
 */
function testArchiveSync() {
    try {
        $archiveUrl = defined('ARCHIVE_API_URL') ? ARCHIVE_API_URL : 'https://archives.minsante.cm/api/status';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $archiveUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'Erreur de connexion Archives: ' . $error];
        }
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Connexion Archives réussie'];
        } else {
            return ['success' => false, 'message' => 'Erreur HTTP Archives: ' . $httpCode];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Exception Archives: ' . $e->getMessage()];
    }
}

/**
 * Sauvegarde la configuration des intégrations
 */
function saveIntegrationConfig($data) {
    try {
        $configs = [
            'rh_api_url' => $data['rh_api_url'] ?? '',
            'rh_api_key' => $data['rh_api_key'] ?? '',
            'finance_api_url' => $data['finance_api_url'] ?? '',
            'finance_api_key' => $data['finance_api_key'] ?? '',
            'archive_api_url' => $data['archive_api_url'] ?? '',
            'sync_frequency' => (int)($data['sync_frequency'] ?? 24),
            'auto_sync_enabled' => isset($data['auto_sync_enabled']) ? 1 : 0,
            'webhook_secret' => $data['webhook_secret'] ?? '',
            'backup_enabled' => isset($data['backup_enabled']) ? 1 : 0
        ];
        
        foreach ($configs as $key => $value) {
            executeQuery(
                "INSERT INTO integrations (service_name, config_key, config_value, updated_at) 
                 VALUES ('system', ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()",
                [$key, $value]
            );
        }
        
        return ['success' => true, 'message' => 'Configuration sauvegardée avec succès'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur sauvegarde: ' . $e->getMessage()];
    }
}

/**
 * Synchronisation améliorée avec le système RH
 */
function syncRHData() {
    try {
        $apiUrl = defined('RH_API_URL') ? RH_API_URL : '';
        $apiKey = defined('RH_API_KEY') ? RH_API_KEY : '';
        
        if (empty($apiUrl) || empty($apiKey)) {
            return ['success' => false, 'message' => 'Configuration RH incomplète'];
        }
        
        // Récupérer les employés depuis l'API RH
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl . '/employees');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'Échec récupération données RH'];
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['employees'])) {
            return ['success' => false, 'message' => 'Format de données RH invalide'];
        }
        
        $syncCount = 0;
        $errorCount = 0;
        
        foreach ($data['employees'] as $emp) {
            try {
                // Mise à jour ou création de l'utilisateur
                executeQuery(
                    "INSERT INTO users (external_id, name, email, department, phone, position, role) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     name = VALUES(name), 
                     department = VALUES(department),
                     phone = VALUES(phone),
                     position = VALUES(position),
                     updated_at = NOW()",
                    [
                        $emp['id'],
                        $emp['name'],
                        $emp['email'],
                        $emp['department'] ?? '',
                        $emp['phone'] ?? '',
                        $emp['position'] ?? '',
                        $emp['role'] ?? ROLE_CONSULTANT
                    ]
                );
                
                $syncCount++;
                
                // Logger la synchronisation
                logAction(
                    $_SESSION['user_id'],
                    'rh_sync',
                    null,
                    "Synchronisé: {$emp['name']} ({$emp['email']})"
                );
                
            } catch (Exception $e) {
                $errorCount++;
                error_log("Erreur sync RH pour {$emp['email']}: " . $e->getMessage());
            }
        }
        
        // Enregistrer les statistiques de synchronisation
        executeQuery(
            "INSERT INTO integration_logs (service_name, action, records_processed, errors, details, created_at) 
             VALUES ('rh', 'sync', ?, ?, ?, NOW())",
            [$syncCount, $errorCount, "Synchronisation RH: $syncCount réussies, $errorCount échecs"]
        );
        
        return [
            'success' => true,
            'message' => "Synchronisation RH terminée: $syncCount utilisateurs synchronisés, $errorCount erreurs"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur synchronisation RH: ' . $e->getMessage()];
    }
}

// Récupérer les configurations actuelles
$currentConfig = fetchAll("SELECT config_key, config_value FROM integrations WHERE service_name = 'system'");
$config = [];
foreach ($currentConfig as $item) {
    $config[$item['config_key']] = $item['config_value'];
}

// Statistiques des intégrations
$integrationStats = fetchAll("
    SELECT 
        service_name,
        action,
        COUNT(*) as total_operations,
        SUM(records_processed) as total_records,
        SUM(errors) as total_errors,
        MAX(created_at) as last_sync
    FROM integration_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY service_name, action
    ORDER BY last_sync DESC
");

// Tests de connectivité
$connectivityTests = [
    'rh' => testRHIntegration(),
    'finance' => testFinancialIntegration(),
    'archive' => testArchiveSync()
];

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intégrations Système - MINSANTE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .integrations-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .integration-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .integration-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .integration-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .integration-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .card-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-online {
            background: #27ae60;
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
        }
        
        .status-offline {
            background: #e74c3c;
        }
        
        .status-unknown {
            background: #f39c12;
        }
        
        .config-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .stats-table th,
        .stats-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .stats-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2d3748;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .api-endpoint {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9em;
            border-left: 4px solid #667eea;
            margin: 10px 0;
        }
        
        .webhook-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="integrations-container">
        <!-- En-tête -->
        <div class="integration-header">
            <h1><i class="fas fa-plug"></i> Gestion des Intégrations Système</h1>
            <p>Configuration et monitoring des connexions avec les systèmes externes</p>
        </div>
        
        <!-- Tests de connectivité -->
        <div class="integration-grid">
            <!-- Système RH -->
            <div class="integration-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-users"></i> Système RH
                    </div>
                    <span class="status-indicator <?= $connectivityTests['rh']['success'] ? 'status-online' : 'status-offline' ?>"></span>
                </div>
                <p><strong>Statut :</strong> <?= $connectivityTests['rh']['message'] ?></p>
                <p><strong>Fonction :</strong> Synchronisation des employés et organigramme</p>
                <div class="api-endpoint">
                    GET <?= $config['rh_api_url'] ?? 'Non configuré' ?>/employees
                </div>
                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="test_rh_integration">
                        <button type="submit" class="btn-secondary">
                            <i class="fas fa-plug"></i> Tester
                        </button>
                    </form>
                    <form method="post" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="action" value="sync_rh_data">
                        <button type="submit" class="btn-success">
                            <i class="fas fa-sync"></i> Synchroniser
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Système Financier -->
            <div class="integration-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-euro-sign"></i> Système Financier
                    </div>
                    <span class="status-indicator <?= $connectivityTests['finance']['success'] ? 'status-online' : 'status-offline' ?>"></span>
                </div>
                <p><strong>Statut :</strong> <?= $connectivityTests['finance']['message'] ?></p>
                <p><strong>Fonction :</strong> Synchronisation des budgets et projets</p>
                <div class="api-endpoint">
                    POST <?= $config['finance_api_url'] ?? 'Non configuré' ?>/projects
                </div>
                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="test_financial_integration">
                        <button type="submit" class="btn-secondary">
                            <i class="fas fa-plug"></i> Tester
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Système d'Archives -->
            <div class="integration-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-archive"></i> Système d'Archives
                    </div>
                    <span class="status-indicator <?= $connectivityTests['archive']['success'] ? 'status-online' : 'status-offline' ?>"></span>
                </div>
                <p><strong>Statut :</strong> <?= $connectivityTests['archive']['message'] ?></p>
                <p><strong>Fonction :</strong> Archivage long terme et compliance</p>
                <div class="api-endpoint">
                    POST <?= $config['archive_api_url'] ?? 'Non configuré' ?>/archive
                </div>
                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="test_archive_sync">
                        <button type="submit" class="btn-secondary">
                            <i class="fas fa-plug"></i> Tester
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Configuration des intégrations -->
        <div class="config-form">
            <h3><i class="fas fa-cog"></i> Configuration des Intégrations</h3>
            <form method="post">
                <input type="hidden" name="action" value="save_integration_config">
                
                <div class="form-grid">
                    <!-- Configuration RH -->
                    <div>
                        <h4 style="color: #667eea; margin-bottom: 15px;"><i class="fas fa-users"></i> Système RH</h4>
                        <div class="form-group">
                            <label class="form-label">URL API RH :</label>
                            <input type="url" name="rh_api_url" class="form-control" 
                                   value="<?= htmlspecialchars($config['rh_api_url'] ?? '') ?>" 
                                   placeholder="https://rh.minsante.cm/api">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clé API RH :</label>
                            <input type="password" name="rh_api_key" class="form-control" 
                                   value="<?= htmlspecialchars($config['rh_api_key'] ?? '') ?>" 
                                   placeholder="Clé d'authentification">
                        </div>
                    </div>
                    
                    <!-- Configuration Finance -->
                    <div>
                        <h4 style="color: #667eea; margin-bottom: 15px;"><i class="fas fa-euro-sign"></i> Système Financier</h4>
                        <div class="form-group">
                            <label class="form-label">URL API Finance :</label>
                            <input type="url" name="finance_api_url" class="form-control" 
                                   value="<?= htmlspecialchars($config['finance_api_url'] ?? '') ?>" 
                                   placeholder="https://finance.minsante.cm/api">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clé API Finance :</label>
                            <input type="password" name="finance_api_key" class="form-control" 
                                   value="<?= htmlspecialchars($config['finance_api_key'] ?? '') ?>" 
                                   placeholder="Clé d'authentification">
                        </div>
                    </div>
                    
                    <!-- Configuration Archives -->
                    <div>
                        <h4 style="color: #667eea; margin-bottom: 15px;"><i class="fas fa-archive"></i> Système d'Archives</h4>
                        <div class="form-group">
                            <label class="form-label">URL API Archives :</label>
                            <input type="url" name="archive_api_url" class="form-control" 
                                   value="<?= htmlspecialchars($config['archive_api_url'] ?? '') ?>" 
                                   placeholder="https://archives.minsante.cm/api">
                        </div>
                    </div>
                    
                    <!-- Configuration générale -->
                    <div>
                        <h4 style="color: #667eea; margin-bottom: 15px;"><i class="fas fa-cogs"></i> Configuration Générale</h4>
                        <div class="form-group">
                            <label class="form-label">Fréquence sync (heures) :</label>
                            <input type="number" name="sync_frequency" class="form-control" 
                                   value="<?= htmlspecialchars($config['sync_frequency'] ?? '24') ?>" 
                                   min="1" max="168">
                        </div>
                        <div class="form-group">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="auto_sync_enabled" id="auto_sync" 
                                       <?= ($config['auto_sync_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label for="auto_sync" class="form-label">Synchronisation automatique</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Secret Webhook :</label>
                            <input type="text" name="webhook_secret" class="form-control" 
                                   value="<?= htmlspecialchars($config['webhook_secret'] ?? '') ?>" 
                                   placeholder="Clé secrète pour webhooks">
                        </div>
                        <div class="form-group">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="backup_enabled" id="backup_enabled" 
                                       <?= ($config['backup_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label for="backup_enabled" class="form-label">Sauvegarde avant sync</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Sauvegarder la Configuration
                    </button>
                </div>
            </form>
            
            <!-- Informations sur les webhooks -->
            <div class="webhook-info">
                <h4><i class="fas fa-webhook"></i> Endpoints Webhook</h4>
                <p>Configurez ces URLs dans vos systèmes externes pour recevoir les notifications en temps réel :</p>
                <div class="api-endpoint">POST <?= getBaseUrl() ?>/api/webhooks/rh.php</div>
                <div class="api-endpoint">POST <?= getBaseUrl() ?>/api/webhooks/finance.php</div>
                <div class="api-endpoint">POST <?= getBaseUrl() ?>/api/webhooks/archive.php</div>
                <p><small><strong>Secret :</strong> Utilisez le secret webhook configuré ci-dessus pour sécuriser les appels.</small></p>
            </div>
        </div>
        
        <!-- Statistiques des intégrations -->
        <div class="integration-card">
            <h3><i class="fas fa-chart-bar"></i> Statistiques des Intégrations (30 derniers jours)</h3>
            <?php if (!empty($integrationStats)): ?>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Action</th>
                            <th>Opérations</th>
                            <th>Enregistrements</th>
                            <th>Erreurs</th>
                            <th>Dernière Sync</th>
                            <th>Succès</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrationStats as $stat): ?>
                            <tr>
                                <td><strong><?= ucfirst($stat['service_name']) ?></strong></td>
                                <td><?= ucfirst($stat['action']) ?></td>
                                <td><?= number_format($stat['total_operations']) ?></td>
                                <td><?= number_format($stat['total_records']) ?></td>
                                <td>
                                    <?php if ($stat['total_errors'] > 0): ?>
                                        <span class="badge badge-danger"><?= $stat['total_errors'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $stat['last_sync'] ? date('d/m/Y H:i', strtotime($stat['last_sync'])) : 'Jamais' ?></td>
                                <td>
                                    <?php
                                    $successRate = $stat['total_operations'] > 0 ? 
                                        (($stat['total_operations'] - $stat['total_errors']) / $stat['total_operations']) * 100 : 0;
                                    ?>
                                    <span class="badge <?= $successRate >= 95 ? 'badge-success' : ($successRate >= 80 ? 'badge-warning' : 'badge-danger') ?>">
                                        <?= number_format($successRate, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #64748b; padding: 40px;">
                    <i class="fas fa-info-circle"></i> Aucune statistique d'intégration disponible
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Documentation API -->
        <div class="integration-card">
            <h3><i class="fas fa-book"></i> Documentation API</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #667eea;"><i class="fas fa-code"></i> Endpoints Disponibles</h4>
                    <div class="api-endpoint">GET /api/dossiers - Liste des dossiers</div>
                    <div class="api-endpoint">POST /api/dossiers - Créer un dossier</div>
                    <div class="api-endpoint">PUT /api/dossiers/{id} - Modifier un dossier</div>
                    <div class="api-endpoint">GET /api/users - Liste des utilisateurs</div>
                    <div class="api-endpoint">POST /api/sync/rh - Sync RH manuelle</div>
                </div>
                <div>
                    <h4 style="color: #667eea;"><i class="fas fa-shield-alt"></i> Authentification</h4>
                    <p>Toutes les API utilisent une authentification Bearer Token :</p>
                    <div class="api-endpoint">Authorization: Bearer YOUR_API_KEY</div>
                    <p><small>Les clés API peuvent être générées dans les paramètres utilisateur.</small></p>
                </div>
                <div>
                    <h4 style="color: #667eea;"><i class="fas fa-exchange-alt"></i> Formats de Données</h4>
                    <p>Toutes les APIs acceptent et retournent du JSON :</p>
                    <div class="api-endpoint">Content-Type: application/json</div>
                    <p><small>Les dates sont au format ISO 8601 (YYYY-MM-DD HH:MM:SS).</small></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide des messages flash
        document.addEventListener('DOMContentLoaded', function() {
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(function(message) {
                setTimeout(function() {
                    message.style.opacity = '0';
                    setTimeout(function() {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Confirmation pour les syncs
        document.querySelectorAll('form[method="post"]').forEach(function(form) {
            const action = form.querySelector('input[name="action"]')?.value;
            if (action && action.includes('sync')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir lancer cette synchronisation ?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

<?php
/**
 * Fonction utilitaire pour obtenir l'URL de base
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . $host . $script, '/');
}
?>
