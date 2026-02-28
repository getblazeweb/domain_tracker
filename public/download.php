<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login();

$allowed = ['import.csv'];
$file = (string) ($_GET['file'] ?? '');
if (!in_array($file, $allowed, true)) {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/assets/' . $file;
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
