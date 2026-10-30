<?php

declare(strict_types=1);

namespace Millacoco\Admin\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class JwtAuthMiddleware implements MiddlewareInterface
{
    private array $jwtConfig;

    public function __construct(array $jwtConfig)
    {
        $this->jwtConfig = $jwtConfig;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('Missing or invalid authorization token.');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtConfig['secret'], $this->jwtConfig['algorithm']));
            // Attach validated user credentials to the request attributes
            $request = $request->withAttribute('jwt_user', $decoded);
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid or expired authentication token.');
        }
    }

    private function extractToken(Request $request): ?string
    {
        // 1. Check Authorization Bearer header
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // 2. Check secure HttpOnly cookie fallback for browser navigations
        $cookies = $request->getCookieParams();
        if (!empty($cookies['admin_jwt'])) {
            return trim($cookies['admin_jwt']);
        }

        return null;
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse(401);
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message,
            'redirect' => '/admin/login'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
