<?php
namespace App\Opening\Infrastructure;

use App\Opening\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }
}
