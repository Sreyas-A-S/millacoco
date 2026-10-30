<?php

declare(strict_types=1);

namespace Millacoco\Admin\Models;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(array $config): PDO
    {
        if (self::$instance === null) {
            $dbPath = $config['path'] ?? __DIR__ . '/../../database/millacoco.sqlite';
            $dbDir = dirname($dbPath);

            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }

            $isNewDatabase = !file_exists($dbPath);

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Uses native prepared statements
            ];

            try {
                self::$instance = new PDO('sqlite:' . $dbPath, null, null, $options);
                
                // SQLite specific performance & concurrency optimizations
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA journal_mode = WAL;'); // Write-Ahead Logging for high concurrent read performance

                if ($isNewDatabase) {
                    self::initializeSchema(self::$instance);
                }
            } catch (PDOException $e) {
                error_log('SQLite Connection Error: ' . $e->getMessage());
                throw new \RuntimeException('Database connection could not be established.');
            }
        }

        return self::$instance;
    }

    private static function initializeSchema(PDO $pdo): void
    {
        $defaultPasswordHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);

        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS document_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            code TEXT NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            tag TEXT DEFAULT 'Bestseller',
            category TEXT DEFAULT 'daily',
            price REAL NOT NULL DEFAULT 0.0,
            image TEXT NOT NULL,
            description TEXT,
            external_url TEXT NOT NULL DEFAULT 'https://wa.me/918089757815',
            platform_name TEXT NOT NULL DEFAULT 'Amazon',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Document Types Seed Data
        INSERT OR IGNORE INTO document_types (id, name, code, description) VALUES
        (1, 'Aadhaar Card', 'AADHAAR', 'Government issued national identity card'),
        (2, 'PAN Card', 'PAN', 'Income tax department permanent account number'),
        (3, 'GST Registration Certificate', 'GST_CERT', 'Goods and services tax business certificate'),
        (4, 'FSSAI License', 'FSSAI', 'Food safety authority compliance license');

        -- Initial Product Showcase Catalog with Amazon Links
        INSERT OR IGNORE INTO products (id, name, slug, tag, category, price, image, description, external_url, platform_name, sort_order) VALUES
        (1, 'Millacoco 1 Ltr Kitchen Pouch', '1-ltr-kitchen-pouch', 'Spout Pouch', 'daily', 280.00, 'images/product_pouch.png', 'Easy-pour spout pack for cooking.', 'https://www.amazon.in/dp/B0DXXXXXX1', 'Amazon', 1),
        (2, 'Millacoco 1 Ltr Classic Bottle', '1-ltr-classic-bottle', 'Bestseller', 'daily', 290.00, 'images/product_bottle.png', 'Daily kitchen staple & massage oil.', 'https://www.amazon.in/dp/B0DXXXXXX2', 'Amazon', 2),
        (3, 'Millacoco 2 Ltr Twin Pack (1L x 2)', '2-ltr-twin-pack', 'Twin Value', 'bulk', 570.00, 'images/twin_pack.jpg', 'Save on regular supply with twin pack.', 'https://www.amazon.in/dp/B0DXXXXXX3', 'Amazon', 3),
        (4, 'Millacoco 5 Ltr Household Can', '5-ltr-household-can', 'Bulk Supply', 'bulk', 1390.00, 'images/can_5ltr.jpg', 'Heavy-duty leakproof can for large families.', 'https://www.amazon.in/dp/B0DXXXXXX4', 'Amazon', 4),
        (5, 'Millacoco Virgin Hair & Face Oil', 'virgin-hair-face-oil', 'Skincare', 'daily', 320.00, 'images/virgin_oil.jpg', 'Deep scalp conditioning and cold-pressed skincare.', 'https://www.amazon.in/dp/B0DXXXXXX5', 'Amazon', 5);
SQL;
        $pdo->exec($schema);

        // Insert default admin user with safe prepared statement
        $userStmt = $pdo->prepare('INSERT OR IGNORE INTO users (id, name, email, password_hash, role) VALUES (1, :name, :email, :hash, :role)');
        $userStmt->execute([
            'name' => 'Millacoco Admin',
            'email' => 'admin@millacoco.com',
            'hash' => $defaultPasswordHash,
            'role' => 'admin'
        ]);
    }
}
