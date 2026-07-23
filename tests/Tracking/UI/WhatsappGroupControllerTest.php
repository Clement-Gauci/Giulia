<?php
namespace App\Tests\Tracking\UI;

use App\Shared\Domain\Announcement;
use App\Shared\Domain\Establishment;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use App\Tracking\UI\WhatsappGroupController;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class WhatsappGroupControllerTest extends TestCase
{
    public function test_redirects_to_configured_whatsapp_url(): void
    {
        $response = (new WhatsappGroupController())(
            Request::create('/join-whatsapp-group'),
            $this->repositoryWithWhatsappUrl('https://chat.whatsapp.com/TEST123'),
            new SpyLogger(),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://chat.whatsapp.com/TEST123', $response->getTargetUrl());
    }

    public function test_logs_scan_with_request_metadata(): void
    {
        $logger = new SpyLogger();
        $request = Request::create(
            '/join-whatsapp-group?src=vitrine-gorges',
            'GET',
            server: [
                'REMOTE_ADDR' => '203.0.113.7',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)',
                'HTTP_REFERER' => 'https://qr.example/menu',
                'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
            ],
        );

        (new WhatsappGroupController())(
            $request,
            $this->repositoryWithWhatsappUrl('https://chat.whatsapp.com/TEST123'),
            $logger,
        );

        self::assertCount(1, $logger->records);
        $record = $logger->records[0];
        self::assertSame('info', $record['level']);
        self::assertSame('203.0.113.7', $record['context']['ip']);
        self::assertSame('Mozilla/5.0 (iPhone)', $record['context']['user_agent']);
        self::assertSame('https://qr.example/menu', $record['context']['referer']);
        self::assertSame('fr-FR,fr;q=0.9', $record['context']['accept_language']);
        self::assertSame(['src' => 'vitrine-gorges'], $record['context']['query']);
    }

    private function repositoryWithWhatsappUrl(string $url): EstablishmentRepositoryInterface
    {
        $establishment = new Establishment(
            'Giulia', 'Pizzeria', '1 rue', 47.1, -1.3, '02', '+33', 'e@mail.fr', '/menu.pdf',
            'https://order', 'https://directions', 'https://reviews', $url, [],
            new Announcement(false, 'titre', 'texte'),
        );

        return new class($establishment) implements EstablishmentRepositoryInterface {
            public function __construct(private Establishment $establishment) {}
            public function get(): Establishment { return $this->establishment; }
        };
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
