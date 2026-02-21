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
}

function get_domains(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM domains ORDER BY name ASC');
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

function create_domain(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO domains (name, url, description, db_host, db_port, db_name, db_user, db_password_enc, created_at, updated_at)
        VALUES (:name, :url, :description, :db_host, :db_port, :db_name, :db_user, :db_password_enc, :created_at, :updated_at)
    ');
    $now = date('c');
    $stmt->execute([
        ':name' => $data['name'],
        ':url' => $data['url'],
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

function update_domain(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare('
        UPDATE domains
        SET name = :name,
            url = :url,
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
