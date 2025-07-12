<?php
require_once 'includes/config.php';
requireAuth();

header("Location: dashboard.php");
exit();
?>