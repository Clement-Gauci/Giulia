<?php
namespace App\Tests\Opening\Infrastructure;

use App\Opening\Infrastructure\YamlClosureRepository;
use PHPUnit\Framework\TestCase;

final class YamlClosureRepositoryTest extends TestCase
{
    private function at(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Paris'));
    }

    public function test_parses_a_closure_period(): void
    {
        $repo = new YamlClosureRepository(__DIR__ . '/fixtures/closures.yaml');
        $closure = $repo->closures()->activeOn($this->at('2026-08-05 12:00'));
        self::assertNotNull($closure);
        self::assertSame('vendredi 14 août', $closure->reopeningLabel());
    }

    public function test_dates_outside_any_period_are_not_closed(): void
    {
        $repo = new YamlClosureRepository(__DIR__ . '/fixtures/closures.yaml');
        self::assertNull($repo->closures()->activeOn($this->at('2026-09-01 12:00')));
    }

    public function test_empty_file_yields_no_closure(): void
    {
        $repo = new YamlClosureRepository(__DIR__ . '/fixtures/closures_empty.yaml');
        self::assertNull($repo->closures()->activeOn($this->at('2026-08-05 12:00')));
    }
}
