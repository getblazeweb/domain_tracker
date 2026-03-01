<?php
declare(strict_types=1);

/**
 * Patch app files so assets and links work when doc root is the install directory (parent of public/).
 * Used by the installer and update check to ensure consistent file content for comparison.
 */
function apply_asset_path_fix(string $installPath): void
{
    $installPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $installPath), DIRECTORY_SEPARATOR);

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

    $patchBootstrap = function (string $path) use ($installPath, $urlFns): void {
        $fullPath = $installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($fullPath)) {
            return;
        }
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return;
        }
        if (!str_contains($content, 'function asset_url(')) {
            $needles = ["}\n\nfunction config(", "}\nfunction config(", "}\r\n\r\nfunction config(", "}\r\nfunction config("];
            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    $content = str_replace($needle, "}\n" . $urlFns . "function config(", $content);
                    file_put_contents($fullPath, $content);
                    return;
                }
            }
        } elseif (!str_contains($content, 'function app_url(')) {
            $appUrlFn = "\n/** URL path for app pages (index, updater, login, etc). Handles paths with query strings. */\n"
                . "function app_url(string \$path): string\n{\n"
                . "    \$path = ltrim(\$path, '/');\n"
                . "    \$query = '';\n"
                . "    if (str_contains(\$path, '?')) {\n"
                . "        [\$path, \$q] = explode('?', \$path, 2);\n"
                . "        \$query = '?' . \$q;\n"
                . "    }\n"
                . "    return asset_url(\$path) . \$query;\n}\n\n";
            $needles = ["}\n\nfunction config(", "}\nfunction config(", "}\r\n\r\nfunction config(", "}\r\nfunction config("];
            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    $content = str_replace($needle, "}\n" . $appUrlFn . "function config(", $content);
                    file_put_contents($fullPath, $content);
                    return;
                }
            }
        }
    };

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

    $patchBootstrap('src/bootstrap.php');

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
            $updaterUrlFn = "\n/** URL path for assets and app pages when doc root may be parent of public/. */\n"
                . "function updater_url(string \$path): string\n{\n"
                . "    \$scriptDir = dirname(\$_SERVER['SCRIPT_NAME'] ?? '/updater.php');\n"
                . "    \$base = (\$scriptDir === '/' || \$scriptDir === '\\\\') ? '' : rtrim(\$scriptDir, '/');\n"
                . "    \$path = ltrim(\$path, '/');\n"
                . "    \$query = '';\n"
                . "    if (str_contains(\$path, '?')) {\n"
                . "        [\$path, \$q] = explode('?', \$path, 2);\n"
                . "        \$query = '?' . \$q;\n"
                . "    }\n"
                . "    return \$base . '/' . \$path . \$query;\n}\n\n";
            $needles = [
                "\$config = require \$basePath . '/config/app.php';\n\nfunction config_value(",
                "\$config = require \$basePath . \"/config/app.php\";\n\nfunction config_value(",
            ];
            $replacement = "\$config = require \$basePath . '/config/app.php';\n" . $updaterUrlFn . "function config_value(";
            $newContent = $content;
            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    $newContent = str_replace($needle, $replacement, $content);
                    break;
                }
            }
            if ($newContent !== $content) {
                $newContent = preg_replace('#href="/assets/([^"]+)"#', 'href="<?php echo htmlspecialchars(updater_url(\'assets/$1\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = preg_replace('#href=\\\"/assets/([^\\"]+)\\\"#', 'href=\\" . htmlspecialchars(updater_url(\'assets/$1\'), ENT_QUOTES, \'UTF-8\') . "\\"', $newContent);
                $newContent = preg_replace('#src="/assets/([^"]+)"#', 'src="<?php echo htmlspecialchars(updater_url(\'assets/$1\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = str_replace('href="/updater.php"', 'href="<?php echo htmlspecialchars(updater_url(\'updater.php\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = str_replace('href="/index.php"', 'href="<?php echo htmlspecialchars(updater_url(\'index.php\'), ENT_QUOTES, \'UTF-8\'); ?>"', $newContent);
                $newContent = preg_replace("#header\\('Location: /([^']+)'\\)#", "header('Location: ' . updater_url('$1'))", $newContent);
                $newContent = preg_replace('#header\("Location: /([^"]+)"\)#', 'header("Location: " . updater_url(\'$1\'))', $newContent);
                file_put_contents($updaterPath, $newContent);
            }
        }
    }
}
