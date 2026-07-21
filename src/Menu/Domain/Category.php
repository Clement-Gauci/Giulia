<?php
namespace App\Menu\Domain;

final readonly class Category
{
    /** @param Pizza[] $pizzas */
    public function __construct(
        private string $kicker,
        private string $label,
        private array $pizzas,
    ) {}

    public function kicker(): string { return $this->kicker; }
    public function label(): string { return $this->label; }
    /** @return Pizza[] */
    public function pizzas(): array { return $this->pizzas; }
}
