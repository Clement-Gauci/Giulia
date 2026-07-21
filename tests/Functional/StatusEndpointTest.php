<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatusEndpointTest extends WebTestCase
{
    public function test_status_endpoint_returns_json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/status');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('open', $data);
        self::assertArrayHasKey('label', $data);
        self::assertArrayHasKey('detail', $data);
        self::assertIsBool($data['open']);
    }
}
