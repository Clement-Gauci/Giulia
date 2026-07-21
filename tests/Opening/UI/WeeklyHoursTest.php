<?php
namespace App\Tests\Opening\UI;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Opening\UI\OpeningStatusExtension;
use App\Shared\Domain\Weekday;
use App\Tests\Opening\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class WeeklyHoursTest extends TestCase
{
    public function test_weekly_hours_labels_days_and_marks_today(): void
    {
        $repo = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([
                    Weekday::Tuesday->value => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1290)],
                ]);
            }
        };
        // 2026-07-20 = lundi
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-20 12:00', new \DateTimeZone('Europe/Paris')));
        $rows = (new OpeningStatusExtension($repo, $clock))->weeklyHours();

        self::assertSame('Lundi', $rows[0]['day']);
        self::assertTrue($rows[0]['today']);
        self::assertSame('Fermé', $rows[0]['hours']);
        self::assertSame('Mardi', $rows[1]['day']);
        self::assertSame('10h – 14h30 · 17h – 21h30', $rows[1]['hours']);
    }
}
