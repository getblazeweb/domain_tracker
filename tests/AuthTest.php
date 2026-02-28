<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        // reset session and config each test
        $_SESSION = [];
        global $config;
        $config = [];
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function test_login_and_logout(): void
    {
        $this->assertFalse(is_logged_in());
        login_user('alice');
        $this->assertTrue(is_logged_in());
        $this->assertEquals('alice', $_SESSION['user_name']);
        logout();
        $this->assertFalse(is_logged_in());
        $this->assertEmpty($_SESSION);
    }

    public function test_verify_credentials(): void
    {
        global $config;
        $config['admin_username'] = 'admin';
        $config['admin_password_hash'] = password_hash('secret', PASSWORD_DEFAULT);

        $this->assertTrue(verify_credentials('admin', 'secret'));
        $this->assertFalse(verify_credentials('admin', 'wrong'));
        $this->assertFalse(verify_credentials('bad', 'secret'));

        // missing configuration fails
        $config['admin_username'] = '';
        $config['admin_password_hash'] = '';
        $this->assertFalse(verify_credentials('admin', 'secret'));
    }

    public function test_csrf_token_and_verify(): void
    {
        $_SESSION = [];
        $token = csrf_token();
        $this->assertEquals($token, $_SESSION['csrf_token']);

        // valid token should not throw
        verify_csrf($token);

    }
}
