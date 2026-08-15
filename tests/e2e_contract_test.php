<?php

declare(strict_types=1);

/**
 * Static E2E contract test for the Mahameru transaction chain.
 * It verifies that every production endpoint required by the business flow
 * exists and that the smoke-test chain cannot silently lose a critical stage.
 */
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
    fwrite(STDERR, "Missing E2E chain endpoints:\n" . implode("\n", array_map(static fn($f) => "- {$f}", $missing)) . "\n");
    exit(1);
}

echo sprintf("Mahameru E2E contract: %d transaction stages present. OK\n", count($chain));
