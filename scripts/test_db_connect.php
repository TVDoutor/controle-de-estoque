<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "CONNECTED\n";
} catch (PDOException $e) {
    echo "PDO ERROR: " . $e->getMessage() . PHP_EOL;
}
