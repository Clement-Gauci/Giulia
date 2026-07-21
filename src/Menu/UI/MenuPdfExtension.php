<?php
namespace App\Menu\UI;

use App\Shared\Domain\EstablishmentRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuPdfExtension extends AbstractExtension
{
    public function __construct(
        private EstablishmentRepositoryInterface $repository,
        private string $publicDir,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('menu_pdf_url', $this->menuPdfUrl(...))];
    }

    public function menuPdfUrl(): string
    {
        $url = $this->repository->get()->menuPdfUrl();
        $file = $this->publicDir . $url;

        return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
    }
}
