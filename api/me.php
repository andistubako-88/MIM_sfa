<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_auth();
json_response(['success' => true, 'user' => $user]);
