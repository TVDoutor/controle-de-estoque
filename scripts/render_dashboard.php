<?php
require __DIR__ . '/../includes/auth.php';
session_bootstrap();
$_SESSION['user_id'] = 1;
ob_start();
require __DIR__ . '/../public/dashboard.php';
file_put_contents(__DIR__ . '/../tmp_dashboard.html', ob_get_clean());
echo 'rendered';
