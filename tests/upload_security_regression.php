<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$uploadDir = $root . '/public/uploads/visits';
$htaccess = $uploadDir . '/.htaccess';

if (!is_file($htaccess)) {
    fwrite(STDERR, "Missing upload hardening file: public/uploads/visits/.htaccess\n");
    exit(1);
}

$content = file_get_contents($htaccess);
if ($content === false) {
    fwrite(STDERR, "Unable to read upload hardening file\n");
    exit(1);
}

$required = [
    'Options -ExecCGI',
    'RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar',
    'RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar',
];

foreach ($required as $rule) {
    if (strpos($content, $rule) === false) {
        fwrite(STDERR, "Missing upload security rule: {$rule}\n");
        exit(1);
    }
}

echo "Upload security regression: PASS\n";
