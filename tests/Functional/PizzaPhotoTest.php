<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PizzaPhotoTest extends WebTestCase
{
    public function test_home_renders_the_photo_of_a_pizza_that_has_one(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $img = $crawler->filter('.pizza-card--photo img')->first();
        self::assertCount(1, $img, 'Aucune carte photographiée sur la page d’accueil.');
        self::assertStringContainsString('images/pizzas/', $img->attr('src'));
        self::assertStringContainsString('Pizza ', $img->attr('alt'));
        self::assertNotEmpty($img->attr('width'), 'Les dimensions évitent un saut de mise en page au chargement.');
    }

    public function test_pizzas_without_photo_keep_their_placeholder(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // La Regina n'a pas encore de photo : sa carte garde le gabarit vide.
        self::assertGreaterThan(0, $crawler->filter('.pizza-card--empty')->count());
    }

    public function test_pizza_page_shows_the_photo_as_a_banner(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/nos-pizzas/margherita');

        self::assertSelectorExists('.pizza-hero__photo--filled');
        self::assertStringContainsString(
            'margherita',
            $crawler->filter('.pizza-hero__photo--filled img')->attr('src'),
        );
    }

    public function test_pizza_page_without_photo_falls_back_to_the_label(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/regina');

        self::assertSelectorNotExists('.pizza-hero__photo--filled');
        self::assertSelectorExists('.pizza-hero__ph-label');
    }

    public function test_footer_credits_the_photographer(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorTextContains('.footer__credit', 'Chloé Laroche');
        self::assertSelectorExists('.footer__credit a[href="https://www.instagram.com/comchloe_/"]');
    }
}
