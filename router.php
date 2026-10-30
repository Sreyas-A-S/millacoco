<?php
// Local router for PHP built-in server (php -S localhost:8000 router.php)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// 1. If file exists directly (e.g. style.css, script.js, images/*, products.html), serve it
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Tells PHP built-in webserver to serve the file directly
}

// 2. If it's the root URL, serve index.html
if ($uri === '/' || $uri === '/index.html') {
    require __DIR__ . '/index.html';
    exit;
}

// 3. For any unknown route, serve 404.html with HTTP 404 status code
http_response_code(404);
if (file_exists(__DIR__ . '/404.html')) {
    require __DIR__ . '/404.html';
} else {
    echo '<h1>404 Page Not Found</h1>';
}
exit;
