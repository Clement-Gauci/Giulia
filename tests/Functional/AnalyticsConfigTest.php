<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnalyticsConfigTest extends WebTestCase
{
    public function test_measurement_id_meta_is_rendered(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // .env.test définit GA_MEASUREMENT_ID=G-TEST00000
        self::assertSelectorExists('meta[name="ga-measurement-id"][content="G-TEST00000"]');
    }
}
