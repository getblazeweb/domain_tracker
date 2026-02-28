<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class UpdateCheckTest extends TestCase
{
    public function test_should_exclude(): void
    {
        $this->assertTrue(update_check_should_exclude('data/anything'));
        $this->assertTrue(update_check_should_exclude('config/foo'));        
        $this->assertTrue(update_check_should_exclude('.env'));            
        $this->assertTrue(update_check_should_exclude('public/updater.php')); 
        $this->assertFalse(update_check_should_exclude('src/repo.php'));
        $this->assertFalse(update_check_should_exclude('assets/style.css'));
    }

    public function test_recursive_scan_builds_list(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'utest_' . bin2hex(random_bytes(4));
        mkdir($dir . '/sub', 0755, true);
        file_put_contents($dir . '/a.txt', 'one');
        file_put_contents($dir . '/sub/b.txt', 'two');
        $files = [];
        update_check_recursive_scan($dir, $dir, $files);
        sort($files);
        $this->assertEquals(['a.txt', 'sub/b.txt'], $files);
        // cleanup
        unlink($dir . '/a.txt');
        unlink($dir . '/sub/b.txt');
        rmdir($dir . '/sub');
        rmdir($dir);
    }
}
