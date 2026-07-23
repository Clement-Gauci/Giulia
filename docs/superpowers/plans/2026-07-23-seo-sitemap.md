# SEO & Sitemap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un sitemap dynamique, un robots.txt, des données structurées JSON-LD Restaurant, des balises canonical et Open Graph/Twitter au site vitrine Giulia.

**Architecture:** Nouveau bounded context `App\Seo` (DDD, cohérent avec l'existant). Un `SitemapUrlProvider` (Application) liste les URLs indexables ; un `SitemapController` (UI) rend `/sitemap.xml`. Un `StructuredDataBuilder` (Application) produit le tableau schema.org, exposé à Twig par `SeoExtension`. Les balises canonical/OG/JSON-LD sont ajoutées dans `base.html.twig`. Le domaine absolu vient d'un global Twig `site_url` lié à `DEFAULT_URI`.

**Tech Stack:** PHP 8, Symfony (FrameworkBundle, Twig, asset-mapper), PHPUnit.

## Global Constraints

- Architecture DDD : le code métier ne vit **pas** dans `src/Controller`. Chaque contexte a `Domain/`, `Application/`, `UI/` (et `Infrastructure/` si besoin). Les `Domain/` sont exclus de l'autowiring (`config/services.yaml`).
- Autowiring activé par défaut (`_defaults: autowire: true, autoconfigure: true`). Les contrôleurs étendent `Symfony\Bundle\FrameworkBundle\Controller\AbstractController` et déclarent les routes via l'attribut `#[Route(...)]`.
- Tests unitaires : `PHPUnit\Framework\TestCase`, dépendances remplacées par des classes anonymes (`new class implements X { ... }`). Namespace de test : `App\Tests\<Contexte>\...`.
- Tests fonctionnels : `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`, `static::createClient()`. Namespace : `App\Tests\Functional`.
- PHPUnit est en mode strict : `failOnDeprecation`, `failOnNotice`, `failOnWarning` = true. Le code ne doit produire aucun deprecation/notice/warning.
- Twig `strict_variables: true` en environnement test : toute variable utilisée dans un template doit exister.
- Domaine canonique de production : `https://giulia-pizza-gorges.fr` (sans www). En dev/test, `DEFAULT_URI=http://localhost` → `site_url = http://localhost`.
- Commande de test : `php bin/phpunit`.
- Ne pas ajouter d'`aggregateRating` au JSON-LD (aucune note chiffrée fiable disponible).

---

### Task 1: SitemapUrl (Domain) + SitemapUrlProvider (Application)

**Files:**
- Create: `src/Seo/Domain/SitemapUrl.php`
- Create: `src/Seo/Application/SitemapUrlProvider.php`
- Test: `tests/Seo/Application/SitemapUrlProviderTest.php`

**Interfaces:**
- Consumes: `App\Menu\Domain\MenuRepositoryInterface` (`categories(): Category[]`), `App\Menu\Domain\Category` (`pizzas(): Pizza[]`), `App\Menu\Domain\Pizza` (`slug(): string`).
- Produces:
  - `App\Seo\Domain\SitemapUrl` — constructeur `(string $loc, string $changefreq, float $priority)`, getters `loc(): string`, `changefreq(): string`, `priority(): float`.
  - `App\Seo\Application\SitemapUrlProvider` — constructeur `(MenuRepositoryInterface $menu)`, méthode `urls(): SitemapUrl[]`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Seo/Application/SitemapUrlProviderTest.php` :

```php
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
```

- [ ] **Step 2: Lancer le test et vérifier l'échec**

Run: `php bin/phpunit --filter=SitemapUrlProviderTest`
Expected: FAIL — classes `SitemapUrl` / `SitemapUrlProvider` introuvables.

- [ ] **Step 3: Créer le value object**

Créer `src/Seo/Domain/SitemapUrl.php` :

```php
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
```

- [ ] **Step 4: Créer le provider**

Créer `src/Seo/Application/SitemapUrlProvider.php` :

```php
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
```

- [ ] **Step 5: Lancer le test et vérifier le succès**

Run: `php bin/phpunit --filter=SitemapUrlProviderTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Seo/Domain/SitemapUrl.php src/Seo/Application/SitemapUrlProvider.php tests/Seo/Application/SitemapUrlProviderTest.php
git commit -m "feat(seo): fournit la liste des URLs indexables pour le sitemap"
```

---

### Task 2: Config site_url + SitemapController + template XML

**Files:**
- Modify: `config/services.yaml` (parameters + bind `$siteUrl`)
- Modify: `config/packages/twig.yaml` (global `site_url`)
- Modify: `.env.local.dist` (documenter `DEFAULT_URI` de prod)
- Create: `src/Seo/UI/SitemapController.php`
- Create: `templates/seo/sitemap.xml.twig`
- Test: `tests/Functional/SitemapTest.php`

**Interfaces:**
- Consumes: `App\Seo\Application\SitemapUrlProvider::urls()` (Task 1), global Twig `site_url` (défini ici).
- Produces:
  - Route `sitemap` → `GET /sitemap.xml`, réponse `application/xml; charset=UTF-8`.
  - Paramètre conteneur `giulia.site_url` et bind `$siteUrl` (consommés par la Task 3).
  - Global Twig `site_url` (consommé par la Task 4).

- [ ] **Step 1: Écrire le test fonctionnel qui échoue**

Créer `tests/Functional/SitemapTest.php` :

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitemapTest extends WebTestCase
{
    public function test_sitemap_is_served_as_xml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_sitemap_lists_pages_with_absolute_urls(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');
        $xml = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<urlset', $xml);
        self::assertStringContainsString('<loc>http://localhost/</loc>', $xml);
        self::assertStringContainsString('<loc>http://localhost/nos-pizzas</loc>', $xml);
    }
}
```

- [ ] **Step 2: Lancer le test et vérifier l'échec**

Run: `php bin/phpunit --filter=SitemapTest`
Expected: FAIL — 404 sur `/sitemap.xml`.

- [ ] **Step 3: Déclarer le paramètre et le bind dans `config/services.yaml`**

Dans la section `parameters:`, ajouter après `giulia.ga_measurement_id` :

```yaml
    giulia.site_url: '%env(DEFAULT_URI)%'
```

Dans `services: _defaults:`, ajouter la clé `bind` (à côté de `autowire`/`autoconfigure`) :

```yaml
    _defaults:
        autowire: true
        autoconfigure: true
        bind:
            $siteUrl: '%giulia.site_url%'
```

- [ ] **Step 4: Exposer le global Twig dans `config/packages/twig.yaml`**

Sous `twig: globals:`, ajouter à côté de `ga_measurement_id` :

```yaml
        site_url: '%giulia.site_url%'
```

- [ ] **Step 5: Documenter la valeur de prod dans `.env.local.dist`**

Ajouter à la fin de `.env.local.dist` :

```dotenv

###> symfony/routing ###
# URL absolue du site en production (sert au sitemap, aux balises canonical et Open Graph).
DEFAULT_URI=https://giulia-pizza-gorges.fr
###< symfony/routing ###
```

- [ ] **Step 6: Créer le contrôleur**

Créer `src/Seo/UI/SitemapController.php` :

```php
<?php
namespace App\Seo\UI;

use App\Seo\Application\SitemapUrlProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function index(SitemapUrlProvider $provider): Response
    {
        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $provider->urls()]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
```

- [ ] **Step 7: Créer le template XML**

Créer `templates/seo/sitemap.xml.twig`. Le fichier doit commencer **directement** par `<?xml` (aucun espace/ligne avant) :

```twig
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{%- for url in urls %}
    <url>
        <loc>{{ site_url|trim('/', 'right') }}{{ url.loc }}</loc>
        <changefreq>{{ url.changefreq }}</changefreq>
        <priority>{{ url.priority|number_format(1, '.', '') }}</priority>
    </url>
{%- endfor %}
</urlset>
```

- [ ] **Step 8: Lancer le test et vérifier le succès**

Run: `php bin/phpunit --filter=SitemapTest`
Expected: PASS (2 tests). Si échec « variable site_url does not exist », vider le cache : `php bin/console cache:clear --env=test`.

- [ ] **Step 9: Commit**

```bash
git add config/services.yaml config/packages/twig.yaml .env.local.dist src/Seo/UI/SitemapController.php templates/seo/sitemap.xml.twig tests/Functional/SitemapTest.php
git commit -m "feat(seo): route dynamique /sitemap.xml + global Twig site_url"
```

---

### Task 3: StructuredDataBuilder (JSON-LD Restaurant)

**Files:**
- Create: `src/Seo/Application/StructuredDataBuilder.php`
- Create: `tests/Seo/Application/StructuredDataBuilderTest.php`
- Create: `tests/Seo/Application/fixtures/establishment.yaml`

**Interfaces:**
- Consumes: `App\Shared\Domain\EstablishmentRepositoryInterface` (`get(): Establishment`), `App\Shared\Domain\Establishment` (`name()`, `phoneHref()`, `address()`, `latitude()`, `longitude()`, `menuPdfUrl()`, `socialLinks(): SocialLink[]`), `App\Shared\Domain\SocialLink` (`url(): string`), `App\Opening\Domain\ScheduleRepositoryInterface` (`schedule(): WeeklySchedule`), `App\Opening\Domain\WeeklySchedule` (`rangesFor(Weekday): TimeRange[]`), `App\Opening\Domain\TimeRange` (`openMinute(): int`, `closeMinute(): int`), `App\Shared\Domain\Weekday` (cases `Monday`..`Sunday`, propriété `->name`), bind `$siteUrl` (Task 2).
- Produces: `App\Seo\Application\StructuredDataBuilder` — constructeur `(EstablishmentRepositoryInterface $establishments, ScheduleRepositoryInterface $schedule, string $siteUrl)`, méthode `build(string $imageUrl): array` retournant le tableau schema.org.

- [ ] **Step 1: Créer la fixture d'établissement**

Créer `tests/Seo/Application/fixtures/establishment.yaml` :

```yaml
name: "Giulia"
tagline: "Pizzeria napolitaine · Gorges"
address: "1 rue de la Cité des Sports, Route de Saint-Fiacre, 44190 Gorges"
latitude: 47.1002191
longitude: -1.3070557
phone: "02 85 52 87 42"
phone_href: "+33285528742"
email: "hello@giulia-pizza-gorges.fr"
menu_pdf_url: "/menu.pdf"
order_url: "https://order.example.test/carte"
directions_url: "https://maps.example.test/giulia"
google_reviews_url: "https://g.page/r/example/review"
whatsapp_url: "https://chat.whatsapp.com/example"
social_links:
  - { label: "Instagram", url: "https://www.instagram.com/giulia_pizza_gorges/", icon: "instagram" }
  - { label: "Facebook", url: "https://www.facebook.com/GiuliaPizzaGorges/", icon: "facebook" }
announcement:
  active: false
  title: "À noter"
  text: "Message."
```

- [ ] **Step 2: Écrire le test qui échoue**

Créer `tests/Seo/Application/StructuredDataBuilderTest.php` :

```php
<?php
namespace App\Tests\Seo\Application;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Seo\Application\StructuredDataBuilder;
use App\Shared\Domain\Weekday;
use App\Shared\Infrastructure\YamlEstablishmentRepository;
use PHPUnit\Framework\TestCase;

final class StructuredDataBuilderTest extends TestCase
{
    private function builder(): StructuredDataBuilder
    {
        $establishments = new YamlEstablishmentRepository(__DIR__ . '/fixtures/establishment.yaml');

        $schedule = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([
                    Weekday::Tuesday->value => [
                        TimeRange::fromMinutes(600, 870),   // 10:00 – 14:30
                        TimeRange::fromMinutes(1020, 1290), // 17:00 – 21:30
                    ],
                    Weekday::Sunday->value => [
                        TimeRange::fromMinutes(1080, 1290), // 18:00 – 21:30
                    ],
                ]);
            }
        };

        return new StructuredDataBuilder($establishments, $schedule, 'https://giulia-pizza-gorges.fr');
    }

    public function test_it_builds_a_restaurant_with_nap_and_geo(): void
    {
        $data = $this->builder()->build('https://giulia-pizza-gorges.fr/img.png');

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('Restaurant', $data['@type']);
        self::assertSame('Giulia', $data['name']);
        self::assertSame('+33285528742', $data['telephone']);
        self::assertSame(47.1002191, $data['geo']['latitude']);
        self::assertSame(-1.3070557, $data['geo']['longitude']);
        self::assertSame('https://giulia-pizza-gorges.fr/img.png', $data['image']);
        self::assertSame('https://giulia-pizza-gorges.fr/menu.pdf', $data['hasMenu']);
    }

    public function test_it_splits_the_french_address(): void
    {
        $address = $this->builder()->build('https://x/i.png')['address'];

        self::assertSame('PostalAddress', $address['@type']);
        self::assertSame('44190', $address['postalCode']);
        self::assertSame('Gorges', $address['addressLocality']);
        self::assertSame('FR', $address['addressCountry']);
        self::assertStringContainsString('Cité des Sports', $address['streetAddress']);
    }

    public function test_it_maps_opening_hours_from_the_schedule(): void
    {
        $hours = $this->builder()->build('https://x/i.png')['openingHoursSpecification'];

        self::assertCount(3, $hours);
        self::assertSame('OpeningHoursSpecification', $hours[0]['@type']);
        self::assertSame('Tuesday', $hours[0]['dayOfWeek']);
        self::assertSame('10:00', $hours[0]['opens']);
        self::assertSame('14:30', $hours[0]['closes']);
    }

    public function test_it_lists_social_profiles_and_omits_ratings(): void
    {
        $data = $this->builder()->build('https://x/i.png');

        self::assertContains('https://www.instagram.com/giulia_pizza_gorges/', $data['sameAs']);
        self::assertContains('https://www.facebook.com/GiuliaPizzaGorges/', $data['sameAs']);
        self::assertArrayNotHasKey('aggregateRating', $data);
    }
}
```

- [ ] **Step 3: Lancer le test et vérifier l'échec**

Run: `php bin/phpunit --filter=StructuredDataBuilderTest`
Expected: FAIL — classe `StructuredDataBuilder` introuvable.

- [ ] **Step 4: Implémenter le builder**

Créer `src/Seo/Application/StructuredDataBuilder.php` :

```php
<?php
namespace App\Seo\Application;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use App\Shared\Domain\SocialLink;
use App\Shared\Domain\Weekday;

final readonly class StructuredDataBuilder
{
    public function __construct(
        private EstablishmentRepositoryInterface $establishments,
        private ScheduleRepositoryInterface $schedule,
        private string $siteUrl,
    ) {}

    /** @return array<string, mixed> */
    public function build(string $imageUrl): array
    {
        $e = $this->establishments->get();
        $base = rtrim($this->siteUrl, '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $e->name(),
            'url' => $base,
            'telephone' => $e->phoneHref(),
            'servesCuisine' => 'Pizza napolitaine',
            'priceRange' => '€€',
            'acceptsReservations' => false,
            'image' => $imageUrl,
            'hasMenu' => $base . $e->menuPdfUrl(),
            'address' => $this->address($e->address()),
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $e->latitude(),
                'longitude' => $e->longitude(),
            ],
            'openingHoursSpecification' => $this->openingHours(),
            'sameAs' => array_values(array_map(
                static fn (SocialLink $l) => $l->url(),
                $e->socialLinks(),
            )),
        ];
    }

    /** @return array<string, string> */
    private function address(string $full): array
    {
        $address = ['@type' => 'PostalAddress', 'addressCountry' => 'FR'];

        if (preg_match('/^(.*?),?\s*(\d{5})\s+(.+)$/u', $full, $m) === 1) {
            $address['streetAddress'] = trim($m[1]);
            $address['postalCode'] = $m[2];
            $address['addressLocality'] = trim($m[3]);
        } else {
            $address['streetAddress'] = $full;
        }

        return $address;
    }

    /** @return list<array<string, string>> */
    private function openingHours(): array
    {
        $schedule = $this->schedule->schedule();
        $specs = [];

        foreach (Weekday::cases() as $day) {
            foreach ($schedule->rangesFor($day) as $range) {
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $day->name,
                    'opens' => $this->hm($range->openMinute()),
                    'closes' => $this->hm($range->closeMinute()),
                ];
            }
        }

        return $specs;
    }

    private function hm(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
```

- [ ] **Step 5: Lancer le test et vérifier le succès**

Run: `php bin/phpunit --filter=StructuredDataBuilderTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Seo/Application/StructuredDataBuilder.php tests/Seo/Application/StructuredDataBuilderTest.php tests/Seo/Application/fixtures/establishment.yaml
git commit -m "feat(seo): construit le JSON-LD Restaurant (adresse, geo, horaires, réseaux)"
```

---

### Task 4: SeoExtension + intégration dans base.html.twig

**Files:**
- Create: `src/Seo/UI/SeoExtension.php`
- Modify: `templates/base.html.twig` (bloc `<head>`)
- Test: `tests/Functional/SeoHeadTest.php`

**Interfaces:**
- Consumes: `App\Seo\Application\StructuredDataBuilder::build(string $imageUrl): array` (Task 3), global Twig `site_url` (Task 2), fonction Twig existante `asset()`.
- Produces: fonction Twig `giulia_structured_data(string $imageUrl): string` (JSON encodé), utilisée dans `base.html.twig`.

- [ ] **Step 1: Écrire le test fonctionnel qui échoue**

Créer `tests/Functional/SeoHeadTest.php` :

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoHeadTest extends WebTestCase
{
    public function test_home_exposes_canonical_and_social_tags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/"]');
        self::assertSelectorExists('meta[property="og:title"]');
        self::assertSelectorExists('meta[property="og:image"]');
        self::assertSelectorExists('meta[name="twitter:card"]');
    }

    public function test_home_embeds_restaurant_json_ld(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $json = $crawler->filter('script[type="application/ld+json"]')->text();
        self::assertStringContainsString('"@type":"Restaurant"', $json);
        self::assertStringContainsString('"addressLocality":"Gorges"', $json);
    }

    public function test_pizza_page_canonical_targets_its_own_path(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/nos-pizzas"]');
    }
}
```

- [ ] **Step 2: Lancer le test et vérifier l'échec**

Run: `php bin/phpunit --filter=SeoHeadTest`
Expected: FAIL — pas de balise canonical / fonction `giulia_structured_data` inconnue.

- [ ] **Step 3: Créer l'extension Twig**

Créer `src/Seo/UI/SeoExtension.php` :

```php
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
```

- [ ] **Step 4: Intégrer les balises dans `base.html.twig`**

Dans `templates/base.html.twig`, remplacer la ligne :

```twig
    <link rel="icon" href="{{ asset('images/giulia-icon.png') }}">
