<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase
{
    public function test_encrypt_and_decrypt_roundtrip(): void
    {
        $key = 'test_key_32_chars_minimum_required';
        $plaintext = 'my_secret_password';

        $encrypted = encrypt_secret_with_key($plaintext, $key);

        $this->assertNotEquals($plaintext, $encrypted, 'Encrypted value should differ from plaintext');
        $this->assertNotEmpty($encrypted, 'Encrypted value should not be empty');

        $decrypted = decrypt_secret_with_key($encrypted, $key);

        $this->assertEquals($plaintext, $decrypted, 'Decrypted value should match original');
    }

    public function test_empty_string_returns_empty(): void
    {
        $key = 'test_key_32_chars_minimum_required';
        $this->assertEquals('', encrypt_secret_with_key('', $key));
        $this->assertEquals('', decrypt_secret_with_key('', $key));
    }
}