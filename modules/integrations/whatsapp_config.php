<?php
require_once '../../includes/config.php';
requireAuth();

require_once 'whatsapp.php';
$whatsapp = new WhatsAppIntegration();

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'configure':
            $config = [
                'access_token' => $_POST['access_token'] ?? '',
                'phone_number_id' => $_POST['phone_number_id'] ?? '',
                'webhook_verify_token' => $_POST['webhook_verify_token'] ?? '',
                'webhook_url' => $_POST['webhook_url'] ?? '',
                'business_account_id' => $_POST['business_account_id'] ?? ''
            ];
            
            if ($whatsapp->configure($config)) {
                $message = 'Configuration WhatsApp sauvegardée avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la sauvegarde de la configuration.';
                $messageType = 'error';
            }
            break;
            
        case 'test_connection':
            $result = $whatsapp->testConnection();
            if ($result['success']) {
                $message = 'Connexion WhatsApp réussie!';
                $messageType = 'success';
            } else {
                $message = 'Erreur de connexion: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'send_test_message':
            $phone = $_POST['test_phone'] ?? '';
            $testMessage = $_POST['test_message'] ?? 'Test de connexion WhatsApp Business API';
            
            $result = $whatsapp->sendTextMessage($phone, $testMessage);
            if ($result['success']) {
                $message = 'Message de test envoyé avec succès!';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de l\'envoi: ' . $result['error'];
                $messageType = 'error';
            }
            break;
    }
}

