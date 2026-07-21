<?php
namespace App\Tests\Menu\UI;

use App\Menu\UI\MenuPdfExtension;
use App\Shared\Infrastructure\YamlEstablishmentRepository;
use PHPUnit\Framework\TestCase;

final class MenuPdfExtensionTest extends TestCase
{
    private function repo(): YamlEstablishmentRepository
    {
        return new YamlEstablishmentRepository(__DIR__ . '/fixtures/establishment.yaml');
    }

    public function test_appends_mtime_version_when_file_exists(): void
    {
        $publicDir = sys_get_temp_dir() . '/giulia_pdf_' . uniqid();
        mkdir($publicDir);
        touch($publicDir . '/menu.pdf');

        $ext = new MenuPdfExtension($this->repo(), $publicDir);
        self::assertMatchesRegularExpression('#^/menu\.pdf\?v=\d+$#', $ext->menuPdfUrl());

        unlink($publicDir . '/menu.pdf');
        rmdir($publicDir);
    }

    public function test_returns_bare_url_when_file_missing(): void
    {
        $ext = new MenuPdfExtension($this->repo(), '/nonexistent-dir-xyz');
        self::assertSame('/menu.pdf', $ext->menuPdfUrl());
    }
}
