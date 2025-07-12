<?php
require_once __DIR__ . '/../../../includes/config.php';
requireAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Fichier invalide.');
}
$fileId = (int)$_GET['id'];

$file = fetchOne("SELECT * FROM pieces_jointes WHERE id = ?", [$fileId]);
if (!$file) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Vérification d'accès au dossier
if (!canAccessDossier($_SESSION['user_id'], $file['dossier_id'])) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Suppression du fichier physique
if (file_exists($file['chemin'])) {
    unlink($file['chemin']);
}

// Suppression de l'entrée en base
executeQuery("DELETE FROM pieces_jointes WHERE id = ?", [$fileId]);

// Log la suppression de la pièce jointe
logAction(
    $_SESSION['user_id'],
    'suppression du fichier',
    $file['dossier_id'],
    'suppression du fichier : ' . $file['nom_fichier']
);

// Redirection vers la page du dossier
header("Location: ../view.php?id=" . $file['dossier_id']);
exit;