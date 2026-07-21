<?php
namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_formats_in_french_with_non_breaking_space(): void
    {
        self::assertSame("11,90\u{00A0}€", Money::fromCents(1190)->format());
        self::assertSame("17,50\u{00A0}€", Money::fromCents(1750)->format());
    }

    public function test_keeps_cents(): void
    {
        self::assertSame(1190, Money::fromCents(1190)->cents());
    }

    public function test_equality(): void
    {
        self::assertTrue(Money::fromCents(1190)->equals(Money::fromCents(1190)));
        self::assertFalse(Money::fromCents(1190)->equals(Money::fromCents(1200)));
    }

    public function test_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromCents(-1);
    }
}
