<?php

declare(strict_types=1);

namespace Millacoco\Admin\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Public API: Returns active products with their external marketplace URLs
     */
    public function getPublicProducts(Request $request, Response $response): Response
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, tag, category, price, image, external_url, platform_name, is_active 
             FROM products 
             WHERE is_active = 1 
             ORDER BY sort_order ASC, id ASC"
        );
        $products = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $products
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: List all products
     */
    public function index(Request $request, Response $response): Response
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, tag, category, price, image, external_url, platform_name, is_active, sort_order 
             FROM products 
             ORDER BY sort_order ASC, id ASC"
        );
        $products = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $products
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin API: Update a product's external redirect URL, platform name, and price
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $productId = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();

        $externalUrl = filter_var(trim($data['external_url'] ?? ''), FILTER_SANITIZE_URL);
        $platformName = htmlspecialchars(trim($data['platform_name'] ?? 'Amazon'), ENT_QUOTES, 'UTF-8');
        $price = filter_var($data['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if (empty($externalUrl) || !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Please provide a valid destination URL (e.g. https://amazon.in/dp/...)'
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare(
            "UPDATE products 
             SET external_url = :external_url,
                 platform_name = :platform_name,
                 price = :price,
                 is_active = :is_active,
                 updated_at = NOW() 
             WHERE id = :id"
        );

        $stmt->execute([
            'external_url' => $externalUrl,
            'platform_name' => $platformName,
            'price' => $price,
            'is_active' => $isActive,
            'id' => $productId
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Product redirect URL updated successfully.'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
