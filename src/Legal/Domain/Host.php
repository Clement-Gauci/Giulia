<?php
namespace App\Legal\Domain;

final readonly class Host
{
    public function __construct(
        private string $name,
        private string $address,
        private string $phone,
        private ?string $note,
    ) {}

    public function name(): string { return $this->name; }
    public function address(): string { return $this->address; }
    public function phone(): string { return $this->phone; }
    public function note(): ?string { return $this->note; }
}
