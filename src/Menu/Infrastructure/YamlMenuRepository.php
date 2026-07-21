<?php
namespace App\Menu\Infrastructure;

use App\Menu\Domain\Category;
use App\Menu\Domain\MenuRepositoryInterface;
use App\Menu\Domain\Pizza;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;

final class YamlMenuRepository implements MenuRepositoryInterface
{
    /** @var Category[]|null */
    private ?array $cache = null;

    public function __construct(private string $file) {}

    public function categories(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $data = Yaml::parseFile($this->file);
        $slugger = new AsciiSlugger('fr');
        $categories = [];

        foreach ($data['categories'] as $cat) {
            $pizzas = [];
            foreach ($cat['pizzas'] as $p) {
                $pizzas[] = new Pizza(
                    $p['name'],
                    strtolower((string) $slugger->slug($p['name'])),
                    $p['ingredients'] ?? [],
                    Money::fromCents((int) $p['price']),
                    array_map(static fn (string $t) => Tag::from($t), $p['tags'] ?? []),
                    $p['allergens'] ?? [],
                    (bool) ($p['signature'] ?? false),
                );
            }
            $categories[] = new Category($cat['kicker'], $cat['label'], $pizzas);
        }

        return $this->cache = $categories;
    }

    public function findBySlug(string $slug): ?Pizza
    {
        foreach ($this->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                if ($pizza->slug() === $slug) {
                    return $pizza;
                }
            }
        }

        return null;
    }

    public function signature(): ?Pizza
    {
        foreach ($this->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                if ($pizza->isSignature()) {
                    return $pizza;
                }
            }
        }

        return null;
    }
}
