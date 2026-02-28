<?php
declare(strict_types=1);

function ensure_schema(PDO $pdo, string $schemaPath): void
{
    if (!file_exists($schemaPath)) {
        throw new RuntimeException('Schema file missing.');
    }
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Failed to read schema.');
    }
    $pdo->exec($schema);

    // Lightweight migrations for existing databases (add missing columns).
    $tables = [
        'domains' => [
            'description' => 'TEXT',
            'registrar' => 'TEXT',
            'expires_at' => 'TEXT',
            'renewal_price' => 'TEXT',
            'auto_renew' => 'TEXT',
            'db_host' => 'TEXT',
            'db_port' => 'TEXT',
            'db_name' => 'TEXT',
            'db_user' => 'TEXT',
            'db_password_enc' => 'TEXT',
        ],
        'subdomains' => [
            'description' => 'TEXT',
            'db_host' => 'TEXT',
            'db_port' => 'TEXT',
            'db_name' => 'TEXT',
            'db_user' => 'TEXT',
            'db_password_enc' => 'TEXT',
        ],
    ];

    foreach ($tables as $table => $columns) {
        $existing = [];
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        if ($stmt) {
            foreach ($stmt->fetchAll() as $row) {
                $existing[] = $row['name'];
            }
        }
        foreach ($columns as $name => $type) {
            if (!in_array($name, $existing, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $type);
            }
        }
    }
}

function get_domains(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM domains ORDER BY name ASC');
    return $stmt->fetchAll();
}

