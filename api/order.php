<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('orders.view');
$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId < 1) {
    json_response(['success' => false, 'message' => 'Order ID wajib diisi.'], 422);
}

$stmt = db()->prepare('SELECT o.id, o.order_number, o.order_type, o.status, o.subtotal, o.discount_total, o.grand_total, o.notes, o.submitted_at, o.approved_at, v.visit_date, s.name AS salesman_name, o.outlet_id, s.user_id AS sales_user_id FROM orders o JOIN visits v ON v.id = o.visit_id JOIN sales s ON s.id = o.sales_id WHERE o.id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    json_response(['success' => false, 'message' => 'Order tidak ditemukan.'], 404);
}

// Sales users may only read their own orders. Management roles can read orders according to RBAC.
if ($user['role_code'] === 'SALES' && (int) $order['sales_user_id'] !== (int) $user['id']) {
    json_response(['success' => false, 'message' => 'Anda tidak dapat melihat order milik salesman lain.'], 403);
}

$items = db()->prepare('SELECT oi.id, oi.product_id, p.sku, p.name, oi.qty, oi.unit_price, oi.discount_percent, oi.discount_amount, oi.line_total FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id');
$items->execute([$orderId]);
$order['items'] = $items->fetchAll();
unset($order['sales_user_id']);

json_response(['success' => true, 'order' => $order]);
