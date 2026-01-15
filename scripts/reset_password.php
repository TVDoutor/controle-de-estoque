<?php
require __DIR__ . '/../includes/database.php';
$pdo = get_pdo();
$hash = password_hash('B2510r28e!', PASSWORD_BCRYPT);
$pdo->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')->execute(['hash' => $hash, 'email' => 'hil.cardoso@gmail.com']);
echo 'done';
