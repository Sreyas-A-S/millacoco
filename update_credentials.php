<?php
$db = new PDO('sqlite:' . __DIR__ . '/admin/database/millacoco.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->prepare('UPDATE users SET email = :email, password_hash = :hash WHERE id = 1 OR role = "admin"');
$stmt->execute([
    'email' => 'nikhils9526@gmail.com',
    'hash' => '$2y$12$VwE7QfMMLQUotOhba4NUfOTaIL7qoZdLXAmJm35UqaQqCJoY.9NsO'
]);

echo "=== ADMIN CREDENTIALS UPDATED SUCCESSFULLY ===\n";
echo "Email: nikhils9526@gmail.com\n";
