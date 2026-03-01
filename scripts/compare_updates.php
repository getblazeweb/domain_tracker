<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$basePath = dirname(__DIR__);
$repoZipUrl = 'https://github.com/getblazeweb/domain_tracker/archive/refs/heads/main.zip';
$asJson = in_array('--json', $argv ?? [], true);

function download_zip(string $url, string $dest): void
{
    if (function_exists('curl_init')) {
        $fp = fopen($dest, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Unable to write zip file.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'DomainTracker-Updater',
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok) {
            throw new RuntimeException('Download failed: ' . $error);
        }
        return;
    }

    if (!ini_get('allow_url_fopen')) {
        throw new RuntimeException('allow_url_fopen is disabled and cURL is unavailable.');
    }
    $data = file_get_contents($url);
    if ($data === false) {
        throw new RuntimeException('Download failed.');
    }
    if (file_put_contents($dest, $data) === false) {
        throw new RuntimeException('Unable to write zip file.');
    }
}

function should_exclude(string $relative): bool
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $first = explode('/', $relative)[0] ?? '';
    if ($first === 'data' || $first === 'config' || $first === 'installer') {
        return true;
    }
    if (str_starts_with($relative, '.env')) {
        return true;
    }
    if ($relative === 'public/updater.php') {
        return true;
    }
    return false;
}

function recursive_scan(string $dir, string $root, array &$files): void
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            recursive_scan($path, $root, $files);
        } else {
            $files[] = ltrim(str_replace($root . DIRECTORY_SEPARATOR, '', $path), DIRECTORY_SEPARATOR);
        }
    }
}

try {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is not available on this server.');
    }

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'domain_tracker_compare_' . bin2hex(random_bytes(6));
    $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'update.zip';
    mkdir($tempDir, 0755, true);

    download_zip($repoZipUrl, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Unable to open update zip.');
    }
    $zip->extractTo($tempDir);
    $zip->close();

    $entries = array_values(array_filter(scandir($tempDir) ?: [], function ($entry) use ($tempDir) {
        return $entry !== '.' && $entry !== '..' && is_dir($tempDir . DIRECTORY_SEPARATOR . $entry);
    }));
    if (empty($entries)) {
        throw new RuntimeException('Update archive did not contain files.');
    }

    $extractedRoot = $tempDir . DIRECTORY_SEPARATOR . $entries[0];
    $files = [];
    recursive_scan($extractedRoot, $extractedRoot, $files);

    $changed = [];
    $missing = [];
    foreach ($files as $relative) {
        if (should_exclude($relative)) {
            continue;
        }
        $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
        $destPath = $basePath . DIRECTORY_SEPARATOR . $relative;

        if (!file_exists($destPath)) {
            $missing[] = $relative;
            continue;
        }
        if (hash_file('sha256', $srcPath) !== hash_file('sha256', $destPath)) {
            $changed[] = $relative;
        }
    }

    if ($asJson) {
        echo json_encode([
            'changed' => $changed,
            'missing' => $missing,
        ], JSON_PRETTY_PRINT);
        exit(0);
    }

    echo "Changed files:\n";
    foreach ($changed as $file) {
        echo "  - {$file}\n";
    }
    echo "\nMissing files:\n";
    foreach ($missing as $file) {
        echo "  - {$file}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
