<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('finance.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
require_csrf();

$input = json_decode((string) file_get_contents('php://input'), true);
$salesId = (int) ($input['sales_id'] ?? 0);
$date = (string) ($input['settlement_date'] ?? date('Y-m-d'));
$submitted = (float) ($input['submitted_cash'] ?? 0);
$notes = trim((string) ($input['notes'] ?? ''));

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$dateErrors = DateTimeImmutable::getLastErrors();
if ($dateObject === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
    json_response(['success' => false, 'message' => 'Tanggal settlement tidak valid.'], 422);
}
if ($salesId < 1 || $submitted < 0 || !is_finite($submitted)) {
    json_response(['success' => false, 'message' => 'Data settlement tidak valid.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $owner = $pdo->prepare('SELECT 1 FROM sales WHERE id = ? AND user_id = ? LIMIT 1');
    $owner->execute([$salesId, $user['id']]);
    if ($user['role_code'] === 'SALES' && !$owner->fetchColumn()) {
        throw new RuntimeException('Salesman tidak valid.', 403);
    }

    $existing = $pdo->prepare("SELECT id, status FROM settlement_documents WHERE sales_id = ? AND settlement_date = ? AND status <> 'REJECTED' LIMIT 1 FOR UPDATE");
    $existing->execute([$salesId, $date]);
    if ($existing->fetch()) {
        throw new RuntimeException('Settlement untuk salesman dan tanggal tersebut sudah dibuat.', 409);
    }

    // Lock all posted CASH payments used by this settlement before calculating
    // expected cash, preventing a concurrent payment from changing the basis.
    $q = $pdo->prepare("SELECT id, amount FROM payments WHERE sales_id = ? AND payment_date = ? AND payment_method = 'CASH' AND status = 'POSTED' ORDER BY id FOR UPDATE");
    $q->execute([$salesId, $date]);
    $expected = 0.0;
    while ($payment = $q->fetch()) {
        $expected += (float) $payment['amount'];
    }
    $expected = round($expected, 2);

    $diff = round($submitted - $expected, 2);
    $num = 'SET-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $ins = $pdo->prepare("INSERT INTO settlement_documents (settlement_number, sales_id, settlement_date, expected_cash, submitted_cash, difference, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'SUBMITTED', ?, ?)");
    $ins->execute([$num, $salesId, $date, $expected, $submitted, $diff, $notes ?: null, $user['id']]);
    $pdo->commit();
    json_response(['success'=>true,'settlement_number'=>$num,'expected_cash'=>$expected,'submitted_cash'=>$submitted,'difference'=>$diff,'status'=>'SUBMITTED'],201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
}
