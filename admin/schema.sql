-- Millacoco Database Schema for Showcase & External Redirect Model

CREATE TABLE IF NOT EXISTS `document_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'admin',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `tag` VARCHAR(50) DEFAULT 'Bestseller',
    `category` VARCHAR(50) DEFAULT 'daily',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `image` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `external_url` VARCHAR(500) NOT NULL DEFAULT 'https://wa.me/918089757815',
    `platform_name` VARCHAR(50) NOT NULL DEFAULT 'Amazon',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data
INSERT IGNORE INTO `document_types` (`id`, `name`, `code`, `description`) VALUES
(1, 'Aadhaar Card', 'AADHAAR', 'Government issued national identity card'),
(2, 'PAN Card', 'PAN', 'Income tax department permanent account number'),
(3, 'GST Registration Certificate', 'GST_CERT', 'Goods and services tax business certificate'),
(4, 'FSSAI License', 'FSSAI', 'Food safety authority compliance license');

-- Initial Products with configurable External Marketplace URLs
INSERT IGNORE INTO `products` (`id`, `name`, `slug`, `tag`, `category`, `price`, `image`, `description`, `external_url`, `platform_name`, `sort_order`) VALUES
(1, 'Millacoco 1 Ltr Kitchen Pouch', '1-ltr-kitchen-pouch', 'Spout Pouch', 'daily', 280.00, 'images/product_pouch.png', 'Easy-pour spout pack for cooking.', 'https://www.amazon.in/dp/B0DXXXXXX1', 'Amazon', 1),
(2, 'Millacoco 1 Ltr Classic Bottle', '1-ltr-classic-bottle', 'Bestseller', 'daily', 290.00, 'images/product_bottle.png', 'Daily kitchen staple & massage oil.', 'https://www.amazon.in/dp/B0DXXXXXX2', 'Amazon', 2),
(3, 'Millacoco 2 Ltr Twin Pack (1L x 2)', '2-ltr-twin-pack', 'Twin Value', 'bulk', 570.00, 'images/twin_pack.jpg', 'Save on regular supply with twin pack.', 'https://www.amazon.in/dp/B0DXXXXXX3', 'Amazon', 3),
(4, 'Millacoco 5 Ltr Household Can', '5-ltr-household-can', 'Bulk Supply', 'bulk', 1390.00, 'images/can_5ltr.jpg', 'Heavy-duty leakproof can for large families.', 'https://www.amazon.in/dp/B0DXXXXXX4', 'Amazon', 4),
(5, 'Millacoco Virgin Hair & Face Oil', 'virgin-hair-face-oil', 'Skincare', 'daily', 320.00, 'images/virgin_oil.jpg', 'Deep scalp conditioning and cold-pressed skincare.', 'https://www.amazon.in/dp/B0DXXXXXX5', 'Amazon', 5);
