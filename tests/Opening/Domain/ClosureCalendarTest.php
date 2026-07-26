<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\ClosureCalendar;
use App\Opening\Domain\ClosurePeriod;
use PHPUnit\Framework\TestCase;

final class ClosureCalendarTest extends TestCase
{
    private function period(string $from, string $until): ClosurePeriod
    {
        return new ClosurePeriod(
            new \DateTimeImmutable($from, new \DateTimeZone('Europe/Paris')),
            new \DateTimeImmutable($until, new \DateTimeZone('Europe/Paris')),
        );
    }

    private function at(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Paris'));
    }

    public function test_returns_the_period_covering_the_date(): void
    {
        $summer = $this->period('2026-07-27', '2026-08-13');
        $calendar = new ClosureCalendar([$summer]);
        self::assertSame($summer, $calendar->activeOn($this->at('2026-08-05 12:00')));
    }

    public function test_returns_null_when_no_period_covers_the_date(): void
    {
        $calendar = new ClosureCalendar([$this->period('2026-07-27', '2026-08-13')]);
        self::assertNull($calendar->activeOn($this->at('2026-09-01 12:00')));
    }

    public function test_empty_calendar_never_closes(): void
    {
        $calendar = new ClosureCalendar([]);
        self::assertNull($calendar->activeOn($this->at('2026-08-05 12:00')));
    }
}
