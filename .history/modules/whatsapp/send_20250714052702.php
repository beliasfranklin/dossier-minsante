<?php
/**
 * Page d'envoi de messages WhatsApp
 * Interface moderne pour l'envoi de messages via WhatsApp Business API
 */

require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/preferences.php';

// Vérifier l'authentification
requireAuth();

// Initialiser le gestionnaire de préférences
$preferencesManager = new PreferencesManager($pdo, $_SESSION['user_id']);

// Variables pour le formulaire
$success = false;
$error = '';
$message = '';

// Récupérer la liste des contacts (dossiers avec numéros de téléphone)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            d.id,
            d.numero_dossier,
            d.nom_patient,
            d.prenom_patient,
            d.telephone
        FROM dossiers d 
        WHERE d.telephone IS NOT NULL 
        AND d.telephone != '' 
        AND LENGTH(TRIM(d.telephone)) >= 8
        ORDER BY d.nom_patient, d.prenom_patient
    ");
    $stmt->execute();
    $contacts = $stmt->fetchAll();
} catch (Exception $e) {
    $contacts = [];
    error_log("Erreur récupération contacts: " . $e->getMessage());
}

// Traitement de l'envoi de message
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $recipient = trim($_POST['recipient'] ?? '');
    $messageText = trim($_POST['message'] ?? '');
    $messageType = $_POST['message_type'] ?? 'text';
    
    if (empty($recipient) || empty($messageText)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Simuler l'envoi WhatsApp (à remplacer par une vraie API)
        try {
            // Ici on intégrerait l'API WhatsApp Business
            // Pour la démo, on simule un succès
            
            // Enregistrer le message dans la base
            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_messages (
                    user_id, recipient, message, message_type, status, created_at
                ) VALUES (?, ?, ?, ?, 'sent', NOW())
            ");
            
            // Créer la table si elle n'existe pas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    recipient VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    message_type ENUM('text', 'template', 'media') DEFAULT 'text',
                    status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_recipient (recipient)
                )
            ");
            
            $stmt->execute([$_SESSION['user_id'], $recipient, $messageText, $messageType]);
            
            $success = true;
            $message = 'Message envoyé avec succès !';
            
            // Redirection pour éviter resoumission
            $_SESSION['success_message'] = $message;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        } catch (Exception $e) {
            $error = 'Erreur lors de l\'envoi : ' . $e->getMessage();
            error_log("Erreur envoi WhatsApp: " . $e->getMessage());
        }
    }
}

// Récupérer l'historique des messages récents
try {
    $stmt = $pdo->prepare("
        SELECT 
            recipient,
            message,
            message_type,
            status,
            created_at
        FROM whatsapp_messages 
        WHERE user_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentMessages = $stmt->fetchAll();
} catch (Exception $e) {
    $recentMessages = [];
    error_log("Erreur récupération historique: " . $e->getMessage());
}

$themeVars = $preferencesManager->getThemeVariables();
$pageTitle = "Envoi WhatsApp";
require_once '../../includes/header.php';
?>

<style>
:root {
    <?php foreach ($themeVars as $var => $value): ?>
    <?= $var ?>: <?= $value ?>;
    <?php endforeach; ?>
    
    /* Couleurs WhatsApp */
    --whatsapp-green: #25D366;
    --whatsapp-green-dark: #128C7E;
    --whatsapp-green-light: #DCF8C6;
    --whatsapp-blue: #34B7F1;
    --whatsapp-gray: #F0F0F0;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.whatsapp-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.whatsapp-header {
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-green-dark));
    color: white;
    padding: 2.5rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 8px 32px rgba(37, 211, 102, 0.3);
}

.whatsapp-header h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.whatsapp-header p {
    margin: 1rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.main-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    margin-bottom: 2rem;
}

.send-panel {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    border: 1px solid #e1e8ed;
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fa;
}

.panel-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-weight: 600;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #fafbfc;
}

.form-control:focus {
    outline: none;
    border-color: var(--whatsapp-green);
    background: white;
    box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.1);
}

.recipient-grid {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: end;
}

.contact-btn {
    background: var(--whatsapp-blue);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.contact-btn:hover {
    background: #2196F3;
    transform: translateY(-2px);
}

.message-textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
}

.message-type-selector {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.type-option {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #e1e8ed;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.type-option.active {
    border-color: var(--whatsapp-green);
    background: var(--whatsapp-green-light);
    color: var(--whatsapp-green-dark);
    font-weight: 600;
}

.send-btn {
    width: 100%;
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-green-dark));
    color: white;
    border: none;
    padding: 1.25rem;
    border-radius: 16px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
}

.send-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.contacts-panel, .history-panel {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    border: 1px solid #e1e8ed;
}

