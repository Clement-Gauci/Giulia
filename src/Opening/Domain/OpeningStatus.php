<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Weekday;

final readonly class OpeningStatus
{
    private function __construct(
        private bool $open,
        private string $label,
        private string $detail,
    ) {}

    public static function compute(WeeklySchedule $schedule, \DateTimeImmutable $now): self
    {
        $day = Weekday::fromDate($now);
        $minute = ((int) $now->format('G')) * 60 + (int) $now->format('i');

        // 1) Ouvert dans un créneau du jour ?
        foreach ($schedule->rangesFor($day) as $range) {
            if ($range->contains($minute)) {
                return new self(true, 'Ouvert', 'Ouvert jusqu’à ' . $range->closeLabel());
            }
        }

        // 2) Un créneau plus tard aujourd’hui ?
        foreach ($schedule->rangesFor($day) as $range) {
            if ($minute < $range->openMinute()) {
                return new self(false, 'Fermé', 'Ouvre aujourd’hui à ' . $range->openLabel());
            }
        }

        // 3) Prochain jour ouvré.
        for ($i = 1; $i <= 7; $i++) {
            $nextDay = Weekday::from((($day->value - 1 + $i) % 7) + 1);
            $ranges = $schedule->rangesFor($nextDay);
            if ($ranges !== []) {
                $when = $i === 1 ? 'demain' : strtolower($nextDay->label());
                return new self(false, 'Fermé', 'Ouvre ' . $when . " à " . $ranges[0]->openLabel());
            }
        }

        return new self(false, 'Fermé', '');
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function detail(): string
    {
        return $this->detail;
    }
}
