<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function test_config_and_base_path(): void
    {
        global $config;
        $config = ['foo' => 'bar'];
        $this->assertEquals('bar', config('foo'));
        $this->assertNull(config('missing'));
        $bp = base_path('sub');
        $this->assertStringEndsWith('/sub', $bp);
    }

    public function test_csrf_persistence(): void
    {
        $_SESSION = [];
        $t1 = csrf_token();
        $t2 = csrf_token();
        $this->assertEquals($t1, $t2);
    }
}
