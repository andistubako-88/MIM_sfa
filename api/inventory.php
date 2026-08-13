<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('inventory.view');
$locationId = (int) ($_GET['location_id'] ?? 0);
if ($locationId < 1) json_response(['success' => false, 'message' => 'location_id wajib diisi.'], 422);

$stmt = db()->prepare('SELECT sl.id, sl.code, sl.name, sl.location_type, sl.sales_id, sb.product_id, p.sku, p.name AS product_name, p.unit, sb.qty FROM stock_locations sl LEFT JOIN stock_balances sb ON sb.stock_location_id = sl.id LEFT JOIN products p ON p.id = sb.product_id WHERE sl.id = ? AND sl.is_active = 1 ORDER BY p.name');
$stmt->execute([$locationId]);
$rows = $stmt->fetchAll();
if (!$rows) json_response(['success' => false, 'message' => 'Stock location tidak ditemukan.'], 404);

$first = $rows[0];
if ($user['role_code'] === 'SALES' && (int) $first['sales_id'] !== (int) ($user['sales_id'] ?? -1)) {
    $owner = db()->prepare('SELECT 1 FROM sales WHERE id = ? AND user_id = ? LIMIT 1');
    $owner->execute([$first['sales_id'], $user['id']]);
    if (!$owner->fetchColumn()) json_response(['success' => false, 'message' => 'Akses stock location ditolak.'], 403);
}

$stock = array_values(array_filter($rows, static fn(array $row): bool => $row['product_id'] !== null));
json_response(['success' => true, 'location' => ['id' => $first['id'], 'code' => $first['code'], 'name' => $first['name'], 'type' => $first['location_type']], 'stock' => $stock]);
