<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\ClosurePeriod;
use PHPUnit\Framework\TestCase;

final class ClosurePeriodTest extends TestCase
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

    public function test_covers_a_date_inside_the_range(): void
    {
        $period = $this->period('2026-07-27', '2026-08-13');
        self::assertTrue($period->covers($this->at('2026-08-05 12:00')));
    }

    public function test_covers_the_boundary_days_whatever_the_time(): void
    {
        $period = $this->period('2026-07-27', '2026-08-13');
        self::assertTrue($period->covers($this->at('2026-07-27 00:00')));
        self::assertTrue($period->covers($this->at('2026-08-13 23:30')));
    }

    public function test_does_not_cover_dates_outside_the_range(): void
    {
        $period = $this->period('2026-07-27', '2026-08-13');
        self::assertFalse($period->covers($this->at('2026-07-26 23:59')));
        self::assertFalse($period->covers($this->at('2026-08-14 00:01')));
    }

    public function test_reopening_label_is_the_day_after_until(): void
    {
        $period = $this->period('2026-07-27', '2026-08-13');
        self::assertSame('vendredi 14 août', $period->reopeningLabel());
    }

    public function test_rejects_a_range_that_ends_before_it_starts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->period('2026-08-13', '2026-07-27');
    }
}
