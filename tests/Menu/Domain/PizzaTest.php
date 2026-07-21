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

    public function test_tag_metadata(): void
    {
        self::assertSame('Végétarienne', Tag::Vegetarian->label());
        self::assertSame('🌱', Tag::Vegetarian->icon());
        self::assertSame('Piquante', Tag::Spicy->label());
        self::assertSame('🌶️', Tag::Spicy->icon());
    }
}
