<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactPageTest extends WebTestCase
{
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
        $client->request('GET', '/contact');
        $client->submitForm('Envoyer', [
            'contact[name]' => 'Marie Dupont',
            'contact[email]' => 'marie@example.fr',
            'contact[phone]' => '0612345678',
            'contact[subject]' => 'cc',
            'contact[message]' => 'Bonjour, une question sur le click & collect.',
        ]);

        self::assertEmailCount(1);
        self::assertResponseRedirects('/contact');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.form-success');
        self::assertSelectorTextContains('.form-success', 'Merci');
    }

    public function test_invalid_submission_shows_errors(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        $client->submitForm('Envoyer', [
            'contact[name]' => '',
            'contact[email]' => 'pas-un-email',
            'contact[subject]' => 'general',
            'contact[message]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function test_missing_subject_does_not_crash(): void
    {
        $client = static::createClient();
        $client->request('POST', '/contact', ['contact' => [
            'name' => 'Marie',
            'email' => 'marie@example.fr',
            'message' => 'Bonjour, un message de test.',
            // 'subject' intentionally omitted
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }
}