function get_domains_expiring(PDO $pdo, int $withinDays = 90): array
{
    $cutoff = date('Y-m-d', strtotime("+{$withinDays} days"));
    $stmt = $pdo->prepare('
        SELECT * FROM domains
        WHERE expires_at IS NOT NULL AND expires_at != ""
          AND expires_at <= :cutoff
        ORDER BY expires_at ASC
    ');
    $stmt->execute([':cutoff' => $cutoff]);
    return $stmt->fetchAll();
}

function get_domains_expiring_between(PDO $pdo, int $startDays, int $endDays): array
{
    $start = date('Y-m-d', strtotime("+{$startDays} days"));
    $end = date('Y-m-d', strtotime("+{$endDays} days"));
    $stmt = $pdo->prepare('
        SELECT * FROM domains
        WHERE expires_at IS NOT NULL AND expires_at != ""
          AND expires_at >= :start AND expires_at <= :end
        ORDER BY expires_at ASC
    ');
    $stmt->execute([':start' => $start, ':end' => $end]);
    return $stmt->fetchAll();
}

function get_domains_overdue(PDO $pdo): array
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT * FROM domains
        WHERE expires_at IS NOT NULL AND expires_at != ""
          AND expires_at < :today
        ORDER BY expires_at ASC
    ');
    $stmt->execute([':today' => $today]);
    return $stmt->fetchAll();
}

function get_domains_search(PDO $pdo, string $term): array
{
    $like = '%' . $term . '%';
    $stmt = $pdo->prepare('
        SELECT DISTINCT d.*
        FROM domains d
        LEFT JOIN subdomains s ON s.domain_id = d.id
        WHERE d.name LIKE :term
           OR d.url LIKE :term
           OR IFNULL(d.description, "") LIKE :term
           OR IFNULL(d.registrar, "") LIKE :term
           OR IFNULL(d.expires_at, "") LIKE :term
           OR IFNULL(d.db_name, "") LIKE :term
           OR IFNULL(d.db_user, "") LIKE :term
           OR IFNULL(d.db_host, "") LIKE :term
           OR s.name LIKE :term
           OR s.url LIKE :term
           OR IFNULL(s.description, "") LIKE :term
           OR IFNULL(s.file_location, "") LIKE :term
           OR IFNULL(s.db_name, "") LIKE :term
           OR IFNULL(s.db_user, "") LIKE :term
           OR IFNULL(s.db_host, "") LIKE :term
        ORDER BY d.name ASC
    ');
    $stmt->execute([':term' => $like]);
    return $stmt->fetchAll();
}

function get_domain(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM domains WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_domain_by_name_or_url(PDO $pdo, string $nameOrUrl): ?array
{
    $key = trim($nameOrUrl);
    if ($key === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM domains WHERE name = :key OR url = :key2');
    $stmt->execute([':key' => $key, ':key2' => $key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_domain(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO domains (name, url, description, registrar, expires_at, renewal_price, auto_renew, db_host, db_port, db_name, db_user, db_password_enc, created_at, updated_at)
        VALUES (:name, :url, :description, :registrar, :expires_at, :renewal_price, :auto_renew, :db_host, :db_port, :db_name, :db_user, :db_password_enc, :created_at, :updated_at)
    ');
    $now = date('c');
    $stmt->execute([
        ':name' => $data['name'],
        ':url' => $data['url'],
        ':description' => $data['description'],
        ':registrar' => $data['registrar'] ?? '',
        ':expires_at' => $data['expires_at'] ?? '',
        ':renewal_price' => $data['renewal_price'] ?? '',
        ':auto_renew' => $data['auto_renew'] ?? '',
        ':db_host' => $data['db_host'],
        ':db_port' => $data['db_port'],
        ':db_name' => $data['db_name'],
        ':db_user' => $data['db_user'],
        ':db_password_enc' => $data['db_password_enc'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    return (int) $pdo->lastInsertId();
}

function update_domain(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare('
        UPDATE domains
        SET name = :name,
            url = :url,
            description = :description,
            registrar = :registrar,
            expires_at = :expires_at,
            renewal_price = :renewal_price,
            auto_renew = :auto_renew,
            db_host = :db_host,
            db_port = :db_port,
            db_name = :db_name,
            db_user = :db_user,
            db_password_enc = :db_password_enc,
            updated_at = :updated_at
        WHERE id = :id
    ');
    $stmt->execute([
        ':name' => $data['name'],
        ':url' => $data['url'],
        ':description' => $data['description'],
        ':registrar' => $data['registrar'] ?? '',
        ':expires_at' => $data['expires_at'] ?? '',
        ':renewal_price' => $data['renewal_price'] ?? '',
        ':auto_renew' => $data['auto_renew'] ?? '',
        ':db_host' => $data['db_host'],
        ':db_port' => $data['db_port'],
        ':db_name' => $data['db_name'],
        ':db_user' => $data['db_user'],
        ':db_password_enc' => $data['db_password_enc'],
        ':updated_at' => date('c'),
        ':id' => $id,
    ]);
}

function delete_domain(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM domains WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function count_subdomains_for_domain(PDO $pdo, int $domainId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM subdomains WHERE domain_id = :domain_id');
    $stmt->execute([':domain_id' => $domainId]);
    $row = $stmt->fetch();
    return (int) ($row['total'] ?? 0);
}

function get_setting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM app_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : null;
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('
        INSERT INTO app_settings (key, value)
        VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
    ');
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

function log_login_attempt(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare('
        INSERT INTO login_attempts (username, ip_address, user_agent, success, reason, created_at)
        VALUES (:username, :ip_address, :user_agent, :success, :reason, :created_at)
    ');
    $stmt->execute([
        ':username' => $data['username'],
        ':ip_address' => $data['ip_address'],
        ':user_agent' => $data['user_agent'],
        ':success' => $data['success'],
        ':reason' => $data['reason'],
        ':created_at' => $data['created_at'],
    ]);
}

function count_failed_attempts(PDO $pdo, string $username, string $ip, int $windowSeconds): int
{
    $since = date('c', time() - $windowSeconds);
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total
        FROM login_attempts
        WHERE success = 0
          AND username = :username
          AND ip_address = :ip_address
          AND created_at >= :since
    ');
    $stmt->execute([
        ':username' => $username,
        ':ip_address' => $ip,
        ':since' => $since,
    ]);
    $row = $stmt->fetch();
    return (int) ($row['total'] ?? 0);
}

function get_login_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM login_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : $default;
}

function set_login_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('
        INSERT INTO login_settings (key, value)
        VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
    ');
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

function list_login_attempts(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare('
        SELECT *
        FROM login_attempts
        ORDER BY created_at DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_subdomains_by_domain(PDO $pdo, int $domainId): array
{
    $stmt = $pdo->prepare('SELECT * FROM subdomains WHERE domain_id = :domain_id ORDER BY name ASC');
    $stmt->execute([':domain_id' => $domainId]);
    return $stmt->fetchAll();
}

function get_subdomains_by_domain_search(PDO $pdo, int $domainId, string $term): array
{
    $like = '%' . $term . '%';
    $stmt = $pdo->prepare('
        SELECT *
        FROM subdomains
        WHERE domain_id = :domain_id
          AND (
              name LIKE :term
              OR url LIKE :term
              OR IFNULL(description, "") LIKE :term
              OR IFNULL(file_location, "") LIKE :term
              OR IFNULL(db_name, "") LIKE :term
              OR IFNULL(db_user, "") LIKE :term
              OR IFNULL(db_host, "") LIKE :term
          )
        ORDER BY name ASC
    ');
    $stmt->execute([
        ':domain_id' => $domainId,
        ':term' => $like,
    ]);
    return $stmt->fetchAll();
}

function get_subdomain(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM subdomains WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_subdomain(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO subdomains (
            domain_id, name, url, file_location, description, db_host, db_port, db_name, db_user, db_password_enc,
            created_at, updated_at
        )
        VALUES (
            :domain_id, :name, :url, :file_location, :description, :db_host, :db_port, :db_name, :db_user, :db_password_enc,
            :created_at, :updated_at
        )
    ');
    $now = date('c');
    $stmt->execute([
        ':domain_id' => $data['domain_id'],
        ':name' => $data['name'],
        ':url' => $data['url'],
        ':file_location' => $data['file_location'],
        ':description' => $data['description'],
        ':db_host' => $data['db_host'],
        ':db_port' => $data['db_port'],
        ':db_name' => $data['db_name'],
        ':db_user' => $data['db_user'],
        ':db_password_enc' => $data['db_password_enc'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    return (int) $pdo->lastInsertId();
}

function update_subdomain(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare('
        UPDATE subdomains
        SET name = :name,
            url = :url,
            file_location = :file_location,
            description = :description,
            db_host = :db_host,
            db_port = :db_port,
            db_name = :db_name,
            db_user = :db_user,
            db_password_enc = :db_password_enc,
            updated_at = :updated_at
        WHERE id = :id
    ');
    $stmt->execute([
        ':name' => $data['name'],
        ':url' => $data['url'],
        ':file_location' => $data['file_location'],
        ':description' => $data['description'],
        ':db_host' => $data['db_host'],
        ':db_port' => $data['db_port'],
        ':db_name' => $data['db_name'],
        ':db_user' => $data['db_user'],
        ':db_password_enc' => $data['db_password_enc'],
        ':updated_at' => date('c'),
        ':id' => $id,
    ]);
}

function delete_subdomain(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM subdomains WHERE id = :id');
    $stmt->execute([':id' => $id]);
}
