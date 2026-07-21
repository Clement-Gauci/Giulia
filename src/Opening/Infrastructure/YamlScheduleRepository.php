<?php
namespace App\Opening\Infrastructure;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Shared\Domain\Weekday;
use Symfony\Component\Yaml\Yaml;

final class YamlScheduleRepository implements ScheduleRepositoryInterface
{
    private const DAYS = [
        'monday' => Weekday::Monday,
        'tuesday' => Weekday::Tuesday,
        'wednesday' => Weekday::Wednesday,
        'thursday' => Weekday::Thursday,
        'friday' => Weekday::Friday,
        'saturday' => Weekday::Saturday,
        'sunday' => Weekday::Sunday,
    ];

    public function __construct(private string $file) {}

    public function schedule(): WeeklySchedule
    {
        $data = Yaml::parseFile($this->file);
        $ranges = [];
        foreach (self::DAYS as $key => $weekday) {
            $slots = $data[$key] ?? [];
            $ranges[$weekday->value] = array_map(
                static fn (array $slot) => TimeRange::fromMinutes(
                    self::toMinutes($slot['open']),
                    self::toMinutes($slot['close']),
                ),
                $slots,
            );
        }
        return new WeeklySchedule($ranges);
    }

    private static function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return $h * 60 + $m;
    }
}
