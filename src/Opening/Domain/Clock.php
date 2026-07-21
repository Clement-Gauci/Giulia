<?php
namespace App\Opening\Domain;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
