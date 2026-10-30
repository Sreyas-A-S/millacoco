<?php
// Unified local router for Millacoco Storefront and Slim 4 Admin Backend
// Usage: php -S localhost:8000 router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Forward /mc-desk and /mc-desk/* directly to Slim 4 Front Controller
if (str_starts_with($uri, '/mc-desk')) {
    require __DIR__ . '/admin/public/index.php';
    exit;
}

// 2. Serve static assets (css, js, images, svg, icons) directly
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Built-in web server serves the static file
}

// 3. Clean URL routing for storefront pages (no .html extensions required)
$cleanPath = trim($uri, '/');

if ($cleanPath === '' || $cleanPath === 'index' || $cleanPath === 'index.html') {
    require __DIR__ . '/index.html';
    exit;
}

$htmlFile = __DIR__ . '/' . $cleanPath . '.html';
if (file_exists($htmlFile)) {
    require $htmlFile;
    exit;
}

// 4. Custom 404 handler for unknown routes
http_response_code(404);
if (file_exists(__DIR__ . '/404.html')) {
    require __DIR__ . '/404.html';
} else {
    echo '<h1>404 Page Not Found</h1>';
}
exit;
