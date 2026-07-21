<?php
namespace App\Menu\Domain;

interface SpecialRepositoryInterface
{
    public function current(): ?MonthlySpecial;
}
