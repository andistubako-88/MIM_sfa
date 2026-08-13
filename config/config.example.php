<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'MIM SFA',
        'env' => 'production',
        'timezone' => 'Asia/Jakarta',
        'base_url' => 'http://localhost/mim_sfa',
        'session_name' => 'mim_sfa_session',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'mim_sfa',
        'username' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'password_min_length' => 8,
        'csrf_enabled' => true,
    ],
];
