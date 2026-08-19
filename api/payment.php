<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('finance.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
require_csrf();

$input = json_decode((string) file_get_contents('php://input'), true);
$invoiceId = (int) ($input['invoice_id'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
$method = strtoupper(trim((string) ($input['payment_method'] ?? '')));
$idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

if ($invoiceId < 1 || $amount <= 0 || !is_finite($amount) || !in_array($method, ['CASH', 'TRANSFER', 'GIRO', 'OTHER'], true)) {
    json_response(['success' => false, 'message' => 'Invoice, amount, dan metode pembayaran tidak valid.'], 422);
}

if ($idempotencyKey !== '' && (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey))) {
    json_response(['success' => false, 'message' => 'Idempotency key tidak valid.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $q = $pdo->prepare('SELECT id,outlet_id,sales_id,grand_total,paid_total,status FROM invoices WHERE id=? LIMIT 1 FOR UPDATE');
    $q->execute([$invoiceId]);
    $inv = $q->fetch();
    if (!$inv || $inv['status'] === 'VOID') {
        throw new RuntimeException('Invoice tidak dapat menerima pembayaran.', 409);
    }

    $owner = $pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');
    $owner->execute([$inv['sales_id'], $user['id']]);
    if ($user['role_code'] === 'SALES' && !$owner->fetchColumn()) {
        throw new RuntimeException('Invoice bukan milik salesman ini.', 403);
    }

    // Idempotency is optional for backward compatibility. When supplied,
    // retries return the original payment instead of creating a duplicate.
    if ($idempotencyKey !== '') {
        $existing = $pdo->prepare('SELECT payment_number, invoice_id, amount, payment_method, status FROM payments WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
        $existing->execute([$idempotencyKey]);
        $payment = $existing->fetch();
        if ($payment) {
            if ((int) $payment['invoice_id'] !== $invoiceId || round((float) $payment['amount'], 2) !== round($amount, 2) || $payment['payment_method'] !== $method) {
                throw new RuntimeException('Idempotency key sudah digunakan untuk transaksi pembayaran yang berbeda.', 409);
            }
            $pdo->commit();
            json_response([
                'success' => true,
                'idempotent_replay' => true,
                'payment_number' => $payment['payment_number'],
                'paid_total' => (float) $inv['paid_total'],
                'invoice_status' => $inv['status'],
            ], 200);
        }
    }

    $remaining = round((float) $inv['grand_total'] - (float) $inv['paid_total'], 2);
    if ($amount > $remaining) {
        throw new RuntimeException('Pembayaran melebihi saldo invoice.', 409);
    }

    $num = 'PAY-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $p = $pdo->prepare("INSERT INTO payments(payment_number,invoice_id,outlet_id,sales_id,amount,payment_method,payment_date,idempotency_key,status,created_by) VALUES(?,?,?,?,?,?,CURDATE(),?,'POSTED',?)");
    try {
        $p->execute([$num, $invoiceId, $inv['outlet_id'], $inv['sales_id'], $amount, $method, $idempotencyKey !== '' ? $idempotencyKey : null, $user['id']]);
    } catch (PDOException $e) {
        // A concurrent request can win the unique idempotency key race.
        if ($idempotencyKey !== '' && (($e->errorInfo[1] ?? null) === 1062)) {
            $existing = $pdo->prepare('SELECT payment_number, invoice_id, amount, payment_method, status FROM payments WHERE idempotency_key=? LIMIT 1');
            $existing->execute([$idempotencyKey]);
            $payment = $existing->fetch();
            if ($payment && (int) $payment['invoice_id'] === $invoiceId && round((float) $payment['amount'], 2) === round($amount, 2) && $payment['payment_method'] === $method) {
                $pdo->rollBack();
                json_response([
                    'success' => true,
                    'idempotent_replay' => true,
                    'payment_number' => $payment['payment_number'],
                    'paid_total' => (float) $inv['paid_total'],
                    'invoice_status' => $inv['status'],
                ], 200);
            }
        }
        throw $e;
    }

    $newPaid = round((float) $inv['paid_total'] + $amount, 2);
    $status = $newPaid >= (float) $inv['grand_total'] ? 'PAID' : 'PARTIAL';
    $pdo->prepare('UPDATE invoices SET paid_total=?,status=? WHERE id=?')->execute([$newPaid, $status, $invoiceId]);

    $pdo->commit();
    json_response(['success' => true, 'idempotent_replay' => false, 'payment_number' => $num, 'paid_total' => $newPaid, 'invoice_status' => $status], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $s = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    json_response(['success' => false, 'message' => $e->getMessage()], $s);
}
