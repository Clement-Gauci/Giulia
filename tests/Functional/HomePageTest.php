<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function test_home_renders_key_blocks(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge'); // statut d'ouverture
        self::assertSelectorTextContains('body', 'Click & Collect');
        self::assertSelectorTextContains('body', 'La Fresca'); // pizza du moment
    }

    public function test_click_and_collect_cta_points_to_order_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorExists('a#commander[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }

    public function test_pizza_du_moment_block_shows_special(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorTextContains('.featured', 'Pizza du moment');
        self::assertSelectorTextContains('.featured', 'La Fresca');
        self::assertSelectorExists('.featured a.featured__cta[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }
}
