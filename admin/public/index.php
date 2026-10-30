<?php

declare(strict_types=1);

use Millacoco\Admin\Controllers\AuthController;
use Millacoco\Admin\Controllers\DashboardController;
use Millacoco\Admin\Controllers\ProductController;
use Millacoco\Admin\Middleware\JwtAuthMiddleware;
use Millacoco\Admin\Middleware\RateLimitMiddleware;
use Millacoco\Admin\Middleware\SecurityHeadersMiddleware;
use Millacoco\Admin\Models\Database;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Load application configurations
$settings = require __DIR__ . '/../config/settings.php';

// Instantiate Slim Application
$app = AppFactory::create();
$app->setBasePath('/mc-desk');

// Global Security Headers Middleware
$app->add(new SecurityHeadersMiddleware());

// Add Body Parsing Middleware for JSON/Form requests
$app->addBodyParsingMiddleware();

// Built-in routing middleware
$app->addRoutingMiddleware();

// Production-safe Error Middleware (Display error details disabled for security)
$errorMiddleware = $app->addErrorMiddleware($settings['app']['debug'] ?? false, true, true);

// Custom 404 Not Found Handler (Returns clean 404 JSON for API or clean 404 HTML for browser)
$errorMiddleware->setErrorHandler(
    \Slim\Exception\HttpNotFoundException::class,
    function (Request $request, \Throwable $exception, bool $displayErrorDetails) {
        $response = new \Slim\Psr7\Response(404);
        $uri = $request->getUri()->getPath();

        // If an API endpoint was requested, return 404 JSON
        if (str_starts_with($uri, '/mc-desk/api')) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'API endpoint not found (404).'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // For browser page requests, serve the 404 page
        $notFoundHtml = file_exists(__DIR__ . '/../../404.html')
            ? file_get_contents(__DIR__ . '/../../404.html')
            : '<h1>404 Page Not Found</h1>';

        $response->getBody()->write($notFoundHtml);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
);

// Initialize DB (Lazy-loaded PDO instance)
$db = Database::getConnection($settings['db']);

// Auth & Product Controllers
$authController = new AuthController($db, $settings);
$dashboardController = new DashboardController($db);
$productController = new ProductController($db);

// --- Public Routes ---
// Endpoint for public storefront to fetch current active products & external URLs
$app->get('/api/products', [$productController, 'getPublicProducts']);

// Rate limit login endpoint: max 5 login attempts per 60 seconds per IP
$app->post('/api/login', [$authController, 'login'])
    ->add(new RateLimitMiddleware(maxAttempts: 5, decaySeconds: 60));

$app->get('/login', function (Request $request, Response $response) {
    $loginHtml = file_get_contents(__DIR__ . '/../src/Views/login.html');
    $response->getBody()->write($loginHtml);
    return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
});

// --- Protected Admin Routes (Guarded by JwtAuthMiddleware) ---
$app->group('', function ($group) use ($dashboardController, $productController) {
    $group->get('', [$dashboardController, 'index']);
    $group->get('/', [$dashboardController, 'index']);
    $group->get('/dashboard', [$dashboardController, 'index']);
    $group->get('/api/admin/products', [$productController, 'index']);
    $group->put('/api/admin/products/{id}', [$productController, 'update']);
})->add(new JwtAuthMiddleware($settings['jwt']));

$app->run();
