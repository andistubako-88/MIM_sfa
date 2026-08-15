<?php

declare(strict_types=1);

/** Static contract test for the Mahameru transaction chain. */
$chain = [
    'api/login.php',
    'api/plan_call.php',
    'api/visit.php',
    'api/orders.php',
    'api/order_approve.php',
    'api/order_reserve.php',
    'api/order_commit.php',
    'api/delivery.php',
    'api/invoice.php',
    'api/payment.php',
    'api/settlement.php',
    'api/settlement_approve.php',
    'api/kpi.php',
];

$missing = array_values(array_filter($chain, static fn(string $file): bool => !is_file(__DIR__ . '/../' . $file)));
if ($missing !== []) {
    fwrite(STDERR, "Missing E2E chain endpoints:\n" . implode("\n", array_map(static fn(string $file): string => "- {$file}", $missing)) . "\n");
    exit(1);
}

echo sprintf("Mahameru E2E contract: %d transaction stages present. OK\n", count($chain));
