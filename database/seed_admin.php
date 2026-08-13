<?php

declare(strict_types=1);

// Run once from a trusted CLI environment after schema.sql and seed_rbac.sql.
// Usage: php database/seed_admin.php

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php\n");
    exit(1);
}

$config = require $configPath;
$db = $config['database'];
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
$pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$username = getenv('MIM_ADMIN_USERNAME') ?: 'admin';
$password = getenv('MIM_ADMIN_PASSWORD');
if (!$password || strlen($password) < 8) {
    fwrite(STDERR, "Set MIM_ADMIN_PASSWORD to a strong password (minimum 8 characters).\n");
    exit(1);
}

$roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'OWNER' LIMIT 1")->fetchColumn();
if (!$roleId) {
    throw new RuntimeException('OWNER role is missing. Run schema.sql first.');
}

$stmt = $pdo->prepare('INSERT INTO users (role_id, username, password_hash, full_name) VALUES (?, ?, ?, ?)');
$stmt->execute([$roleId, $username, password_hash($password, PASSWORD_DEFAULT), 'System Owner']);

echo "Owner account created: {$username}\n";
