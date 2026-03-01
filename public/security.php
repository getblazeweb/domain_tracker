<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/totp.php';

require_login();

$pdo = db();
$demoMode = config('demo_mode');
$error = '';
$message = '';
$twoFactorEnabled = !$demoMode && get_setting($pdo, 'two_factor_enabled') === '1';
$twoFactorSecret = $twoFactorEnabled ? (get_setting($pdo, 'two_factor_secret') ?? '') : '';
$username = (string) config('admin_username');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
$loginWindowMinutes = (int) (get_login_setting($pdo, 'login_window_minutes', '15') ?? 15);
$loginMaxAttempts = (int) (get_login_setting($pdo, 'login_max_attempts', '5') ?? 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$demoMode) {
    verify_csrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'enable') {
        $pendingSecret = (string) ($_SESSION['pending_2fa_secret'] ?? '');
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        if ($pendingSecret === '') {
            $error = 'No pending secret found. Refresh and try again.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 0,
                'reason' => '2fa_enable_no_secret',
                'created_at' => date('c'),
            ]);
        } elseif (!totp_verify($pendingSecret, $code)) {
            $error = 'Invalid verification code.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 0,
                'reason' => '2fa_enable_invalid_code',
                'created_at' => date('c'),
            ]);
        } else {
            set_setting($pdo, 'two_factor_secret', $pendingSecret);
            set_setting($pdo, 'two_factor_enabled', '1');
            unset($_SESSION['pending_2fa_secret']);
            $twoFactorEnabled = true;
            $twoFactorSecret = $pendingSecret;
            $message = 'Two-factor authentication enabled.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 1,
                'reason' => '2fa_enabled',
                'created_at' => date('c'),
            ]);
        }
    }

    if ($action === 'disable') {
        $password = (string) ($_POST['password'] ?? '');
        $code = trim((string) ($_POST['totp_code'] ?? ''));

        if (!verify_credentials($username, $password)) {
            $error = 'Password is incorrect.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 0,
                'reason' => '2fa_disable_bad_password',
                'created_at' => date('c'),
            ]);
        } elseif ($twoFactorSecret === '' || !totp_verify($twoFactorSecret, $code)) {
            $error = 'Invalid verification code.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 0,
                'reason' => '2fa_disable_invalid_code',
                'created_at' => date('c'),
            ]);
        } else {
            set_setting($pdo, 'two_factor_enabled', '0');
            set_setting($pdo, 'two_factor_secret', '');
            $twoFactorEnabled = false;
            $twoFactorSecret = '';
            $message = 'Two-factor authentication disabled.';
            log_login_attempt($pdo, [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'success' => 1,
                'reason' => '2fa_disabled',
                'created_at' => date('c'),
            ]);
        }
    }

    if ($action === 'login_limits') {
        $loginWindowMinutes = max(1, (int) ($_POST['login_window_minutes'] ?? 15));
        $loginMaxAttempts = max(1, (int) ($_POST['login_max_attempts'] ?? 5));
        set_login_setting($pdo, 'login_window_minutes', (string) $loginWindowMinutes);
        set_login_setting($pdo, 'login_max_attempts', (string) $loginMaxAttempts);
        $message = 'Login limits updated.';
    }
}

if (!$twoFactorEnabled && empty($_SESSION['pending_2fa_secret'])) {
    $_SESSION['pending_2fa_secret'] = totp_generate_secret();
}
$pendingSecret = (string) ($_SESSION['pending_2fa_secret'] ?? '');

