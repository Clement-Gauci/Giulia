<?php
namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class WeekdayTest extends TestCase
{
    public function test_labels_are_french(): void
    {
        self::assertSame('Lundi', Weekday::Monday->label());
        self::assertSame('Dimanche', Weekday::Sunday->label());
    }

    public function test_from_date_uses_iso_day(): void
    {
        // 2026-07-20 est un lundi
        $monday = new \DateTimeImmutable('2026-07-20');
        self::assertSame(Weekday::Monday, Weekday::fromDate($monday));
        // 2026-07-26 est un dimanche
        $sunday = new \DateTimeImmutable('2026-07-26');
        self::assertSame(Weekday::Sunday, Weekday::fromDate($sunday));
    }
}
