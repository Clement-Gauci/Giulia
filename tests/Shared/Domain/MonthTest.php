<?php
namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Month;
use PHPUnit\Framework\TestCase;

final class MonthTest extends TestCase
{
    public function test_it_names_the_month_in_french(): void
    {
        self::assertSame('septembre', Month::September->label());
        self::assertSame('février', Month::February->label());
        self::assertSame('août', Month::August->label());
    }

    public function test_it_reads_the_month_of_a_date(): void
    {
        self::assertSame(Month::December, Month::fromDate(new \DateTimeImmutable('2026-12-24')));
    }

    public function test_the_twelve_months_are_named(): void
    {
        foreach (Month::cases() as $month) {
            self::assertNotSame('', $month->label());
        }

        self::assertCount(12, Month::cases());
    }
}
