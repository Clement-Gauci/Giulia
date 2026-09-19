<?php
namespace App\Tests\Account\Domain;

use App\Account\Domain\LoginCode;
use PHPUnit\Framework\TestCase;

final class LoginCodeTest extends TestCase
{
    private const EMAIL = 'hello@giulia-pizza-gorges.fr';

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-09 10:00:00');
    }

    private function code(): LoginCode
    {
        return LoginCode::issue(self::EMAIL, '204815', $this->now(), 10);
    }

    public function test_a_freshly_issued_code_is_usable(): void
    {
        $code = $this->code();

        self::assertTrue($code->matches('204815'));
        self::assertFalse($code->isExpired($this->now()));
        self::assertFalse($code->isConsumed());
        self::assertSame(0, $code->tries());
    }

    public function test_the_clear_code_is_never_stored(): void
    {
        self::assertStringNotContainsString('204815', $this->code()->codeHash());
    }

    public function test_another_value_does_not_match(): void
    {
        self::assertFalse($this->code()->matches('000000'));
    }

    public function test_the_code_expires_at_the_end_of_its_lifetime(): void
    {
        $code = $this->code();

        self::assertFalse($code->isExpired($this->now()->modify('+10 minutes')));
        self::assertTrue($code->isExpired($this->now()->modify('+10 minutes 1 second')));
    }

    public function test_a_failed_try_is_counted_without_touching_the_original(): void
    {
        $code = $this->code();

        self::assertSame(1, $code->withFailedTry()->tries());
        self::assertSame(0, $code->tries());
    }

    public function test_a_consumed_code_is_marked_as_such(): void
    {
        $consumed = $this->code()->consume($this->now()->modify('+1 minute'));

        self::assertTrue($consumed->isConsumed());
        self::assertSame('2026-09-09 10:01:00', $consumed->consumedAt()->format('Y-m-d H:i:s'));
    }
}
