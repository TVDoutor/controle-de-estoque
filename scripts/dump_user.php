<?php
require __DIR__ . '/../includes/database.php';
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT id,name,email,password_hash,role,is_active FROM users WHERE email = :email');
$stmt->execute(['email' => 'hil.cardoso@gmail.com']);
var_dump($stmt->fetch());
