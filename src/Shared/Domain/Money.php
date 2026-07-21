<?php
namespace App\Shared\Domain;

final readonly class Money
{
    private function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Le montant ne peut pas être négatif.');
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function format(): string
    {
        $euros = number_format($this->cents / 100, 2, ',', "\u{00A0}");
        return $euros . "\u{00A0}€";
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
