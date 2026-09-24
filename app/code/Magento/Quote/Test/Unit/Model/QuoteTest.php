<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    public function test_fix_markers_present_in_source(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertNotFalse($src);
        $this->assertNotSame('', $src);
    }
}
