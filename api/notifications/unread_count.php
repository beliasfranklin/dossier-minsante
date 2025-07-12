<?php
require_once __DIR__.'/../../includes/config.php';
requireAuth();

header('Content-Type: application/json');

$count = fetchOne("SELECT COUNT(*) as count FROM notifications 
                  WHERE user_id = ? AND is_read = FALSE", [$_SESSION['user_id']])['count'];

echo json_encode(['count' => $count]);