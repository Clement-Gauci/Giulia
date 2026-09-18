<?php
namespace App\Tests\Functional;

use App\Contact\AntiSpam\FormSignature;
use App\Contact\Domain\ContactMailerException;
use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;
use App\Contact\Infrastructure\SymfonyContactMailer;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

final class ContactPageTest extends WebTestCase
{
    /** Valeurs d'un message parfaitement ordinaire ; chaque test n'écrase que ce qui l'intéresse. */
    private const array LEGITIMATE = [
        'contact[name]' => 'Marie Dupont',
        'contact[email]' => 'marie@example.fr',
        'contact[phone]' => '0612345678',
        'contact[subject]' => 'cc',
        'contact[message]' => 'Bonjour, une question sur le click & collect.',
    ];

    /**
     * Récupéré une fois pour toutes : aller le chercher dans le conteneur au
     * milieu d'un scénario empêcherait le client de redémarrer le noyau entre
     * deux requêtes, et les e-mails collectés s'additionneraient.
     */
    private FormSignature $signature;

    /**
     * Prépare une soumission crédible : le formulaire réel, rempli, avec un
     * jeton antidaté de 30 s — sans quoi le garde la jugerait trop rapide
     * pour venir d'un humain.
     *
     * @param array<string, string> $values
     */
    private function filledForm(KernelBrowser $client, array $values = self::LEGITIMATE, int $secondsAgo = 30): Form
    {
        $crawler = $client->request('GET', '/contact');
        $form = $crawler->selectButton('Envoyer')->form($values);

        $issuedAt = (int) explode('.', (string) $form['contact[ts]']->getValue())[0];
        $form['contact[ts]'] = $this->signature->issue($issuedAt - $secondsAgo);

        return $form;
    }

    /** Nom du champ leurre tel que la page vient de le rendre (il change chaque jour). */
    private function honeypotField(Form $form): string
    {
        foreach ($form->all() as $name => $_field) {
            if (str_starts_with($name, 'contact[site_')) {
                return $name;
            }
        }

        self::fail('Aucun champ leurre dans le formulaire rendu.');
    }

    protected function setUp(): void
    {
        self::bootKernel();
        // Le quota par IP survit d'un test à l'autre via le cache : on repart net.
        /** @var CacheItemPoolInterface $pool */
        $pool = static::getContainer()->get('cache.rate_limiter');
        $pool->clear();
        /** @var FormSignature $signature */
        $signature = static::getContainer()->get(FormSignature::class);
        $this->signature = $signature;
        self::ensureKernelShutdown();
    }

    public function test_form_is_displayed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function test_valid_submission_sends_email_and_redirects(): void
    {
        $client = static::createClient();
        $client->submit($this->filledForm($client));

        self::assertEmailCount(1);
        self::assertResponseRedirects('/contact');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-success', 'Merci');
    }

