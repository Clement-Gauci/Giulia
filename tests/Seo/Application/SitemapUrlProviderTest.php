<?php
namespace App\Tests\Seo\Application;

use App\Menu\Domain\Category;
use App\Menu\Domain\MenuRepositoryInterface;
use App\Menu\Domain\Pizza;
use App\Seo\Application\SitemapUrlProvider;
use App\Seo\Domain\SitemapUrl;
use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class SitemapUrlProviderTest extends TestCase
{
    private function menuWith(string ...$slugs): MenuRepositoryInterface
    {
        $pizzas = array_map(
            static fn (string $slug) => new Pizza($slug, $slug, [], Money::fromCents(1000), [], [], false),
            $slugs,
        );

        return new class($pizzas) implements MenuRepositoryInterface {
            /** @param Pizza[] $pizzas */
            public function __construct(private array $pizzas) {}
            public function categories(): array { return [new Category('kicker', 'Classiques', $this->pizzas)]; }
            public function findBySlug(string $slug): ?Pizza { return null; }
            public function signature(): ?Pizza { return null; }
        };
    }

    public function test_it_lists_static_routes_and_one_url_per_pizza(): void
    {
        $provider = new SitemapUrlProvider($this->menuWith('margherita', 'regina'));
        $locs = array_map(static fn (SitemapUrl $u) => $u->loc(), $provider->urls());

        self::assertContains('/', $locs);
        self::assertContains('/nos-pizzas', $locs);
        self::assertContains('/contact', $locs);
        self::assertContains('/mentions-legales', $locs);
        self::assertContains('/nos-pizzas/margherita', $locs);
        self::assertContains('/nos-pizzas/regina', $locs);
    }

    public function test_it_excludes_non_indexable_routes(): void
    {
        $provider = new SitemapUrlProvider($this->menuWith('margherita'));
        $locs = array_map(static fn (SitemapUrl $u) => $u->loc(), $provider->urls());

        self::assertNotContains('/api/status', $locs);
        self::assertNotContains('/join-whatsapp-group', $locs);
    }

    public function test_every_url_has_a_priority_between_0_and_1(): void
    {
        $provider = new SitemapUrlProvider($this->menuWith('margherita'));

        foreach ($provider->urls() as $url) {
            self::assertGreaterThanOrEqual(0.0, $url->priority());
            self::assertLessThanOrEqual(1.0, $url->priority());
        }
    }
}
