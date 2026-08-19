<?php

declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

$pdo = db();

$column = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'idempotency_key'")->fetchColumn();
if ((int) $column !== 1) {
    throw new RuntimeException('Finance hardening regression: payments.idempotency_key is missing.');
}

$canonicalIndex = 'uq_payments_idempotency_key';
$legacyIndex = 'uq_payment_idempotency_key';
$uniqueStmt = $pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name IN ('{$canonicalIndex}', '{$legacyIndex}') AND non_unique = 0");
if ((int) $uniqueStmt->fetchColumn() !== 1) {
    throw new RuntimeException('Finance hardening regression: exactly one canonical/legacy idempotency unique index is required.');
}

$settlementIndex = $pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'idx_payment_settlement_lookup' AND non_unique = 1")->fetchColumn();
if ((int) $settlementIndex !== 1) {
    throw new RuntimeException('Finance hardening regression: settlement lookup index is missing.');
}

$settlementColumns = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'idx_payment_settlement_lookup'")->fetchColumn();
$expectedSettlementColumns = 'sales_id,payment_date,status,payment_method';
if ($settlementColumns !== $expectedSettlementColumns) {
    throw new RuntimeException("Finance hardening regression: settlement lookup index columns are invalid. Expected {$expectedSettlementColumns}, got {$settlementColumns}.");
}

$nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'idempotency_key'")->fetchColumn();
if ($nullable !== 'YES') {
    throw new RuntimeException('Finance hardening regression: idempotency_key must remain nullable.');
}

$financeManage = $pdo->query("SELECT COUNT(*) FROM permissions WHERE code = 'finance.manage'")->fetchColumn();
if ((int) $financeManage !== 1) {
    throw new RuntimeException('Finance hardening regression: finance.manage permission is missing.');
}

foreach (['OWNER', 'ADMIN', 'SUPERVISOR'] as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = ? AND p.code = 'finance.manage'");
    $stmt->execute([$role]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException("Finance hardening regression: {$role} lacks finance.manage permission.");
    }
}

echo "Finance hardening regression: PASS\n";
