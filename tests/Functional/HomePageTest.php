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
}
