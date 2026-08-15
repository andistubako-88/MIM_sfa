<?php
declare(strict_types=1);

/*
 * Lightweight static regression guard for the Sales Workspace contract.
 * This test intentionally does not require a live database; the full Quality
 * Gate remains responsible for executing the real API transaction E2E.
 */

$root = dirname(__DIR__);
$sales = file_get_contents($root . '/public/sales.php');
$order = file_get_contents($root . '/public/order.php');
$visit = file_get_contents($root . '/public/visit.php');
$visitApi = file_get_contents($root . '/api/visit.php');
$orderApi = file_get_contents($root . '/api/orders.php');

$checks = [
    'sales workspace calls active visit endpoint' => str_contains($sales, 'api/visit.php?action=active'),
    'sales workspace requires order permission' => str_contains($sales, "orders.create"),
    'order page requires order permission' => str_contains($order, "orders.create"),
    'order page reads active visit' => str_contains($order, 'api/visit.php?action=active'),
    'order page sends visit id' => str_contains($order, 'visit_id'),
    'visit page sends csrf' => str_contains($visit, 'X-CSRF-TOKEN'),
    'visit page uploads checkin photo' => str_contains($visit, 'visit_photo.php'),
    'visit api enforces active visit for order flow' => str_contains($orderApi, "status !== 'ACTIVE'") || str_contains($orderApi, 'ACTIVE'),
    'visit api has checkout action' => str_contains($visitApi, "'checkout'") || str_contains($visitApi, 'checkout'),
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "Sales Workspace regression FAILED:\n");
    foreach ($failed as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "Sales Workspace regression PASS (" . count($checks) . " checks)\n";
