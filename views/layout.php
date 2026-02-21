<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle . ' - ' . (string) config('app_name')); ?></title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand"><?php echo e((string) config('app_name')); ?></div>
            <nav class="topbar-actions">
                <a href="/index.php" class="link">Dashboard</a>
                <a href="/security.php" class="link">Security</a>
                <a href="/updater.php" class="link">Update</a>
                <a href="/logout.php" class="link">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (config('app_key') === ''): ?>
            <div class="alert alert-warn">
                APP_KEY is missing. Set it in your server environment to enable encryption.
            </div>
        <?php endif; ?>

        <?php require base_path('views/' . $view . '.php'); ?>
    </main>

    <script>
    (function () {
        var forms = document.querySelectorAll('.data-form');
        if (!forms.length) {
            return;
        }

        var isDirty = false;
        var ignoreUnload = false;

        function markDirty() {
            isDirty = true;
        }

        forms.forEach(function (form) {
            form.addEventListener('change', markDirty);
            form.addEventListener('input', markDirty);
            form.addEventListener('submit', function () {
                ignoreUnload = true;
            });
        });

        window.addEventListener('beforeunload', function (event) {
            if (!isDirty || ignoreUnload) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });

        document.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (!link || link.hasAttribute('data-skip-confirm')) {
                return;
            }
            var href = link.getAttribute('href');
            if (!href || href.startsWith('#')) {
                return;
            }
            if (isDirty && !confirm('You have unsaved changes. Leave this page?')) {
                event.preventDefault();
            }
        });
    })();
    </script>
</body>
</html>
