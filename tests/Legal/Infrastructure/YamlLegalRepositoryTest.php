<?php
namespace App\Tests\Legal\Infrastructure;

use App\Legal\Infrastructure\YamlLegalRepository;
use PHPUnit\Framework\TestCase;

final class YamlLegalRepositoryTest extends TestCase
{
    private function repo(): YamlLegalRepository
    {
        return new YamlLegalRepository(__DIR__ . '/fixtures/legal.yaml');
    }

    public function test_reads_editor_fields(): void
    {
        $notice = $this->repo()->get();
        self::assertSame('GIULIA PIZZAS', $notice->legalName());
        self::assertSame('SARL', $notice->legalForm());
        self::assertSame('918 159 211 00013', $notice->siret());
        self::assertSame('FR73918159211', $notice->vat());
        self::assertSame('Clément GAUCI', $notice->publicationDirector());
    }

    public function test_reads_host(): void
    {
        $host = $this->repo()->get()->host();
        self::assertSame('OVH SAS', $host->name());
        self::assertSame('1007', $host->phone());
        self::assertStringContainsString('Gravelines', $host->note());
    }
}
