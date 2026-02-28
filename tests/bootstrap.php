<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
// load full application bootstrap so helper functions (config, base_path,
// csrf, etc.) are available in tests. bootstrap.php also includes repo and
// crypto, so we no longer need to require them directly.
require_once __DIR__ . '/../src/bootstrap.php';
// extra helpers not loaded by default
require_once __DIR__ . '/../src/totp.php';
require_once __DIR__ . '/../src/update_check.php';
require_once __DIR__ . '/../src/expiry_check.php';