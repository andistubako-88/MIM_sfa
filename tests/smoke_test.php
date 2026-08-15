<?php

declare(strict_types=1);

$required = [
    // Core API
    'api/bootstrap.php',
    'api/health.php',
    'api/auth.php',
    'api/login.php',
    'api/logout.php',
    'api/master.php',
    'api/permission.php',
    'api/me.php',

    // Sales / Visit
    'api/plan_call.php',
    'api/visit.php',
    'database/visit_schema.sql',

    // Order / EC-OC
    'api/order.php',
    'api/orders.php',
    'api/order_approve.php',
    'api/order_reserve.php',
    'api/order_commit.php',
    'database/order_schema.sql',
    'database/order_permissions.sql',
    'database/order_approval_permissions.sql',
    'database/order_stock_reservation.sql',

    // Warehouse / delivery / return
    'api/loading.php',
    'api/inventory.php',
    'api/stock.php',
    'api/stock_balance.php',
    'api/delivery.php',
    'api/return.php',
    'database/inventory_schema.sql',
    'database/delivery_return_schema.sql',

    // Finance / settlement
    'api/invoice.php',
    'api/payment.php',
    'api/settlement.php',
    'api/settlement_approve.php',
    'database/finance_schema.sql',
    'database/finance_approval_permissions.sql',

    // KPI / reporting
    'api/kpi.php',
    'api/report.php',
    'database/report_permissions.sql',

    // Frontend entry points
    'public/index.php',
    'public/login.php',
    'public/dashboard.php',
    'public/sales.php',
    'public/visit.php',
    'public/assets/app.css',
    'public/assets/app.js',
];

$missing = [];
foreach ($required as $file) {
    if (!is_file(__DIR__ . '/../' . $file)) {
        $missing[] = $file;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing required Mahameru files:\n");
    foreach ($missing as $file) {
        fwrite(STDERR, "- {$file}\n");
    }
    exit(1);
}

echo sprintf("Mahameru smoke test: %d required files present. OK\n", count($required));
