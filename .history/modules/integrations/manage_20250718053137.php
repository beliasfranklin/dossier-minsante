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

// Récupérer les logs récents
$integrationLogs = fetchAll("
    SELECT 
        service_name,
        action,
        records_processed,
        errors,
        created_at,
        details
    FROM integration_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 10
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
    <title>Gestion des Intégrations - Système de Gestion des Dossiers</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: rgba(0, 0, 0, 0.1);
            --shadow-medium: rgba(0, 0, 0, 0.15);
            --shadow-heavy: rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2d3748;
            line-height: 1.6;
        }

        .integrations-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px var(--shadow-light);
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px var(--shadow-medium);
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .page-title i {
            margin-right: 15px;
            color: #ffd700;
        }

        /* Messages Flash */
        .flash-messages {
            margin-bottom: 20px;
        }

        .flash-message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInDown 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .flash-message.success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left: 4px solid #10b981;
        }

        .flash-message.error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .flash-message.info {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            border-left: 4px solid #3b82f6;
        }

        .flash-message.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Grille de connectivité */
        .connectivity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .connectivity-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .connectivity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px var(--shadow-medium);
        }

        .connectivity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 16px 16px 0 0;
        }

        .connectivity-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .connectivity-header i {
            font-size: 1.5rem;
            color: #667eea;
        }

        .connectivity-header h3 {
            color: white;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .connectivity-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-online .status-indicator {
            background: #10b981;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        .status-offline .status-indicator {
            background: #ef4444;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        .connectivity-status span {
            color: white;
            font-weight: 600;
        }

        .connectivity-message {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        /* Cartes de contenu */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-card, .stats-card, .info-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px var(--shadow-light);
        }

        .card-header {
            padding: 20px 25px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid var(--glass-border);
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .card-title i {
            color: #667eea;
        }

        .card-content {
            padding: 25px;
        }

        /* Formulaires modernes */
        .modern-form {
            max-width: none;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-title i {
            color: #667eea;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: white;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            transform: translateY(-1px);
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            transform: translateX(5px);
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        /* Boutons modernes */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .form-actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Cartes d'actions */
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px var(--shadow-light);
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px var(--shadow-medium);
        }

        .action-card h4 {
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .action-card h4 i {
            color: #667eea;
        }

        .action-card p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
        }

        /* Sidebar */
        .content-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .logs-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .log-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .log-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .log-stats {
            display: flex;
            gap: 15px;
        }

        .stat-success {
            color: #10b981;
            font-size: 0.9rem;
        }

        .stat-error {
            color: #ef4444;
            font-size: 0.9rem;
        }

        .log-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* États vides */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.3);
        }

        /* Guide steps */
        .guide-steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .guide-step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-content strong {
            color: white;
            display: block;
            margin-bottom: 5px;
        }

        .step-content p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .connectivity-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .integrations-container {
                padding: 10px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .connectivity-card,
            .form-card,
            .action-card {
                padding: 20px;
            }
        }

        /* Scrollbar personnalisée */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>

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

/**
 * Récupère une valeur de configuration
 */
function getConfig($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM integrations WHERE service_name = 'system' AND config_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        return $default;
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

    <div class="integrations-container">
        <!-- En-tête moderne -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-plug"></i>
                Gestion des Intégrations
            </h1>
            <p class="page-subtitle">Configuration et monitoring des intégrations systèmes</p>
        </div>

        <!-- Messages flash -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="flash-messages">
                <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                    <div class="flash-message <?= $type === 'error' ? 'error' : $type ?>">
                        <i class="fas fa-<?= $type === 'success' ? 'check-circle' : ($type === 'error' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

        <!-- Tests de connectivité -->
        <div class="connectivity-grid">
            <?php foreach ($connectivityTests as $service => $test): ?>
                <div class="connectivity-card">
                    <div class="connectivity-header">
                        <i class="fas fa-<?= $service === 'rh' ? 'users' : ($service === 'finance' ? 'euro-sign' : 'archive') ?>"></i>
                        <h3><?= ucfirst($service) ?></h3>
                    </div>
                    <div class="connectivity-status status-<?= $test['success'] ? 'online' : 'offline' ?>">
                        <div class="status-indicator"></div>
                        <span><?= $test['success'] ? 'En ligne' : 'Hors ligne' ?></span>
                    </div>
                    <p class="connectivity-message"><?= htmlspecialchars($test['message']) ?></p>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="test_<?= $service ?>_integration">
                        <button type="submit" class="btn btn-outline btn-sm">
                            <i class="fas fa-sync-alt"></i> Tester
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

    <div class="content-grid">
        <div class="content-main">
            <!-- Configuration générale -->
            <div class="form-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-settings"></i>
                        Configuration des Intégrations
                    </h3>
                </div>
                <div class="card-content">
                    <form method="POST" class="modern-form">
                        <input type="hidden" name="action" value="save_integration_config">
                        
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="icon-users"></i>
                                Système RH
                            </h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="rh_api_url" class="form-label">URL API RH</label>
                                    <input type="url" class="form-input" id="rh_api_url" name="rh_api_url" 
                                           value="<?= htmlspecialchars(getConfig('rh_api_url', '')) ?>"
                                           placeholder="https://rh.minsante.cm/api">
                                </div>
                                <div class="form-group">
                                    <label for="rh_api_key" class="form-label">Clé API RH</label>
                                    <input type="password" class="form-input" id="rh_api_key" name="rh_api_key" 
                                           value="<?= htmlspecialchars(getConfig('rh_api_key', '')) ?>"
                                           placeholder="Clé secrète d'authentification">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="icon-dollar-sign"></i>
                                Système Financier
                            </h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="finance_api_url" class="form-label">URL API Finance</label>
                                    <input type="url" class="form-input" id="finance_api_url" name="finance_api_url" 
                                           value="<?= htmlspecialchars(getConfig('finance_api_url', '')) ?>"
                                           placeholder="https://finance.minsante.cm/api">
                                </div>
                                <div class="form-group">
                                    <label for="finance_api_key" class="form-label">Clé API Finance</label>
                                    <input type="password" class="form-input" id="finance_api_key" name="finance_api_key" 
                                           value="<?= htmlspecialchars(getConfig('finance_api_key', '')) ?>"
                                           placeholder="Clé secrète d'authentification">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="icon-archive"></i>
                                Système d'Archivage
                            </h4>
                            <div class="form-group">
                                <label for="archive_api_url" class="form-label">URL API Archives</label>
                                <input type="url" class="form-input" id="archive_api_url" name="archive_api_url" 
                                       value="<?= htmlspecialchars(getConfig('archive_api_url', '')) ?>"
                                       placeholder="https://archives.minsante.cm/api">
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="icon-clock"></i>
                                Paramètres de Synchronisation
                            </h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="sync_frequency" class="form-label">Fréquence de sync (heures)</label>
                                    <input type="number" class="form-input" id="sync_frequency" name="sync_frequency" 
                                           value="<?= htmlspecialchars(getConfig('sync_frequency', '24')) ?>"
                                           min="1" max="168">
                                </div>
                                <div class="form-group">
                                    <label for="webhook_secret" class="form-label">Secret Webhook</label>
                                    <input type="password" class="form-input" id="webhook_secret" name="webhook_secret" 
                                           value="<?= htmlspecialchars(getConfig('webhook_secret', '')) ?>"
                                           placeholder="Secret pour validation webhooks">
                                </div>
                            </div>
                            
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="auto_sync_enabled" 
                                           <?= getConfig('auto_sync_enabled', 0) ? 'checked' : '' ?>>
                                    <span class="checkmark"></span>
                                    Activer la synchronisation automatique
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="backup_enabled" 
                                           <?= getConfig('backup_enabled', 0) ? 'checked' : '' ?>>
                                    <span class="checkmark"></span>
                                    Activer les sauvegardes automatiques
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-save"></i>
                                Sauvegarder la Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="action-cards">
                <div class="action-card">
                    <h4>
                        <i class="icon-refresh"></i>
                        Synchronisation RH
                    </h4>
                    <p>Synchroniser les données du personnel avec le système RH</p>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="sync_rh_data">
                        <button type="submit" class="btn btn-secondary">
                            <i class="icon-download"></i>
                            Synchroniser maintenant
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <h4>
                        <i class="icon-archive"></i>
                        Test Archivage
                    </h4>
                    <p>Tester la connexion avec le système d'archivage</p>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="test_archive_sync">
                        <button type="submit" class="btn btn-secondary">
                            <i class="icon-check"></i>
                            Tester l'archivage
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="content-sidebar">
            <!-- Statistiques -->
            <div class="stats-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-activity"></i>
                        Logs d'Intégration
                    </h3>
                </div>
                <div class="card-content">
                    <?php if (!empty($integrationLogs)): ?>
                        <div class="logs-list">
                            <?php foreach ($integrationLogs as $log): ?>
                                <div class="log-item">
                                    <div class="log-header">
                                        <span class="badge badge-<?= $log['success_count'] > $log['total_errors'] ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($log['service_name']) ?>
                                        </span>
                                        <span class="log-date"><?= date('d/m H:i', strtotime($log['last_sync'])) ?></span>
                                    </div>
                                    <div class="log-stats">
                                        <span class="stat-success"><?= $log['success_count'] ?> succès</span>
                                        <?php if ($log['total_errors'] > 0): ?>
                                            <span class="stat-error"><?= $log['total_errors'] ?> erreurs</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="icon-info"></i>
                            <p>Aucun log disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Guide rapide -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="icon-help-circle"></i>
                        Guide Rapide
                    </h3>
                </div>
                <div class="card-content">
                    <div class="guide-steps">
                        <div class="guide-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <strong>Configuration</strong>
                                <p>Configurez les URLs et clés API pour chaque système</p>
                            </div>
                        </div>
                        <div class="guide-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <strong>Test</strong>
                                <p>Testez la connectivité avec chaque service</p>
                            </div>
                        </div>
                        <div class="guide-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <strong>Surveillance</strong>
                                <p>Surveillez les logs et statistiques</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes de connectivité
    animateElementsOnScroll('.connectivity-card');
    
    // Gestion des formulaires avec notifications modernes
    document.querySelectorAll('form.modern-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Animation de soumission
            submitBtn.innerHTML = '<i class="icon-loading animate-spin"></i> Enregistrement...';
            submitBtn.disabled = true;
            
            // Simulation de l'envoi (remplacez par votre logique AJAX si nécessaire)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Soumission réelle du formulaire
                this.submit();
            }, 500);
        });
    });
    
    // Tests de connectivité avec feedback
    document.querySelectorAll('.inline-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            const action = this.querySelector('input[name="action"]')?.value;
            
            if (action && action.includes('test')) {
                e.preventDefault();
                
                btn.innerHTML = '<i class="icon-loading animate-spin"></i> Test...';
                btn.disabled = true;
                
                // Simulation du test
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    // Soumission réelle
                    this.submit();
                }, 1000);
            }
        });
    });
    
    // Confirmation pour les synchronisations
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        const action = form.querySelector('input[name="action"]')?.value;
        if (action && action.includes('sync')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                showConfirmDialog(
                    'Confirmer la synchronisation',
                    'Êtes-vous sûr de vouloir lancer cette synchronisation ?',
                    () => {
                        this.submit();
                    }
                );
            });
        }
    });
    
    // Auto-masquage des alertes
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            if (!alert.querySelector('.alert-close:hover')) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }
        });
    }, 5000);
    
    // Validation en temps réel des URLs
    document.querySelectorAll('input[type="url"]').forEach(input => {
        input.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url && !isValidUrl(url)) {
                this.classList.add('form-input-error');
                showNotification('URL invalide', 'warning');
            } else {
                this.classList.remove('form-input-error');
            }
        });
    });
    
    // Helper pour valider les URLs
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
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
