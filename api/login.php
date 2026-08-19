<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

// Backward-compatible login endpoint. The canonical implementation lives in auth.php.
$_GET['action'] = 'login';
handle_auth_request();
