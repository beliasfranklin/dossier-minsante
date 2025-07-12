<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $dossier_id = (int)$_POST['dossier_id'];
    $new_status = $_POST['new_status'];
    $comment = $_POST['comment'] ?? '';
    
    if (!$dossier_id || !$new_status) {
        throw new Exception("Paramètres manquants");
    }
    
    // Récupérer le dossier actuel
    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
    $stmt->execute([$dossier_id]);
    $dossier = $stmt->fetch();
    
    if (!$dossier) {
        throw new Exception("Dossier non trouvé");
    }
    
    $current_status = $dossier['status'];
    
    // Vérifier si la transition est identique (pas de changement)
    if ($current_status === $new_status) {
        throw new Exception("Le statut est déjà '$new_status'");
    }
    
    // Vérifier si la transition est autorisée
    $stmt = $pdo->prepare("
        SELECT * FROM status_transitions 
        WHERE from_status = ? AND to_status = ? AND actif = 1
    ");
    $stmt->execute([$current_status, $new_status]);
    $transition = $stmt->fetch();
    
    if (!$transition) {
        throw new Exception("Transition non autorisée de '$current_status' vers '$new_status'");
    }
    
    // Vérifier les permissions de l'utilisateur
    $user_role = $_SESSION['role'];
    if ($user_role > $transition['role_requis']) {
        $required_role_name = match($transition['role_requis']) {
            1 => 'Administrateur',
            2 => 'Gestionnaire',
            3 => 'Consultant',
            default => 'Inconnu'
        };
        throw new Exception("Transition autorisée uniquement pour le rôle: $required_role_name");
    }
    
    // Vérifications supplémentaires selon le nouveau statut
    switch ($new_status) {
        case 'archive':
            // Seuls les admins peuvent archiver
            if ($user_role > ROLE_ADMIN) {
                throw new Exception("Seuls les administrateurs peuvent archiver");
            }
            break;
            
        case 'valide':
            // Vérifier que le dossier a tous les éléments requis
            if (empty($dossier['titre']) || empty($dossier['description'])) {
                throw new Exception("Le dossier doit avoir un titre et une description pour être validé");
            }
            break;
    }
    
    // Effectuer le changement de statut
    $pdo->beginTransaction();
    
    try {
        // Mettre à jour le statut
        $stmt = $pdo->prepare("
            UPDATE dossiers 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $dossier_id]);
        
        // Enregistrer dans l'historique
        logAction(
            $_SESSION['user_id'],
            'CHANGE_STATUS',
            $dossier_id,
            "Changement de statut: $current_status → $new_status" . 
            ($comment ? " | Commentaire: $comment" : "")
        );
        
        // Créer une notification pour le responsable si ce n'est pas lui qui fait le changement
        if ($dossier['responsable_id'] != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, titre, message, dossier_id) 
                VALUES (?, 'status_change', ?, ?, ?)
            ");
            
            $message = sprintf(
                "Le statut du dossier %s a été changé de '%s' vers '%s' par %s%s",
                $dossier['reference'],
                $current_status,
                $new_status,
                $_SESSION['prenom'] . ' ' . $_SESSION['nom'],
                $comment ? "\n\nCommentaire: $comment" : ""
            );
            
            $stmt->execute([
                $dossier['responsable_id'],
                "Changement de statut: " . $dossier['reference'],
                $message,
                $dossier_id
            ]);
        }
        
        // Gérer les actions automatiques selon le nouveau statut
        switch ($new_status) {
            case 'valide':
                // Calculer une nouvelle échéance si définie
                $stmt = $pdo->prepare("
                    SELECT delai_jours FROM echeances_config 
                    WHERE type_dossier = ? AND service = ? AND actif = 1
                ");
                $stmt->execute([$dossier['type'], $dossier['service']]);
                $config = $stmt->fetch();
                
                if ($config) {
                    $nouvelle_echeance = date('Y-m-d', strtotime("+{$config['delai_jours']} days"));
                    $stmt = $pdo->prepare("UPDATE dossiers SET deadline = ? WHERE id = ?");
                    $stmt->execute([$nouvelle_echeance, $dossier_id]);
                }
                break;
                
            case 'archive':
                // Nettoyer les alertes d'échéances
                $stmt = $pdo->prepare("DELETE FROM alertes_echeances WHERE dossier_id = ?");
                $stmt->execute([$dossier_id]);
                break;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Statut changé avec succès vers '$new_status'",
            'new_status' => $new_status,
            'status_label' => getStatusLabel($new_status),
            'status_color' => getStatusColor($new_status)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getStatusLabel($status) {
    $labels = [
        'en_cours' => 'En cours',
        'valide' => 'Validé',
        'rejete' => 'Rejeté',
        'archive' => 'Archivé'
    ];
    return $labels[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'en_cours' => 'warning',
        'valide' => 'success',
        'rejete' => 'danger',
        'archive' => 'secondary'
    ];
    return $colors[$status] ?? 'light';
}
?>
