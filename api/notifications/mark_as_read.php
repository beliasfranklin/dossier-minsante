<?php
require_once __DIR__.'/../../includes/config.php';
requireAuth();

if (isset($_GET['id'])) {
    markAsRead($_GET['id']);
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);