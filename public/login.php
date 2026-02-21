<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf((string) ($_POST['csrf_token'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($username, $password)) {
        header('Location: /index.php');
        exit;
    }

    $error = 'Invalid username or password.';
}

$configReady = config('admin_password_hash') !== '' && config('app_key') !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?php echo htmlspecialchars((string) config('app_name'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>Domain Tracker</h1>
        <p class="muted">Sign in to continue</p>

        <?php if (!$configReady): ?>
            <div class="alert alert-warn">
                Admin password hash or APP_KEY is missing. Set `ADMIN_PASSWORD_HASH` and `APP_KEY` in your server environment.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
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
