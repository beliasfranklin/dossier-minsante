<?php
require_once __DIR__ . '/../../../includes/config.php';
requireAuth();

// Vérification de l'ID du dossier
$dossierId = isset($_POST['dossier_id']) ? (int)$_POST['dossier_id'] : 0;
if ($dossierId <= 0) {
    http_response_code(400);
    exit('ID de dossier invalide.');
}

// Vérification des droits d'accès
if (!canAccessDossier($_SESSION['user_id'], $dossierId, true)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Vérification du fichier
if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('Aucun fichier valide.');
}

$file = $_FILES['attachment'];
$maxSize = 10 * 1024 * 1024; // 10 Mo
$allowedTypes = [
    'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip', 'application/x-rar-compressed', 'text/plain'
];

if ($file['size'] > $maxSize) {
    http_response_code(400);
    exit('Fichier trop volumineux (max 10 Mo).');
}
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    exit('Type de fichier non autorisé.');
}

// Génération d'un nom de fichier unique
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
$uniqueName = uniqid('pj_') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $baseName) . '.' . $ext;
$uploadDir = __DIR__ . '/../../../uploads/dossiers/' . $dossierId;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}
$destPath = $uploadDir . '/' . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    exit('Erreur lors de l\'enregistrement du fichier.');
}

// Enregistrement en base
executeQuery(
    "INSERT INTO pieces_jointes (dossier_id, nom_fichier, chemin, type, taille, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
    [$dossierId, $file['name'], $destPath, $file['type'], $file['size'], $_SESSION['user_id']]
);

// Log et réponse
logAction(
    $_SESSION['user_id'],
    'chargement du fichier',
    $dossierId,
    'chargement du fichier : ' . $file['name']
);
header('Location: ../edit.php?id=' . $dossierId . '&pj=ok');
exit();
