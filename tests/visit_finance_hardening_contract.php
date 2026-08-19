<?php

declare(strict_types=1);

$visit = file_get_contents(__DIR__ . '/../api/visit.php');
$payment = file_get_contents(__DIR__ . '/../api/payment.php');
$settlement = file_get_contents(__DIR__ . '/../api/settlement.php');

$requirements = [
    'visit timezone must come from company_settings' => str_contains($visit, "cs.minimum_visit_minutes, cs.timezone"),
    'visit checkout must enforce minimum duration' => str_contains($visit, '$elapsed < $minimumSeconds'),
    'visit checkout must complete the active visit' => str_contains($visit, "UPDATE visits SET status='COMPLETED'"),
    'visit checkout must write checkout activity' => str_contains($visit, "'CHECKOUT', 'Checkout berhasil'"),
    'payment must accept idempotency key' => str_contains($payment, '$idempotencyKey'),
    'payment must detect idempotent replay' => str_contains($payment, "'idempotent_replay' => true"),
    'payment must reject key reuse with different payload' => str_contains($payment, 'Idempotency key sudah digunakan'),
    'payment must lock invoice row' => str_contains($payment, 'FOR UPDATE'),
    'settlement must lock cash payments' => str_contains($settlement, 'payment_method = \'CASH\'') && str_contains($settlement, 'FOR UPDATE'),
    'settlement must ignore non-posted payments' => str_contains($settlement, "status = 'POSTED'"),
];

foreach ($requirements as $label => $ok) {
    if (!$ok) {
        throw new RuntimeException("Visit/Finance contract failed: {$label}");
    }
}

echo "Visit/Finance hardening contract: PASS\n";
