<?php
declare(strict_types=1);
/**
 * Domain Tracker - Web-based Installer
 * Copy this file to your server (e.g. public_html/installer.php), visit it in a browser, and follow the steps.
 */
if (PHP_SAPI === 'cli') {
    echo "Run this installer via a web browser.\n";
    exit(1);
}

$installPath = __DIR__;
$lockFile = $installPath . DIRECTORY_SEPARATOR . 'install.lock';
$repoZipUrl = 'https://github.com/getblazeweb/domain_tracker/archive/refs/heads/main.zip';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['installer_csrf'])) {
        $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['installer_csrf'];
}

function verify_csrf(string $token): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['installer_csrf']) || !hash_equals($_SESSION['installer_csrf'], $token)) {
        http_response_code(400);
        echo 'Invalid request.';
        exit;
    }
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
            CURLOPT_USERAGENT => 'DomainTracker-Installer',
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok) {
            throw new RuntimeException('Download failed: ' . $err);
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

function recursive_copy(string $src, string $dest, array $exclude): void
{
    if (!is_dir($src)) {
        return;
    }
    $items = scandir($src);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $rel = ltrim(str_replace($src . DIRECTORY_SEPARATOR, '', $srcPath), DIRECTORY_SEPARATOR);
        $norm = str_replace('\\', '/', $rel);
        $first = explode('/', $norm)[0] ?? '';
        if (in_array($first, $exclude, true) || in_array($norm, $exclude, true)) {
            continue;
        }
        $destPath = $dest . DIRECTORY_SEPARATOR . $item;
        if (is_dir($srcPath)) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
            recursive_copy($srcPath, $destPath, $exclude);
        } else {
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            copy($srcPath, $destPath);
        }
    }
}

