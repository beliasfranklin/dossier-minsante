<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';

requireAuth();

$pageTitle = "Support Technique - " . t('app_name');

// Traitement du formulaire de ticket
$message = '';
$messageType = '';

if ($_POST) {
    $sujet = trim($_POST['sujet'] ?? '');
    $priorite = $_POST['priorite'] ?? 'normale';
    $categorie = $_POST['categorie'] ?? 'general';
    $description = trim($_POST['description'] ?? '');
    $fichier = $_FILES['fichier'] ?? null;
    
    if (empty($sujet) || empty($description)) {
        $message = 'Veuillez remplir tous les champs obligatoires.';
        $messageType = 'error';
    } else {
        try {
            // Insérer le ticket dans la base de données
            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (user_id, sujet, priorite, categorie, description, statut, created_at) 
                VALUES (?, ?, ?, ?, ?, 'ouvert', NOW())
            ");
            
            $stmt->execute([$_SESSION['user_id'], $sujet, $priorite, $categorie, $description]);
            $ticketId = $pdo->lastInsertId();
            
            // Gérer l'upload de fichier si présent
            if ($fichier && $fichier['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/support/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extension = pathinfo($fichier['name'], PATHINFO_EXTENSION);
                $fileName = 'ticket_' . $ticketId . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($fichier['tmp_name'], $uploadPath)) {
                    $stmt = $pdo->prepare("
                        UPDATE support_tickets 
                        SET fichier_joint = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$fileName, $ticketId]);
                }
            }
            
            $message = 'Votre ticket de support #' . $ticketId . ' a été créé avec succès. Notre équipe vous répondra dans les plus brefs délais.';
            $messageType = 'success';
            
            // Réinitialiser le formulaire
            $_POST = [];
            
        } catch (Exception $e) {
            $message = 'Erreur lors de la création du ticket. Veuillez réessayer.';
            $messageType = 'error';
        }
    }
}

// Récupérer les tickets de l'utilisateur
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, sujet, priorite, categorie, statut, created_at, updated_at
        FROM support_tickets 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll();
} catch (Exception $e) {
    // Silencieux si la table n'existe pas encore
}
?>

<div class="page-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-life-ring"></i>
            Support Technique
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            Obtenez de l'aide pour résoudre vos problèmes techniques
        </p>
    </div>
</div>

