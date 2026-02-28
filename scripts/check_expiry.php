<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'expiry_check.php';

$pdo = db();
$result = expiry_check_run($pdo, true);

$total = $result['count_7'] + $result['count_30'] + $result['count_60'] + $result['count_90'];
if ($total === 0) {
    echo "No domains expiring in the next 90 days.\n";
    exit(0);
}

echo "Domains expiring:\n";
echo "  Within 7 days:  {$result['count_7']}\n";
echo "  Within 30 days: {$result['count_30']}\n";
echo "  Within 60 days: {$result['count_60']}\n";
echo "  Within 90 days: {$result['count_90']}\n";
echo "\nChecked at: {$result['checked_at']}\n";
