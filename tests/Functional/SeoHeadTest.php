<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoHeadTest extends WebTestCase
{
    public function test_home_exposes_canonical_and_social_tags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/"]');
        self::assertSelectorExists('meta[property="og:title"]');
        self::assertSelectorExists('meta[property="og:image"]');
        self::assertSelectorExists('meta[name="twitter:card"]');
    }

    public function test_home_embeds_restaurant_json_ld(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $json = $crawler->filter('script[type="application/ld+json"]')->text();
        self::assertStringContainsString('"@type":"Restaurant"', $json);
        self::assertStringContainsString('"addressLocality":"Gorges"', $json);
    }

    public function test_pizza_page_canonical_targets_its_own_path(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/nos-pizzas"]');
    }
}
