<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle . ' - ' . (string) config('app_name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset_url('assets/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('assets/style.css')); ?>">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand"><?php echo e((string) config('app_name')); ?></div>
            <nav class="topbar-actions">
                <a href="/index.php" class="link">Dashboard</a>
                <a href="/index.php?action=expiry" class="link" data-tour="nav-expiry">Expiry</a>
                <a href="/index.php?action=domain_import" class="link">Import</a>
                <a href="/security.php" class="link" data-tour="nav-security">Security</a>
                <a href="/updater.php" class="link<?php echo config('demo_mode') ? ' is-disabled' : ''; ?>">Update</a>
                <button type="button" class="link tour-help-btn" id="tour-help-btn">Help</button>
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

    <form id="tour-dismiss-form" method="post" action="/index.php?action=tour_dismiss" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    </form>

    <?php if (!empty($showDemoModal)): ?>
    <?php $demoDismissUrl = '/index.php?action=demo_modal_dismiss&return=' . rawurlencode($demoModalReturn ?? '/index.php'); ?>
    <div id="demo-modal" class="modal is-open" role="dialog" aria-labelledby="demo-modal-title" aria-modal="true">
        <div class="modal-backdrop" onclick="window.location='<?php echo e($demoDismissUrl); ?>'"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="demo-modal-title">Demo Instance</h2>
                <button type="button" class="modal-close" onclick="window.location='<?php echo e($demoDismissUrl); ?>'" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p>This is a <strong>demo</strong> of Domain Tracker—a lightweight web app for tracking domains, subdomains, file locations, and database credentials in a single dashboard.</p>
                <p><strong>Important:</strong> This demo is public-facing. Do not enter real-world domain credentials, database passwords, or other sensitive information. Data entered here may be visible to others.</p>
                <p class="muted">Domain Tracker helps you centralize infrastructure details with encrypted storage, search, CSV import, expiry tracking, and optional 2FA. Deploy your own instance for production use.</p>
                <a href="<?php echo e($demoDismissUrl); ?>" class="button primary">Got it</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    window.TOUR_AUTO_SHOW = <?php echo !empty($tourAutoShow) ? 'true' : 'false'; ?>;
    </script>
    <script src="<?php echo e(asset_url('assets/tour.js')); ?>"></script>

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
