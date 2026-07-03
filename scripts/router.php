<?php
// simple router for PHP built-in server to handle .php extension automatically
// Run from www directory: php -S localhost:9000 ../scripts/router.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $uri;

// Handle static files first - if file exists, let built-in server serve it
if (file_exists($file) && is_file($file)) {
    return false; // Let built-in server handle it
}

// Handle root path as index.php
if ($uri === '/') {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/index.php')) {
        include $_SERVER['DOCUMENT_ROOT'] . '/index.php';
        return true;
    }
}

// Only process extensionless URLs (potential PHP pages)
$pathinfo = pathinfo($uri);
if (!isset($pathinfo['extension'])) {
    // Try adding .php extension
    if (file_exists($file . '.php')) {
        include $file . '.php';
        return true;
    }
}

// 404 for missing pages only (not static assets)
if (!isset($pathinfo['extension'])) {
    http_response_code(404);
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/404.php';
    return true;
}

// Let built-in server handle missing static assets
return false;
?>