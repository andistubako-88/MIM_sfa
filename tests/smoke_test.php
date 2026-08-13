<?php

declare(strict_types=1);

$required = [
    'api/auth.php',
    'api/order_reserve.php',
    'api/order_commit.php',
    'api/delivery.php',
    'api/return.php',
    'api/payment.php',
    'api/settlement.php',
    'api/kpi.php',
    'database/order_schema.sql',
    'database/inventory_schema.sql',
    'database/order_stock_reservation.sql',
    'database/delivery_return_schema.sql',
    'database/finance_schema.sql',
];

foreach ($required as $file) {
    if (!is_file(__DIR__ . '/../' . $file)) {
        fwrite(STDERR, "Missing: {$file}\n");
        exit(1);
    }
}

echo "Required Mahameru order-engine files: OK\n";
