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

    /**
     * Admin: Update password verifying current password and confirming new password
     */
    public function updatePassword(Request $request, Response $response): Response
    {
        $jwtUser = $request->getAttribute('jwt_user');
        $userId = $jwtUser->sub ?? null;

        if (!$userId) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Unauthorized user session.'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $data = (array)$request->getParsedBody();
        $currentPassword = (string)($data['current_password'] ?? '');
        $newPassword = (string)($data['new_password'] ?? '');
        $confirmPassword = (string)($data['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Please provide current password, new password, and confirmation.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        if ($newPassword !== $confirmPassword) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'New password and confirmation password do not match.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        if (strlen($newPassword) < 6) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'New password must be at least 6 characters long.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Fetch current hash from database
        $stmt = $this->db->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Current password entered is incorrect.'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Hash new password with bcrypt
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateStmt = $this->db->prepare('UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([
            ':hash' => $newHash,
            ':id' => $userId
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Password updated successfully! Please use your new password next time you sign in.'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
