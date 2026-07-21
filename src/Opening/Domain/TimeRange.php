<?php
namespace App\Opening\Domain;

final readonly class TimeRange
{
    private function __construct(public int $openMinute, public int $closeMinute)
    {
        if ($openMinute < 0 || $closeMinute > 1440 || $openMinute >= $closeMinute) {
            throw new \InvalidArgumentException('Créneau horaire invalide.');
        }
    }

    public static function fromMinutes(int $open, int $close): self
    {
        return new self($open, $close);
    }

    public function openMinute(): int
    {
        return $this->openMinute;
    }

    public function closeMinute(): int
    {
        return $this->closeMinute;
    }

    public function contains(int $minute): bool
    {
        return $minute >= $this->openMinute && $minute < $this->closeMinute;
    }

    public function openLabel(): string
    {
        return self::formatMinute($this->openMinute);
    }

    public function closeLabel(): string
    {
        return self::formatMinute($this->closeMinute);
    }

    public static function formatMinute(int $minute): string
    {
        $h = intdiv($minute, 60);
        $m = $minute % 60;
        return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}
