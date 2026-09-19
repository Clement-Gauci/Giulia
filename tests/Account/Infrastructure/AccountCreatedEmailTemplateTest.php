<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Rendu réel du gabarit d'ouverture d'accès. Le test avec espion s'arrête au
 * contexte transmis : il ne dirait rien des URL ni du texte.
 */
final class AccountCreatedEmailTemplateTest extends KernelTestCase
{
    private const EMAIL = 'camille@giulia-pizza-gorges.fr';

    private function render(): string
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);

        return $twig->render('emails/account_created.html.twig', [
            'account' => Account::create(self::EMAIL, 'Camille', new \DateTimeImmutable()),
            'login_url' => 'https://giulia-pizza-gorges.fr/admin/connexion',
        ]);
    }

    public function test_it_greets_the_person_and_shows_their_address(): void
    {
        $html = $this->render();

        self::assertStringContainsString('Camille', $html);
        self::assertStringContainsString(self::EMAIL, $html);
    }

    public function test_it_explains_that_there_is_no_password(): void
    {
        // C'est le cœur du message : sans cette explication, la personne cherche
        // un mot de passe qui n'existe pas.
        $html = $this->render();

        self::assertStringContainsString('mot de passe', $html);
        self::assertStringContainsString('6', $html);
    }

    public function test_it_does_not_promise_features_that_do_not_exist(): void
    {
        // La maquette annonçait « les commandes » : il n'y a pas de click & collect,
        // et le dashboard ne sert qu'à modifier le contenu du site.
        self::assertStringNotContainsString('commandes', $this->render());
    }

    public function test_its_links_and_images_are_absolute(): void
    {
        $html = $this->render();

        // Une boîte mail n'a pas de base d'URL : tout chemin relatif est mort.
        self::assertStringNotContainsString('src="assets/', $html);
        self::assertStringNotContainsString('src="/', $html);
        self::assertMatchesRegularExpression('#<img[^>]+src="https?://#', $html);
        self::assertStringContainsString('https://giulia-pizza-gorges.fr/admin/connexion', $html);
    }

    public function test_it_reaches_the_pizzeria_by_phone(): void
    {
        self::assertStringContainsString('tel:+33285528742', $this->render());
    }
}
