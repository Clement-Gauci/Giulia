<?php
namespace App\Tests\Support;

use App\Shared\Domain\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
