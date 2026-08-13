<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('dashboard.view');

$stmt = db()->prepare('SELECT p.code, p.name, p.module FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? ORDER BY p.module, p.code');
$stmt->execute([(int) $user['role_id']]);

json_response([
    'success' => true,
    'role' => $user['role_code'],
    'permissions' => $stmt->fetchAll(),
]);
