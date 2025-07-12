<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_POST['recipient_id']) ||
    empty($_POST['subject']) ||
    empty($_POST['content'])) {
    die('Requête invalide. Veuillez soumettre le formulaire de messagerie.');
}

$recipientId = (int)$_POST['recipient_id'];
$subject = cleanInput($_POST['subject']);
$content = cleanInput($_POST['content']);

// Vérifier que le destinataire existe
$recipient = fetchOne("SELECT id FROM users WHERE id = ?", [$recipientId]);
if (!$recipient) {
    die("Destinataire invalide");
}

// Empêcher l'envoi à soi-même
if ($recipientId == $_SESSION['user_id']) {
    die("Vous ne pouvez pas vous envoyer un message à vous-même");
}

// Enregistrement
executeQuery(
    "INSERT INTO messages (sender_id, recipient_id, subject, content) 
     VALUES (?, ?, ?, ?)",
    [$_SESSION['user_id'], $recipientId, $subject, $content]
);

header("Location: list.php?sent=1");