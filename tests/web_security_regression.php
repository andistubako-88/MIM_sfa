<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$rootHtaccess = $root . '/.htaccess';
$uploadHtaccess = $root . '/uploads/.htaccess';

foreach ([$rootHtaccess, $uploadHtaccess] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing security file: {$file}\n");
        exit(1);
    }
}

$rootRules = file_get_contents($rootHtaccess);
$uploadRules = file_get_contents($uploadHtaccess);
if ($rootRules === false || $uploadRules === false) {
    fwrite(STDERR, "Unable to read security configuration\n");
    exit(1);
}

$requiredRoot = [
    'Options -Indexes',
    'X-Content-Type-Options',
    'X-Frame-Options',
    'Referrer-Policy',
    'Permissions-Policy',
    'FilesMatch',
];

foreach ($requiredRoot as $rule) {
    if (strpos($rootRules, $rule) === false) {
        fwrite(STDERR, "Missing root security rule: {$rule}\n");
        exit(1);
    }
}

$requiredUpload = [
    'Options -ExecCGI',
    'php',
    'phtml',
    'phar',
    'Require all denied',
    'X-Content-Type-Options',
];

foreach ($requiredUpload as $rule) {
    if (strpos($uploadRules, $rule) === false) {
        fwrite(STDERR, "Missing upload security rule: {$rule}\n");
        exit(1);
    }
}

echo "Web security regression: PASS\n";
