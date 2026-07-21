<?php
namespace App\Menu\Domain;

interface MenuRepositoryInterface
{
    /** @return Category[] */
    public function categories(): array;

    public function findBySlug(string $slug): ?Pizza;

    public function signature(): ?Pizza;
}
