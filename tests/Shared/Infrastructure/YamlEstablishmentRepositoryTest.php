<?php
namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\YamlEstablishmentRepository;
use PHPUnit\Framework\TestCase;

final class YamlEstablishmentRepositoryTest extends TestCase
{
    private function repo(): YamlEstablishmentRepository
    {
        return new YamlEstablishmentRepository(__DIR__ . '/fixtures/establishment.yaml');
    }

    public function test_reads_core_fields(): void
    {
        $e = $this->repo()->get();
        self::assertSame('Giulia', $e->name());
        self::assertSame('02 85 52 87 42', $e->phone());
        self::assertSame('+33285528742', $e->phoneHref());
        self::assertSame('hello@giulia-pizza-gorges.fr', $e->email());
        self::assertSame(47.1002191, $e->latitude());
        self::assertSame(-1.3070557, $e->longitude());
    }

    public function test_reads_social_links(): void
    {
        $links = $this->repo()->get()->socialLinks();
        self::assertCount(2, $links);
        self::assertSame('Instagram', $links[0]->label());
    }

    public function test_announcement_is_inactive_by_default(): void
    {
        $a = $this->repo()->get()->announcement();
        self::assertFalse($a->isActive());
        self::assertSame('À noter', $a->title());
    }

    public function test_reads_order_url(): void
    {
        self::assertSame('https://order.example.test/carte', $this->repo()->get()->orderUrl());
    }
}
