<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'update_check.php';

$result = update_check_run($basePath, true);
if (!empty($result['available'])) {
    echo "Update available: {$result['count']} file(s)\n";
    exit(0);
}

if (!empty($result['error'])) {
    fwrite(STDERR, $result['error'] . "\n");
    exit(1);
}

echo "No updates found.\n";
