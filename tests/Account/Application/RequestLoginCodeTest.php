<?php
namespace App\Tests\Account\Application;

use App\Account\Application\RequestLoginCode;
use App\Account\Domain\Account;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginPolicy;
use App\Account\Domain\ResendTooSoon;
use App\Account\Domain\SendLimitReached;
use App\Account\Domain\UnknownEmail;
use App\Tests\Account\Support\FixedCodeGenerator;
use App\Tests\Account\Support\InMemoryAccountRepository;
use App\Tests\Account\Support\InMemoryLoginAttemptRepository;
use App\Tests\Account\Support\InMemoryLoginCodeRepository;
use App\Tests\Account\Support\RecordingLoginCodeMailer;
use App\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class RequestLoginCodeTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';
    private const IP = '203.0.113.7';

    private \DateTimeImmutable $now;
    private InMemoryAccountRepository $accounts;
    private InMemoryLoginCodeRepository $codes;
    private InMemoryLoginAttemptRepository $attempts;
    private RecordingLoginCodeMailer $mailer;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-09-09 10:00:00');
        $this->accounts = new InMemoryAccountRepository(
            Account::create(self::EMAIL, 'Clément', $this->now),
        );
        $this->codes = new InMemoryLoginCodeRepository();
        $this->attempts = new InMemoryLoginAttemptRepository();
        $this->mailer = new RecordingLoginCodeMailer();
    }

    private function request(?\DateTimeImmutable $at = null): RequestLoginCode
    {
        return new RequestLoginCode(
            $this->accounts,
            $this->codes,
            $this->attempts,
            $this->mailer,
            new FixedCodeGenerator('204815'),
            new FrozenClock($at ?? $this->now),
        );
    }

    public function test_it_issues_a_code_and_mails_it(): void
    {
        $sent = ($this->request())(self::EMAIL, self::IP);

        self::assertSame('204815', $this->mailer->lastCode);
        self::assertSame(self::EMAIL, $this->mailer->lastAccount?->email());
        self::assertTrue($this->codes->findFor(self::EMAIL)?->matches('204815'));
        self::assertSame('2026-09-09 10:10:00', $sent->expiresAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-09 10:00:45', $sent->resendAvailableAt->format('Y-m-d H:i:s'));
        self::assertSame(3, $sent->sendsLeft);
    }

    public function test_an_unknown_address_is_told_how_many_tries_remain(): void
    {
        try {
            ($this->request())('inconnu@example.com', self::IP);
            self::fail('Une adresse inconnue devait être refusée.');
        } catch (UnknownEmail $e) {
            self::assertSame(LoginPolicy::MAX_UNKNOWN_EMAILS - 1, $e->triesLeft());
        }

        self::assertNull($this->mailer->lastCode);
    }

    public function test_an_inactive_account_behaves_like_an_unknown_address(): void
    {
        $this->accounts->save(new Account(self::EMAIL, 'Clément', false, $this->now));

        $this->expectException(UnknownEmail::class);
        ($this->request())(self::EMAIL, self::IP);
    }

    public function test_the_third_unknown_address_from_one_ip_blocks_it(): void
    {
        $request = $this->request();

        for ($i = 1; $i <= LoginPolicy::MAX_UNKNOWN_EMAILS - 1; $i++) {
            try {
                $request('inconnu' . $i . '@example.com', self::IP);
            } catch (UnknownEmail) {
            }
        }

        $this->expectException(LoginBlocked::class);
        $request('inconnu3@example.com', self::IP);
    }

    public function test_a_blocked_address_is_refused_before_anything_else(): void
    {
        for ($i = 0; $i < LoginPolicy::MAX_CODE_TRIES; $i++) {
            $this->attempts->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-1 minute')));
        }

        try {
            ($this->request())(self::EMAIL, self::IP);
            self::fail('Une adresse bloquée devait être refusée.');
        } catch (LoginBlocked $e) {
            self::assertSame(LoginPolicy::BLOCK_MINUTES * 60 - 60, $e->secondsLeft($this->now));
        }

        self::assertNull($this->mailer->lastCode);
    }

    public function test_a_new_code_cannot_be_asked_before_the_resend_delay(): void
    {
        ($this->request())(self::EMAIL, self::IP);

        try {
            ($this->request($this->now->modify('+30 seconds')))(self::EMAIL, self::IP);
            self::fail('Le renvoi devait être refusé avant 45 s.');
        } catch (ResendTooSoon $e) {
            self::assertSame(15, $e->secondsLeft($this->now->modify('+30 seconds')));
        }
    }

    public function test_the_send_cap_is_reached_after_one_send_and_three_resends(): void
    {
        $at = $this->now;
        for ($i = 0; $i < LoginPolicy::MAX_SENDS_PER_WINDOW; $i++) {
            ($this->request($at))(self::EMAIL, self::IP);
            $at = $at->modify('+1 minute');
        }

        $this->expectException(SendLimitReached::class);
        ($this->request($at))(self::EMAIL, self::IP);
    }
}
