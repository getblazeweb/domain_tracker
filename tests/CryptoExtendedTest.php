<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CryptoExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        // ensure config has a fresh state
        global $config;
        $config = [];
        $_SESSION = [];
    }

    public function test_app_key_bytes_throws_if_not_configured(): void
    {
        global $config;
        $config['app_key'] = '';
        $this->expectException(RuntimeException::class);
        app_key_bytes();
    }

    public function test_app_key_bytes_returns_32_bytes(): void
    {
        global $config;
        $config['app_key'] = 'foo';
        $bytes = app_key_bytes();
        $this->assertIsString($bytes);
        $this->assertEquals(32, strlen($bytes));
        // calling again should be deterministic for same key
        $this->assertEquals($bytes, app_key_bytes());
    }

    public function test_encrypt_decrypt_with_app_key(): void
    {
        global $config;
        $config['app_key'] = 'testkey123';
        $plaintext = 'hello world';

        $cipher = encrypt_secret($plaintext);
        $this->assertNotEmpty($cipher);
        $this->assertNotEquals($plaintext, $cipher);

        $decoded = decrypt_secret($cipher);
        $this->assertEquals($plaintext, $decoded);
    }

    public function test_default_encrypt_empty_returns_empty(): void
    {
        global $config;
        $config['app_key'] = 'whatever';
        $this->assertEquals('', encrypt_secret(''));
        $this->assertEquals('', decrypt_secret(''));
    }

    public function test_decrypt_invalid_data_returns_empty(): void
    {
        global $config;
        $config['app_key'] = 'xyz';
        $this->assertEquals('', decrypt_secret('not-base64'));
        $this->assertEquals('', decrypt_secret(base64_encode('short')));
    }

    public function test_key_bytes_from_string(): void
    {
        $this->expectException(RuntimeException::class);
        key_bytes_from_string('');
    }

    public function test_key_bytes_from_string_nonempty(): void
    {
        $bytes = key_bytes_from_string('abc');
        $this->assertIsString($bytes);
        $this->assertEquals(32, strlen($bytes));
    }
}
