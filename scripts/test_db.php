<?php
require __DIR__ . '/../includes/database.php';
try {
    $pdo = get_pdo();
    echo 'ok';
} catch (Throwable $e) {
    echo 'fail: ' . $e->getMessage();
}
