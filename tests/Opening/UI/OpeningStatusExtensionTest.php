<?php
namespace App\Tests\Opening\UI;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Opening\UI\OpeningStatusExtension;
use App\Shared\Domain\Weekday;
use App\Tests\Opening\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class OpeningStatusExtensionTest extends TestCase
{
    public function test_status_uses_injected_schedule_and_clock(): void
    {
        $repo = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([Weekday::Tuesday->value => [TimeRange::fromMinutes(600, 870)]]);
            }
        };
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-21 12:00', new \DateTimeZone('Europe/Paris')));
        $extension = new OpeningStatusExtension($repo, $clock);

        self::assertTrue($extension->status()->isOpen());
    }
}
