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

// Force PHP to re-read this file when ?nocache=1 (fixes OPCache serving stale code)
if (isset($_GET['nocache']) && function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/updater.php', '?'));
    exit;
}

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
    if ($first === 'data' || $first === 'config' || $first === 'installer') {
        return true;
    }
    if (str_starts_with($relative, '.env')) {
        return true;
    }
    if ($relative === 'public/updater.php') {
        return true;
    }
    $excludedPaths = [
        '.github/workflows/php.yml',
        '.github/workflows/phpunit.yml',
        'composer.json',
        'composer.lock',
        'phpunit.xml',
    ];
    return in_array($relative, $excludedPaths, true);
}

/** Allowed directory prefixes and root files for Domain Tracker. Ignores anything else in the repo. */
function is_project_file(string $relative): bool
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $first = explode('/', $relative)[0] ?? '';
    $allowedDirs = ['public', 'src', 'views', 'migrations', 'scripts', 'demo', '.github'];
    if (in_array($first, $allowedDirs, true)) {
        return true;
    }
    $allowedRootFiles = [
        'composer.json', 'composer.lock', 'phpunit.xml', 'phpunit.xml.dist',
        'README.md', 'DESCRIPTION.md', 'env.example', '.env.example',
        'seed_demo.php', '.gitignore', '.htaccess',
    ];
    return in_array($relative, $allowedRootFiles, true);
}

function append_changelog(string $basePath, array $entry): void
{
    $logPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'changelog.json';
    $entries = [];
    if (file_exists($logPath)) {
        $contents = file_get_contents($logPath);
        if ($contents !== false) {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }
    }
    $entries[] = $entry;
    file_put_contents($logPath, json_encode($entries, JSON_PRETTY_PRINT));
}

/**
 * Compute update preview (created, overwritten, deleted) without applying changes.
 * Uses same rules as the run action: should_exclude + is_project_file.
 */
function update_preview(string $basePath, string $repoZipUrl): array
{
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'domain_tracker_preview_' . bin2hex(random_bytes(6));
    $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'update.zip';
    mkdir($tempDir, 0755, true);

    try {
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
        $remoteFiles = [];
        recursive_scan($extractedRoot, $extractedRoot, $remoteFiles);

        $projectFilesFromRemote = array_filter($remoteFiles, function ($r) {
            return !should_exclude($r) && is_project_file($r);
        });

        $created = [];
        $overwritten = [];
        foreach ($projectFilesFromRemote as $relative) {
            $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
            $destPath = $basePath . DIRECTORY_SEPARATOR . $relative;
            if (!file_exists($destPath)) {
                $created[] = $relative;
            } elseif (hash_file('sha256', $srcPath) !== hash_file('sha256', $destPath)) {
                $overwritten[] = $relative;
            }
        }

        $localFiles = [];
        $excludeRoots = ['data', 'config'];
        collect_local_paths($basePath, $localFiles, $excludeRoots);
        $remoteSet = array_fill_keys($projectFilesFromRemote, true);
        $deleted = [];
        foreach ($localFiles as $relative) {
            if (should_exclude($relative) || !is_project_file($relative)) {
                continue;
            }
            if (!isset($remoteSet[$relative])) {
                $deleted[] = $relative;
            }
        }

        return [
            'created' => array_values($created),
            'overwritten' => array_values($overwritten),
            'deleted' => array_values($deleted),
        ];
    } finally {
        if (is_dir($tempDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($tempDir);
        }
    }
}

function detect_excluded_changes(string $extractedRoot, array $files, string $basePath): array
{
    $changed = [];
    foreach ($files as $relative) {
        if (!should_exclude($relative)) {
            continue;
        }
        $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
        $destPath = $basePath . DIRECTORY_SEPARATOR . $relative;
        if (!file_exists($destPath)) {
            $changed[] = ['path' => $relative, 'status' => 'missing_local'];
            continue;
        }
        if (hash_file('sha256', $srcPath) !== hash_file('sha256', $destPath)) {
            $changed[] = ['path' => $relative, 'status' => 'changed'];
        }
    }
    return $changed;
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

function collect_local_paths(string $basePath, array &$paths, array $excludeRoots): void
{
    $items = scandir($basePath);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $basePath . DIRECTORY_SEPARATOR . $item;
        $relative = ltrim(str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path), DIRECTORY_SEPARATOR);
        $first = explode(DIRECTORY_SEPARATOR, $relative)[0] ?? '';
        if (in_array($first, $excludeRoots, true)) {
            continue;
        }
        if (is_dir($path)) {
            collect_local_paths($path, $paths, $excludeRoots);
        } else {
            $paths[] = $relative;
        }
    }
}

