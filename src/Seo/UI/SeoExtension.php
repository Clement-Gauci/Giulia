<?php
namespace App\Seo\UI;

use App\Seo\Application\StructuredDataBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    public function __construct(private StructuredDataBuilder $builder) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('giulia_structured_data', $this->structuredData(...))];
    }

    public function structuredData(string $imageUrl): string
    {
        return json_encode(
            $this->builder->build($imageUrl),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
