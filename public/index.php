<?php

declare(strict_types=1);

require __DIR__ . '/../api/auth.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
