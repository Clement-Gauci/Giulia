<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Weekday;

final readonly class ClosurePeriod
{
    private const MONTHS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

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
        return $weekday . ' ' . $reopening->format('j') . ' ' . self::MONTHS[(int) $reopening->format('n')];
    }
}
