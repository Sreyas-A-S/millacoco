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

        // 1. Verify CAPTCHA Code
        $captchaToken = (string)($data['captcha_token'] ?? '');
        $captchaAnswer = strtoupper(trim((string)($data['captcha_answer'] ?? '')));

        if (empty($captchaToken) || empty($captchaAnswer)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Please enter the verification security code.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $tokenData = json_decode((string)base64_decode($captchaToken), true);
        if (!is_array($tokenData) || empty($tokenData['sig']) || empty($tokenData['t'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Invalid security token. Please refresh the code.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Expire challenge after 5 minutes
        if ((time() - (int)$tokenData['t']) > 300) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Security code has expired. Please refresh and try again.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $secret = $this->settings['jwt']['secret'] ?? 'millacoco-admin-secret-2026';
        $expectedSig = hash_hmac('sha256', $captchaAnswer . '|' . $tokenData['t'], $secret);

        if (!hash_equals($expectedSig, $tokenData['sig'])) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Incorrect verification code. Please check and try again.'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

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

    /**
     * Generate an encrypted visual SVG challenge
     */
    public function getCaptcha(Request $request, Response $response): Response
    {
        // 5 unambiguous alphanumeric characters (excludes easily confused letters like 0/O, 1/I)
        $charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        $maxIndex = strlen($charset) - 1;
        for ($i = 0; $i < 5; $i++) {
            $code .= $charset[random_int(0, $maxIndex)];
        }

        $time = time();
        $secret = $this->settings['jwt']['secret'] ?? 'millacoco-admin-secret-2026';
        $sig = hash_hmac('sha256', $code . '|' . $time, $secret);
        $token = base64_encode(json_encode(['sig' => $sig, 't' => $time]));

        $width = 170;
        $height = 46;
        $charElements = '';
        $colors = ['#611B10', '#852112', '#1C3822', '#26542D', '#916317'];

        // Noise lines
        $noiseLines = '';
        for ($i = 0; $i < 3; $i++) {
            $x1 = random_int(5, 40);
            $y1 = random_int(5, $height - 5);
            $x2 = random_int($width - 40, $width - 5);
            $y2 = random_int(5, $height - 5);
            $lineColor = $colors[random_int(0, count($colors) - 1)];
            $noiseLines .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='{$lineColor}' stroke-width='1.5' stroke-opacity='0.25' />";
        }

        $step = ($width - 30) / 5;
        for ($i = 0; $i < 5; $i++) {
            $char = $code[$i];
            $x = 18 + ($i * $step) + random_int(-2, 2);
            $y = 31 + random_int(-3, 3);
            $rot = random_int(-14, 14);
            $col = $colors[$i % count($colors)];
            $charElements .= "<text x='{$x}' y='{$y}' font-family='monospace, sans-serif' font-weight='800' font-size='22' fill='{$col}' transform='rotate({$rot}, {$x}, {$y})' letter-spacing='2'>{$char}</text>";
        }

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}' viewBox='0 0 {$width} {$height}' style='border-radius:10px; background:#FAF5EC; display:block;'>
            <rect width='100%' height='100%' fill='#FAF5EC' rx='10' stroke='#D4A853' stroke-opacity='0.4' stroke-width='1'/>
            {$noiseLines}
            {$charElements}
        </svg>";

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'captcha_token' => $token,
            'captcha_svg' => $svg
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
