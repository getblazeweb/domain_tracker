<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    echo "This updater is intended for web access.\n";
    exit(1);
}

function load_env(string $path): void
{
    if (!file_exists($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$basePath = dirname(__DIR__);
load_env($basePath . '/.env');
$config = require $basePath . '/config/app.php';

function config_value(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

session_name('domain_tracker_updater');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): void
{
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['updater_logged_in']);
}

function attempt_login(string $username, string $password): bool
{
    $expectedUser = (string) config_value('admin_username');
    $expectedHash = (string) config_value('admin_password_hash');
    if ($expectedHash === '' || $expectedUser === '') {
        return false;
    }
    if (!hash_equals($expectedUser, $username)) {
        return false;
    }
    if (!password_verify($password, $expectedHash)) {
        return false;
    }
    $_SESSION['updater_logged_in'] = true;
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

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

function copy_with_backup(string $src, string $destRoot, string $relative, string $backupRoot, array &$manifest): void
{
    $dest = $destRoot . DIRECTORY_SEPARATOR . $relative;
    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (file_exists($dest)) {
        if (hash_file('sha256', $src) === hash_file('sha256', $dest)) {
            return;
        }
        $backupPath = $backupRoot . DIRECTORY_SEPARATOR . $relative;
        $backupDir = dirname($backupPath);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        copy($dest, $backupPath);
        $manifest['overwritten'][] = $relative;
    } else {
        $manifest['created'][] = $relative;
    }

    copy($src, $dest);
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

function latest_backup_dir(string $backupsRoot): ?string
{
    if (!is_dir($backupsRoot)) {
        return null;
    }
    $entries = array_values(array_filter(scandir($backupsRoot) ?: [], function ($entry) use ($backupsRoot) {
        return $entry !== '.' && $entry !== '..' && is_dir($backupsRoot . DIRECTORY_SEPARATOR . $entry);
    }));
    rsort($entries);
    return $entries[0] ?? null;
}

function list_backups(string $backupsRoot): array
{
    if (!is_dir($backupsRoot)) {
        return [];
    }
    $entries = array_values(array_filter(scandir($backupsRoot) ?: [], function ($entry) use ($backupsRoot) {
        return $entry !== '.' && $entry !== '..' && is_dir($backupsRoot . DIRECTORY_SEPARATOR . $entry);
    }));
    rsort($entries);
    return $entries;
}

function prune_backups(string $backupsRoot, int $keep): void
{
    $backups = list_backups($backupsRoot);
    if (count($backups) <= $keep) {
        return;
    }
    $remove = array_slice($backups, $keep);
    foreach ($remove as $entry) {
        $path = $backupsRoot . DIRECTORY_SEPARATOR . $entry;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}

$repoZipUrl = 'https://github.com/getblazeweb/domain_tracker/archive/refs/heads/main.zip';
$messages = [];
$errors = [];

if (isset($_GET['logout'])) {
    logout();
    header('Location: /updater.php');
    exit;
}

if (!is_logged_in()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!attempt_login($username, $password)) {
            $error = 'Invalid username or password.';
        }
    }

    if (!is_logged_in()):
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Updater Login</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon" aria-hidden="true">DT</div>
            <div>
                <h1 class="auth-title">Updater</h1>
                <p class="muted auth-subtitle">Sign in to continue</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" class="form auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="login">
            <label>
                Username
                <input type="text" name="username" required autocomplete="username">
            </label>
            <label>
                Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="button primary">Sign in</button>
        </form>
    </div>
</body>
</html>
<?php
        exit;
    endif;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update') {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">";
        echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
        echo "<title>Updating...</title>";
        echo "<link rel=\"stylesheet\" href=\"/assets/style.css\"></head><body>";
        echo "<main class=\"container\"><div class=\"card\">";
        echo "<h1>Updating...</h1><p class=\"muted\">Please keep this tab open.</p>";
        echo "<div class=\"progress-wrap\"><div class=\"progress-bar\" id=\"progressBar\"></div></div>";
        echo "<div id=\"progressMessage\" class=\"muted\" style=\"margin-top:8px;\">Starting update...</div>";
        echo "</div></main>";
        echo "<script>
        function updateProgress(percent, message) {
            var bar = document.getElementById('progressBar');
            var msg = document.getElementById('progressMessage');
            if (bar) bar.style.width = percent + '%';
            if (msg) msg.textContent = message || '';
        }
        </script>";
        flush();

        $step = function (int $percent, string $message): void {
            echo '<script>updateProgress(' . $percent . ', ' . json_encode($message) . ');</script>';
            flush();
        };

        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive extension is not available on this server.');
            }

            $step(10, 'Preparing backup...');
            $backupsRoot = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';
            if (!is_dir($backupsRoot)) {
                mkdir($backupsRoot, 0700, true);
            }
            $backupDirName = date('Ymd_His');
            $backupDir = $backupsRoot . DIRECTORY_SEPARATOR . $backupDirName;
            $backupFilesDir = $backupDir . DIRECTORY_SEPARATOR . 'files';
            mkdir($backupFilesDir, 0700, true);

            $step(20, 'Downloading update...');
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'domain_tracker_update_' . bin2hex(random_bytes(6));
            $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'update.zip';
            mkdir($tempDir, 0755, true);

            download_zip($repoZipUrl, $zipPath);

            $step(35, 'Extracting update...');
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

            $step(50, 'Scanning files...');
            $extractedRoot = $tempDir . DIRECTORY_SEPARATOR . $entries[0];
            $files = [];
            recursive_scan($extractedRoot, $extractedRoot, $files);

            $manifest = [
                'created' => [],
                'overwritten' => [],
                'timestamp' => $backupDirName,
            ];

            $step(65, 'Applying changes...');
            foreach ($files as $relative) {
                if (should_exclude($relative)) {
                    continue;
                }
                $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
                copy_with_backup($srcPath, $basePath, $relative, $backupFilesDir, $manifest);
            }

            $step(85, 'Finalizing update...');
            file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            prune_backups($backupsRoot, 5);
            $flagPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_available.json';
            if (file_exists($flagPath)) {
                unlink($flagPath);
            }
            $step(100, 'Update completed successfully.');
            echo "<div class=\"card\" style=\"margin-top:16px;\"><a class=\"button primary\" href=\"/updater.php\">Return to updater</a></div>";
            echo "</body></html>";
            exit;
        } catch (Throwable $e) {
            echo "<script>updateProgress(100, " . json_encode('Update failed.') . ");</script>";
            echo "<div class=\"alert alert-error\" style=\"margin-top:16px;\">" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            echo "<div class=\"card\" style=\"margin-top:16px;\"><a class=\"button\" href=\"/updater.php\">Return to updater</a></div>";
            echo "</body></html>";
            exit;
        }
    }

    if ($action === 'rollback') {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">";
        echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
        echo "<title>Rolling back...</title>";
        echo "<link rel=\"stylesheet\" href=\"/assets/style.css\"></head><body>";
        echo "<main class=\"container\"><div class=\"card\">";
        echo "<h1>Rolling back...</h1><p class=\"muted\">Please keep this tab open.</p>";
        echo "<div class=\"progress-wrap\"><div class=\"progress-bar\" id=\"progressBar\"></div></div>";
        echo "<div id=\"progressMessage\" class=\"muted\" style=\"margin-top:8px;\">Starting rollback...</div>";
        echo "</div></main>";
        echo "<script>
        function updateProgress(percent, message) {
            var bar = document.getElementById('progressBar');
            var msg = document.getElementById('progressMessage');
            if (bar) bar.style.width = percent + '%';
            if (msg) msg.textContent = message || '';
        }
        </script>";
        flush();

        $step = function (int $percent, string $message): void {
            echo '<script>updateProgress(' . $percent . ', ' . json_encode($message) . ');</script>';
            flush();
        };

        try {
            $backupsRoot = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';
            $selected = (string) ($_POST['backup'] ?? '');
            $target = $selected !== '' ? $selected : latest_backup_dir($backupsRoot);
            if ($target === null || $target === '') {
                throw new RuntimeException('No backups found.');
            }
            $step(15, 'Loading backup...');
            $backupDir = $backupsRoot . DIRECTORY_SEPARATOR . $target;
            $manifestPath = $backupDir . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!file_exists($manifestPath)) {
                throw new RuntimeException('Backup manifest missing.');
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                throw new RuntimeException('Invalid backup manifest.');
            }

            $step(40, 'Removing created files...');
            foreach ($manifest['created'] ?? [] as $relative) {
                $path = $basePath . DIRECTORY_SEPARATOR . $relative;
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $step(70, 'Restoring previous versions...');
            $backupFilesDir = $backupDir . DIRECTORY_SEPARATOR . 'files';
            foreach ($manifest['overwritten'] ?? [] as $relative) {
                $backupPath = $backupFilesDir . DIRECTORY_SEPARATOR . $relative;
                $destPath = $basePath . DIRECTORY_SEPARATOR . $relative;
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                if (file_exists($backupPath)) {
                    copy($backupPath, $destPath);
                }
            }

            $step(90, 'Finalizing rollback...');
            $flagPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_available.json';
            if (file_exists($flagPath)) {
                unlink($flagPath);
            }
            $step(100, 'Rollback completed successfully.');
            echo "<div class=\"card\" style=\"margin-top:16px;\"><a class=\"button primary\" href=\"/updater.php\">Return to updater</a></div>";
            echo "</body></html>";
            exit;
        } catch (Throwable $e) {
            echo "<script>updateProgress(100, " . json_encode('Rollback failed.') . ");</script>";
            echo "<div class=\"alert alert-error\" style=\"margin-top:16px;\">" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            echo "<div class=\"card\" style=\"margin-top:16px;\"><a class=\"button\" href=\"/updater.php\">Return to updater</a></div>";
            echo "</body></html>";
            exit;
        }
    }

    if ($action === 'check') {
        require_once $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'update_check.php';
        $result = update_check_run($basePath, true);
        if (!empty($result['error'])) {
            $errors[] = 'Update check failed: ' . $result['error'];
        } elseif (!empty($result['available'])) {
            $messages[] = 'Update available (' . (int) $result['count'] . ' file' . ((int) $result['count'] === 1 ? '' : 's') . ').';
        } else {
            $messages[] = 'No updates found.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Updater</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand"><?php echo htmlspecialchars((string) config_value('app_name'), ENT_QUOTES, 'UTF-8'); ?></div>
            <nav class="topbar-actions">
                <a href="/index.php" class="link">Dashboard</a>
                <a href="/updater.php?logout=1" class="link">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1>Updater</h1>
            <p class="muted">Safely update app files from the repository. Your database and env files are preserved.</p>

            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>

            <div class="alert alert-warn">
                This will overwrite app files. Backups are stored in `data/backups/`.
            </div>

            <form method="post" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="check">
                <button type="submit" class="button">Check for Updates</button>
            </form>

            <form method="post" class="form" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update">
                <button type="submit" class="button primary" onclick="return confirm('Run update now?');">Run Update</button>
            </form>

            <?php $backupOptions = list_backups($basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups'); ?>
            <form method="post" class="form" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="rollback">
                <label>
                    Rollback to backup
                    <select name="backup">
                        <option value="">Latest backup</option>
                        <?php foreach ($backupOptions as $backup): ?>
                            <?php
                            $label = $backup;
                            if (preg_match('/^(\\d{4})(\\d{2})(\\d{2})_(\\d{2})(\\d{2})(\\d{2})$/', $backup, $m)) {
                                $label = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
                            }
                            ?>
                            <option value="<?php echo htmlspecialchars($backup, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="button" onclick="return confirm('Rollback the selected backup?');">Rollback</button>
            </form>
        </div>
    </main>
</body>
</html>
