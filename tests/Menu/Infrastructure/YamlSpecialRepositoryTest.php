<?php
namespace App\Tests\Menu\Infrastructure;

use App\Menu\Domain\Tag;
use App\Menu\Infrastructure\YamlSpecialRepository;
use PHPUnit\Framework\TestCase;

final class YamlSpecialRepositoryTest extends TestCase
{
    public function test_reads_active_special(): void
    {
        $repo = new YamlSpecialRepository(__DIR__ . '/fixtures/special.yaml');
        $special = $repo->current();

        self::assertNotNull($special);
        self::assertSame('La Fresca', $special->name());
        self::assertSame('Édition du moment', $special->period());
        self::assertSame("17,90\u{00A0}€", $special->price()->format());
        self::assertTrue($special->hasTag(Tag::Spicy));
        self::assertContains('Bresaola', $special->ingredients());
    }

    public function test_inactive_special_returns_null(): void
    {
        $repo = new YamlSpecialRepository(__DIR__ . '/fixtures/special_inactive.yaml');
        self::assertNull($repo->current());
    }
}
