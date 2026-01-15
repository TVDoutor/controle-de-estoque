<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function sanitize(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $key, ?string $message = null, string $type = 'info')
{
    session_bootstrap();

    if ($message === null) {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }

    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function format_datetime(?string $value, string $format = 'd/m/Y H:i'): string
{
    if (!$value) {
        return '-';
    }
    $date = new DateTime($value);
    return $date->format($format);
}

function format_date(?string $value, string $format = 'd/m/Y'): string
{
    if (!$value) {
        return '-';
    }
    $date = new DateTime($value);
    return $date->format($format);
}

function pagination(int $currentPage, int $totalPages): array
{
    $pages = [];
    for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
        $pages[] = $i;
    }
    return $pages;
}

