<?php
namespace App\Opening\Domain;

interface ClosureRepositoryInterface
{
    public function closures(): ClosureCalendar;
}
