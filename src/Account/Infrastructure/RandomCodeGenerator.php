<?php
namespace App\Account\Infrastructure;

use App\Account\Domain\LoginCodeGenerator;

final readonly class RandomCodeGenerator implements LoginCodeGenerator
{
    public function generate(): string
    {
        // `random_int` et non `rand` : le code ouvre un accès, il doit être
        // imprévisible. Le formatage garde les zéros de tête, sans quoi un
        // tirage comme 42 donnerait un code à deux chiffres.
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
