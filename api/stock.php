<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('inventory.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
require_csrf();

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['success' => false, 'message' => 'Payload JSON tidak valid.'], 422);
}

$type = strtoupper((string) ($input['movement_type'] ?? ''));
$productId = (int) ($input['product_id'] ?? 0);
$from = (int) ($input['from_location_id'] ?? 0);
$to = (int) ($input['to_location_id'] ?? 0);
$qty = (float) ($input['qty'] ?? 0);
$notes = trim((string) ($input['notes'] ?? ''));

$allowed = ['OPENING','LOADING','RETURN','ADJUSTMENT','TRANSFER'];
if (!in_array($type, $allowed, true) || $productId < 1 || $qty <= 0) {
    json_response(['success' => false, 'message' => 'Jenis movement, produk, dan qty tidak valid.'], 422);
}
if ($type === 'TRANSFER' || $type === 'LOADING') {
    if ($from < 1 || $to < 1 || $from === $to) {
        json_response(['success' => false, 'message' => 'Lokasi asal dan tujuan wajib berbeda.'], 422);
    }
}

$pdo = db();
$pdo->beginTransaction();
try {
    $product = $pdo->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $product->execute([$productId]);
    if (!$product->fetch()) {
        throw new RuntimeException('Produk tidak aktif atau tidak ditemukan.', 422);
    }

    $movementNumber = 'MOV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $insert = $pdo->prepare('INSERT INTO stock_movements (movement_number, product_id, from_location_id, to_location_id, movement_type, qty, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$movementNumber, $productId, $from ?: null, $to ?: null, $type, $qty, $notes ?: null, $user['id']]);

    if ($from) {
        $lock = $pdo->prepare('SELECT qty FROM stock_balances WHERE stock_location_id = ? AND product_id = ? FOR UPDATE');
        $lock->execute([$from, $productId]);
        $balance = $lock->fetchColumn();
        if ($balance === false || (float) $balance < $qty) {
            throw new RuntimeException('Stok lokasi asal tidak mencukupi.', 409);
        }
        $pdo->prepare('UPDATE stock_balances SET qty = qty - ? WHERE stock_location_id = ? AND product_id = ?')->execute([$qty, $from, $productId]);
    }

    if ($to) {
        $pdo->prepare('INSERT INTO stock_balances (stock_location_id, product_id, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)')->execute([$to, $productId, $qty]);
    }

    $pdo->commit();
    json_response(['success' => true, 'movement_number' => $movementNumber], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    json_response(['success' => false, 'message' => $e->getMessage()], $status);
}
