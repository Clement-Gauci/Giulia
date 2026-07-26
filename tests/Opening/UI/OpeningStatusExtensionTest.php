<?php
namespace App\Tests\Opening\UI;

use App\Opening\Domain\ClosureCalendar;
use App\Opening\Domain\ClosurePeriod;
use App\Opening\Domain\ClosureRepositoryInterface;
use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Opening\UI\OpeningStatusExtension;
use App\Shared\Domain\Weekday;
use App\Tests\Opening\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class OpeningStatusExtensionTest extends TestCase
{
    private function scheduleRepo(): ScheduleRepositoryInterface
    {
        return new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([Weekday::Tuesday->value => [TimeRange::fromMinutes(600, 870)]]);
            }
        };
    }

    private function closureRepo(ClosureCalendar $calendar): ClosureRepositoryInterface
    {
        return new class($calendar) implements ClosureRepositoryInterface {
            public function __construct(private ClosureCalendar $calendar) {}

            public function closures(): ClosureCalendar
            {
                return $this->calendar;
            }
        };
    }

    private function at(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Paris'));
    }

    public function test_status_uses_injected_schedule_and_clock(): void
    {
        $clock = new FrozenClock($this->at('2026-07-21 12:00'));
        $extension = new OpeningStatusExtension($this->scheduleRepo(), $clock, $this->closureRepo(new ClosureCalendar([])));

        self::assertTrue($extension->status()->isOpen());
    }

    public function test_status_reflects_an_active_closure(): void
    {
        $clock = new FrozenClock($this->at('2026-07-28 12:00')); // mardi, mais en congés
        $calendar = new ClosureCalendar([
            new ClosurePeriod($this->at('2026-07-27 00:00'), $this->at('2026-08-13 00:00')),
        ]);
        $extension = new OpeningStatusExtension($this->scheduleRepo(), $clock, $this->closureRepo($calendar));

        self::assertSame('En congés', $extension->status()->label());
    }
}
