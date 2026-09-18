<?php
namespace App\Tests\Functional;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRole;
use App\Account\Infrastructure\Doctrine\DoctrineAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AdminLoginTest extends WebTestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        (new DoctrineAccountRepository($this->em))->save(
            Account::create(self::EMAIL, 'Clément', AccountRole::Manager, new \DateTimeImmutable()),
        );
    }

    public function test_an_anonymous_visitor_is_sent_to_the_login_screen(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/connexion');
    }

    public function test_the_login_screen_opens_on_step_one(): void
    {
        $crawler = $this->client->request('GET', '/admin/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.step__eyebrow', 'Étape 1 sur 2');
        self::assertSelectorExists('input[name="email"]');
        // Le dashboard ne doit jamais finir dans un index.
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }

    public function test_an_unknown_address_is_told_so_and_receives_nothing(): void
    {
        $this->submitEmail('personne@example.com');

        // Le collecteur est remis à zéro à chaque requête : on interroge donc les
        // e-mails avant de suivre la redirection, pas après.
        self::assertEmailCount(0);

        $crawler = $this->client->followRedirect();

        self::assertStringContainsString("n'est pas enregistrée", $crawler->filter('.panel--error')->text());
        self::assertSelectorTextContains('.step__eyebrow', 'Étape 1 sur 2');
    }

    public function test_a_known_address_receives_a_code_and_reaches_step_two(): void
    {
        $this->submitEmail(self::EMAIL);
        $code = $this->mailedCode();
        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        self::assertSelectorTextContains('.step__eyebrow--green', 'Étape 2 sur 2');
        self::assertCount(6, $crawler->filter('input[name="code[]"]'));
        // L'adresse ne doit apparaître ni en clair dans la page, ni dans l'URL.
        self::assertStringNotContainsString(self::EMAIL, $crawler->html());
        self::assertSame('/admin/connexion/code', $this->client->getRequest()->getPathInfo());
    }

    public function test_the_expiry_is_shown_in_the_pizzeria_s_timezone(): void
    {
        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        // PHP tourne en UTC : sans fuseau d'affichage, l'écran annoncerait au
        // gérant une heure décalée d'une ou deux heures selon la saison.
        $expected = (new \DateTimeImmutable('+10 minutes'))
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->format('H:i');

        self::assertStringContainsString($expected, $crawler->filter('.step__lead')->text());
    }

    public function test_the_right_code_opens_the_dashboard(): void
    {
        $this->submitEmail(self::EMAIL);
        $code = $this->mailedCode();
        $crawler = $this->client->followRedirect();

        $this->submitCode($crawler, $code);

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.shell__title', 'Clément');
    }

    public function test_a_wrong_code_says_how_many_tries_remain(): void
    {
        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        $this->submitCode($crawler, '000000');
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Encore 2 essai(s)', $crawler->filter('.panel--error')->text());
        self::assertSelectorTextContains('.step__eyebrow--green', 'Étape 2 sur 2');
    }

    public function test_three_wrong_codes_show_the_blocked_screen(): void
    {
        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        for ($i = 1; $i <= 3; $i++) {
            $this->submitCode($crawler, '000000');
            $crawler = $this->client->followRedirect();
        }

        self::assertSelectorTextContains('.blocked__title', 'Accès temporairement bloqué');
        // mm:ss, zéros compris : un « 15:0 » est passé en production le temps
        // d'un contrôle visuel, faute d'assertion sur le format.
        self::assertMatchesRegularExpression(
            '/^\d{2}:\d{2}$/',
            trim($crawler->filter('.blocked__clock')->text()),
        );
    }

    public function test_the_blocked_screen_survives_a_refresh(): void
    {
        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        for ($i = 1; $i <= 3; $i++) {
            $this->submitCode($crawler, '000000');
            $crawler = $this->client->followRedirect();
        }

        $this->client->request('GET', '/admin/connexion/code');

        self::assertSelectorTextContains('.blocked__title', 'Accès temporairement bloqué');
    }

    public function test_leaving_the_blocked_screen_clears_it(): void
    {
        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        for ($i = 1; $i <= 3; $i++) {
            $this->submitCode($crawler, '000000');
            $crawler = $this->client->followRedirect();
        }

        // L'écran de blocage est volontairement persistant, mais il doit pouvoir
        // être quitté : sinon on y reste même une fois la pause terminée.
        $this->client->request('GET', '/admin/connexion/changer');
        $this->client->followRedirect();

        self::assertSelectorNotExists('.blocked__title');
        self::assertSelectorTextContains('.step__eyebrow', 'Étape 1 sur 2');
    }

    public function test_a_revoked_account_cannot_ask_for_a_code(): void
    {
        (new DoctrineAccountRepository($this->em))->save(
            new Account(self::EMAIL, 'Clément', AccountRole::Manager, false, new \DateTimeImmutable()),
        );

        $this->submitEmail(self::EMAIL);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString("n'est pas enregistrée", $crawler->filter('.panel--error')->text());
    }

    private function submitEmail(string $email): void
    {
        $crawler = $this->client->request('GET', '/admin/connexion');
        $form = $crawler->filter('form')->form();
        $form['email'] = $email;

        $this->client->submit($form);
    }

    private function submitCode(Crawler $crawler, string $code): void
    {
        $form = $crawler->filter('form')->eq(0)->form();

        foreach (str_split($code) as $index => $digit) {
            $form['code[' . $index . ']'] = $digit;
        }

        $this->client->submit($form);
    }

    /**
     * Le code est relu dans l'e-mail réellement envoyé : c'est ce qui prouve la
     * chaîne complète (tirage, hachage en base, remise).
     *
     * Le gabarit provisoire ne contient aucun autre nombre de six chiffres d'affilée
     * (le téléphone et le code postal sont découpés autrement). Si le design final
     * casse cette lecture, l'échec sera légitime : l'e-mail doit porter le code.
     */
    private function mailedCode(): string
    {
        $messages = self::getMailerMessages();
        self::assertNotEmpty($messages, "Aucun e-mail n'a été envoyé.");

        $text = strip_tags((string) end($messages)->getHtmlBody());
        self::assertMatchesRegularExpression('/(?<!\d)\d{6}(?!\d)/', $text);
        preg_match('/(?<!\d)(\d{6})(?!\d)/', $text, $matches);

        return $matches[1];
    }
}
