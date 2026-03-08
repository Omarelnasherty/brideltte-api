<?php
/**
 * Router for PHP built-in server
 * Replaces .htaccess rewrite rules
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve static files (uploads) directly
if (preg_match('/^\/uploads\//', $path) && file_exists(__DIR__ . $path)) {
    return false;
}

// Route everything else to index.php
require __DIR__ . '/index.php';
