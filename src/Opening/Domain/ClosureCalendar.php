<?php
namespace App\Opening\Domain;

final readonly class ClosureCalendar
{
    /** @var ClosurePeriod[] */
    private array $periods;

    /** @param ClosurePeriod[] $periods */
    public function __construct(array $periods)
    {
        $this->periods = array_values($periods);
    }

    public function activeOn(\DateTimeImmutable $date): ?ClosurePeriod
    {
        foreach ($this->periods as $period) {
            if ($period->covers($date)) {
                return $period;
            }
        }
        return null;
    }
}
