<?php
namespace App\Tests\Contact\AntiSpam;

use App\Contact\AntiSpam\Decision;
use App\Contact\AntiSpam\FormSignature;
use App\Contact\AntiSpam\SpamScorer;
use App\Contact\AntiSpam\SubmissionGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SubmissionGuardTest extends TestCase
{
    private const int RENDERED_AT = 1_758_240_000;

    private FormSignature $signature;
    private SubmissionGuard $guard;

    protected function setUp(): void
    {
        $this->signature = new FormSignature('un-secret-de-test');
        $this->guard = new SubmissionGuard(
            $this->signature,
            new SpamScorer(),
            new RateLimiterFactory(
                ['id' => 'contact_form', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 hour'],
                new InMemoryStorage(),
            ),
        );
    }

    /** @param array<string, string> $overrides */
    private function inspect(array $overrides = []): \App\Contact\AntiSpam\SubmissionVerdict
    {
        $fields = array_merge([
            'token' => $this->signature->issue(self::RENDERED_AT),
            'honeypot' => '',
            'ip' => '203.0.113.7',
            'name' => 'Marie Dupont',
            'email' => 'marie@example.fr',
            'message' => 'Bonjour, avez-vous une pizza sans gluten ?',
        ], $overrides);

        return $this->guard->inspect(
            $fields['token'],
            $fields['honeypot'],
            $fields['ip'],
            $fields['name'],
            $fields['email'],
            $fields['message'],
            self::RENDERED_AT + 30,
        );
    }

    public function test_an_ordinary_message_is_accepted(): void
    {
        self::assertSame(Decision::Accept, $this->inspect()->decision);
    }

    public function test_a_submission_without_a_token_is_silently_dropped(): void
    {
        $verdict = $this->inspect(['token' => '']);

        self::assertSame(Decision::Silence, $verdict->decision);
        self::assertSame('token_invalid', $verdict->reason);
    }

    public function test_a_submission_faster_than_a_human_is_silently_dropped(): void
    {
        $verdict = $this->guard->inspect(
            $this->signature->issue(self::RENDERED_AT),
            '',
            '203.0.113.7',
            'Marie',
            'marie@example.fr',
            'Bonjour',
            self::RENDERED_AT + 1,
        );

        self::assertSame(Decision::Silence, $verdict->decision);
        self::assertSame('too_fast', $verdict->reason);
    }

    public function test_a_stale_page_asks_the_visitor_to_try_again(): void
    {
        $verdict = $this->guard->inspect(
            $this->signature->issue(self::RENDERED_AT),
            '',
            '203.0.113.7',
            'Marie',
            'marie@example.fr',
            'Bonjour, une question.',
            self::RENDERED_AT + 86_400,
        );

        // Un vrai client a pu laisser l'onglet ouvert : on ne fait pas semblant
        // d'avoir envoyé son message, on lui demande de renvoyer.
        self::assertSame(Decision::Retry, $verdict->decision);
    }

    public function test_a_filled_honeypot_is_silently_dropped(): void
    {
        $verdict = $this->inspect(['honeypot' => 'http://spam.example']);

        self::assertSame(Decision::Silence, $verdict->decision);
        self::assertSame('honeypot', $verdict->reason);
    }

    public function test_a_message_above_the_spam_threshold_is_silently_dropped(): void
    {
        $verdict = $this->inspect(['message' => 'SEO backlinks: https://a.example https://b.example https://c.example']);

        self::assertSame(Decision::Silence, $verdict->decision);
        self::assertStringStartsWith('score:', $verdict->reason);
    }

    public function test_the_fourth_submission_from_the_same_ip_within_the_hour_is_dropped(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            self::assertSame(Decision::Accept, $this->inspect()->decision);
        }

        $verdict = $this->inspect();

        self::assertSame(Decision::Silence, $verdict->decision);
        self::assertSame('rate_limited', $verdict->reason);
    }

    public function test_another_visitor_keeps_their_own_quota(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->inspect();
        }

        self::assertSame(Decision::Accept, $this->inspect(['ip' => '198.51.100.4'])->decision);
    }

    public function test_a_rejected_submission_does_not_eat_the_quota(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->inspect(['honeypot' => 'rempli']);
        }

        self::assertSame(Decision::Accept, $this->inspect()->decision);
    }
}
