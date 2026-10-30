<?php

declare(strict_types=1);

namespace Millacoco\Admin\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;
    private string $storageDir;

    public function __construct(int $maxAttempts = 5, int $decaySeconds = 60, ?string $storageDir = null)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/millacoco_ratelimit';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $key = 'rate_' . hash('sha256', $clientIp . '_' . $request->getUri()->getPath());
        $cacheFile = $this->storageDir . '/' . $key . '.json';

        $currentTime = time();
        $attempts = [];

        if (file_exists($cacheFile)) {
            $data = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($data)) {
                // Filter out expired timestamps older than decaySeconds window
                $attempts = array_filter($data, fn($ts) => ($currentTime - $ts) < $this->decaySeconds);
            }
        }

        // Check if rate limit threshold exceeded
        if (count($attempts) >= $this->maxAttempts) {
            $oldestAttempt = reset($attempts);
            $retryAfter = max(1, $this->decaySeconds - ($currentTime - $oldestAttempt));

            $response = new SlimResponse(429);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Too many requests. Please try again later.',
                'retry_after_seconds' => $retryAfter,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        // Record this attempt
        $attempts[] = $currentTime;
        @file_put_contents($cacheFile, json_encode($attempts), LOCK_EX);

        $response = $handler->handle($request);

        $remaining = max(0, $this->maxAttempts - count($attempts));
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining);
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check trusted reverse-proxy headers if present
        if (!empty($serverParams['HTTP_CF_CONNECTING_IP'])) {
            return $serverParams['HTTP_CF_CONNECTING_IP'];
        }

        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