function remove_empty_dirs(string $basePath, array $excludeRoots): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            $relative = ltrim(str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $first = explode(DIRECTORY_SEPARATOR, $relative)[0] ?? '';
            if (in_array($first, $excludeRoots, true)) {
                continue;
            }
            @rmdir($file->getPathname());
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
$demoMode = filter_var(config_value('demo_mode') ?? false, FILTER_VALIDATE_BOOLEAN);

if ($demoMode) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Updater - Demo</title>';
    echo '<link rel="icon" type="image/png" href="/assets/favicon.png">';
    echo '<link rel="stylesheet" href="/assets/style.css"></head><body>';
    echo '<header class="topbar"><div class="container topbar-inner">';
    echo '<div class="brand">' . htmlspecialchars((string) config_value('app_name'), ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<nav class="topbar-actions">';
    echo '<a href="/index.php" class="link">Dashboard</a>';
    echo '<a href="/index.php?action=expiry" class="link">Expiry</a>';
    echo '<a href="/index.php?action=domain_import" class="link">Import</a>';
    echo '<a href="/security.php" class="link">Security</a>';
    echo '<a href="/updater.php" class="link is-disabled">Update</a>';
    echo '<a href="/index.php" class="link">Help</a>';
    echo '<a href="/logout.php" class="link">Logout</a>';
    echo '</nav></div></header>';
    echo '<main class="container"><div class="card is-disabled">';
    echo '<h1>Updater</h1>';
    echo '<div class="alert alert-info">Demo mode: Updates are disabled.</div>';
    echo '<p class="muted">This feature is not available in the demo instance.</p>';
    echo '<a href="/index.php" class="button primary">Return to Dashboard</a>';
    echo '</div></main></body></html>';
    exit;
}

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
                'deleted' => [],
                'timestamp' => $backupDirName,
            ];

            $step(65, 'Applying changes...');
            $projectFilesFromRemote = array_filter($files, function ($r) {
                return !should_exclude($r) && is_project_file($r);
            });
            foreach ($projectFilesFromRemote as $relative) {
                $srcPath = $extractedRoot . DIRECTORY_SEPARATOR . $relative;
                copy_with_backup($srcPath, $basePath, $relative, $backupFilesDir, $manifest);
            }

            $step(75, 'Removing deleted files...');
            $localFiles = [];
            $excludeRoots = ['data', 'config'];
            collect_local_paths($basePath, $localFiles, $excludeRoots);
            $remoteSet = array_fill_keys($projectFilesFromRemote, true);
            foreach ($localFiles as $relative) {
                if (should_exclude($relative) || !is_project_file($relative)) {
                    continue;
                }
                if (!isset($remoteSet[$relative])) {
                    $path = $basePath . DIRECTORY_SEPARATOR . $relative;
                    $backupPath = $backupFilesDir . DIRECTORY_SEPARATOR . $relative;
                    $backupDir = dirname($backupPath);
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    if (file_exists($path)) {
                        copy($path, $backupPath);
                        unlink($path);
                        $manifest['deleted'][] = $relative;
                    }
                }
            }
            remove_empty_dirs($basePath, $excludeRoots);

            $step(85, 'Finalizing update...');
            file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            prune_backups($backupsRoot, 5);
            $flagPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_available.json';
            if (file_exists($flagPath)) {
                unlink($flagPath);
            }
            $excludedChanged = detect_excluded_changes($extractedRoot, $files, $basePath);
            append_changelog($basePath, [
                'timestamp' => date('c'),
                'created' => $manifest['created'],
                'overwritten' => $manifest['overwritten'],
                'deleted' => $manifest['deleted'],
                'excluded_changed' => $excludedChanged,
            ]);
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
            foreach (array_merge($manifest['overwritten'] ?? [], $manifest['deleted'] ?? []) as $relative) {
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
            append_changelog($basePath, [
                'timestamp' => date('c'),
                'rollback_to' => $target,
            ]);
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

    if ($action === 'preview') {
        $flagPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_available.json';
        if (!file_exists($flagPath)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No update available. Run Check for Updates first.']);
            exit;
        }
        try {
            $preview = update_preview($basePath, $repoZipUrl);
            header('Content-Type: application/json');
            echo json_encode($preview);
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
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

// Prevent browser caching so updates to this page are visible
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
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
                <a href="/index.php?action=expiry" class="link">Expiry</a>
                <a href="/index.php?action=domain_import" class="link">Import</a>
                <a href="/security.php" class="link">Security</a>
                <a href="/updater.php" class="link">Update</a>
                <a href="/index.php" class="link">Help</a>
                <a href="/updater.php?logout=1" class="link">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1>Updater</h1>
            <p class="muted">Safely update app files from the repository. Your database and env files are preserved.</p>

            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (str_starts_with((string) $msg, 'Update available')): ?>
                        <button type="button" class="button view-update-details-btn" style="margin-left:12px; vertical-align:middle;">View details</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>

            <div class="alert alert-warn">
                This will overwrite app files. Backups are stored in `data/backups/`.
            </div>

            <?php
            $logPath = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'changelog.json';
            $changelog = [];
            if (file_exists($logPath)) {
                $contents = file_get_contents($logPath);
                if ($contents !== false) {
                    $decoded = json_decode($contents, true);
                    if (is_array($decoded)) {
                        $changelog = array_reverse($decoded);
                    }
                }
            }
            ?>
            <?php if (!empty($changelog)): ?>
                <div class="card-section">
                    <h2>Change Log</h2>
                    <div class="table">
                        <div class="table-row table-head">
                            <div>Timestamp</div>
                            <div>Created</div>
                            <div>Overwritten</div>
                            <div>Deleted</div>
                            <div>Excluded Updates</div>
                        </div>
                        <?php foreach (array_slice($changelog, 0, 20) as $entry): ?>
                            <div class="table-row">
                                <div><?php echo htmlspecialchars((string) ($entry['timestamp'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div><?php echo (int) count($entry['created'] ?? []); ?></div>
                                <div><?php echo (int) count($entry['overwritten'] ?? []); ?></div>
                                <div><?php echo (int) count($entry['deleted'] ?? []); ?></div>
                                <div>
                                    <?php
                                    $excluded = $entry['excluded_changed'] ?? [];
                                    if (!empty($excluded)) {
                                        $paths = array_map(function ($item) {
                                            return $item['path'] ?? '';
                                        }, $excluded);
                                        echo htmlspecialchars(implode(', ', array_slice($paths, 0, 3)), ENT_QUOTES, 'UTF-8');
                                        if (count($paths) > 3) {
                                            echo '...';
                                        }
                                    } else {
                                        echo 'None';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="check">
                    <button type="submit" class="button">Check for Updates</button>
                </form>
                <button type="button" class="button view-update-details-btn">View update details</button>
            </div>

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

    <div id="updateDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="updateDetailsTitle" hidden>
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="updateDetailsTitle">Update details</h2>
                <button type="button" class="modal-close" id="closeUpdateDetailsModal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="updateDetailsLoading" class="muted">Loading...</div>
                <div id="updateDetailsError" class="alert alert-error" hidden></div>
                <div id="updateDetailsContent" hidden>
                    <div id="updateDetailsCreated" class="update-details-section">
                        <h3>Created</h3>
                        <ul id="updateDetailsCreatedList"></ul>
                    </div>
                    <div id="updateDetailsOverwritten" class="update-details-section">
                        <h3>Overwritten</h3>
                        <ul id="updateDetailsOverwrittenList"></ul>
                    </div>
                    <div id="updateDetailsDeleted" class="update-details-section">
                        <h3>Deleted</h3>
                        <ul id="updateDetailsDeletedList"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        var viewBtns = document.querySelectorAll('.view-update-details-btn');
        var modal = document.getElementById('updateDetailsModal');
        var closeBtn = document.getElementById('closeUpdateDetailsModal');
        var loading = document.getElementById('updateDetailsLoading');
        var errorEl = document.getElementById('updateDetailsError');
        var content = document.getElementById('updateDetailsContent');

        if (!viewBtns.length) return;

        function showModal() {
            modal.hidden = false;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function hideModal() {
            modal.hidden = true;
            modal.style.display = '';
            document.body.style.overflow = '';
        }

        function renderList(container, items) {
            container.innerHTML = '';
            items.forEach(function(path) {
                var li = document.createElement('li');
                li.textContent = path;
                li.title = path;
                container.appendChild(li);
            });
        }

        function handleViewDetails() {
            showModal();
            loading.hidden = false;
            errorEl.hidden = true;
            content.hidden = true;

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'preview');

            fetch('/updater.php', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loading.hidden = true;
                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.hidden = false;
                    return;
                }
                content.hidden = false;
                renderList(document.getElementById('updateDetailsCreatedList'), data.created || []);
                renderList(document.getElementById('updateDetailsOverwrittenList'), data.overwritten || []);
                renderList(document.getElementById('updateDetailsDeletedList'), data.deleted || []);
                document.getElementById('updateDetailsCreated').hidden = !(data.created && data.created.length);
                document.getElementById('updateDetailsOverwritten').hidden = !(data.overwritten && data.overwritten.length);
                document.getElementById('updateDetailsDeleted').hidden = !(data.deleted && data.deleted.length);
            })
            .catch(function(err) {
                loading.hidden = true;
                errorEl.textContent = 'Failed to load update details.';
                errorEl.hidden = false;
            });
        }
        viewBtns.forEach(function(btn) { btn.addEventListener('click', handleViewDetails); });

        closeBtn.addEventListener('click', hideModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) hideModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.hidden) hideModal();
        });
    })();
    </script>

    <style>
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .modal-card {
        background: var(--card-bg, #fff);
        border-radius: 8px;
        max-width: 560px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }
    .modal-header h2 { margin: 0; font-size: 1.25rem; }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        line-height: 1;
        padding: 0 4px;
        color: var(--text-muted, #666);
    }
    .modal-close:hover { color: var(--text-color, #333); }
    .modal-body {
        padding: 20px;
        overflow-y: auto;
        max-height: calc(80vh - 80px);
    }
    .update-details-section { margin-bottom: 16px; }
    .update-details-section h3 { margin: 0 0 8px; font-size: 0.9rem; }
    .update-details-section ul {
        margin: 0;
        padding-left: 20px;
        font-family: monospace;
        font-size: 0.85rem;
        word-break: break-all;
    }
    .update-details-section li { margin: 4px 0; }
    </style>
</body>
</html>
