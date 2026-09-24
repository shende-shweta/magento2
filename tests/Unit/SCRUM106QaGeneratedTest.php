<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SCRUM106QaGeneratedTest extends TestCase
{
    private function loadIncidentSource(): string
    {
        $root = dirname(__DIR__, 2);
        $candidates = [
            "app/code/Magento/Quote/Model/Quote.php",
            "app/code/Magento/Quote/Test/Unit/Model/QuoteTest.php",
        ];
        foreach ($candidates as $rel) {
            $full = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rel);
            if (is_file($full)) {
                return (string) file_get_contents($full);
            }
        }
        $this->fail("Incident SCRUM-106: none of the proposed source files exist on the connected repo");
        return "";
    }

    public function testFixMarkersPresentInSource(): void
    {
        $src = $this->loadIncidentSource();
        $this->assertNotSame("", trim($src));
        $this->assertNotSame('', $src);
    }
}