```

par (garder `<link rel="icon">`, ajouter les balises SEO juste après) :

```twig
    <link rel="icon" href="{{ asset('images/giulia-icon.png') }}">

    {% set canonical = site_url|trim('/', 'right') ~ app.request.pathInfo %}
    {% set social_image = site_url|trim('/', 'right') ~ asset('images/giulia-logo.png') %}
    <link rel="canonical" href="{{ canonical }}">
    <meta property="og:type" content="{% block og_type 'website' %}">
    <meta property="og:site_name" content="Giulia">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="{{ block('title') }}">
    <meta property="og:description" content="{{ block('description') }}">
    <meta property="og:url" content="{{ canonical }}">
    <meta property="og:image" content="{{ social_image }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ block('title') }}">
    <meta name="twitter:description" content="{{ block('description') }}">
    <meta name="twitter:image" content="{{ social_image }}">
    <script type="application/ld+json">{{ giulia_structured_data(social_image)|raw }}</script>
```

- [ ] **Step 5: Lancer le test et vérifier le succès**

Run: `php bin/phpunit --filter=SeoHeadTest`
Expected: PASS (3 tests). En cas d'erreur « variable does not exist », lancer `php bin/console cache:clear --env=test`.

- [ ] **Step 6: Commit**

```bash
git add src/Seo/UI/SeoExtension.php templates/base.html.twig tests/Functional/SeoHeadTest.php
git commit -m "feat(seo): balises canonical, Open Graph/Twitter et JSON-LD dans le head"
```

---

### Task 5: robots.txt

**Files:**
- Create: `public/robots.txt`
- Test: `tests/Seo/RobotsTxtTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: fichier statique `public/robots.txt` déclarant le sitemap et bloquant `/api/`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Seo/RobotsTxtTest.php` :

```php
<?php
namespace App\Tests\Seo;

