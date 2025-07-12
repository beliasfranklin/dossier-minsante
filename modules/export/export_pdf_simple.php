<?php
/**
 * Export PDF simple et robuste
 */

require_once __DIR__ . '/../../includes/db.php';

// Fonction utilitaire pour vérifier les permissions de visualisation
if (!function_exists('canViewDossier')) {
    function canViewDossier($user_id, $user_role, $responsable_id) {
        return $user_role <= 2 || $user_id == $responsable_id; // Gestionnaire ou responsable
    }
}

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification basique
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Récupération de l'ID du dossier
$dossierId = (int)($_GET['id'] ?? 0);

if (!$dossierId) {
    $_SESSION['error'] = "ID de dossier invalide";
    header('Location: ../dossiers/list.php');
    exit;
}

try {
    // Récupération du dossier avec gestion d'erreurs
    $stmt = $pdo->prepare("
        SELECT d.*, 
               u1.prenom as created_by_prenom, u1.nom as created_by_nom,
               u2.prenom as responsable_prenom, u2.nom as responsable_nom
        FROM dossiers d
        LEFT JOIN users u1 ON d.created_by = u1.id
        LEFT JOIN users u2 ON d.responsable_id = u2.id
        WHERE d.id = ?
    ");
    $stmt->execute([$dossierId]);
    $dossier = $stmt->fetch();

    if (!$dossier) {
        $_SESSION['error'] = "Dossier non trouvé";
        header('Location: ../dossiers/list.php');
        exit;
    }

    // Vérification des permissions
    if (!canViewDossier($_SESSION['user_id'], $_SESSION['user_role'] ?? 3, $dossier['responsable_id'])) {
        $_SESSION['error'] = "Accès non autorisé";
        header('Location: ../dossiers/list.php');
        exit;
    }

    // Récupération des commentaires (si la table existe)
    $commentaires = [];
    try {
        $stmt = $pdo->prepare("
            SELECT dc.*, u.prenom, u.nom 
            FROM dossier_comments dc
            LEFT JOIN users u ON dc.user_id = u.id
            WHERE dc.dossier_id = ?
            ORDER BY dc.created_at DESC
        ");
        $stmt->execute([$dossierId]);
        $commentaires = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table n'existe pas, continuer sans commentaires
    }

    // Récupération des logs (si la table existe)
    $logs = [];
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u.prenom, u.nom
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.dossier_id = ?
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$dossierId]);
        $logs = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table n'existe pas, continuer sans logs
    }

} catch (PDOException $e) {
    error_log("Erreur export PDF: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la récupération des données";
    header('Location: ../dossiers/list.php');
    exit;
}

// Configuration de l'affichage
header('Content-Type: text/html; charset=utf-8');

// Fonction pour obtenir la couleur du statut
function getStatusColor($status) {
    switch ($status) {
        case 'brouillon': return '#6c757d';
        case 'en_cours': return '#17a2b8';
        case 'en_attente': return '#ffc107';
        case 'valide': return '#28a745';
        case 'rejete': return '#dc3545';
        default: return '#6c757d';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dossier <?= htmlspecialchars($dossier['titre']) ?> - Export PDF</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.6;
        }
        .print-info { 
            background: #e3f2fd; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #2980b9; 
            padding-bottom: 20px; 
            margin-bottom: 30px;
        }
        .title { 
            color: #2980b9; 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 10px;
        }
        .subtitle { 
            color: #666; 
            font-size: 14px;
        }
        .section { 
            margin-bottom: 25px; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
        }
        .section-title { 
            color: #2980b9; 
            font-size: 16px; 
            font-weight: bold; 
            margin-bottom: 15px; 
            border-bottom: 1px solid #eee; 
            padding-bottom: 5px;
        }
        .info-row { 
            margin-bottom: 8px; 
        }
        .label { 
            font-weight: bold; 
            color: #333; 
            width: 150px; 
            display: inline-block;
        }
        .value { 
            color: #666; 
        }
        .status { 
            padding: 4px 8px; 
            border-radius: 3px; 
            color: white; 
            font-weight: bold;
        }
        .comment { 
            background: #f8f9fa; 
            padding: 10px; 
            margin-bottom: 10px; 
            border-left: 3px solid #2980b9; 
            border-radius: 3px;
        }
        .comment-meta { 
            font-size: 12px; 
            color: #666; 
            margin-bottom: 5px;
        }
        .log-entry { 
            background: #f1f3f4; 
            padding: 8px; 
            margin-bottom: 5px; 
            border-radius: 3px; 
            font-size: 13px;
        }
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            color: #666; 
            font-size: 12px; 
            border-top: 1px solid #ddd; 
            padding-top: 15px;
        }
        .no-print { 
            display: block; 
        }
        @media print {
            .print-info, .no-print { 
                display: none; 
            }
        }
    </style>
</head>
<body>
    <div class="print-info no-print">
        <h3>📄 Document d'Export - Dossier #<?= $dossierId ?></h3>
        <p>Ce document peut être imprimé ou sauvegardé en PDF via votre navigateur.</p>
        <button onclick="window.print()" style="background:#2196f3;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;">
            🖨️ Imprimer / Sauvegarder en PDF
        </button>
        <button onclick="window.close()" style="background:#666;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;margin-left:10px;">
            ✖️ Fermer
        </button>
        <hr style="margin: 20px 0;">
    </div>

    <div class="header">
        <div class="title">SYSTÈME DE GESTION DES DOSSIERS</div>
        <div class="subtitle">MINISTÈRE DE LA SANTÉ PUBLIQUE</div>
    </div>

    <div class="section">
        <div class="section-title">Informations du Dossier</div>
        <div class="info-row">
            <span class="label">ID:</span>
            <span class="value"><?= $dossier['id'] ?></span>
        </div>
        <div class="info-row">
            <span class="label">Titre:</span>
            <span class="value"><?= htmlspecialchars($dossier['titre']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Description:</span>
            <span class="value"><?= htmlspecialchars($dossier['description'] ?? 'Non spécifiée') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Statut:</span>
            <span class="status" style="background-color: <?= getStatusColor($dossier['status']) ?>">
                <?= strtoupper($dossier['status']) ?>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Priorité:</span>
            <span class="value"><?= ucfirst($dossier['priority'] ?? 'normale') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Créé le:</span>
            <span class="value"><?= date('d/m/Y à H:i', strtotime($dossier['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Créé par:</span>
            <span class="value"><?= htmlspecialchars(($dossier['created_by_prenom'] ?? '') . ' ' . ($dossier['created_by_nom'] ?? '')) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Responsable:</span>
            <span class="value"><?= htmlspecialchars(($dossier['responsable_prenom'] ?? '') . ' ' . ($dossier['responsable_nom'] ?? '')) ?></span>
        </div>
        <?php if (!empty($dossier['deadline'])): ?>
        <div class="info-row">
            <span class="label">Échéance:</span>
            <span class="value"><?= date('d/m/Y', strtotime($dossier['deadline'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($commentaires)): ?>
    <div class="section">
        <div class="section-title">Commentaires (<?= count($commentaires) ?>)</div>
        <?php foreach ($commentaires as $comment): ?>
        <div class="comment">
            <div class="comment-meta">
                Par <?= htmlspecialchars(($comment['prenom'] ?? '') . ' ' . ($comment['nom'] ?? 'Utilisateur')) ?> 
                le <?= date('d/m/Y à H:i', strtotime($comment['created_at'])) ?>
            </div>
            <div><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($logs)): ?>
    <div class="section">
        <div class="section-title">Historique des Actions (<?= count($logs) ?>)</div>
        <?php foreach ($logs as $log): ?>
        <div class="log-entry">
            <strong><?= htmlspecialchars($log['action']) ?></strong> 
            par <?= htmlspecialchars(($log['prenom'] ?? '') . ' ' . ($log['nom'] ?? 'Système')) ?> 
            le <?= date('d/m/Y à H:i', strtotime($log['created_at'])) ?>
            <?php if (!empty($log['details'])): ?>
                <br><em><?= htmlspecialchars($log['details']) ?></em>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        Document généré le <?= date('d/m/Y à H:i:s') ?> par <?= htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur') ?><br>
        MINSANTE - Système de Gestion des Dossiers
    </div>
</body>
</html>
