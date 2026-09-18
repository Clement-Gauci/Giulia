<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRole;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Le gabarit est rendu pour de vrai : c'est le seul moyen de vérifier l'heure
 * qu'il annonce. Le test avec espion, lui, s'arrête au contexte transmis.
 */
final class LoginCodeEmailTemplateTest extends KernelTestCase
{
    public function test_it_announces_the_expiry_in_the_pizzeria_s_timezone(): void
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);

        // 20 h 10 UTC, soit 22 h 10 à Gorges en heure d'été. Annoncer « 20:10 »
        // à quelqu'un qui regarde sa montre enverrait chercher un code périmé.
        $expiresAt = new \DateTimeImmutable('2026-09-09 20:10:00', new \DateTimeZone('UTC'));

        $html = $twig->render('emails/login_code.html.twig', [
            'code' => '204815',
            'account' => Account::create('gerant@giulia-pizza-gorges.fr', 'Clément', AccountRole::Manager, $expiresAt),
            'expires_at' => $expiresAt,
            'expires_in_minutes' => 10,
        ]);

        self::assertStringContainsString('22:10', $html);
        self::assertStringNotContainsString('20:10', $html);
    }
}
