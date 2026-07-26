<?php
namespace App\Opening\Infrastructure;

use App\Opening\Domain\ClosureCalendar;
use App\Opening\Domain\ClosurePeriod;
use App\Opening\Domain\ClosureRepositoryInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlClosureRepository implements ClosureRepositoryInterface
{
    public function __construct(private string $file) {}

    public function closures(): ClosureCalendar
    {
        $rows = Yaml::parseFile($this->file) ?? [];
        $periods = array_map(
            static fn (array $row) => new ClosurePeriod(
                new \DateTimeImmutable($row['from'], new \DateTimeZone('Europe/Paris')),
                new \DateTimeImmutable($row['until'], new \DateTimeZone('Europe/Paris')),
            ),
            $rows,
        );
        return new ClosureCalendar($periods);
    }
}
