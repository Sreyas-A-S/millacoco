<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private PDO $db;
    private array $settings;

    public function __construct(PDO $db, array $settings)
    {
        $this->db = $db;
        $this->settings = $settings;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Email and password are required.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Strict prepared query - avoids any SQL injection
        $stmt = $this->db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Timing-attack safe password verification
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Invalid email or credentials.'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Issue secure JWT token
        $issuedAt = time();
        $expireAt = $issuedAt + ($this->settings['jwt']['expiry'] ?? 86400);

        $payload = [
            'iss' => $this->settings['jwt']['issuer'] ?? 'millacoco-admin',
            'iat' => $issuedAt,
            'exp' => $expireAt,
            'sub' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];

        $token = JWT::encode($payload, $this->settings['jwt']['secret'], $this->settings['jwt']['algorithm']);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            // Set secure, HttpOnly, SameSite=Strict cookie for browser sessions
            ->withHeader('Set-Cookie', "admin_jwt={$token}; Path=/mc-desk; HttpOnly; SameSite=Strict; Max-Age=86400");
    }
}
