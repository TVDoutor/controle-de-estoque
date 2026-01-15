<?php
declare(strict_types=1);

// TEMPORARY debug script - remove after troubleshooting
// Shows detailed DB connection errors and writes a small log file.

// Enable errors for debugging (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo '<h2 style="font-family:Arial,Helvetica,sans-serif;color:#2b6cb0;">Conexão bem sucedida ao MySQL!</h2>';
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    if ($tables) {
        echo '<p>Tabelas encontradas:</p><ul>';
        foreach ($tables as $t) {
            echo '<li>' . htmlspecialchars($t[0]) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Banco conectado, mas sem tabelas (importe schema.sql).</p>';
    }
} catch (PDOException $e) {
    // Show a helpful message and the real exception (temporary)
    echo '<h2 style="font-family:Arial,Helvetica,sans-serif;color:#b02b2b;">Erro ao conectar ao banco</h2>';
    echo '<p>Mensagem do PDO:</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p>Verifique as credenciais em <code>includes/config.php</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS) e se o banco existe no cPanel/phpMyAdmin.</p>';

    // Log to a file one directory up for inspection via File Manager
    $logfile = __DIR__ . '/../test_db_error.log';
    @file_put_contents($logfile, date('c') . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo '<p>Erro também registrado em: ' . htmlspecialchars($logfile) . '</p>';
}

// Reminder: remove this file after debugging
echo '<hr><p style="font-size:90%">Este arquivo é temporário. Remova-o após diagnosticar o problema.</p>';
