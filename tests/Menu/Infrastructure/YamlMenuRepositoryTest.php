<?php
namespace App\Tests\Menu\Infrastructure;

use App\Menu\Domain\Tag;
use App\Menu\Infrastructure\YamlMenuRepository;
use PHPUnit\Framework\TestCase;

final class YamlMenuRepositoryTest extends TestCase
{
    private function repo(): YamlMenuRepository
    {
        return new YamlMenuRepository(__DIR__ . '/fixtures/menu.yaml');
    }

    public function test_reads_categories_and_pizzas(): void
    {
        $categories = $this->repo()->categories();
        self::assertNotEmpty($categories);
        self::assertSame('Les rouges', $categories[0]->label());
        self::assertSame('Margherita', $categories[0]->pizzas()[0]->name());
    }

    public function test_computes_slug_from_name(): void
    {
        $pizza = $this->repo()->findBySlug('quattro-formaggi');
        self::assertNotNull($pizza);
        self::assertSame('Quattro Formaggi', $pizza->name());
    }

    public function test_unknown_slug_returns_null(): void
    {
        self::assertNull($this->repo()->findBySlug('inexistante'));
    }

    public function test_parses_tags_and_price(): void
    {
        $pizza = $this->repo()->findBySlug('margherita');
        self::assertNotNull($pizza);
        self::assertTrue($pizza->hasTag(Tag::Vegetarian));
        self::assertSame("11,90\u{00A0}€", $pizza->price()->format());
    }

    public function test_featured_returns_la_fresca(): void
    {
        $featured = $this->repo()->featured();
        self::assertNotNull($featured);
        self::assertSame('La Fresca', $featured->name());
        self::assertTrue($featured->isFeatured());
    }
}
