<?php
namespace App\Tests\Opening\Support;

use App\Opening\Domain\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
