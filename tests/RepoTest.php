<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RepoTest extends TestCase
{
    private PDO $pdo;

protected function setUp(): void
{
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->exec('PRAGMA foreign_keys = ON');

    $schemaPath = __DIR__ . '/../migrations/001_init.sql';
    ensure_schema($this->pdo, $schemaPath);
}

    public function test_create_and_retrieve_domain(): void
    {
        $id = create_domain($this->pdo, [
            'name' => 'Example Site',
            'url' => 'https://example.com',
            'description' => 'A test domain',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);

        $this->assertGreaterThan(0, $id);

        $domain = get_domain($this->pdo, $id);
        $this->assertNotNull($domain);
        $this->assertEquals('Example Site', $domain['name']);
        $this->assertEquals('https://example.com', $domain['url']);
    }

    public function test_get_domains_returns_empty_when_none_exist(): void
    {
        $domains = get_domains($this->pdo);
        $this->assertIsArray($domains);
        $this->assertEmpty($domains);
    }
}