<?php
namespace App\Tests\Seo;

use PHPUnit\Framework\TestCase;

final class RobotsTxtTest extends TestCase
{
    public function test_robots_declares_sitemap_and_blocks_api(): void
    {
        $robots = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/robots.txt');

        self::assertStringContainsString('User-agent: *', $robots);
        self::assertStringContainsString('Disallow: /api/', $robots);
        self::assertStringContainsString('Sitemap: https://giulia-pizza-gorges.fr/sitemap.xml', $robots);
    }
}
