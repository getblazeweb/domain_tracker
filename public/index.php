<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/update_check.php';
require_once __DIR__ . '/../src/expiry_check.php';

require_login();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method not allowed.';
        exit;
    }
}

$pdo = db();
$action = (string) ($_GET['action'] ?? 'dashboard');
$flash = get_flash();
$errors = [];

switch ($action) {
    case 'domain_create':
        $pageTitle = 'Add Domain';
        $domain = [
            'id' => null,
            'name' => '',
            'url' => '',
            'description' => '',
            'registrar' => '',
            'expires_at' => '',
            'renewal_price' => '',
            'auto_renew' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password' => '',
        ];
        $view = 'domain_form';
        $mode = 'create';
        break;

    case 'domain_store':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));

        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $registrar = trim((string) ($_POST['registrar'] ?? ''));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $renewalPrice = trim((string) ($_POST['renewal_price'] ?? ''));
        $autoRenew = trim((string) ($_POST['auto_renew'] ?? ''));
        $dbHost = trim((string) ($_POST['db_host'] ?? ''));
        $dbPort = trim((string) ($_POST['db_port'] ?? ''));
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPassword = (string) ($_POST['db_password'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Domain name is required.';
        }
        if ($url === '') {
            $errors['url'] = 'Domain URL is required.';
        }

        if ($dbPassword !== '' && config('app_key') === '') {
            $errors['db_password'] = 'APP_KEY is required to encrypt database passwords.';
        }

        if ($errors) {
            $pageTitle = 'Add Domain';
            $domain = [
                'id' => null,
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'registrar' => $registrar,
                'expires_at' => $expiresAt,
                'renewal_price' => $renewalPrice,
                'auto_renew' => $autoRenew,
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_password' => $dbPassword,
            ];
            $view = 'domain_form';
            $mode = 'create';
            break;
        }

        $encrypted = $dbPassword === '' ? '' : encrypt_secret($dbPassword);
        create_domain($pdo, [
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'registrar' => $registrar,
            'expires_at' => $expiresAt,
            'renewal_price' => $renewalPrice,
            'auto_renew' => $autoRenew,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password_enc' => $encrypted,
        ]);
        set_flash('success', 'Domain added.');
        redirect_to('/index.php');
        break;

    case 'domain_edit':
        $id = (int) ($_GET['id'] ?? 0);
        $domain = get_domain($pdo, $id);
        if (!$domain) {
            set_flash('error', 'Domain not found.');
            redirect_to('/index.php');
        }
        $pageTitle = 'Edit Domain';
        $domain['description'] = (string) ($domain['description'] ?? '');
        $domain['db_password'] = config('app_key') === '' ? '' : decrypt_secret((string) $domain['db_password_enc']);
        $view = 'domain_form';
        $mode = 'edit';
        break;

    case 'domain_update':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        $domain = get_domain($pdo, $id);
        if (!$domain) {
            set_flash('error', 'Domain not found.');
            redirect_to('/index.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $registrar = trim((string) ($_POST['registrar'] ?? ''));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $renewalPrice = trim((string) ($_POST['renewal_price'] ?? ''));
        $autoRenew = trim((string) ($_POST['auto_renew'] ?? ''));
        $dbHost = trim((string) ($_POST['db_host'] ?? ''));
        $dbPort = trim((string) ($_POST['db_port'] ?? ''));
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPassword = (string) ($_POST['db_password'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Domain name is required.';
        }
        if ($url === '') {
            $errors['url'] = 'Domain URL is required.';
        }
        if ($dbPassword !== '' && config('app_key') === '') {
            $errors['db_password'] = 'APP_KEY is required to encrypt database passwords.';
        }

        if ($errors) {
            $pageTitle = 'Edit Domain';
            $domain = [
                'id' => $id,
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'registrar' => $registrar,
                'expires_at' => $expiresAt,
                'renewal_price' => $renewalPrice,
                'auto_renew' => $autoRenew,
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_password' => $dbPassword,
            ];
            $view = 'domain_form';
            $mode = 'edit';
            break;
        }
        $encrypted = (string) $domain['db_password_enc'];
        if ($dbPassword !== '') {
            $encrypted = encrypt_secret($dbPassword);
        }

        update_domain($pdo, $id, [
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'registrar' => $registrar,
            'expires_at' => $expiresAt,
            'renewal_price' => $renewalPrice,
            'auto_renew' => $autoRenew,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password_enc' => $encrypted,
        ]);
        set_flash('success', 'Domain updated.');
        redirect_to('/index.php');
        break;

    case 'domain_delete':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        if (count_subdomains_for_domain($pdo, $id) > 0) {
            set_flash('error', 'Delete all subdomains before deleting this domain.');
            redirect_to('/index.php');
        }
        delete_domain($pdo, $id);
        set_flash('success', 'Domain deleted.');
        redirect_to('/index.php');
        break;

    case 'subdomain_create':
        $domainId = (int) ($_GET['domain_id'] ?? 0);
        $domain = get_domain($pdo, $domainId);
        if (!$domain) {
            set_flash('error', 'Domain not found.');
            redirect_to('/index.php');
        }
        $pageTitle = 'Add Subdomain';
        $subdomain = [
            'id' => null,
            'domain_id' => $domainId,
            'name' => '',
            'url' => '',
            'file_location' => '',
            'description' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password' => '',
        ];
        $view = 'subdomain_form';
        $mode = 'create';
        break;

    case 'subdomain_store':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $domain = get_domain($pdo, $domainId);
        if (!$domain) {
            set_flash('error', 'Domain not found.');
            redirect_to('/index.php');
        }
        $subdomain = [
            'id' => null,
            'domain_id' => $domainId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'url' => trim((string) ($_POST['url'] ?? '')),
            'file_location' => trim((string) ($_POST['file_location'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'db_host' => trim((string) ($_POST['db_host'] ?? '')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '')),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
        ];

        foreach (['name', 'url', 'file_location'] as $field) {
            if (trim((string) $subdomain[$field]) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($subdomain['db_password'] !== '' && config('app_key') === '') {
            $errors['db_password'] = 'APP_KEY is required to encrypt database passwords.';
        }

        if ($errors) {
            $pageTitle = 'Add Subdomain';
            $view = 'subdomain_form';
            $mode = 'create';
            break;
        }

        $encrypted = $subdomain['db_password'] === '' ? '' : encrypt_secret($subdomain['db_password']);
        create_subdomain($pdo, [
            'domain_id' => $domainId,
            'name' => $subdomain['name'],
            'url' => $subdomain['url'],
            'file_location' => $subdomain['file_location'],
            'description' => $subdomain['description'],
            'db_host' => $subdomain['db_host'],
            'db_port' => $subdomain['db_port'],
            'db_name' => $subdomain['db_name'],
            'db_user' => $subdomain['db_user'],
            'db_password_enc' => $encrypted,
        ]);

        set_flash('success', 'Subdomain added.');
        redirect_to('/index.php');
        break;

    case 'subdomain_edit':
        $id = (int) ($_GET['id'] ?? 0);
        $subdomain = get_subdomain($pdo, $id);
        if (!$subdomain) {
            set_flash('error', 'Subdomain not found.');
            redirect_to('/index.php');
        }
        $domain = get_domain($pdo, (int) $subdomain['domain_id']);
        $pageTitle = 'Edit Subdomain';
        $subdomain['description'] = (string) ($subdomain['description'] ?? '');
        $subdomain['db_password'] = config('app_key') === '' ? '' : decrypt_secret((string) $subdomain['db_password_enc']);
        $view = 'subdomain_form';
        $mode = 'edit';
        break;

    case 'subdomain_update':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        $existing = get_subdomain($pdo, $id);
        if (!$existing) {
            set_flash('error', 'Subdomain not found.');
            redirect_to('/index.php');
        }
        $domain = get_domain($pdo, (int) $existing['domain_id']);
        $subdomain = [
            'id' => $id,
            'domain_id' => (int) $existing['domain_id'],
            'name' => trim((string) ($_POST['name'] ?? '')),
            'url' => trim((string) ($_POST['url'] ?? '')),
            'file_location' => trim((string) ($_POST['file_location'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'db_host' => trim((string) ($_POST['db_host'] ?? '')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '')),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
        ];

        foreach (['name', 'url', 'file_location'] as $field) {
            if (trim((string) $subdomain[$field]) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($subdomain['db_password'] !== '' && config('app_key') === '') {
            $errors['db_password'] = 'APP_KEY is required to encrypt database passwords.';
        }

        if ($errors) {
            $pageTitle = 'Edit Subdomain';
            $view = 'subdomain_form';
            $mode = 'edit';
            break;
        }

        $encrypted = $existing['db_password_enc'];
        if ($subdomain['db_password'] !== '') {
            $encrypted = encrypt_secret($subdomain['db_password']);
        }

        update_subdomain($pdo, $id, [
            'name' => $subdomain['name'],
            'url' => $subdomain['url'],
            'file_location' => $subdomain['file_location'],
            'description' => $subdomain['description'],
            'db_host' => $subdomain['db_host'],
            'db_port' => $subdomain['db_port'],
            'db_name' => $subdomain['db_name'],
            'db_user' => $subdomain['db_user'],
            'db_password_enc' => $encrypted,
        ]);

        set_flash('success', 'Subdomain updated.');
        redirect_to('/index.php');
        break;

    case 'subdomain_delete':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        delete_subdomain($pdo, $id);
        set_flash('success', 'Subdomain deleted.');
        redirect_to('/index.php');
        break;

    case 'check_expiry':
        $expiryData = expiry_check_run($pdo, true);
        set_flash('success', 'Expiry data refreshed.');
        redirect_to('/index.php?action=expiry');
        break;

    case 'expiry':
        $pageTitle = 'Expiry Dashboard';
        $expiryData = expiry_check_run($pdo, false);
        $view = 'expiry';
        break;

    case 'domain_import':
        $pageTitle = 'Import Domains';
        $view = 'domain_import';
        break;

    case 'domain_import_process':
        require_post();
        verify_csrf((string) ($_POST['csrf_token'] ?? ''));
        $csv = (string) ($_POST['csv_data'] ?? '');
        $importErrors = [];
        $imported = 0;
        if ($csv !== '') {
            $lines = array_map('trim', explode("\n", $csv));
            $header = array_map('trim', str_getcsv(array_shift($lines) ?? ''));
            $headerLower = array_map('strtolower', $header);
            $nameIdx = array_search('name', $headerLower);
            $urlIdx = array_search('url', $headerLower);
            if ($nameIdx === false || $urlIdx === false) {
                $importErrors[] = 'CSV must have "name" and "url" columns.';
            } else {
                $typeIdx = array_search('type', $headerLower);
                $parentIdx = array_search('parent_domain', $headerLower);
                $fileLocIdx = array_search('file_location', $headerLower);
                $descIdx = array_search('description', $headerLower);
                $regIdx = array_search('registrar', $headerLower);
                $expIdx = array_search('expires_at', $headerLower);
                $priceIdx = array_search('renewal_price', $headerLower);
                $autoIdx = array_search('auto_renew', $headerLower);
                $dbHostIdx = array_search('db_host', $headerLower);
                $dbPortIdx = array_search('db_port', $headerLower);
                $dbNameIdx = array_search('db_name', $headerLower);
                $dbUserIdx = array_search('db_user', $headerLower);
                $dbPassIdx = array_search('db_password', $headerLower);

                foreach ($lines as $i => $line) {
                    if ($line === '') {
                        continue;
                    }
                    $row = str_getcsv($line);
                    $name = trim((string) ($row[$nameIdx] ?? ''));
                    $url = trim((string) ($row[$urlIdx] ?? ''));
                    if ($name === '' || $url === '') {
                        continue;
                    }

                    $type = $typeIdx !== false ? strtolower(trim((string) ($row[$typeIdx] ?? 'domain'))) : 'domain';
                    if ($type !== 'domain' && $type !== 'subdomain') {
                        $type = 'domain';
                    }

                    $description = $descIdx !== false ? trim((string) ($row[$descIdx] ?? '')) : '';
                    $registrar = $regIdx !== false ? trim((string) ($row[$regIdx] ?? '')) : '';
                    $expiresAt = $expIdx !== false ? trim((string) ($row[$expIdx] ?? '')) : '';
                    $renewalPrice = $priceIdx !== false ? trim((string) ($row[$priceIdx] ?? '')) : '';
                    $autoRenew = $autoIdx !== false ? trim((string) ($row[$autoIdx] ?? '')) : '';
                    $dbHost = $dbHostIdx !== false ? trim((string) ($row[$dbHostIdx] ?? '')) : '';
                    $dbPort = $dbPortIdx !== false ? trim((string) ($row[$dbPortIdx] ?? '')) : '';
                    $dbName = $dbNameIdx !== false ? trim((string) ($row[$dbNameIdx] ?? '')) : '';
                    $dbUser = $dbUserIdx !== false ? trim((string) ($row[$dbUserIdx] ?? '')) : '';
                    $dbPassword = $dbPassIdx !== false ? (string) ($row[$dbPassIdx] ?? '') : '';
                    if (in_array(strtolower($autoRenew), ['1', 'yes', 'true', 'on'], true)) {
                        $autoRenew = '1';
                    } elseif (in_array(strtolower($autoRenew), ['0', 'no', 'false', 'off'], true)) {
                        $autoRenew = '0';
                    }

                    if ($type === 'subdomain') {
                        $parentDomain = $parentIdx !== false ? trim((string) ($row[$parentIdx] ?? '')) : '';
                        $fileLocation = $fileLocIdx !== false ? trim((string) ($row[$fileLocIdx] ?? '')) : '';
                        if ($parentDomain === '') {
                            $importErrors[] = 'Row ' . ($i + 2) . ': subdomain requires parent_domain.';
                            continue;
                        }
                        if ($fileLocation === '') {
                            $importErrors[] = 'Row ' . ($i + 2) . ': subdomain requires file_location.';
                            continue;
                        }
                        $parent = get_domain_by_name_or_url($pdo, $parentDomain);
                        if (!$parent) {
                            $importErrors[] = 'Row ' . ($i + 2) . ': parent domain "' . $parentDomain . '" not found. Add the domain row before its subdomains.';
                            continue;
                        }
                        $encrypted = ($dbPassword !== '' && config('app_key') !== '') ? encrypt_secret($dbPassword) : '';
                        try {
                            create_subdomain($pdo, [
                                'domain_id' => (int) $parent['id'],
                                'name' => $name,
                                'url' => $url,
                                'file_location' => $fileLocation,
                                'description' => $description,
                                'db_host' => $dbHost,
                                'db_port' => $dbPort,
                                'db_name' => $dbName,
                                'db_user' => $dbUser,
                                'db_password_enc' => $encrypted,
                            ]);
                            $imported++;
                        } catch (Throwable $e) {
                            $importErrors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
                        }
                    } else {
                        $encrypted = ($dbPassword !== '' && config('app_key') !== '') ? encrypt_secret($dbPassword) : '';
                        try {
                            create_domain($pdo, [
                                'name' => $name,
                                'url' => $url,
                                'description' => $description,
                                'registrar' => $registrar,
                                'expires_at' => $expiresAt,
                                'renewal_price' => $renewalPrice,
                                'auto_renew' => $autoRenew,
                                'db_host' => $dbHost,
                                'db_port' => $dbPort,
                                'db_name' => $dbName,
                                'db_user' => $dbUser,
                                'db_password_enc' => $encrypted,
                            ]);
                            $imported++;
                        } catch (Throwable $e) {
                            $importErrors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
                        }
                    }
                }
            }
        }
        if (!empty($importErrors)) {
            set_flash('error', 'Import completed with errors: ' . implode(' ', array_slice($importErrors, 0, 3)));
        } else {
            set_flash('success', "Imported {$imported} domain(s).");
        }
        redirect_to('/index.php');
        break;

    case 'dashboard':
    default:
        $pageTitle = 'Dashboard';
        $search = trim((string) ($_GET['q'] ?? ''));
        if (isset($_GET['check_updates']) && $_GET['check_updates'] === '1') {
            $updateAvailable = update_check_run(base_path(), true);
            if (!empty($updateAvailable['error'])) {
                set_flash('error', 'Update check failed: ' . $updateAvailable['error']);
            } elseif (!empty($updateAvailable['available'])) {
                set_flash('success', 'Update available. Click Open Updater to apply.');
            } else {
                set_flash('success', 'No updates found.');
            }
            redirect_to('/index.php');
        }
        $domains = $search === '' ? get_domains($pdo) : get_domains_search($pdo, $search);
        $subdomainsByDomain = [];
        foreach ($domains as $domain) {
            $subs = $search === ''
                ? get_subdomains_by_domain($pdo, (int) $domain['id'])
                : get_subdomains_by_domain_search($pdo, (int) $domain['id'], $search);
            foreach ($subs as &$sub) {
                $sub['db_password_plain'] = config('app_key') === '' ? '' : decrypt_secret((string) $sub['db_password_enc']);
            }
            unset($sub);
            $subdomainsByDomain[$domain['id']] = $subs;
        }
        $updateAvailable = update_check_run(base_path(), false);
        if (empty($updateAvailable['available'])) {
            $updateAvailable = null;
        }
        $expiryAlert = expiry_check_run($pdo, false);
        $view = 'dashboard';
        break;
}

require base_path('views/layout.php');
