<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (php_sapi_name() !== 'cli') {
    exit("Este script deve ser executado via linha de comando.\n");
}

[$script, $name, $email, $password] = array_pad($argv, 4, null);

if (!$name || !$email || !$password) {
    exit("Uso: php scripts/create_admin.php \"Nome do Admin\" email@dominio.com senha\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("E-mail invlido.\n");
}

$pdo = get_pdo();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        exit("J existe um usuário com este e-mail.\n");
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :hash, :role, 1)');
    $insert->execute([
        'name' => $name,
        'email' => $email,
        'hash' => $hash,
        'role' => 'admin',
    ]);
    $pdo->commit();
    echo "Usuário administrador criado com sucesso.\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    echo 'Erro: ' . $exception->getMessage() . "\n";
}