    public function test_invalid_submission_shows_errors(): void
    {
        $client = static::createClient();
        $client->submit($this->filledForm($client, [
            'contact[name]' => '',
            'contact[email]' => 'pas-un-email',
            'contact[subject]' => 'general',
            'contact[message]' => '',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function test_missing_subject_does_not_crash(): void
    {
        $client = static::createClient();
        $form = $this->filledForm($client);
        $form->remove('contact[subject]');
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function test_the_honeypot_has_no_guessable_name(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        // « website » figure dans toutes les listes de bots : il ne doit plus exister.
        self::assertCount(0, $crawler->filter('input[name="contact[website]"]'));
        $trap = $crawler->filter('.field-extra input');
        self::assertCount(1, $trap);
        self::assertMatchesRegularExpression('~^contact\[site_[0-9a-f]{8}\]$~', (string) $trap->attr('name'));
        self::assertSame('-1', $trap->attr('tabindex'));
        self::assertSame('true', $crawler->filter('.field-extra')->attr('aria-hidden'));
    }

    public function test_a_filled_honeypot_sends_nothing_but_looks_like_a_success(): void
    {
        $client = static::createClient();
        $form = $this->filledForm($client);
        $form[$this->honeypotField($form)] = 'http://spam.example';
        $client->submit($form);

        // Aucun e-mail, mais une réponse indiscernable d'un envoi réussi :
        // le bot n'apprend rien du piège.
        self::assertEmailCount(0);
        self::assertResponseRedirects('/contact');

        $client->followRedirect();
        self::assertSelectorTextContains('.form-success', 'Merci');
    }

    public function test_a_blind_post_is_already_stopped_by_the_csrf_token(): void
    {
        $client = static::createClient();
        $client->request('POST', '/contact', ['contact' => [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'subject' => 'general',
            'message' => 'Achetez nos backlinks pas chers !',
        ]]);

        self::assertEmailCount(0);
        self::assertResponseStatusCodeSame(422);
    }

    public function test_a_submission_stripped_of_its_signed_token_sends_nothing(): void
    {
        $client = static::createClient();
        // Le bot a bien chargé la page (il a donc un jeton CSRF valide) mais
        // n'a pas renvoyé le jeton horodaté : la preuve de rendu manque.
        $form = $this->filledForm($client);
        $form->remove('contact[ts]');
        $client->submit($form);

        self::assertEmailCount(0);
        self::assertResponseRedirects('/contact');
    }

    public function test_a_submission_returned_instantly_sends_nothing(): void
    {
        $client = static::createClient();
        // Jeton non antidaté : le formulaire revient dans la seconde, comme un bot.
        $client->submit($this->filledForm($client, self::LEGITIMATE, secondsAgo: 0));

        self::assertEmailCount(0);
        self::assertResponseRedirects('/contact');
    }

    public function test_a_stale_page_asks_the_visitor_to_resend(): void
    {
        $client = static::createClient();
        $client->submit($this->filledForm($client, self::LEGITIMATE, secondsAgo: 86_400));

        // Un onglet oublié n'est pas du spam : on le dit, plutôt que de faire
        // croire à un envoi qui n'a pas eu lieu.
        self::assertEmailCount(0);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.form-error');
    }

    public function test_a_spammy_message_sends_nothing(): void
    {
        $client = static::createClient();
        $client->submit($this->filledForm($client, [
            ...self::LEGITIMATE,
            'contact[message]' => 'SEO backlinks https://a.example https://b.example https://c.example',
        ]));

        self::assertEmailCount(0);
        self::assertResponseRedirects('/contact');
    }

    public function test_a_flood_from_the_same_address_is_throttled(): void
    {
        // Un visiteur par requête, comme en vrai : le quota, lui, se souvient
        // de l'adresse IP d'une visite à l'autre.
        for ($i = 0; $i < 5; ++$i) {
            self::ensureKernelShutdown();
            $client = static::createClient();
            $client->submit($this->filledForm($client));
            self::assertEmailCount(1, message: "Envoi n°{$i} refusé alors qu'il est dans le quota.");
        }

        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->submit($this->filledForm($client));

        // Le sixième est écarté en silence : même réponse, aucun e-mail.
        self::assertEmailCount(0);
        self::assertResponseRedirects('/contact');
    }

    public function test_mailer_failure_shows_a_friendly_error_instead_of_a_500(): void
    {
        $client = static::createClient();
        // Le kernel est rebooté entre chaque requête : on le désactive pour que le
        // mailer remplacé (double qui échoue) survive du GET au POST.
        $client->disableReboot();
        // SendContactMessage dépend du service concret : c'est lui qu'on remplace.
        static::getContainer()->set(SymfonyContactMailer::class, new class implements ContactMailerInterface {
            public function send(ContactMessage $message): void
            {
                throw new ContactMailerException('SMTP indisponible');
            }
        });

        $client->submit($this->filledForm($client));

        // Pas de 500 : la page se ré-affiche avec un message d'erreur clair.
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorExists('.form-error');
        self::assertSelectorNotExists('.form-success');
    }
}
