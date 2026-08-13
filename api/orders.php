<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('orders.create');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_csrf();

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['success' => false, 'message' => 'Payload JSON tidak valid.'], 422);
}

$visitId = (int) ($input['visit_id'] ?? 0);
$orderType = strtoupper(trim((string) ($input['order_type'] ?? '')));
$items = $input['items'] ?? [];
$notes = trim((string) ($input['notes'] ?? ''));

if ($visitId < 1 || !in_array($orderType, ['EC', 'OC'], true) || !is_array($items) || count($items) < 1) {
    json_response(['success' => false, 'message' => 'Visit, tipe EC/OC, dan minimal satu item wajib diisi.'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Critical rule: the server, not the browser, decides whether an order is allowed.
    $stmt = $pdo->prepare("SELECT v.id, v.sales_id, v.outlet_id, v.status, v.checkout_at, s.user_id FROM visits v JOIN sales s ON s.id = v.sales_id WHERE v.id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$visitId]);
    $visit = $stmt->fetch();

    if (!$visit || (int) $visit['user_id'] !== (int) $user['id']) {
        throw new RuntimeException('Visit tidak ditemukan atau bukan milik salesman aktif.', 403);
    }
    if ($visit['status'] !== 'ACTIVE' || $visit['checkout_at'] !== null) {
        throw new RuntimeException('Order hanya dapat dibuat saat visit masih ACTIVE.', 409);
    }

    $subtotal = 0.0;
    $discountTotal = 0.0;
    $normalizedItems = [];

    $productStmt = $pdo->prepare('SELECT id, sku, name, sell_price, is_active FROM products WHERE id = ? LIMIT 1');
    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $qty = (float) ($item['qty'] ?? 0);
        $discountPercent = (float) ($item['discount_percent'] ?? 0);

        if ($productId < 1 || $qty <= 0 || $discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException('Item order tidak valid.', 422);
        }

        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        if (!$product || !(int) $product['is_active']) {
            throw new RuntimeException('Produk tidak aktif atau tidak ditemukan.', 422);
        }

        $unitPrice = (float) $product['sell_price'];
        $gross = round($qty * $unitPrice, 2);
        $discountAmount = round($gross * ($discountPercent / 100), 2);
        $lineTotal = round($gross - $discountAmount, 2);
        $subtotal += $gross;
        $discountTotal += $discountAmount;

        $normalizedItems[] = [$productId, $qty, $unitPrice, $discountPercent, $discountAmount, $lineTotal];
    }

    $grandTotal = round($subtotal - $discountTotal, 2);
    $orderNumber = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $orderStmt = $pdo->prepare('INSERT INTO orders (order_number, visit_id, sales_id, outlet_id, order_type, status, subtotal, discount_total, grand_total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $orderStmt->execute([$orderNumber, $visit['id'], $visit['sales_id'], $visit['outlet_id'], $orderType, 'SUBMITTED', $subtotal, $discountTotal, $grandTotal, $notes ?: null]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price, discount_percent, discount_amount, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($normalizedItems as $row) {
        $itemStmt->execute([$orderId, ...$row]);
    }

    $historyStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, notes) VALUES (?, NULL, ?, ?, ?)');
    $historyStmt->execute([$orderId, 'SUBMITTED', $user['id'], 'Order dibuat oleh salesman.']);

    $pdo->commit();
    json_response(['success' => true, 'message' => 'Order berhasil dibuat.', 'order_id' => $orderId, 'order_number' => $orderNumber, 'grand_total' => $grandTotal], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    json_response(['success' => false, 'message' => $e->getMessage()], $status);
}
