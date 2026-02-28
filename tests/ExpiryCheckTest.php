<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ExpiryCheckTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $schemaPath = __DIR__ . '/../migrations/001_init.sql';
        ensure_schema($this->pdo, $schemaPath);
        // clear any existing flag file
        $flag = base_path('data/expiry_check.json');
        if (file_exists($flag)) {
            unlink($flag);
        }
    }

    public function test_run_counts_and_payload(): void
    {
        // create domains with various expiry dates
        $today = date('Y-m-d');
        $past = date('Y-m-d', strtotime('-1 day'));
        $in5 = date('Y-m-d', strtotime('+5 days'));
        $in20 = date('Y-m-d', strtotime('+20 days'));
        $in40 = date('Y-m-d', strtotime('+40 days'));
        $in70 = date('Y-m-d', strtotime('+70 days'));

        $create = function ($expires) {
            return create_domain($this->pdo, [
                'name' => 'd' . $expires,
                'url' => 'https://'.$expires,
                'description' => '',
                'db_host' => '',
                'db_port' => '',
                'db_name' => '',
                'db_user' => '',
                'db_password_enc' => '',
                'expires_at' => $expires,
            ]);
        };

        $create($past);
        $create($in5);
        $create($in20);
        $create($in40);
        $create($in70);

        $payload = expiry_check_run($this->pdo, true);
        $this->assertArrayHasKey('count_overdue', $payload);
        $this->assertEquals(1, $payload['count_overdue']);
        $this->assertEquals(1, $payload['count_7']);
        $this->assertEquals(1, $payload['count_30']);
        $this->assertEquals(1, $payload['count_60']);
        $this->assertEquals(1, $payload['count_90']);

        // re-run without force should return with same checked_at (within interval)
        $copy = expiry_check_run($this->pdo, false, 3600);
        $this->assertEquals($payload['checked_at'], $copy['checked_at']);
    }
}
