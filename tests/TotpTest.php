<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_base32_encode_decode_roundtrip(): void
    {
        $data = random_bytes(10);
        $encoded = totp_base32_encode($data);
        $this->assertIsString($encoded);
        $decoded = totp_base32_decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    public function test_generate_secret_strength(): void
    {
        $secret = totp_generate_secret(5);
        $this->assertIsString($secret);
        // should decode back to 5 bytes
        $this->assertEquals(5, strlen(totp_base32_decode($secret)));
    }

    public function test_totp_at_and_verify(): void
    {
        $secret = totp_generate_secret();
        $step = (int) floor(time() / 30);
        $code = totp_at($secret, $step);
        $this->assertEquals(6, strlen($code));
        $this->assertTrue(totp_verify($secret, $code));
        $this->assertFalse(totp_verify($secret, '000000'));
        $this->assertFalse(totp_verify($secret, '')); // empty string
    }
}
