<?php

declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

$pdo = db();

$column = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'idempotency_key'")->fetchColumn();
if ((int) $column !== 1) {
    throw new RuntimeException('Finance hardening regression: payments.idempotency_key is missing.');
}

$unique = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'uq_payment_idempotency_key' AND non_unique = 0")->fetchColumn();
if ((int) $unique !== 1) {
    throw new RuntimeException('Finance hardening regression: idempotency unique index is missing.');
}

$settlementIndex = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'idx_payment_settlement_lookup'")->fetchColumn();
if ((int) $settlementIndex !== 1) {
    throw new RuntimeException('Finance hardening regression: settlement lookup index is missing.');
}

$nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'idempotency_key'")->fetchColumn();
if ($nullable !== 'YES') {
    throw new RuntimeException('Finance hardening regression: idempotency_key must remain nullable.');
}

echo "Finance hardening regression: PASS\n";
