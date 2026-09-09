<?php
namespace App\Account\Domain;

/**
 * Adresse non enregistrée — ou compte désactivé, indistinctement : rien ne doit
 * laisser deviner qu'un accès a existé.
 */
final class UnknownEmail extends LoginRefused
{
    private function __construct(private readonly int $triesLeft)
    {
        parent::__construct('Cette adresse n\'est pas enregistrée.');
    }

    public static function withTriesLeft(int $triesLeft): self
    {
        return new self($triesLeft);
    }

    public function triesLeft(): int
    {
        return $this->triesLeft;
    }
}
