<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$currentKey = (string) config('app_key');
if ($currentKey === '') {
    fwrite(STDERR, "APP_KEY is not configured. Set it in your .env file.\n");
    exit(1);
}

$newKey = $argv[1] ?? getenv('APP_KEY_NEW') ?: '';
if ($newKey === '') {
    if (stream_isatty(STDIN)) {
        echo "Enter new encryption key (32+ chars recommended): ";
        $newKey = trim((string) fgets(STDIN));
    }
    if ($newKey === '') {
        fwrite(STDERR, "Provide the new key via: APP_KEY_NEW=your_new_key php scripts/rotate_key.php\n");
        fwrite(STDERR, "Or pass as argument: php scripts/rotate_key.php your_new_key\n");
        exit(1);
    }
}

$newKey = trim($newKey);
if (strlen($newKey) < 16) {
    fwrite(STDERR, "New key should be at least 16 characters.\n");
    exit(1);
}

$pdo = db();

$rotated = 0;
$errors = [];

foreach (['domains', 'subdomains'] as $table) {
    $stmt = $pdo->query("SELECT id, db_password_enc FROM {$table} WHERE db_password_enc IS NOT NULL AND db_password_enc != ''");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $enc = (string) $row['db_password_enc'];
        if ($enc === '') {
            continue;
        }

        try {
            $plain = decrypt_secret_with_key($enc, $currentKey);
            $reenc = encrypt_secret_with_key($plain, $newKey);
        } catch (Throwable $e) {
            $errors[] = "{$table} id={$row['id']}: " . $e->getMessage();
            continue;
        }

        $update = $pdo->prepare("UPDATE {$table} SET db_password_enc = :enc WHERE id = :id");
        $update->execute([':enc' => $reenc, ':id' => $row['id']]);
        $rotated++;
    }
}

if (!empty($errors)) {
    foreach ($errors as $err) {
        fwrite(STDERR, $err . "\n");
    }
    exit(1);
}

echo "Rotated {$rotated} encrypted value(s).\n";
echo "\n";
echo "Next step: Update your .env file. Replace APP_KEY with the new key you provided.\n";
echo "Remove any temporary APP_KEY_NEW from your environment.\n";
