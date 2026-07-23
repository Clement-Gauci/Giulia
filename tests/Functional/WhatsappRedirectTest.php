<?php
namespace App\Tests\Functional;

use App\Shared\Domain\EstablishmentRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WhatsappRedirectTest extends WebTestCase
{
    public function test_join_whatsapp_group_redirects_to_configured_url(): void
    {
        $client = static::createClient();
        $expectedUrl = static::getContainer()
            ->get(EstablishmentRepositoryInterface::class)
            ->get()
            ->whatsappUrl();

        $client->request('GET', '/join-whatsapp-group');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects($expectedUrl);
    }

    public function test_scan_is_written_to_dedicated_tracking_log(): void
    {
        $client = static::createClient();
        $marker = 'scan-' . bin2hex(random_bytes(6));
        $logFile = static::getContainer()->getParameter('kernel.logs_dir') . '/whatsapp_group_scans.log';

        $client->request('GET', '/join-whatsapp-group?src=' . $marker);

        self::assertFileExists($logFile);
        self::assertStringContainsString($marker, file_get_contents($logFile));
    }
}
