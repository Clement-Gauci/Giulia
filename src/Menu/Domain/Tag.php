<?php
namespace App\Menu\Domain;

enum Tag: string
{
    case Vegetarian = 'veg';
    case Spicy = 'spicy';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Végétarienne',
            self::Spicy => 'Piquante',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vegetarian => '🌱',
            self::Spicy => '🌶️',
        };
    }
}