/** Patch extracted app files so assets and links work when doc root is the install directory (parent of public/). */
function apply_asset_path_fix(string $installPath): void
{
    $urlFns = "\n/** URL path to the public directory (for assets). Works when doc root is public/ or a parent. */\n"
        . "function asset_url(string \$path): string\n{\n"
        . "    \$scriptDir = dirname(\$_SERVER['SCRIPT_NAME'] ?? '/index.php');\n"
        . "    \$base = (\$scriptDir === '/' || \$scriptDir === '\\\\') ? '' : rtrim(\$scriptDir, '/');\n"
        . "    return \$base . '/' . ltrim(\$path, '/');\n}\n\n"
        . "/** URL path for app pages (index, updater, login, etc). Handles paths with query strings. */\n"
        . "function app_url(string \$path): string\n{\n"
        . "    \$path = ltrim(\$path, '/');\n"
        . "    \$query = '';\n"
        . "    if (str_contains(\$path, '?')) {\n"
        . "        [\$path, \$q] = explode('?', \$path, 2);\n"
        . "        \$query = '?' . \$q;\n"
        . "    }\n"
        . "    return asset_url(\$path) . \$query;\n}\n\n";
    $bootstrapPath = $installPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';
    if (file_exists($bootstrapPath)) {
        $content = file_get_contents($bootstrapPath);
        if ($content !== false && !str_contains($content, 'function asset_url(')) {
            $needle = "}\n\nfunction config(";
            $replacement = "}\n" . $urlFns . "function config(";
            $newContent = str_replace($needle, $replacement, $content);
            if ($newContent !== $content) {
                file_put_contents($bootstrapPath, $newContent);
            }
        } elseif ($content !== false && !str_contains($content, 'function app_url(')) {
            $appUrlFn = "\n/** URL path for app pages (index, updater, login, etc). Handles paths with query strings. */\n"
                . "function app_url(string \$path): string\n{\n"
                . "    \$path = ltrim(\$path, '/');\n"
                . "    \$query = '';\n"
                . "    if (str_contains(\$path, '?')) {\n"
                . "        [\$path, \$q] = explode('?', \$path, 2);\n"
                . "        \$query = '?' . \$q;\n"
                . "    }\n"
                . "    return asset_url(\$path) . \$query;\n}\n\n";
            $needle2 = "}\n\nfunction config(";
            $replacement2 = "}\n" . $appUrlFn . "function config(";
            $newContent = str_replace($needle2, $replacement2, $content);
            if ($newContent !== $content) {
                file_put_contents($bootstrapPath, $newContent);
            }
        }
    }

    $patchFile = function (string $path, array $replacements) use ($installPath): void {
        $fullPath = $installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($fullPath)) {
            return;
        }
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return;
        }
        $changed = false;
        foreach ($replacements as [$from, $to]) {
            if (str_contains($content, $from)) {
                $content = str_replace($from, $to, $content);
                $changed = true;
            }
        }
        if ($changed) {
            file_put_contents($fullPath, $content);
        }
    };

    $patchFile('public/login.php', [
        ['<link rel="icon" type="image/png" href="/assets/favicon.png">', '<link rel="icon" type="image/png" href="<?php echo htmlspecialchars(asset_url(\'assets/favicon.png\'), ENT_QUOTES, \'UTF-8\'); ?>">'],
        ['<link rel="stylesheet" href="/assets/style.css">', '<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url(\'assets/style.css\'), ENT_QUOTES, \'UTF-8\'); ?>">'],
        ["header('Location: /index.php');", "header('Location: ' . app_url('index.php'));"],
    ]);
    $patchFile('public/logout.php', [
        ["header('Location: /login.php');", "header('Location: ' . app_url('login.php'));"],
    ]);
    $patchFile('src/auth.php', [
        ["header('Location: /login.php');", "header('Location: ' . app_url('login.php'));"],
    ]);
    $patchFile('public/index.php', [
        ["\$return = (string) (\$_GET['return'] ?? '/index.php');\n    if (\$return === '' || \$return[0] !== '/' || strpos(\$return, '//') !== false) {\n        \$return = '/index.php';\n    }", "\$return = (string) (\$_GET['return'] ?? '');\n    if (\$return === '' || \$return[0] !== '/' || strpos(\$return, '//') !== false) {\n        \$return = app_url('index.php');\n    }"],
    ]);

    $patchFile('views/layout.php', [
        ['<link rel="icon" type="image/png" href="/assets/favicon.png">', '<link rel="icon" type="image/png" href="<?php echo e(asset_url(\'assets/favicon.png\')); ?>">'],
        ['<link rel="stylesheet" href="/assets/style.css">', '<link rel="stylesheet" href="<?php echo e(asset_url(\'assets/style.css\')); ?>">'],
        ['<script src="/assets/tour.js"></script>', '<script src="<?php echo e(asset_url(\'assets/tour.js\')); ?>"></script>'],
        ['action="/index.php?action=tour_dismiss"', 'action="<?php echo e(app_url(\'index.php?action=tour_dismiss\')); ?>"'],
        ['action="<?php echo e(asset_url(\'index.php\') . \'?action=tour_dismiss\'); ?>"', 'action="<?php echo e(app_url(\'index.php?action=tour_dismiss\')); ?>"'],
        ['<a href="/index.php" class="link">Dashboard</a>', '<a href="<?php echo e(app_url(\'index.php\')); ?>" class="link">Dashboard</a>'],
        ['<a href="/index.php?action=expiry" class="link"', '<a href="<?php echo e(app_url(\'index.php?action=expiry\')); ?>" class="link"'],
        ['<a href="/index.php?action=domain_import" class="link">Import</a>', '<a href="<?php echo e(app_url(\'index.php?action=domain_import\')); ?>" class="link">Import</a>'],
        ['<a href="/security.php" class="link"', '<a href="<?php echo e(app_url(\'security.php\')); ?>" class="link"'],
        ['<a href="/updater.php" class="link', '<a href="<?php echo e(app_url(\'updater.php\')); ?>" class="link'],
        ['<a href="/logout.php" class="link">Logout</a>', '<a href="<?php echo e(app_url(\'logout.php\')); ?>" class="link">Logout</a>'],
    ]);

    $patchFile('public/security.php', [
        ['<link rel="icon" type="image/png" href="/assets/favicon.png">', '<link rel="icon" type="image/png" href="<?php echo htmlspecialchars(asset_url(\'assets/favicon.png\'), ENT_QUOTES, \'UTF-8\'); ?>">'],
        ['<link rel="stylesheet" href="/assets/style.css">', '<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url(\'assets/style.css\'), ENT_QUOTES, \'UTF-8\'); ?>">'],
        ['<a href="/index.php" class="link">Dashboard</a>', '<a href="<?php echo e(app_url(\'index.php\')); ?>" class="link">Dashboard</a>'],
        ['<a href="/index.php?action=expiry" class="link">Expiry</a>', '<a href="<?php echo e(app_url(\'index.php?action=expiry\')); ?>" class="link">Expiry</a>'],
        ['<a href="/index.php?action=domain_import" class="link">Import</a>', '<a href="<?php echo e(app_url(\'index.php?action=domain_import\')); ?>" class="link">Import</a>'],
        ['<a href="/security.php" class="link">Security</a>', '<a href="<?php echo e(app_url(\'security.php\')); ?>" class="link">Security</a>'],
        ['<a href="/updater.php" class="link', '<a href="<?php echo e(app_url(\'updater.php\')); ?>" class="link'],
        ['<a href="/index.php" class="link">Help</a>', '<a href="<?php echo e(app_url(\'index.php\')); ?>" class="link">Help</a>'],
        ['<a href="/logout.php" class="link">Logout</a>', '<a href="<?php echo e(app_url(\'logout.php\')); ?>" class="link">Logout</a>'],
    ]);

    $patchFile('views/dashboard.php', [
        ['<a href="/updater.php" class="button">Open Updater</a>', '<a href="<?php echo e(app_url(\'updater.php\')); ?>" class="button">Open Updater</a>'],
        ['<a href="/index.php?action=expiry" class="button">View Expiry</a>', '<a href="<?php echo e(app_url(\'index.php?action=expiry\')); ?>" class="button">View Expiry</a>'],
        ['<form method="get" class="search-form" action="/index.php">', '<form method="get" class="search-form" action="<?php echo e(app_url(\'index.php\')); ?>">'],
        ['<a class="button" href="/index.php">Clear</a>', '<a class="button" href="<?php echo e(app_url(\'index.php\')); ?>">Clear</a>'],
        ['<a class="button" href="/index.php?action=domain_import" data-tour="import">Import CSV</a>', '<a class="button" href="<?php echo e(app_url(\'index.php?action=domain_import\')); ?>" data-tour="import">Import CSV</a>'],
        ['<a class="button primary" href="/index.php?action=domain_create" data-tour="add-domain">Add Domain</a>', '<a class="button primary" href="<?php echo e(app_url(\'index.php?action=domain_create\')); ?>" data-tour="add-domain">Add Domain</a>'],
        ['<a class="button" href="/index.php?action=subdomain_create&domain_id=<?php echo (int) $domain[\'id\']; ?>">Add Subdomain</a>', '<a class="button" href="<?php echo e(app_url(\'index.php?action=subdomain_create&domain_id=\' . (int) $domain[\'id\'])); ?>">Add Subdomain</a>'],
        ['<a class="button" href="/index.php?action=domain_edit&id=<?php echo (int) $domain[\'id\']; ?>">Edit</a>', '<a class="button" href="<?php echo e(app_url(\'index.php?action=domain_edit&id=\' . (int) $domain[\'id\'])); ?>">Edit</a>'],
        ['<form method="post" action="/index.php?action=domain_delete"', '<form method="post" action="<?php echo e(app_url(\'index.php?action=domain_delete\')); ?>"'],
        ['<a class="button" href="/index.php?action=subdomain_edit&id=<?php echo (int) $sub[\'id\']; ?>">Edit</a>', '<a class="button" href="<?php echo e(app_url(\'index.php?action=subdomain_edit&id=\' . (int) $sub[\'id\'])); ?>">Edit</a>'],
        ['<form method="post" action="/index.php?action=subdomain_delete"', '<form method="post" action="<?php echo e(app_url(\'index.php?action=subdomain_delete\')); ?>"'],
    ]);

    $patchFile('views/expiry.php', [
        ['<a class="button refresh-attention" href="/index.php?action=check_expiry">Refresh</a>', '<a class="button refresh-attention" href="<?php echo e(app_url(\'index.php?action=check_expiry\')); ?>">Refresh</a>'],
        ['<a class="button primary" href="/index.php">Dashboard</a>', '<a class="button primary" href="<?php echo e(app_url(\'index.php\')); ?>">Dashboard</a>'],
        ['<a class="link" href="/index.php?action=domain_edit&id=<?php echo (int) $d[\'id\']; ?>"><?php echo e((string) $d[\'name\']); ?></a>', '<a class="link" href="<?php echo e(app_url(\'index.php?action=domain_edit&id=\' . (int) $d[\'id\'])); ?>"><?php echo e((string) $d[\'name\']); ?></a>'],
        ['<a class="button tiny" href="/index.php?action=domain_edit&id=<?php echo (int) $d[\'id\']; ?>">Edit</a>', '<a class="button tiny" href="<?php echo e(app_url(\'index.php?action=domain_edit&id=\' . (int) $d[\'id\'])); ?>">Edit</a>'],
    ]);

    $patchFile('views/domain_import.php', [
        ['<a class="button" href="/download.php?file=import.csv"', '<a class="button" href="<?php echo e(app_url(\'download.php?file=import.csv\')); ?>"'],
        ['<a class="button" href="/index.php">Back</a>', '<a class="button" href="<?php echo e(app_url(\'index.php\')); ?>">Back</a>'],
        ['action="/index.php?action=domain_import_process"', 'action="<?php echo e(app_url(\'index.php?action=domain_import_process\')); ?>"'],
    ]);

    $patchFile('views/domain_form.php', [
        ['<a class="button" href="/index.php">Back</a>', '<a class="button" href="<?php echo e(app_url(\'index.php\')); ?>">Back</a>'],
        ['<form method="post" class="form card data-form" action="/index.php?action=<?php echo e($action); ?>">', '<form method="post" class="form card data-form" action="<?php echo e(app_url(\'index.php?action=\' . $action)); ?>">'],
    ]);

    $patchFile('views/subdomain_form.php', [
        ['<a class="button" href="/index.php">Back</a>', '<a class="button" href="<?php echo e(app_url(\'index.php\')); ?>">Back</a>'],
        ['<form method="post" class="form card data-form" action="/index.php?action=<?php echo e($action); ?>">', '<form method="post" class="form card data-form" action="<?php echo e(app_url(\'index.php?action=\' . $action)); ?>">'],
    ]);

    $updaterPath = $installPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'updater.php';
    if (file_exists($updaterPath)) {
        $content = file_get_contents($updaterPath);
        if ($content !== false && !str_contains($content, 'function updater_url(')) {
            $updaterUrlFn = "\nfunction updater_url(string \$path): string\n{\n"
                . "    \$path = ltrim(\$path, '/');\n"
                . "    \$query = '';\n"
                . "    if (str_contains(\$path, '?')) {\n"
                . "        [\$path, \$q] = explode('?', \$path, 2);\n"
                . "        \$query = '?' . \$q;\n"
                . "    }\n"
                . "    \$scriptDir = dirname(\$_SERVER['SCRIPT_NAME'] ?? '/updater.php');\n"
                . "    \$base = (\$scriptDir === '/' || \$scriptDir === '\\\\') ? '' : rtrim(\$scriptDir, '/');\n"
                . "    return \$base . '/' . \$path . \$query;\n}\n\n";
            $needle = "require_login();\n\n";
            $replacement = "require_login();\n" . $updaterUrlFn;
            $newContent = str_replace($needle, $replacement, $content);
            if ($newContent === $content) {
                $needle = "require_login();\n";
                $replacement = "require_login();\n" . $updaterUrlFn;
                $newContent = str_replace($needle, $replacement, $content);
            }
            if ($newContent !== $content) {
                $newContent = preg_replace('#href="/assets/([^"]+)"#', 'href="<?php echo htmlspecialchars(updater_url(\'assets/$1\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = preg_replace('#src="/assets/([^"]+)"#', 'src="<?php echo htmlspecialchars(updater_url(\'assets/$1\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = str_replace('href="/updater.php"', 'href="<?php echo htmlspecialchars(updater_url(\'updater.php\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = str_replace('href="/index.php"', 'href="<?php echo htmlspecialchars(updater_url(\'index.php\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = preg_replace("#header\\('Location: /([^']+)'\\)#", "header('Location: ' . updater_url('$1'))", $newContent);
                file_put_contents($updaterPath, $newContent);
            }
        }
    }
}

