<?php
namespace App\Seo\Application;

use App\Menu\Domain\MenuRepositoryInterface;
use App\Seo\Domain\SitemapUrl;

final readonly class SitemapUrlProvider
{
    public function __construct(private MenuRepositoryInterface $menu) {}

    /** @return SitemapUrl[] */
    public function urls(): array
    {
        $urls = [
            new SitemapUrl('/', 'weekly', 1.0),
            new SitemapUrl('/nos-pizzas', 'weekly', 0.9),
            new SitemapUrl('/contact', 'monthly', 0.6),
            new SitemapUrl('/mentions-legales', 'yearly', 0.3),
        ];

        foreach ($this->menu->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                $urls[] = new SitemapUrl('/nos-pizzas/' . $pizza->slug(), 'monthly', 0.7);
            }
        }

        return $urls;
    }
}
