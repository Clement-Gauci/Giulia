<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoutesSmokeTest extends WebTestCase
{
    #[DataProvider('routes')]
    public function test_route_renders_successfully(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<array{string}>
     */
    public static function routes(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'carte' => ['/nos-pizzas'];
        yield 'fiche pizza' => ['/nos-pizzas/giulia'];
        yield 'contact' => ['/contact'];
        yield 'mentions légales' => ['/mentions-legales'];
        yield 'statut' => ['/api/status'];
    }
}
