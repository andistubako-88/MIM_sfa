<?php

declare(strict_types=1);

// cPanel deployment adapter.
// The source API remains outside the public document root; this wrapper
// dispatches /api/<endpoint>.php to the corresponding private API script.
$endpoint = basename((string) ($_GET['endpoint'] ?? ''));
if ($endpoint === '' || !preg_match('/^[a-zA-Z0-9_-]+\.php$/', $endpoint)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
    exit;
}

$privateApi = dirname(__DIR__, 2) . '/api/' . $endpoint;
if (!is_file($privateApi)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
    exit;
}

require $privateApi;