use PHPUnit\Framework\TestCase;

final class RobotsTxtTest extends TestCase
{
    public function test_robots_declares_sitemap_and_blocks_api(): void
    {
        $robots = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/robots.txt');

        self::assertStringContainsString('User-agent: *', $robots);
        self::assertStringContainsString('Disallow: /api/', $robots);
        self::assertStringContainsString('Sitemap: https://giulia-pizza-gorges.fr/sitemap.xml', $robots);
    }
}
```

- [ ] **Step 2: Lancer le test et vérifier l'échec**

Run: `php bin/phpunit --filter=RobotsTxtTest`
Expected: FAIL — `public/robots.txt` inexistant.

- [ ] **Step 3: Créer le fichier**

Créer `public/robots.txt` :

```
User-agent: *
Allow: /
Disallow: /api/

Sitemap: https://giulia-pizza-gorges.fr/sitemap.xml
```

- [ ] **Step 4: Lancer le test et vérifier le succès**

Run: `php bin/phpunit --filter=RobotsTxtTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add public/robots.txt tests/Seo/RobotsTxtTest.php
git commit -m "feat(seo): robots.txt déclarant le sitemap et bloquant /api"
```

---

### Task 6: Vérification globale

**Files:** aucun (validation).

- [ ] **Step 1: Lancer toute la suite de tests**

Run: `php bin/phpunit`
Expected: PASS — les tests existants (~67) + les nouveaux, sans deprecation/notice/warning.

- [ ] **Step 2: Contrôle visuel du sitemap et du robots**

Run: `php bin/console debug:router | grep -E 'sitemap|nos-pizzas'`
Expected: la route `sitemap` apparaît, mappée sur `/sitemap.xml`.

- [ ] **Step 3: Vérifier le rendu XML localement (optionnel si un serveur tourne)**

Run: `curl -s http://giulia-pizza-gorges.local/sitemap.xml | head -20`
Expected: `<?xml ...?>` puis `<urlset>` avec une `<url>` par page et par pizza.

---

## Notes de reprise (hors plan)

- **Image sociale** : `og:image` pointe pour l'instant vers `giulia-logo.png`. Fournir un visuel dédié **1200×630** (photo de pizza) et le déclarer via un bloc `og_image` si un rendu par page devient nécessaire.
- **En production** : positionner `DEFAULT_URI=https://giulia-pizza-gorges.fr` dans `.env.local` pour que sitemap/canonical/OG génèrent les bonnes URLs absolues.
- **Après mise en ligne** : soumettre le sitemap dans Google Search Console et Bing Webmaster Tools.
