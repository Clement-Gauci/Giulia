<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class WeeklyScheduleTest extends TestCase
{
    public function test_returns_ranges_sorted_by_open(): void
    {
        $schedule = new WeeklySchedule([
            Weekday::Tuesday->value => [
                TimeRange::fromMinutes(1020, 1290),
                TimeRange::fromMinutes(600, 870),
            ],
        ]);
        $ranges = $schedule->rangesFor(Weekday::Tuesday);
        self::assertCount(2, $ranges);
        self::assertSame(600, $ranges[0]->openMinute());
        self::assertSame(1020, $ranges[1]->openMinute());
    }

    public function test_empty_day_returns_no_ranges(): void
    {
        $schedule = new WeeklySchedule([]);
        self::assertSame([], $schedule->rangesFor(Weekday::Monday));
    }
}
