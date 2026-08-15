<?php

declare(strict_types=1);

/**
 * Database integration preflight. Runs only when MIM_TEST_DB_* variables exist.
 * It deliberately does not mutate production data: CI must point it at a disposable DB.
 */
$host = getenv('MIM_TEST_DB_HOST');
$name = getenv('MIM_TEST_DB_NAME');
$user = getenv('MIM_TEST_DB_USER');
$pass = getenv('MIM_TEST_DB_PASS');
if (!$host || !$name || !$user) {
    echo "MySQL integration: SKIPPED (test DB variables not configured)\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name),
    $user,
    $pass ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$required = ['users','roles','sales','outlets','products','orders','order_items','invoices','payments','audit_logs'];
foreach ($required as $table) {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=?');
    $stmt->execute([$name, $table]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException("Required table missing: {$table}");
    }
}

$pdo->beginTransaction();
try {
    $pdo->query('SELECT id FROM roles ORDER BY id LIMIT 1 FOR UPDATE')->fetch();
    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

echo 'MySQL integration preflight: PASS' . PHP_EOL;
