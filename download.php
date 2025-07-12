<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

// Vérification de l'identifiant du fichier
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Fichier invalide.');
}
$fileId = (int)$_GET['id'];

// Récupération des infos du fichier
$file = fetchOne("SELECT * FROM pieces_jointes WHERE id = ?", [$fileId]);
if (!$file || !file_exists($file['chemin'])) {
    error_log('DOWNLOAD 404: id=' . $fileId . ' chemin=' . ($file['chemin'] ?? 'null'));
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Vérification d'accès au dossier
if (!canAccessDossier($_SESSION['user_id'], $file['dossier_id'])) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Envoi du fichier
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['nom_fichier']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file['chemin']));
readfile($file['chemin']);
exit;
