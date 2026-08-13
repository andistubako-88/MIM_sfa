<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('inventory.manage');
$locationId = (int) ($_GET['location_id'] ?? 0);
if ($locationId < 1) json_response(['success' => false, 'message' => 'location_id wajib diisi.'], 422);

$stmt = db()->prepare('SELECT sb.product_id, p.sku, p.name, p.unit, sb.qty FROM stock_balances sb JOIN products p ON p.id = sb.product_id WHERE sb.stock_location_id = ? ORDER BY p.name');
$stmt->execute([$locationId]);
json_response(['success' => true, 'location_id' => $locationId, 'stock' => $stmt->fetchAll()]);
