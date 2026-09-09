<?php
namespace App\Account\Domain;

final class WrongCode extends LoginRefused
{
    private function __construct(private readonly int $triesLeft)
    {
        parent::__construct('Code incorrect.');
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
