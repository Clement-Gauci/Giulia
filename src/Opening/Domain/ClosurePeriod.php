<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Month;
use App\Shared\Domain\Weekday;

final readonly class ClosurePeriod
{
    public function __construct(
        private \DateTimeImmutable $from,
        private \DateTimeImmutable $until,
    ) {
        if ($this->from->format('Y-m-d') > $this->until->format('Y-m-d')) {
            throw new \InvalidArgumentException('Période de fermeture invalide : la fin précède le début.');
        }
    }

    public function covers(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');
        return $day >= $this->from->format('Y-m-d') && $day <= $this->until->format('Y-m-d');
    }

    public function reopeningLabel(): string
    {
        $reopening = $this->until->modify('+1 day');
        $weekday = strtolower(Weekday::fromDate($reopening)->label());
        return $weekday . ' ' . $reopening->format('j') . ' ' . Month::fromDate($reopening)->label();
    }
}
