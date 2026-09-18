<?php
namespace App\Shared\Domain;

enum Month: int
{
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;
    case July = 7;
    case August = 8;
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;

    /** En minuscules : un mois s'écrit toujours au fil d'une phrase. */
    public function label(): string
    {
        return match ($this) {
            self::January => 'janvier',
            self::February => 'février',
            self::March => 'mars',
            self::April => 'avril',
            self::May => 'mai',
            self::June => 'juin',
            self::July => 'juillet',
            self::August => 'août',
            self::September => 'septembre',
            self::October => 'octobre',
            self::November => 'novembre',
            self::December => 'décembre',
        };
    }

    public static function fromDate(\DateTimeImmutable $date): self
    {
        return self::from((int) $date->format('n'));
    }
}
