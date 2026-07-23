<?php
namespace App\Seo\Domain;

final readonly class SitemapUrl
{
    public function __construct(
        private string $loc,
        private string $changefreq,
        private float $priority,
    ) {}

    public function loc(): string { return $this->loc; }
    public function changefreq(): string { return $this->changefreq; }
    public function priority(): float { return $this->priority; }
}
