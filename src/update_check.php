<?php
declare(strict_types=1);

function update_check_run(string $basePath, bool $force = false, int $intervalSeconds = 43200): array
{
    $flagPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_available.json';
    $checkPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_check.json';
    $repoZipUrl = 'https://github.com/getblazeweb/domain_tracker/archive/refs/heads/main.zip';

    if (!$force && file_exists($checkPath)) {
        $contents = file_get_contents($checkPath);
        if ($contents !== false) {
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && !empty($decoded['checked_at'])) {
                $last = strtotime((string) $decoded['checked_at']) ?: 0;
                if ($last > 0 && (time() - $last) < $intervalSeconds) {
                    if (file_exists($flagPath)) {
                        $flag = json_decode((string) file_get_contents($flagPath), true);
                        return is_array($flag) ? $flag : ['available' => true, 'count' => 0];
                    }
                    return ['available' => false, 'count' => 0, 'checked_at' => $decoded['checked_at']];
                }
            }
        }
    }

    try {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is not available on this server.');
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'domain_tracker_check_' . bin2hex(random_bytes(6));
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'update.zip';
        mkdir($tempDir, 0755, true);

        update_check_download_zip($repoZipUrl, $zipPath);

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
        update_check_recursive_scan($extractedRoot, $extractedRoot, $files);

        $changed = [];
        foreach ($files as $relative) {
            if (update_check_should_exclude($relative)) {
                continue;
            }
            $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
            $destPath = $basePath . DIRECTORY_SEPARATOR . $relative;

            if (!file_exists($destPath)) {
                $changed[] = $relative;
                continue;
            }
            if (hash_file('sha256', $srcPath) !== hash_file('sha256', $destPath)) {
                $changed[] = $relative;
            }
        }

        $checkedAt = date('c');
        file_put_contents($checkPath, json_encode(['checked_at' => $checkedAt], JSON_PRETTY_PRINT));

        if ($changed) {
            $payload = [
                'available' => true,
                'count' => count($changed),
                'checked_at' => $checkedAt,
            ];
            file_put_contents($flagPath, json_encode($payload, JSON_PRETTY_PRINT));
            return $payload;
        }

        if (file_exists($flagPath)) {
            unlink($flagPath);
        }
        return ['available' => false, 'count' => 0, 'checked_at' => $checkedAt];
    } catch (Throwable $e) {
        return ['available' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
}

function update_check_download_zip(string $url, string $dest): void
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

function update_check_should_exclude(string $relative): bool
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $first = explode('/', $relative)[0] ?? '';
    if ($first === 'data' || $first === 'config') {
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

function update_check_recursive_scan(string $dir, string $root, array &$files): void
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
            update_check_recursive_scan($path, $root, $files);
        } else {
            $files[] = ltrim(str_replace($root . DIRECTORY_SEPARATOR, '', $path), DIRECTORY_SEPARATOR);
        }
    }
}
