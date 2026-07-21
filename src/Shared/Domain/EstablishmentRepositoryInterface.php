<?php
namespace App\Shared\Domain;

interface EstablishmentRepositoryInterface
{
    public function get(): Establishment;
}
