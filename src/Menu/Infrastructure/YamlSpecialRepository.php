<?php
namespace App\Menu\Infrastructure;

use App\Menu\Domain\MonthlySpecial;
use App\Menu\Domain\SpecialRepositoryInterface;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use Symfony\Component\Yaml\Yaml;

final class YamlSpecialRepository implements SpecialRepositoryInterface
{
    public function __construct(private string $file) {}

    public function current(): ?MonthlySpecial
    {
        $d = Yaml::parseFile($this->file);

        if (!($d['active'] ?? false)) {
            return null;
        }

        return new MonthlySpecial(
            $d['name'],
            $d['period'] ?? null,
            $d['pitch'] ?? null,
            $d['ingredients'] ?? [],
            Money::fromCents((int) $d['price']),
            array_map(static fn (string $t) => Tag::from($t), $d['tags'] ?? []),
        );
    }
}
