<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuPageTest extends WebTestCase
{
    public function test_menu_index_lists_categories(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Base sauce tomate');
        self::assertSelectorTextContains('body', 'Margherita');
        self::assertSelectorTextContains('body', 'Base spéciale');
    }

    public function test_pizza_page_shows_details(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/giulia');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Giulia');
        self::assertSelectorTextContains('body', 'confiture de figues');
    }

    public function test_unknown_pizza_returns_404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/inexistante');
        self::assertResponseStatusCodeSame(404);
    }

    public function test_menu_cta_points_to_order_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');
        self::assertSelectorExists('a#commander[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }
}
