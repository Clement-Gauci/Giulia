<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitemapTest extends WebTestCase
{
    public function test_sitemap_is_served_as_xml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_sitemap_lists_pages_with_absolute_urls(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');
        $xml = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<urlset', $xml);
        self::assertStringContainsString('<loc>http://localhost/</loc>', $xml);
        self::assertStringContainsString('<loc>http://localhost/nos-pizzas</loc>', $xml);
    }
}
