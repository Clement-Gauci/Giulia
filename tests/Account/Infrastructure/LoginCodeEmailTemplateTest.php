<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Le gabarit est rendu pour de vrai. Le test avec espion, lui, s'arrête au
 * contexte transmis : il ne dirait rien des heures ni des URL.
 */
final class LoginCodeEmailTemplateTest extends KernelTestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private function render(): string
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);

        // 20 h 10 UTC, soit 22 h 10 à Gorges en heure d'été.
        $requestedAt = new \DateTimeImmutable('2026-09-09 20:10:00', new \DateTimeZone('UTC'));

        return $twig->render('emails/login_code.html.twig', [
            'code' => '204815',
            'account' => Account::create(self::EMAIL, 'Clément', $requestedAt),
            'requested_at' => $requestedAt,
            'expires_at' => $requestedAt->modify('+10 minutes'),
            'expires_in_minutes' => 10,
        ]);
    }

    public function test_it_carries_the_code(): void
    {
        self::assertStringContainsString('204815', $this->render());
    }

    public function test_it_names_the_address_that_asked(): void
    {
        // La maquette montre l'adresse, pas le prénom : c'est ce qui permet au
        // destinataire de reconnaître une demande qu'il n'a pas faite.
        self::assertStringContainsString(self::EMAIL, $this->render());
    }

    public function test_it_dates_the_request_in_the_pizzeria_s_timezone(): void
    {
        // Annoncer « 20:10 » à quelqu'un dont la montre indique 22:10 sème le doute
        // sur une demande pourtant légitime.
        $html = $this->render();

        self::assertStringContainsString('9 septembre', $html);
        self::assertStringContainsString('22:10', $html);
        self::assertStringNotContainsString('20:10', $html);
    }

    public function test_its_links_and_images_are_absolute(): void
    {
        $html = $this->render();

        // Une boîte mail n'a pas de base d'URL : tout chemin relatif est mort.
        self::assertStringNotContainsString('src="assets/', $html);
        self::assertStringNotContainsString('src="/', $html);
        self::assertMatchesRegularExpression('#<img[^>]+src="https?://#', $html);
        self::assertStringContainsString('/admin/connexion"', $html);
        self::assertMatchesRegularExpression('#href="https?://[^"]*/admin/connexion"#', $html);
    }
}
