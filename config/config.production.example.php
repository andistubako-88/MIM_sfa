<?php

declare(strict_types=1);

// Copy this file to config/config.php on the production server.
// Never commit the real production config or credentials to Git.

return [
    'app' => [
        'name' => 'MIM SFA',
        'env' => 'production',
        'timezone' => 'Asia/Jakarta',
        'base_url' => 'https://YOUR-DOMAIN.example',
        'session_name' => 'mim_sfa_session',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'CPANEL_DB_NAME',
        'username' => 'CPANEL_DB_USER',
        'password' => 'CHANGE_ON_SERVER',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'password_min_length' => 8,
        'csrf_enabled' => true,
    ],
];
