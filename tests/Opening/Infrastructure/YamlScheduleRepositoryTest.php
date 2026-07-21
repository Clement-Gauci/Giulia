<?php
namespace App\Tests\Opening\Infrastructure;

use App\Opening\Infrastructure\YamlScheduleRepository;
use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class YamlScheduleRepositoryTest extends TestCase
{
    private function repo(): YamlScheduleRepository
    {
        return new YamlScheduleRepository(__DIR__ . '/fixtures/hours.yaml');
    }

    public function test_monday_is_closed(): void
    {
        self::assertSame([], $this->repo()->schedule()->rangesFor(Weekday::Monday));
    }

    public function test_tuesday_has_two_ranges_parsed_to_minutes(): void
    {
        $ranges = $this->repo()->schedule()->rangesFor(Weekday::Tuesday);
        self::assertCount(2, $ranges);
        self::assertSame(600, $ranges[0]->openMinute());
        self::assertSame(870, $ranges[0]->closeMinute());
        self::assertSame(1020, $ranges[1]->openMinute());
        self::assertSame(1290, $ranges[1]->closeMinute());
    }

    public function test_friday_closes_later(): void
    {
        $ranges = $this->repo()->schedule()->rangesFor(Weekday::Friday);
        self::assertSame(1320, $ranges[1]->closeMinute());
    }
}
