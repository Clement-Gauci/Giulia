<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPageTest extends WebTestCase
{
    public function test_legal_page_renders_official_data(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        self::assertSelectorTextContains('body', 'GIULIA PIZZAS');
        self::assertSelectorTextContains('body', '918 159 211 00013');
        self::assertSelectorTextContains('body', 'OVH SAS');
        self::assertSelectorTextContains('body', 'Clément GAUCI');
    }

    public function test_cookies_section_has_reopen_button(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'mesure d’audience anonyme');
        self::assertSelectorExists('button.cookie-manage-btn[data-action="cookie-consent#reopen"]');
    }
}
