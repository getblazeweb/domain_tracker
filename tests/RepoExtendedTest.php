<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RepoExtendedTest extends TestCase
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

    public function test_ensure_schema_missing_throws(): void
    {
        $this->expectException(RuntimeException::class);
        ensure_schema($this->pdo, '/path/does/not/exist.sql');
    }

    public function test_crud_domain_and_search(): void
    {
        $id = create_domain($this->pdo, [
            'name' => 'Example',
            'url' => 'https://example.com',
            'description' => 'foo',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertNotNull(get_domain($this->pdo, $id));

        $row = get_domain_by_name_or_url($this->pdo, 'Example');
        $this->assertNotNull($row);
        $row2 = get_domain_by_name_or_url($this->pdo, 'https://example.com');
        $this->assertNotNull($row2);
        $this->assertNull(get_domain_by_name_or_url($this->pdo, ''));

        update_domain($this->pdo, $id, [
            'name' => 'Changed',
            'url' => 'https://changed',
            'description' => 'bar',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $updated = get_domain($this->pdo, $id);
        $this->assertEquals('Changed', $updated['name']);

        delete_domain($this->pdo, $id);
        $this->assertNull(get_domain($this->pdo, $id));
    }

    public function test_expiry_helpers(): void
    {
        // create domains with different expiry dates
        $dates = [
            date('Y-m-d', strtotime('-1 day')),
            date('Y-m-d', strtotime('+2 days')),
            date('Y-m-d', strtotime('+40 days')),
        ];
        foreach ($dates as $d) {
            create_domain($this->pdo, [
                'name' => $d,
                'url' => 'u',
                'description' => '',
                'db_host' => '',
                'db_port' => '',
                'db_name' => '',
                'db_user' => '',
                'db_password_enc' => '',
                'expires_at' => $d,
            ]);
        }
        $this->assertCount(1, get_domains_overdue($this->pdo));
        // function includes past dates when computing withinDays
        $this->assertCount(2, get_domains_expiring($this->pdo, 7));
        $this->assertCount(0, get_domains_expiring_between($this->pdo, 10, 20));
    }

    public function test_search_returns_domains(): void
    {
        $did = create_domain($this->pdo, [
            'name' => 'SearchMe',
            'url' => 'https://search',
            'description' => 'desc',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        // add a subdomain linked to domain id
        $sid = create_subdomain($this->pdo, [
            'domain_id' => $did,
            'name' => 'Sub',
            'url' => 'https://sub',
            'file_location' => '',
            'description' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $results = get_domains_search($this->pdo, 'SearchMe');
        $this->assertNotEmpty($results);
        $this->assertEquals('SearchMe', $results[0]['name']);

        $results2 = get_domains_search($this->pdo, 'Sub');
        $this->assertNotEmpty($results2);
    }

    public function test_settings_and_login_attempts(): void
    {
        $this->assertNull(get_setting($this->pdo, 'foo'));
        set_setting($this->pdo, 'foo', 'bar');
        $this->assertEquals('bar', get_setting($this->pdo, 'foo'));
        set_setting($this->pdo, 'foo', 'baz');
        $this->assertEquals('baz', get_setting($this->pdo, 'foo'));

        // login attempts
        log_login_attempt($this->pdo, [
            'username' => 'u',
            'ip_address' => '1.2.3.4',
            'user_agent' => 'ua',
            'success' => 0,
            'reason' => 'r',
            'created_at' => date('c'),
        ]);
        log_login_attempt($this->pdo, [
            'username' => 'u',
            'ip_address' => '1.2.3.4',
            'user_agent' => 'ua',
            'success' => 1,
            'reason' => '',
            'created_at' => date('c'),
        ]);
        $this->assertGreaterThanOrEqual(1, count_failed_attempts($this->pdo, 'u', '1.2.3.4', 3600));
        $list = list_login_attempts($this->pdo, 10);
        $this->assertIsArray($list);
        $this->assertCount(2, $list);

        // login settings
        $this->assertNull(get_login_setting($this->pdo, 'key'));
        $this->assertEquals('def', get_login_setting($this->pdo, 'key', 'def'));
        set_login_setting($this->pdo, 'key', 'val');
        $this->assertEquals('val', get_login_setting($this->pdo, 'key', 'def'));
    }

    public function test_subdomain_crud_and_count(): void
    {
        $did = create_domain($this->pdo, [
            'name' => 'Dom',
            'url' => 'https://d',
            'description' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $this->assertEquals(0, count_subdomains_for_domain($this->pdo, $did));

        $sid = create_subdomain($this->pdo, [
            'domain_id' => $did,
            'name' => 'Sub1',
            'url' => 'u',
            'file_location' => '',
            'description' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $this->assertEquals(1, count_subdomains_for_domain($this->pdo, $did));
        $this->assertNotEmpty(get_subdomains_by_domain($this->pdo, $did));

        $s = get_subdomain($this->pdo, $sid);
        $this->assertEquals('Sub1', $s['name']);

        $results = get_subdomains_by_domain_search($this->pdo, $did, 'Sub');
        $this->assertNotEmpty($results);

        update_subdomain($this->pdo, $sid, [
            'name' => 'Sub2',
            'url' => 'u',
            'file_location' => '',
            'description' => '',
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_password_enc' => '',
        ]);
        $s2 = get_subdomain($this->pdo, $sid);
        $this->assertEquals('Sub2', $s2['name']);

        delete_subdomain($this->pdo, $sid);
        $this->assertEquals(0, count_subdomains_for_domain($this->pdo, $did));
    }
}
