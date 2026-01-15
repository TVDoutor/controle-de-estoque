<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function session_bootstrap(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);

        // Configure secure cookie parameters when running under HTTPS
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $cookieParams = session_get_cookie_params();
        $cookieParams['secure'] = $secure;
        $cookieParams['httponly'] = true;
        // Use Lax to allow top-level GET navigations but protect POST/CSRF.
        $cookieParams['samesite'] = 'Lax';

        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'] ?? 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'],
        ]);

        session_start();
    }
}
