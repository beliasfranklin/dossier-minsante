<?php
/**
 * Export PDF d'un dossier
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Inclure mPDF
require_once __DIR__ . '/../../libs/mpdf/autoload.php';

requireRole(ROLE_CONSULTANT);

// Fonction utilitaire pour vérifier les permissions de visualisation
if (!function_exists('canViewDossier')) {
    function canViewDossier($user_id, $user_role, $responsable_id) {
        return $user_role <= ROLE_GESTIONNAIRE || $user_id == $responsable_id;
    }
}

// Récupération de l'ID du dossier
$dossierId = (int)$_GET['id'];

if (!$dossierId) {
    $_SESSION['error'] = "ID de dossier invalide";
    header('Location: ../dossiers/list.php');
    exit;
}

// Récupération du dossier avec toutes les informations
$stmt = $pdo->prepare("
    SELECT d.*, 
           u1.prenom as created_by_prenom, u1.nom as created_by_nom,
           u2.prenom as responsable_prenom, u2.nom as responsable_nom,
           c.nom as category_name, c.couleur as category_color
    FROM dossiers d
    LEFT JOIN users u1 ON d.created_by = u1.id
    LEFT JOIN users u2 ON d.responsable_id = u2.id
    LEFT JOIN categories c ON d.category_id = c.id
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
if (!canViewDossier($_SESSION['user_id'], $_SESSION['user_role'], $dossier['responsable_id'])) {
    $_SESSION['error'] = "Accès non autorisé";
    header('Location: ../dossiers/list.php');
    exit;
}

// Récupération des logs
$stmt = $pdo->prepare("
    SELECT l.*, u.prenom, u.nom
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.dossier_id = ?
    ORDER BY l.created_at DESC
    LIMIT 20
");
$stmt->execute([$dossierId]);
$logs = $stmt->fetchAll();

// Récupération des commentaires
$stmt = $pdo->prepare("
    SELECT dc.*, u.prenom, u.nom 
    FROM dossier_comments dc
    LEFT JOIN users u ON dc.user_id = u.id
    WHERE dc.dossier_id = ?
    ORDER BY dc.created_at DESC
");
$stmt->execute([$dossierId]);
$commentaires = $stmt->fetchAll();

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

// Configuration PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="dossier_' . $dossierId . '_' . date('Y-m-d') . '.pdf"');

// Générer le contenu HTML pour le PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dossier <?= htmlspecialchars($dossier['titre']) ?></title>
    <style>
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            margin: 20px;
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
            font-size: 10px; 
            color: #666; 
            margin-bottom: 5px;
        }
        .log-entry { 
            background: #f1f3f4; 
            padding: 8px; 
            margin-bottom: 5px; 
            border-radius: 3px; 
            font-size: 11px;
        }
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            color: #666; 
            font-size: 10px; 
            border-top: 1px solid #ddd; 
            padding-top: 15px;
        }
    </style>
</head>
<body>
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
            <span class="value"><?= htmlspecialchars($dossier['description']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Statut:</span>
            <span class="status" style="background-color: <?= getStatusColor($dossier['status']) ?>">
                <?= strtoupper($dossier['status']) ?>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Priorité:</span>
            <span class="value"><?= ucfirst($dossier['priority']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Créé le:</span>
            <span class="value"><?= date('d/m/Y à H:i', strtotime($dossier['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Créé par:</span>
            <span class="value"><?= htmlspecialchars($dossier['created_by_prenom'] . ' ' . $dossier['created_by_nom']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Responsable:</span>
            <span class="value"><?= htmlspecialchars($dossier['responsable_prenom'] . ' ' . $dossier['responsable_nom']) ?></span>
        </div>
        <?php if ($dossier['deadline']): ?>
        <div class="info-row">
            <span class="label">Échéance:</span>
            <span class="value"><?= date('d/m/Y', strtotime($dossier['deadline'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($dossier['category_name']): ?>
        <div class="info-row">
            <span class="label">Catégorie:</span>
            <span class="value"><?= htmlspecialchars($dossier['category_name']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($commentaires)): ?>
    <div class="section">
        <div class="section-title">Commentaires (<?= count($commentaires) ?>)</div>
        <?php foreach ($commentaires as $comment): ?>
        <div class="comment">
            <div class="comment-meta">
                Par <?= htmlspecialchars($comment['prenom'] . ' ' . $comment['nom']) ?> 
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
            par <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?> 
            le <?= date('d/m/Y à H:i', strtotime($log['created_at'])) ?>
            <?php if ($log['details']): ?>
                <br><em><?= htmlspecialchars($log['details']) ?></em>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        Document généré le <?= date('d/m/Y à H:i:s') ?> par <?= htmlspecialchars($_SESSION['user_name'] ?? 'Système') ?><br>
        <?= BASE_URL ?> - MINSANTE - Système de Gestion des Dossiers
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Utiliser mPDF pour générer le PDF
try {
    // Créer une instance mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 16,
        'margin_bottom' => 16,
        'orientation' => 'P'
    ]);
    
    // Écrire le HTML dans le PDF
    $mpdf->WriteHTML($html);
    
    // Définir le nom du fichier
    $filename = 'dossier_' . $dossierId . '_' . date('Y-m-d_H-i') . '.pdf';
    
    // Forcer le téléchargement du PDF
    $mpdf->Output($filename, 'D');
    exit;
    
} catch (Exception $e) {
    error_log("Erreur mPDF: " . $e->getMessage());
    
    // En cas d'erreur, afficher un message et proposer l'alternative HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="background:#ffebee;color:#c62828;padding:20px;border-radius:5px;margin:20px;border-left:4px solid #f44336;">';
    echo '<h3>⚠️ Erreur lors de la génération du PDF</h3>';
    echo '<p><strong>Erreur:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Solution:</strong> <a href="export_pdf_simple.php?id=' . $dossierId . '" style="color:#1976d2;">Utiliser l\'export HTML simple</a></p>';
    echo '<p><a href="../dossiers/view.php?id=' . $dossierId . '" style="color:#1976d2;">← Retour au dossier</a></p>';
    echo '</div>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dossier <?= htmlspecialchars($dossier['titre']) ?> - Aperçu PDF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .print-info { 
            background: #e3f2fd; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        @media print {
            .print-info { display: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-info no-print">
        <h3>📄 Aperçu du Document PDF</h3>
        <p><strong>mPDF n'est pas installé.</strong> Vous pouvez :</p>
        <ul>
            <li><strong>Imprimer cette page</strong> en PDF (Ctrl+P → Enregistrer en PDF)</li>
            <li>Installer mPDF avec Composer : <code>composer require mpdf/mpdf</code></li>
        </ul>
        <button onclick="window.print()" style="background:#2196f3;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;">
            🖨️ Imprimer / Sauvegarder en PDF
        </button>
        <button onclick="window.close()" style="background:#666;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;margin-left:10px;">
            ✖️ Fermer
        </button>
        <hr style="margin: 20px 0;">
    </div>
    
    <?= $html ?>
</body>
</html>