$issuer = rawurlencode((string) config('app_name'));
$account = rawurlencode((string) config('admin_username'));
$otpauth = $pendingSecret !== ''
    ? "otpauth://totp/{$issuer}:{$account}?secret={$pendingSecret}&issuer={$issuer}"
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security - <?php echo htmlspecialchars((string) config('app_name'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(asset_url('assets/favicon.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand"><?php echo htmlspecialchars((string) config('app_name'), ENT_QUOTES, 'UTF-8'); ?></div>
            <nav class="topbar-actions">
                <a href="<?php echo e(app_url('index.php')); ?>" class="link">Dashboard</a>
                <a href="<?php echo e(app_url('index.php?action=expiry')); ?>" class="link">Expiry</a>
                <a href="<?php echo e(app_url('index.php?action=domain_import')); ?>" class="link">Import</a>
                <a href="<?php echo e(app_url('security.php')); ?>" class="link">Security</a>
                <a href="<?php echo e(app_url('updater.php')); ?>" class="link<?php echo $demoMode ? ' is-disabled' : ''; ?>">Update</a>
                <a href="<?php echo e(app_url('index.php')); ?>" class="link">Help</a>
                <a href="<?php echo e(app_url('logout.php')); ?>" class="link">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($demoMode): ?>
            <div class="alert alert-info">
                Demo mode: Security features (2FA, login limits, key rotation, password reset) are disabled.
            </div>
        <?php endif; ?>

        <div class="card<?php echo $demoMode ? ' is-disabled' : ''; ?>">
            <h1>Security</h1>
            <p class="muted">Manage two-factor authentication for your admin account.</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="card-section">
                <h2>Password Reset</h2>
                <p class="muted">Request a password reset link via email.</p>
                <button type="button" class="button" disabled>Request Password Reset</button>
            </div>

            <div class="card-section">
                <h2>Login Limits</h2>
                <form method="post" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="login_limits">
                    <label>
                        Attempt window (minutes)
                        <input type="number" name="login_window_minutes" min="1" value="<?php echo (int) $loginWindowMinutes; ?>">
                    </label>
                    <label>
                        Max failed attempts
                        <input type="number" name="login_max_attempts" min="1" value="<?php echo (int) $loginMaxAttempts; ?>">
                    </label>
                    <button type="submit" class="button">Save Limits</button>
                </form>
            </div>

            <?php if (!$twoFactorEnabled): ?>
                <div class="alert alert-warn">
                    Two-factor authentication is currently disabled.
                </div>
                <div class="card-section">
                <div class="form">
                    <label>
                        Secret
                        <input type="text" value="<?php echo htmlspecialchars($pendingSecret, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </label>
                    <?php if ($otpauth !== ''): ?>
                    <label>
                        OTP URI
                        <div class="qr-wrap">
                            <div class="qr-box">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?php echo urlencode($otpauth); ?>" alt="2FA QR Code">
                            </div>
                            <div class="qr-meta">
                                <input type="text" value="<?php echo htmlspecialchars($otpauth, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                <span class="helper-text">Scan the QR code or copy the OTP URI into your authenticator app.</span>
                            </div>
                        </div>
                    </label>
                        <span class="helper-text">Scan the QR code with Google Authenticator, Authy, 1Password, or similar.</span>
                    <?php endif; ?>
                </div>
                <form method="post" class="form" style="margin-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="enable">
                    <label>
                        Verification Code
                        <input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                    </label>
                    <button type="submit" class="button primary">Enable 2FA</button>
                </form>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    Two-factor authentication is enabled.
                </div>
                <div class="card-section">
                <form method="post" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="disable">
                    <label>
                        Password
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <label>
                        Verification Code
                        <input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                    </label>
                    <button type="submit" class="button">Disable 2FA</button>
                </form>
                </div>
            <?php endif; ?>

            <div class="card-section">
                <h2>Encryption Key Rotation</h2>
                <p class="muted">Rotate your APP_KEY to re-encrypt stored database passwords. Run the CLI script from your server.</p>
                <button type="button" class="button" onclick="document.getElementById('rotate-key-modal').classList.add('is-open')">Rotate Encryption Keys</button>
            </div>
        </div>

        <div id="rotate-key-modal" class="modal" role="dialog" aria-labelledby="rotate-key-modal-title" aria-modal="true">
            <div class="modal-backdrop" onclick="document.getElementById('rotate-key-modal').classList.remove('is-open')"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="rotate-key-modal-title">Rotate Encryption Keys</h2>
                    <button type="button" class="modal-close" onclick="document.getElementById('rotate-key-modal').classList.remove('is-open')" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Key rotation re-encrypts all stored database passwords with a new key. Run the script from your project root via SSH or your hosting control panel.</p>
                    <ol class="modal-steps">
                        <li><strong>Back up</strong> your database (<code>data/app.db</code>) and <code>.env</code> file.</li>
                        <li><strong>Generate</strong> a new key:
                            <pre><code>php -r "echo bin2hex(random_bytes(32));"</code></pre>
                        </li>
                        <li><strong>Run</strong> the rotation script from the project root (the directory containing <code>scripts/</code>):
                            <pre><code>php scripts/rotate_key.php</code></pre>
                            When prompted, paste the new key. Or use an environment variable:
                            <pre><code>APP_KEY_NEW=your_new_key php scripts/rotate_key.php</code></pre>
                        </li>
                        <li><strong>Update</strong> your <code>.env</code> file: replace <code>APP_KEY</code> with the new key.</li>
                        <li><strong>Remove</strong> any temporary <code>APP_KEY_NEW</code> from your environment.</li>
                    </ol>
                    <p class="muted">The app will use the new key on the next request. No restart is required.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Recent Login Attempts</h2>
            <div class="table">
                <div class="table-row table-head">
                    <div>Time</div>
                    <div>Username</div>
                    <div>IP</div>
                    <div>User Agent</div>
                    <div>Result</div>
                    <div>Reason</div>
                </div>
                <?php foreach (list_login_attempts($pdo, 50) as $attempt): ?>
                    <div class="table-row">
                        <div><?php echo htmlspecialchars((string) $attempt['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars((string) $attempt['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(preg_replace('/[0-9a-fA-F]/', 'x', (string) $attempt['ip_address']), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="truncate" title="<?php echo htmlspecialchars((string) $attempt['user_agent'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $attempt['user_agent'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div><?php echo (int) $attempt['success'] === 1 ? 'Success' : 'Fail'; ?></div>
                        <div><?php echo htmlspecialchars((string) $attempt['reason'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
