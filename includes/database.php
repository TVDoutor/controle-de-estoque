<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        http_response_code(500);
        $errorTitle = 'Não foi possível conectar ao banco de dados';
        $errorMessage = 'Encontramos um problema ao estabelecer conexão com o servidor MySQL.';
        $helpText = 'Verifique se as credenciais esto corretas (host, usuário, senha e banco) e tente novamente. Caso o problema persista, entre em contato com o suporte.';
        error_log('PDO Exception: ' . $exception->getMessage());
        require __DIR__ . '/../templates/error.php';
        exit;
    }

    return $pdo;
}