<div class="container">
    
    <?php if ($message): ?>
        <div class="alert" style="background: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $messageType === 'success' ? '#155724' : '#721c24' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid <?= $messageType === 'success' ? '#c3e6cb' : '#f5c6cb' ?>;">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start;">
        
        <!-- Formulaire de création de ticket -->
        <div style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
            <div style="background: linear-gradient(135deg, #e74c3c15, #e74c3c08); padding: 2rem; border-bottom: 1px solid #e1e8ed;">
                <h2 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-ticket-alt" style="color: #e74c3c;"></i>
                    Créer un ticket de support
                </h2>
                <p style="margin: 8px 0 0 0; color: #7f8c8d;">
                    Décrivez votre problème ou votre demande en détail
                </p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding: 2rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-tag" style="color: #e74c3c; margin-right: 8px;"></i>
                            Catégorie *
                        </label>
                        <select name="categorie" required 
                                style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; background: white;">
                            <option value="general" <?= ($_POST['categorie'] ?? '') === 'general' ? 'selected' : '' ?>>Problème général</option>
                            <option value="connexion" <?= ($_POST['categorie'] ?? '') === 'connexion' ? 'selected' : '' ?>>Problème de connexion</option>
                            <option value="dossiers" <?= ($_POST['categorie'] ?? '') === 'dossiers' ? 'selected' : '' ?>>Gestion des dossiers</option>
                            <option value="rapports" <?= ($_POST['categorie'] ?? '') === 'rapports' ? 'selected' : '' ?>>Rapports et exports</option>
                            <option value="performance" <?= ($_POST['categorie'] ?? '') === 'performance' ? 'selected' : '' ?>>Performance</option>
                            <option value="fonctionnalite" <?= ($_POST['categorie'] ?? '') === 'fonctionnalite' ? 'selected' : '' ?>>Demande de fonctionnalité</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-exclamation-circle" style="color: #f39c12; margin-right: 8px;"></i>
                            Priorité
                        </label>
                        <select name="priorite" 
                                style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; background: white;">
                            <option value="basse" <?= ($_POST['priorite'] ?? '') === 'basse' ? 'selected' : '' ?>>Basse</option>
                            <option value="normale" <?= ($_POST['priorite'] ?? 'normale') === 'normale' ? 'selected' : '' ?>>Normale</option>
                            <option value="haute" <?= ($_POST['priorite'] ?? '') === 'haute' ? 'selected' : '' ?>>Haute</option>
                            <option value="urgente" <?= ($_POST['priorite'] ?? '') === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                        <i class="fas fa-heading" style="color: #3498db; margin-right: 8px;"></i>
                        Sujet *
                    </label>
                    <input type="text" name="sujet" required 
                           value="<?= htmlspecialchars($_POST['sujet'] ?? '') ?>"
                           placeholder="Résumez votre problème en quelques mots"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px;">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                        <i class="fas fa-align-left" style="color: #9b59b6; margin-right: 8px;"></i>
                        Description détaillée *
                    </label>
                    <textarea name="description" required rows="6"
                              placeholder="Décrivez votre problème en détail : &#10;- Que s'est-il passé ?&#10;- Quand cela s'est-il produit ?&#10;- Quelles sont les étapes pour reproduire le problème ?&#10;- Quel était le résultat attendu ?"
                              style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; resize: vertical; font-family: inherit;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                        <i class="fas fa-paperclip" style="color: #16a085; margin-right: 8px;"></i>
                        Fichier joint (optionnel)
                    </label>
                    <input type="file" name="fichier" 
                           accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8fafc;">
                    <small style="color: #7f8c8d; font-size: 0.85rem; margin-top: 4px; display: block;">
                        Formats acceptés : JPG, PNG, PDF, DOC, TXT (max 5 MB)
                    </small>
                </div>
                
                <button type="submit" 
                        style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; width: 100%; transition: all 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(231,76,60,0.3)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <i class="fas fa-paper-plane"></i>
                    Envoyer le ticket
                </button>
            </form>
        </div>
        
        <!-- Sidebar avec informations et historique -->
        <div>
            <!-- Contact rapide -->
            <div style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 2rem; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 1.5rem; text-align: center;">
                    <h3 style="margin: 0 0 8px 0;">
                        <i class="fas fa-phone"></i> Contact rapide
                    </h3>
                    <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Besoin d'aide immédiate ?</p>
                </div>
                
                <div style="padding: 1.5rem;">
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">
                            <i class="fas fa-envelope" style="color: #e74c3c; margin-right: 8px;"></i>
                            Email
                        </strong>
                        <a href="mailto:support@minsante.gov" style="color: #3498db; text-decoration: none;">
                            support@minsante.gov
                        </a>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">
                            <i class="fas fa-clock" style="color: #f39c12; margin-right: 8px;"></i>
                            Horaires
                        </strong>
                        <span style="color: #7f8c8d; font-size: 0.9rem;">
                            Lun-Ven : 8h00 - 18h00
                        </span>
                    </div>
                    
                    <div>
                        <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">
                            <i class="fas fa-reply" style="color: #27ae60; margin-right: 8px;"></i>
                            Délai de réponse
                        </strong>
                        <span style="color: #7f8c8d; font-size: 0.9rem;">
                            Sous 24h ouvrées
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Mes derniers tickets -->
            <?php if ($tickets): ?>
                <div style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
                    <div style="background: linear-gradient(135deg, #9b59b615, #9b59b608); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                        <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-history" style="color: #9b59b6;"></i>
                            Mes derniers tickets
                        </h3>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($tickets as $ticket): ?>
                            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f0f4f8;">
                                <div style="display: flex; justify-content: between; align-items: start; gap: 1rem;">
                                    <div style="flex: 1; min-width: 0;">
                                        <h5 style="margin: 0 0 4px 0; color: #2c3e50; font-size: 0.9rem; font-weight: 600;">
                                            #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['sujet']) ?>
                                        </h5>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                        </div>
                                    </div>
                                    
                                    <span style="background: <?= getStatutColor($ticket['statut']) ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; white-space: nowrap;">
                                        <?= getStatutLabel($ticket['statut']) ?>
                                    </span>
                                </div>
                                
                                <div style="margin-top: 8px; display: flex; gap: 8px;">
                                    <span style="background: <?= getPrioriteColor($ticket['priorite']) ?>; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem;">
                                        <?= ucfirst($ticket['priorite']) ?>
                                    </span>
                                    <span style="background: #f8fafc; color: #5a6c7d; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem;">
                                        <?= ucfirst($ticket['categorie']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Section FAQ rapide -->
    <div style="margin-top: 3rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 16px; padding: 3rem; text-align: center;">
        <h2 style="margin: 0 0 1rem 0;">
            <i class="fas fa-lightbulb" style="margin-right: 12px;"></i>
            Avant de créer un ticket...
        </h2>
        <p style="margin: 0 0 2rem 0; opacity: 0.9; font-size: 1.1rem;">
            Consultez d'abord notre FAQ pour des solutions rapides
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="<?= BASE_URL ?>modules/help/faq.php" 
               style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-4px)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
                <i class="fas fa-question-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <strong>FAQ</strong><br>
                <small>Questions fréquentes</small>
            </a>

            <a href="<?= BASE_URL ?>modules/help/guide.php" 
               style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-4px)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
                <i class="fas fa-book" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <strong>Guide</strong><br>
                <small>Documentation</small>
            </a>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const sujet = document.querySelector('input[name="sujet"]').value.trim();
    const description = document.querySelector('textarea[name="description"]').value.trim();
    
    if (!sujet || !description) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
    }
    
    if (description.length < 20) {
        e.preventDefault();
        alert('Veuillez fournir une description plus détaillée (minimum 20 caractères).');
        return;
    }
});

// Validation de fichier
document.querySelector('input[type="file"]').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5 MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'text/plain'];
        
        if (file.size > maxSize) {
            alert('Le fichier est trop volumineux. Taille maximum : 5 MB.');
            this.value = '';
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            alert('Type de fichier non autorisé. Formats acceptés : JPG, PNG, PDF, DOC, TXT.');
            this.value = '';
            return;
        }
    }
});
</script>

<?php
function getStatutColor($statut) {
    switch ($statut) {
        case 'ouvert': return '#e74c3c';
        case 'en_cours': return '#f39c12';
        case 'resolu': return '#27ae60';
        case 'ferme': return '#95a5a6';
        default: return '#7f8c8d';
    }
}

function getStatutLabel($statut) {
    switch ($statut) {
        case 'ouvert': return 'Ouvert';
        case 'en_cours': return 'En cours';
        case 'resolu': return 'Résolu';
        case 'ferme': return 'Fermé';
        default: return ucfirst($statut);
    }
}

function getPrioriteColor($priorite) {
    switch ($priorite) {
        case 'basse': return '#27ae60';
        case 'normale': return '#3498db';
        case 'haute': return '#f39c12';
        case 'urgente': return '#e74c3c';
        default: return '#7f8c8d';
    }
}

require_once '../../includes/footer.php';
?>