function check_requirements(): array
{
    $errors = [];
    $warnings = [];
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        $errors[] = 'PHP 8.1+ required (current: ' . PHP_VERSION . ')';
    }
    foreach (['pdo_sqlite', 'openssl', 'zip'] as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "PHP extension required: $ext";
        }
    }
    if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
        $errors[] = 'cURL or allow_url_fopen required for download';
    }
    $installPath = __DIR__;
    if (!is_writable($installPath)) {
        $errors[] = 'Install directory is not writable: ' . $installPath;
    }
    if (file_exists($installPath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db')) {
        $warnings[] = 'Existing database found. Extract will skip data/. You can still update .env.';
    }
    return ['errors' => $errors, 'warnings' => $warnings];
}

session_name('domain_tracker_installer');
session_start();

$step = (int) ($_GET['step'] ?? 1);
$errors = [];
$messages = [];
$installComplete = false;

if (file_exists($lockFile)) {
    $step = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'download') {
        $step = 2;
        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive extension is not available.');
            }
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dt_install_' . bin2hex(random_bytes(6));
            $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'main.zip';
            mkdir($tempDir, 0755, true);
            download_zip($repoZipUrl, $zipPath);
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Unable to open archive.');
            }
            $zip->extractTo($tempDir);
            $zip->close();
            $entries = array_values(array_filter(scandir($tempDir) ?: [], fn($e) => $e !== '.' && $e !== '..' && is_dir($tempDir . DIRECTORY_SEPARATOR . $e)));
            if (empty($entries)) {
                throw new RuntimeException('Archive did not contain files.');
            }
            $extractedRoot = $tempDir . DIRECTORY_SEPARATOR . $entries[0];
            $exclude = ['data', '.env', '.git', 'installer'];
            recursive_copy($extractedRoot, $installPath, $exclude);
            $dataDir = $installPath . DIRECTORY_SEPARATOR . 'data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0700, true);
            }
            $indexRedirect = $installPath . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($indexRedirect)) {
                file_put_contents($indexRedirect, "<?php\nheader('Location: public/index.php');\nexit;\n");
            }
            apply_asset_path_fix($installPath);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $f) {
                if ($f->isDir()) {
                    rmdir($f->getPathname());
                } else {
                    unlink($f->getPathname());
                }
            }
            rmdir($tempDir);
            $messages[] = 'Download and extraction complete.';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            if (isset($tempDir) && is_dir($tempDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $f) {
                    if ($f->isDir()) {
                        @rmdir($f->getPathname());
                    } else {
                        @unlink($f->getPathname());
                    }
                }
                @rmdir($tempDir);
            }
        }
    }

    if ($action === 'configure') {
        $username = trim((string) ($_POST['admin_username'] ?? 'admin'));
        $password = (string) ($_POST['admin_password'] ?? '');
        $appKey = trim((string) ($_POST['app_key'] ?? ''));
        $demoMode = isset($_POST['demo_mode']);

        if ($username === '') {
            $errors[] = 'Admin username is required.';
        }
        if (!$demoMode && $password === '') {
            $errors[] = 'Admin password is required.';
        }
        if (!$demoMode && $appKey !== '' && strlen($appKey) < 16) {
            $errors[] = 'APP_KEY must be at least 16 characters (or leave empty to auto-generate).';
        }

        if (empty($errors)) {
            if ($appKey === '' && !$demoMode) {
                $appKey = bin2hex(random_bytes(32));
            }
            $hash = $demoMode ? '' : password_hash($password, PASSWORD_DEFAULT);
            $env = "APP_KEY=" . ($demoMode ? 'demo_key_32_chars_minimum_required' : $appKey) . "\n";
            $env .= "ADMIN_USERNAME=" . ($demoMode ? 'demo' : $username) . "\n";
            $env .= "ADMIN_PASSWORD_HASH=" . $hash . "\n";
            $env .= "DEMO_MODE=" . ($demoMode ? 'true' : 'false') . "\n";
            $envPath = $installPath . DIRECTORY_SEPARATOR . '.env';
            if (file_put_contents($envPath, $env) === false) {
                $errors[] = 'Unable to write .env file.';
            } else {
                $dataDir = $installPath . DIRECTORY_SEPARATOR . 'data';
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0700, true);
                }
                putenv('APP_KEY=' . ($demoMode ? 'demo_key_32_chars_minimum_required' : $appKey));
                putenv('ADMIN_USERNAME=' . ($demoMode ? 'demo' : $username));
                putenv('ADMIN_PASSWORD_HASH=' . $hash);
                putenv('DEMO_MODE=' . ($demoMode ? 'true' : 'false'));
                $_ENV['APP_KEY'] = $demoMode ? 'demo_key_32_chars_minimum_required' : $appKey;
                $_ENV['ADMIN_USERNAME'] = $demoMode ? 'demo' : $username;
                $_ENV['ADMIN_PASSWORD_HASH'] = $hash;
                $_ENV['DEMO_MODE'] = $demoMode ? 'true' : 'false';
                $dbPath = $installPath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db';
                $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON');
                $schemaPath = $installPath . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001_init.sql';
                require_once $installPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'repo.php';
                ensure_schema($pdo, $schemaPath);
                file_put_contents($lockFile, json_encode(['installed_at' => date('c')], JSON_PRETTY_PRINT));
                $installComplete = true;
            }
        }
    }
}

