<?php
declare(strict_types=1);

// TEMPORARY debug script - root-level version
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Try to require includes/config.php from the same root (not public)
if (!file_exists(__DIR__ . '/includes/config.php')) {
    echo "includes/config.php not found at " . __DIR__ . '/includes/config.php';
    exit;
}

require_once __DIR__ . '/includes/config.php';

echo '<p>Loaded includes/config.php OK</p>';
echo '<p>DB_HOST: ' . htmlspecialchars(DB_HOST) . '</p>';
echo '<p>DB_NAME: ' . htmlspecialchars(DB_NAME) . '</p>';

// Do not attempt connection here; this is just an include/path test.
