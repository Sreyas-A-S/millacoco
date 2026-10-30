<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Millacoco Admin',
        'env' => 'production', // 'development' or 'production'
        'debug' => false,      // Display detailed errors only in local development
        'base_url' => '/admin',
    ],
    'db' => [
        'driver' => 'sqlite',
        'path' => __DIR__ . '/../database/millacoco.sqlite',
    ],
    'jwt' => [
        'secret' => 'milla_coco_super_secure_jwt_secret_key_change_in_production_2026',
        'algorithm' => 'HS256',
        'expiry' => 86400, // 24 hours in seconds
        'issuer' => 'millacoco-admin',
    ],
];
