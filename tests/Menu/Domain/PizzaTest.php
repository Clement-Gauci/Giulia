<?php
namespace App\Tests\Menu\Domain;

use App\Menu\Domain\Pizza;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class PizzaTest extends TestCase
{
    private function margherita(): Pizza
    {
        return new Pizza(
            'Margherita',
            'margherita',
            ['San Marzano', 'fior di latte', 'basilic'],
            Money::fromCents(1190),
            [Tag::Vegetarian],
            ['gluten', 'lait'],
            false,
        );
    }

    public function test_exposes_its_data(): void
    {
        $pizza = $this->margherita();
        self::assertSame('Margherita', $pizza->name());
        self::assertSame('margherita', $pizza->slug());
        self::assertSame("11,90\u{00A0}€", $pizza->price()->format());
        self::assertTrue($pizza->hasTag(Tag::Vegetarian));
        self::assertFalse($pizza->hasTag(Tag::Spicy));
        self::assertFalse($pizza->isSignature());
    }

    public function test_photo_is_optional(): void
    {
        self::assertNull($this->margherita()->photo());
        self::assertFalse($this->margherita()->hasPhoto());
    }

    public function test_exposes_its_photo(): void
    {
        $pizza = new Pizza(
            'Margherita',
            'margherita',
            ['San Marzano'],
            Money::fromCents(1190),
            [],
            [],
            false,
            'margherita',
        );
        self::assertSame('margherita', $pizza->photo());
        self::assertTrue($pizza->hasPhoto());
    }

    public function test_diameter_defaults_to_the_standard_size(): void
    {
        self::assertSame(33, Pizza::STANDARD_DIAMETER_CM);
        self::assertSame(33, $this->margherita()->diameterCm());
        self::assertTrue($this->margherita()->hasStandardDiameter());
    }

    public function test_exposes_an_exceptional_diameter(): void
    {
        $pizza = new Pizza(
            'Camembert Rôti',
            'camembert-roti',
            ['camembert entier rôti'],
            Money::fromCents(1550),
            [],
            [],
            false,
            null,
            20,
        );
        self::assertSame(20, $pizza->diameterCm());
        self::assertFalse($pizza->hasStandardDiameter());
    }

    public function test_tag_metadata(): void
    {
        self::assertSame('Végétarienne', Tag::Vegetarian->label());
        self::assertSame('🌱', Tag::Vegetarian->icon());
        self::assertSame('Piquante', Tag::Spicy->label());
        self::assertSame('🌶️', Tag::Spicy->icon());
    }
}