// Récupérer la configuration actuelle
$currentConfig = [];
try {
    $stmt = $conn->prepare("SELECT config_data FROM integrations_config WHERE service = 'whatsapp'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        $currentConfig = json_decode($config['config_data'], true) ?? [];
    }
} catch (Exception $e) {
    // Table n'existe pas encore, la créer
    WhatsAppIntegration::createTables();
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.integration-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.integration-header {
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
}

.integration-header h1 {
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.integration-header p {
    margin: 1rem 0 0 0;
    opacity: 0.9;
}

.config-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.config-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    border: 1px solid #e0e0e0;
}

.config-section h3 {
    margin: 0 0 1.5rem 0;
    color: #2c3e50;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #25D366;
}

.form-group small {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-style: italic;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: #25D366;
    color: white;
}

.btn-primary:hover {
    background: #1fbb5a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.status-indicator {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-connected {
    background: #d4edda;
    color: #155724;
}

.status-disconnected {
    background: #f8d7da;
    color: #721c24;
}

.webhook-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.webhook-info h4 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.webhook-url {
    background: #e9ecef;
    padding: 0.75rem;
    border-radius: 4px;
    font-family: monospace;
    word-break: break-all;
    margin: 0.5rem 0;
}

.test-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.message-history {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    background: white;
}

.message-item {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.message-item:last-child {
    border-bottom: none;
}

.message-sent {
    background: #e3f2fd;
}

.message-received {
    background: #f3e5f5;
}

.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.template-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
}

.template-card h5 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.template-preview {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9rem;
    border-left: 4px solid #25D366;
}

@media (max-width: 768px) {
    .config-grid {
        grid-template-columns: 1fr;
    }
    
    .integration-header h1 {
        font-size: 1.5rem;
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<div class="integration-container">
    <div class="integration-header">
        <h1>
            <i class="fab fa-whatsapp"></i>
            Intégration WhatsApp Business
        </h1>
        <p>Configurez l'intégration WhatsApp Business API pour les notifications automatiques</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="config-grid">
        <!-- Configuration -->
        <div class="config-section">
            <h3>
                <i class="fas fa-cog"></i>
                Configuration API
            </h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="configure">
                
                <div class="form-group">
                    <label for="access_token">Token d'accès</label>
                    <input type="password" id="access_token" name="access_token" 
                           value="<?= htmlspecialchars($currentConfig['access_token'] ?? '') ?>" 
                           placeholder="Votre token d'accès WhatsApp Business">
                    <small>Token permanent de votre application Meta</small>
                </div>
                
                <div class="form-group">
                    <label for="phone_number_id">ID du numéro de téléphone</label>
                    <input type="text" id="phone_number_id" name="phone_number_id" 
                           value="<?= htmlspecialchars($currentConfig['phone_number_id'] ?? '') ?>" 
                           placeholder="123456789012345">
                    <small>ID du numéro WhatsApp Business dans Meta Business</small>
                </div>
                
                <div class="form-group">
                    <label for="business_account_id">ID du compte Business</label>
                    <input type="text" id="business_account_id" name="business_account_id" 
                           value="<?= htmlspecialchars($currentConfig['business_account_id'] ?? '') ?>" 
                           placeholder="123456789012345">
                    <small>ID de votre compte WhatsApp Business</small>
                </div>
                
                <div class="form-group">
                    <label for="webhook_verify_token">Token de vérification Webhook</label>
                    <input type="text" id="webhook_verify_token" name="webhook_verify_token" 
                           value="<?= htmlspecialchars($currentConfig['webhook_verify_token'] ?? '') ?>" 
                           placeholder="MonTokenSecurise123">
                    <small>Token personnalisé pour sécuriser les webhooks</small>
                </div>
                
                <div class="form-group">
                    <label for="webhook_url">URL Webhook</label>
                    <input type="url" id="webhook_url" name="webhook_url" 
                           value="<?= htmlspecialchars($currentConfig['webhook_url'] ?? '') ?>" 
                           placeholder="https://votre-domaine.com/webhook">
                    <small>URL publique pour recevoir les webhooks</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Sauvegarder la configuration
                </button>
            </form>
        </div>

        <!-- Test et statut -->
        <div class="config-section">
            <h3>
                <i class="fas fa-wifi"></i>
                Test de connexion
            </h3>
            
            <div class="status-indicator <?= !empty($currentConfig['access_token']) ? 'status-connected' : 'status-disconnected' ?>">
                <i class="fas <?= !empty($currentConfig['access_token']) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= !empty($currentConfig['access_token']) ? 'Configuré' : 'Non configuré' ?>
            </div>
            
            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-plug"></i>
                    Tester la connexion
                </button>
            </form>
            
            <div class="test-section">
                <h4><i class="fas fa-paper-plane"></i> Envoyer un message de test</h4>
                
                <form method="POST">
                    <input type="hidden" name="action" value="send_test_message">
                    
                    <div class="form-group">
                        <label for="test_phone">Numéro de téléphone</label>
                        <input type="tel" id="test_phone" name="test_phone" 
                               placeholder="+33612345678" required>
                        <small>Format international avec indicatif pays</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="test_message">Message</label>
                        <textarea id="test_message" name="test_message" rows="3" 
                                  placeholder="Message de test...">🤖 Test de connexion WhatsApp Business API depuis MINSANTE

✅ Si vous recevez ce message, l'intégration fonctionne correctement!

Pour toute question, contactez l'équipe technique.</textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer le test
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Information sur les webhooks -->
    <div class="config-section">
        <h3>
            <i class="fas fa-exchange-alt"></i>
            Configuration des Webhooks
        </h3>
        
        <div class="webhook-info">
            <h4>URL du Webhook à configurer dans Meta:</h4>
            <div class="webhook-url">
                <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/modules/integrations/webhook.php
            </div>
            
            <h4>Token de vérification:</h4>
            <div class="webhook-url">
                <?= htmlspecialchars($currentConfig['webhook_verify_token'] ?? 'Non configuré') ?>
            </div>
            
            <p><strong>Instructions:</strong></p>
            <ol>
                <li>Accédez à votre console Meta for Developers</li>
                <li>Sélectionnez votre application WhatsApp Business</li>
                <li>Allez dans Configuration > Webhooks</li>
                <li>Ajoutez l'URL webhook ci-dessus</li>
                <li>Utilisez le token de vérification indiqué</li>
                <li>Abonnez-vous aux événements : messages, message_status</li>
            </ol>
        </div>
    </div>

    <!-- Templates de messages -->
    <div class="config-section">
        <h3>
            <i class="fas fa-comment-dots"></i>
            Templates de messages
        </h3>
        
        <p>Les templates suivants sont disponibles pour les notifications automatiques:</p>
        
        <div class="templates-grid">
            <div class="template-card">
                <h5>🔔 Nouveau dossier</h5>
                <div class="template-preview">
🔔 Nouveau dossier assigné

📋 Référence: [REFERENCE]
📝 Titre: [TITRE]
⚡ Priorité: [PRIORITE]
📅 Échéance: [ECHEANCE]

Connectez-vous au système pour plus de détails.
                </div>
            </div>
            
            <div class="template-card">
                <h5>⏰ Échéance proche</h5>
                <div class="template-preview">
⚠️ Échéance proche - [X] jour(s) restant(s)

📋 Dossier: [REFERENCE]
📝 Titre: [TITRE]
⏰ Échéance: [DATE]
📊 Statut: [STATUT]

Action requise rapidement.
                </div>
            </div>
            
            <div class="template-card">
                <h5>📝 Changement de statut</h5>
                <div class="template-preview">
📝 Changement de statut

📋 Dossier: [REFERENCE]
📝 Titre: [TITRE]
🔄 Ancien statut: [ANCIEN]
🆕 Nouveau statut: [NOUVEAU]

Consultez le dossier pour plus d'informations.
                </div>
            </div>
            
            <div class="template-card">
                <h5>🤖 Commandes automatiques</h5>
                <div class="template-preview">
Commandes disponibles:
• 'aide' - Afficher l'aide
• 'mes dossiers' - Vos statistiques
• 'urgent' - Dossiers prioritaires
• 'échéances' - Prochaines échéances
• 'statut [ref]' - Statut d'un dossier
                </div>
            </div>
        </div>
    </div>

    <!-- Historique des messages récents -->
    <div class="config-section">
        <h3>
            <i class="fas fa-history"></i>
            Historique des messages récents
        </h3>
        
        <div class="message-history">
            <?php
            try {
                $stmt = $conn->prepare("
                    SELECT phone_number, content, direction, created_at 
                    FROM whatsapp_messages 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($messages)) {
                    echo '<p class="text-muted">Aucun message dans l\'historique</p>';
                } else {
                    foreach ($messages as $msg) {
                        $directionIcon = $msg['direction'] === 'sent' ? 'fa-arrow-right' : 'fa-arrow-left';
                        $directionClass = $msg['direction'] === 'sent' ? 'message-sent' : 'message-received';
                        echo '<div class="message-item ' . $directionClass . '">';
                        echo '<div>';
                        echo '<strong><i class="fas ' . $directionIcon . '"></i> ' . htmlspecialchars($msg['phone_number']) . '</strong><br>';
                        echo '<small>' . htmlspecialchars(substr($msg['content'], 0, 100)) . (strlen($msg['content']) > 100 ? '...' : '') . '</small>';
                        echo '</div>';
                        echo '<small>' . date('d/m/Y H:i', strtotime($msg['created_at'])) . '</small>';
                        echo '</div>';
                    }
                }
            } catch (Exception $e) {
                echo '<p class="text-muted">Historique non disponible (tables en cours de création)</p>';
            }
            ?>
        </div>
    </div>
</div>

<script>
// Auto-actualisation de l'historique des messages
setInterval(function() {
    // TODO: Implémenter l'actualisation AJAX de l'historique
}, 30000);

// Validation du formulaire
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    const accessToken = document.getElementById('access_token').value;
    const phoneNumberId = document.getElementById('phone_number_id').value;
    
    if (!accessToken || !phoneNumberId) {
        alert('Veuillez remplir au minimum le token d\'accès et l\'ID du numéro de téléphone.');
        e.preventDefault();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
