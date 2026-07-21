<?php
namespace App\Opening\UI;

use App\Opening\Domain\Clock;
use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Shared\Domain\Weekday;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OpeningStatusExtension extends AbstractExtension
{
    public function __construct(
        private ScheduleRepositoryInterface $schedule,
        private Clock $clock,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('opening_status', $this->status(...)),
            new TwigFunction('weekly_hours', $this->weeklyHours(...)),
        ];
    }

    public function status(): OpeningStatus
    {
        return OpeningStatus::compute($this->schedule->schedule(), $this->clock->now());
    }

    /** @return array<int, array{day: string, hours: string, today: bool}> */
    public function weeklyHours(): array
    {
        $schedule = $this->schedule->schedule();
        $today = Weekday::fromDate($this->clock->now());
        $rows = [];
        foreach (Weekday::cases() as $day) {
            $ranges = $schedule->rangesFor($day);
            $hours = $ranges === []
                ? 'Fermé'
                : implode(' · ', array_map(
                    static fn ($r) => $r->openLabel() . ' – ' . $r->closeLabel(),
                    $ranges,
                ));
            $rows[] = ['day' => $day->label(), 'hours' => $hours, 'today' => $day === $today];
        }
        return $rows;
    }
}
