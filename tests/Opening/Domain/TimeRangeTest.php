<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\TimeRange;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    public function test_contains_is_open_inclusive_close_exclusive(): void
    {
        $range = TimeRange::fromMinutes(600, 870); // 10h00 - 14h30
        self::assertTrue($range->contains(600));
        self::assertTrue($range->contains(869));
        self::assertFalse($range->contains(870));
        self::assertFalse($range->contains(599));
    }

    public function test_labels(): void
    {
        $range = TimeRange::fromMinutes(600, 870);
        self::assertSame('10h', $range->openLabel());
        self::assertSame('14h30', $range->closeLabel());
    }

    public function test_rejects_invalid_bounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeRange::fromMinutes(870, 600);
    }
}
