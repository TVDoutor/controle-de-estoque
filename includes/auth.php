<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function login(string $email, string $password): bool
{
    session_bootstrap();

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['is_active']) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    return true;
}

function logout(): void
{
    session_bootstrap();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function enforce_session_timeout(): void
{
    session_bootstrap();

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $timeout = defined('SESSION_IDLE_TIMEOUT') ? (int) SESSION_IDLE_TIMEOUT : 0;
    if ($timeout <= 0) {
        $_SESSION['last_activity'] = time();
        return;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity && (time() - $lastActivity) > $timeout) {
        logout();
        flash('auth_notice', 'Sua sessao expirou por inatividade. Faca login novamente.', 'warning');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = time();
}

function current_user(): ?array
{
    session_bootstrap();

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;

    if ($cachedUser && $cachedUser['id'] === $_SESSION['user_id']) {
        return $cachedUser;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, name, email, role, phone, is_active, last_login, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $cachedUser = $stmt->fetch() ?: null;

    if (!$cachedUser) {
        logout();
    }

    return $cachedUser;
}

function user_has_role(array|string $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $roles = (array) $roles;
    return in_array($user['role'], $roles, true);
}

function require_login(array|string $roles = []): void
{
    enforce_session_timeout();

    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if ($roles) {
        $roles = (array) $roles;
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('Você não tem permissão para acessar esta área.');
        }
    }
}

function ensure_csrf_token(): string
{
    session_bootstrap();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool
{
    session_bootstrap();
    if (empty($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}