.contact-list {
    max-height: 300px;
    overflow-y: auto;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.contact-item:hover {
    background: #f8f9fa;
    border-color: var(--whatsapp-green);
}

.contact-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
}

.contact-info h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

.contact-info p {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.history-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.history-item:last-child {
    border-bottom: none;
}

.history-status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-sent { background: var(--whatsapp-green); }
.status-delivered { background: var(--whatsapp-blue); }
.status-read { background: #9C27B0; }
.status-failed { background: var(--danger-color); }

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.quick-templates {
    display: grid;
    gap: 0.75rem;
    margin-top: 1rem;
}

.template-btn {
    background: #f8f9fa;
    border: 1px solid #e1e8ed;
    padding: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.template-btn:hover {
    background: var(--whatsapp-green-light);
    border-color: var(--whatsapp-green);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e1e8ed;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--whatsapp-green);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

@media (max-width: 1024px) {
    .main-grid {
        grid-template-columns: 1fr;
    }
    
    .whatsapp-container {
        padding: 1rem;
    }
    
    .whatsapp-header h1 {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .recipient-grid {
        grid-template-columns: 1fr;
    }
    
    .message-type-selector {
        flex-direction: column;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

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

.send-panel, .contacts-panel, .history-panel {
    animation: fadeInUp 0.6s ease forwards;
}

/* Scrollbar personnalisée */
.contact-list::-webkit-scrollbar {
    width: 6px;
}

.contact-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.contact-list::-webkit-scrollbar-thumb {
    background: var(--whatsapp-green);
    border-radius: 10px;
}
</style>

<div class="whatsapp-container">
    <!-- En-tête WhatsApp -->
    <div class="whatsapp-header">
        <h1>
            <i class="fab fa-whatsapp"></i>
            Envoi WhatsApp Business
        </h1>
        <p>Envoyez des messages professionnels à vos patients et contacts</p>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($contacts) ?></div>
            <div class="stat-label">Contacts disponibles</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($recentMessages) ?></div>
            <div class="stat-label">Messages récents</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?= count(array_filter($recentMessages, fn($m) => $m['status'] === 'sent')) ?>
            </div>
            <div class="stat-label">Messages envoyés</div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-grid">
        <!-- Panel d'envoi -->
        <div class="send-panel">
            <div class="panel-header">
                <i class="fas fa-paper-plane" style="color: var(--whatsapp-green); font-size: 1.5rem;"></i>
                <h2>Nouveau Message</h2>
            </div>

            <form method="POST" id="whatsappForm">
                <input type="hidden" name="action" value="send_message">
                
                <!-- Destinataire -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Destinataire
                    </label>
                    <div class="recipient-grid">
                        <input type="tel" 
                               name="recipient" 
                               class="form-control" 
                               placeholder="+33 6 12 34 56 78"
                               id="recipientInput"
                               required>
                        <button type="button" class="contact-btn" onclick="toggleContacts()">
                            <i class="fas fa-address-book"></i> Contacts
                        </button>
                    </div>
                </div>

                <!-- Type de message -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Type de message
                    </label>
                    <div class="message-type-selector">
                        <div class="type-option active" data-type="text">
                            <i class="fas fa-font"></i><br>Texte
                        </div>
                        <div class="type-option" data-type="template">
                            <i class="fas fa-file-alt"></i><br>Modèle
                        </div>
                        <div class="type-option" data-type="media">
                            <i class="fas fa-image"></i><br>Média
                        </div>
                    </div>
                    <input type="hidden" name="message_type" value="text" id="messageType">
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-comment"></i> Message
                    </label>
                    <textarea name="message" 
                              class="form-control message-textarea" 
                              placeholder="Saisissez votre message..."
                              id="messageText"
                              required></textarea>
                    <div style="text-align: right; margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                        <span id="charCount">0</span>/1000 caractères
                    </div>
                </div>

                <!-- Modèles rapides -->
                <div class="form-group" id="templatesSection">
                    <label class="form-label">
                        <i class="fas fa-magic"></i> Modèles rapides
                    </label>
                    <div class="quick-templates">
                        <button type="button" class="template-btn" data-template="Bonjour, votre rendez-vous est confirmé pour demain à 14h.">
                            📅 Confirmation RDV
                        </button>
                        <button type="button" class="template-btn" data-template="Merci de vous présenter 15 minutes avant votre rendez-vous.">
                            ⏰ Rappel ponctualité
                        </button>
                        <button type="button" class="template-btn" data-template="Votre dossier a été mis à jour. Vous pouvez le consulter dans votre espace patient.">
                            📋 Mise à jour dossier
                        </button>
                    </div>
                </div>

                <!-- Bouton d'envoi -->
                <button type="submit" class="send-btn" id="sendBtn">
                    <i class="fab fa-whatsapp"></i>
                    Envoyer le message
                </button>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Panel des contacts -->
            <div class="contacts-panel" id="contactsPanel" style="display: none;">
                <div class="panel-header">
                    <i class="fas fa-users" style="color: var(--whatsapp-blue); font-size: 1.2rem;"></i>
                    <h3 style="margin: 0; font-size: 1.1rem;">Contacts</h3>
                </div>
                
                <div class="contact-list">
                    <?php if (empty($contacts)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-address-book" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Aucun contact avec numéro de téléphone</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-item" onclick="selectContact('<?= htmlspecialchars($contact['telephone']) ?>', '<?= htmlspecialchars($contact['nom_patient'] . ' ' . $contact['prenom_patient']) ?>')">
                                <div class="contact-avatar">
                                    <?= strtoupper(substr($contact['nom_patient'], 0, 1)) ?>
                                </div>
                                <div class="contact-info">
                                    <h4><?= htmlspecialchars($contact['nom_patient'] . ' ' . $contact['prenom_patient']) ?></h4>
                                    <p><?= htmlspecialchars($contact['telephone']) ?></p>
                                    <p style="font-size: 0.8rem; opacity: 0.7;">Dossier: <?= htmlspecialchars($contact['numero_dossier']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel d'historique -->
            <div class="history-panel">
                <div class="panel-header">
                    <i class="fas fa-history" style="color: var(--warning-color); font-size: 1.2rem;"></i>
                    <h3 style="margin: 0; font-size: 1.1rem;">Messages récents</h3>
                </div>
                
                <div class="history-list">
                    <?php if (empty($recentMessages)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-comment-slash" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Aucun message envoyé</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentMessages as $msg): ?>
                            <div class="history-item">
                                <div class="history-status status-<?= $msg['status'] ?>" title="<?= ucfirst($msg['status']) ?>"></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary);">
                                        <?= htmlspecialchars($msg['recipient']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                        <?= htmlspecialchars(substr($msg['message'], 0, 50)) ?><?= strlen($msg['message']) > 50 ? '...' : '' ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                        <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des types de messages
document.querySelectorAll('.type-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.type-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('messageType').value = this.dataset.type;
        
        // Afficher/masquer les modèles selon le type
        const templatesSection = document.getElementById('templatesSection');
        if (this.dataset.type === 'template') {
            templatesSection.style.display = 'block';
        } else {
            templatesSection.style.display = 'none';
        }
    });
});

// Gestion des modèles rapides
document.querySelectorAll('.template-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('messageText').value = this.dataset.template;
        updateCharCount();
    });
});

// Compteur de caractères
const messageText = document.getElementById('messageText');
const charCount = document.getElementById('charCount');

function updateCharCount() {
    const count = messageText.value.length;
    charCount.textContent = count;
    charCount.style.color = count > 900 ? 'var(--danger-color)' : 'var(--text-secondary)';
}

messageText.addEventListener('input', updateCharCount);

// Gestion des contacts
function toggleContacts() {
    const panel = document.getElementById('contactsPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function selectContact(phone, name) {
    document.getElementById('recipientInput').value = phone;
    document.getElementById('contactsPanel').style.display = 'none';
    
    // Animation de confirmation
    const input = document.getElementById('recipientInput');
    input.style.borderColor = 'var(--whatsapp-green)';
    setTimeout(() => {
        input.style.borderColor = '';
    }, 2000);
}

// Validation du formulaire
document.getElementById('whatsappForm').addEventListener('submit', function(e) {
    const recipient = document.getElementById('recipientInput').value.trim();
    const message = document.getElementById('messageText').value.trim();
    
    if (!recipient || !message) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
    }
    
    // Validation du numéro de téléphone
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,15}$/;
    if (!phoneRegex.test(recipient)) {
        e.preventDefault();
        alert('Veuillez saisir un numéro de téléphone valide.');
        return;
    }
    
    // Animation de chargement
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
});

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des statistiques
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((stat, index) => {
        setTimeout(() => {
            stat.style.opacity = '0';
            stat.style.transform = 'scale(0.5)';
            stat.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                stat.style.opacity = '1';
                stat.style.transform = 'scale(1)';
            }, 100);
        }, index * 200);
    });
    
    // Focus automatique sur le champ destinataire
    setTimeout(() => {
        document.getElementById('recipientInput').focus();
    }, 500);
});

// Masquer les modèles par défaut
document.getElementById('templatesSection').style.display = 'none';

console.log('📱 Page WhatsApp chargée avec succès');
</script>

<?php require_once '../../includes/footer.php'; ?>
