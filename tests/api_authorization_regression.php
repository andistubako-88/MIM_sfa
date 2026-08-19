<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'orders endpoint requires create permission' => [
        $root . '/api/orders.php',
        "require_permission('orders.create')",
    ],
    'orders bind visit to authenticated salesman' => [
        $root . '/api/orders.php',
        'v.id = ? LIMIT 1 FOR UPDATE',
    ],
    'orders reject visit owned by another user' => [
        $root . '/api/orders.php',
        "Visit tidak ditemukan atau bukan milik salesman aktif.",
    ],
    'visit checkin requires visit permission' => [
        $root . '/api/visit.php',
        "require_permission('visits.create')",
    ],
    'visit checkin binds outlet to authenticated salesman' => [
        $root . '/api/visit.php',
        'WHERE o.id=? AND s.user_id=? AND o.is_active=1 AND so.is_active=1',
    ],
    'inventory requires view permission' => [
        $root . '/api/inventory.php',
        "require_permission('inventory.view')",
    ],
    'inventory enforces sales ownership' => [
        $root . '/api/inventory.php',
        'Akses stock location ditolak.',
    ],
    'master requires manage permission' => [
        $root . '/api/master.php',
        "require_permission('masters.manage')",
    ],
    'invoice requires finance permission' => [
        $root . '/api/invoice.php',
        "require_permission('finance.manage')",
    ],
    'invoice enforces salesman delivery ownership' => [
        $root . '/api/invoice.php',
        'Delivery bukan milik salesman ini.',
    ],
];

foreach ($checks as $label => [$file, $needle]) {
    $content = file_get_contents($file);
    if ($content === false || !str_contains($content, $needle)) {
        throw new RuntimeException("API authorization regression failed: {$label}");
    }
}

echo "API authorization regression: PASS\n";
