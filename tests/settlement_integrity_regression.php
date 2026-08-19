<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/database/migrations/2026_08_19_settlement_integrity.sql';

if (!is_file($path)) {
    fwrite(STDERR, "Missing settlement integrity migration\n");
    exit(1);
}

$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Unable to read settlement integrity migration\n");
    exit(1);
}

$required = [
    'ALTER TABLE settlement_documents',
    'UNIQUE KEY uq_settlement_sales_date',
    '(sales_id, settlement_date)',
];

foreach ($required as $rule) {
    if (strpos($sql, $rule) === false) {
        fwrite(STDERR, "Missing settlement integrity rule: {$rule}\n");
        exit(1);
    }
}

echo "Settlement integrity regression: PASS\n";