$req = check_requirements();
$allErrors = array_merge($req['errors'], $errors);
$baseUrl = dirname($_SERVER['SCRIPT_NAME']);
if ($baseUrl === '/' || $baseUrl === '\\') {
    $baseUrl = '';
}
$publicUrl = $baseUrl . '/public';
$docRootPath = realpath($installPath . DIRECTORY_SEPARATOR . 'public') ?: $installPath . DIRECTORY_SEPARATOR . 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Domain Tracker Installer</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:24px;background:#f8fafc;color:#1e293b;line-height:1.5}
        .card{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:24px;max-width:560px;margin:0 auto 24px}
        h1{margin:0 0 8px;font-size:1.5rem}
        .muted{color:#64748b;font-size:0.9rem;margin:0 0 20px}
        .alert{padding:12px 16px;border-radius:6px;margin-bottom:16px}
        .alert-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
        .alert-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        .alert-success{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
        .alert-info{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd}
        label{display:block;margin-bottom:4px;font-weight:500;font-size:0.9rem}
        input[type=text],input[type=password]{width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;margin-bottom:16px}
        input[type=checkbox]{margin-right:8px}
        .form-group{margin-bottom:16px}
        .button{display:inline-block;padding:8px 16px;border-radius:6px;font-size:14px;font-weight:500;cursor:pointer;border:1px solid #d0d5dd;background:#fff;color:#374151;text-decoration:none}
        .button:hover{background:#f8fafc}
        .button.primary{background:#2563eb;color:#fff;border-color:transparent}
        .button.primary:hover{background:#1d4ed8}
        .button:disabled{opacity:0.6;cursor:not-allowed}
        .mono{font-family:ui-monospace,monospace;font-size:0.85rem;background:#f1f5f9;padding:8px 12px;border-radius:4px;word-break:break-all;margin:12px 0}
        ul{margin:0;padding-left:20px}
        li{margin:4px 0}
    </style>
</head>
<body>
    <div class="card">
        <h1>Domain Tracker Installer</h1>
        <p class="muted">Set up Domain Tracker on your server in a few steps.</p>

        <?php if (file_exists($lockFile)): ?>
            <div class="alert alert-success">
                <strong>Already installed.</strong> Domain Tracker is set up.
            </div>
            <p><a href="<?php echo e($publicUrl); ?>/login.php" class="button primary">Open Domain Tracker</a></p>
            <p class="muted" style="margin-top:16px">To reinstall, delete <code>install.lock</code> from this directory.</p>
            <?php exit; endif; ?>

        <?php foreach ($allErrors as $err): ?>
            <div class="alert alert-error"><?php echo e($err); ?></div>
        <?php endforeach; ?>
        <?php foreach ($req['warnings'] as $w): ?>
            <div class="alert alert-warn"><?php echo e($w); ?></div>
        <?php endforeach; ?>
        <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><?php echo e($m); ?></div>
        <?php endforeach; ?>

        <?php if ($step === 1): ?>
            <h2 style="font-size:1.1rem;margin:0 0 12px">Step 1: Requirements</h2>
            <?php if (!empty($req['errors'])): ?>
                <p>Fix the issues above before continuing.</p>
            <?php else: ?>
                <p>All requirements met. Click below to download and extract Domain Tracker.</p>
                <form method="post" style="margin-top:16px">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="download">
                    <button type="submit" class="button primary">Download and extract</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($step === 2 && empty($allErrors) && !$installComplete): ?>
            <h2 style="font-size:1.1rem;margin:0 0 12px">Step 2: Configure</h2>
            <p class="muted">Set your admin credentials and encryption key.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="configure">
                <div class="form-group">
                    <label>Admin username</label>
                    <input type="text" name="admin_username" value="admin" required>
                </div>
                <div class="form-group">
                    <label>Admin password</label>
                    <input type="password" name="admin_password" id="admin_password" required>
                </div>
                <div class="form-group">
                    <label>APP_KEY (32+ chars for encryption)</label>
                    <input type="text" name="app_key" placeholder="Leave empty to auto-generate">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="demo_mode" value="1" id="demo_mode"> Demo mode (demo/demo login, no encryption)</label>
                </div>
                <script>
                document.getElementById('demo_mode').addEventListener('change', function() {
                    document.getElementById('admin_password').required = !this.checked;
                });
                </script>
                <button type="submit" class="button primary">Complete installation</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($step === 2 && !empty($allErrors)): ?>
    <div class="card">
        <p><a href="?step=1" class="button">Back to requirements</a></p>
    </div>
    <?php endif; ?>

    <?php if ($installComplete): ?>
    <div class="card">
        <div class="alert alert-success">
            <strong>Installation complete.</strong>
        </div>
        <p><strong>Next steps:</strong></p>
        <ul>
            <li>Point your domain's document root to: <code class="mono"><?php echo e($docRootPath); ?></code></li>
            <li>Or keep the current setup — an <code>index.php</code> redirect was created so your site works at the current URL.</li>
        </ul>
        <p><a href="<?php echo e($publicUrl); ?>/login.php" class="button primary">Open Domain Tracker</a></p>
    </div>
    <?php endif; ?>
</body>
</html>
