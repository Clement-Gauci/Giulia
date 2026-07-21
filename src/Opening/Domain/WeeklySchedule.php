<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Weekday;

final readonly class WeeklySchedule
{
    /** @var array<int, TimeRange[]> */
    private array $ranges;

    /** @param array<int, TimeRange[]> $ranges indexé par Weekday::value */
    public function __construct(array $ranges)
    {
        $normalized = [];
        foreach ($ranges as $day => $dayRanges) {
            usort($dayRanges, static fn (TimeRange $a, TimeRange $b) => $a->openMinute() <=> $b->openMinute());
            $normalized[$day] = array_values($dayRanges);
        }
        $this->ranges = $normalized;
    }

    /** @return TimeRange[] */
    public function rangesFor(Weekday $day): array
    {
        return $this->ranges[$day->value] ?? [];
    }
}
