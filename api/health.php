<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

try {
    db()->query('SELECT 1');
    json_response([
        'success' => true,
        'service' => 'MIM SFA API',
        'status' => 'healthy',
        'timestamp' => date(DATE_ATOM),
        'database' => 'connected',
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'service' => 'MIM SFA API',
        'status' => 'degraded',
        'timestamp' => date(DATE_ATOM),
        'database' => 'unavailable',
    ], 503);
}
