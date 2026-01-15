<?php

declare(strict_types=1);

// If server already routed to public/, just include directly
if (strpos(__DIR__, '/public') !== false || strpos(__FILE__, '/public') !== false) {
    require_once __DIR__ . '/public/index.php';
    exit;
}

// Try to include the public bootstrap. This keeps compatibility for hosts
// where DocumentRoot cannot be changed and mod_rewrite might be disabled.
$publicIndex = __DIR__ . '/public/index.php';
if (file_exists($publicIndex)) {
    require_once $publicIndex;
    exit;
}

// Fallback: show a minimal message if public index not found
http_response_code(500);
echo 'Application misconfigured: public/index.php not found.';
