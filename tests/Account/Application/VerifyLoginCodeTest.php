<?php
namespace App\Tests\Account\Application;

use App\Account\Application\VerifyLoginCode;
use App\Account\Domain\Account;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginCode;
use App\Account\Domain\LoginPolicy;
use App\Account\Domain\NoActiveCode;
use App\Account\Domain\WrongCode;
use App\Tests\Account\Support\InMemoryAccountRepository;
use App\Tests\Account\Support\InMemoryLoginAttemptRepository;
use App\Tests\Account\Support\InMemoryLoginCodeRepository;
use App\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class VerifyLoginCodeTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';
    private const IP = '203.0.113.7';
    private const CODE = '204815';

    private \DateTimeImmutable $now;
    private InMemoryAccountRepository $accounts;
    private InMemoryLoginCodeRepository $codes;
    private InMemoryLoginAttemptRepository $attempts;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-09-09 10:00:00');
        $this->accounts = new InMemoryAccountRepository(
            Account::create(self::EMAIL, 'Clément', $this->now->modify('-1 day')),
        );
        $this->codes = new InMemoryLoginCodeRepository();
        $this->codes->save(LoginCode::issue(self::EMAIL, self::CODE, $this->now, LoginPolicy::CODE_LIFETIME_MINUTES));
        $this->attempts = new InMemoryLoginAttemptRepository();
    }

    private function verify(?\DateTimeImmutable $at = null): VerifyLoginCode
    {
        return new VerifyLoginCode(
            $this->accounts,
            $this->codes,
            $this->attempts,
            new FrozenClock($at ?? $this->now),
        );
    }

    public function test_a_valid_code_returns_the_account_and_records_the_connection(): void
    {
        $at = $this->now->modify('+2 minutes');

        $account = ($this->verify($at))(self::EMAIL, self::CODE, self::IP);

        self::assertSame(self::EMAIL, $account->email());
        self::assertSame('2026-09-09 10:02:00', $account->lastLoginAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-09 10:02:00', $this->accounts->findByEmail(self::EMAIL)?->lastLoginAt()?->format('Y-m-d H:i:s'));
    }

    public function test_a_used_code_cannot_serve_twice(): void
    {
        ($this->verify())(self::EMAIL, self::CODE, self::IP);

        $this->expectException(NoActiveCode::class);
        ($this->verify())(self::EMAIL, self::CODE, self::IP);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->expectException(NoActiveCode::class);
        ($this->verify($this->now->modify('+11 minutes')))(self::EMAIL, self::CODE, self::IP);
    }

    public function test_a_missing_code_is_refused(): void
    {
        $this->codes->deleteFor(self::EMAIL);

        $this->expectException(NoActiveCode::class);
        ($this->verify())(self::EMAIL, self::CODE, self::IP);
    }

    public function test_a_wrong_code_says_how_many_tries_remain_and_is_counted(): void
    {
        try {
            ($this->verify())(self::EMAIL, '000000', self::IP);
            self::fail('Un code erroné devait être refusé.');
        } catch (WrongCode $e) {
            self::assertSame(LoginPolicy::MAX_CODE_TRIES - 1, $e->triesLeft());
        }

        self::assertSame(1, $this->codes->findFor(self::EMAIL)?->tries());
    }

    public function test_the_right_code_still_works_after_a_wrong_one(): void
    {
        try {
            ($this->verify())(self::EMAIL, '000000', self::IP);
        } catch (WrongCode) {
        }

        self::assertSame(self::EMAIL, ($this->verify())(self::EMAIL, self::CODE, self::IP)->email());
    }

    public function test_the_third_wrong_code_blocks_the_address(): void
    {
        for ($i = 1; $i <= LoginPolicy::MAX_CODE_TRIES - 1; $i++) {
            try {
                ($this->verify())(self::EMAIL, '000000', self::IP);
            } catch (WrongCode) {
            }
        }

        $this->expectException(LoginBlocked::class);
        ($this->verify())(self::EMAIL, '000000', self::IP);
    }

    public function test_a_blocked_address_is_refused_before_the_code_is_even_read(): void
    {
        for ($i = 0; $i < LoginPolicy::MAX_CODE_TRIES; $i++) {
            $this->attempts->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-1 minute')));
        }

        $this->expectException(LoginBlocked::class);
        ($this->verify())(self::EMAIL, self::CODE, self::IP);
    }

    public function test_an_account_revoked_between_the_send_and_the_check_cannot_enter(): void
    {
        $this->accounts->save(new Account(self::EMAIL, 'Clément', false, $this->now->modify('-1 day')));

        $this->expectException(NoActiveCode::class);
        ($this->verify())(self::EMAIL, self::CODE, self::IP);
    }
}
