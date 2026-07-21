<?php
namespace App\Contact\Domain;

enum Subject: string
{
    case GeneralQuestion = 'general';
    case ClickAndCollect = 'cc';
    case Event = 'event';
    case Allergy = 'allergy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GeneralQuestion => 'Une question générale',
            self::ClickAndCollect => 'Une commande click & collect',
            self::Event => 'Un événement / grande commande',
            self::Allergy => 'Une allergie ou un régime',
            self::Other => 'Autre',
        };
    }

    /** @return array<string,string> label => value */
    public static function choices(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->label()] = $case->value;
        }
        return $out;
    }
}
