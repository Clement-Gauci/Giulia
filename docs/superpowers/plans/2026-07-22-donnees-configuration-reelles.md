# Données de configuration réelles & contexte Legal — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les données de démonstration par les vraies données de Giulia, externaliser les mentions légales dans un contexte `Legal`, séparer pizza signature / pizza du moment, corriger le câblage Click & Collect et versionner le PDF de la carte.

**Architecture:** Site Symfony sans base de données. Les données vivent en YAML sous `config/giulia/`, lues par des repositories (1 contexte DDD = 1 fichier = 1 repository). Les vues Twig consomment ces repositories via des extensions ou des controllers. On étend ce pattern (nouveau fichier `special.yaml`, nouveau contexte `Legal`) sans le contredire.

**Tech Stack:** PHP 8.2+, Symfony (FrameworkBundle, Twig, symfony/yaml, symfony/string), PHPUnit 11, AssetMapper.

## Global Constraints

- **PHP 8.2+** : classes `final readonly`, enums `string`, syntaxe callable de première classe (`$this->repo->get(...)`). Copier le style exact des classes existantes.
- **Aucune base de données**, aucun worker Messenger.
- **PHPUnit strict** : `phpunit.dist.xml` a `failOnDeprecation`, `failOnNotice`, `failOnWarning` = `true`. Le moindre warning fait échouer la suite.
- **Lancer les tests** : `php bin/phpunit` (un test précis : `php bin/phpunit --filter test_name`).
- **Prix en centimes** : `Money::fromCents(int)` ; `->format()` rend `"17,90\u{00A0}€"` (espace insécable U+00A0).
- **Namespaces** : source `App\<Context>\<Layer>` ; tests `App\Tests\<Context>\<Layer>`.
- **Copie/textes en français**, accents et diacritiques obligatoires.
- **Tests fonctionnels** (`WebTestCase`) lisent les **vrais** fichiers `config/giulia/*.yaml` (pas les fixtures). Toute modification de données doit s'accompagner de l'adaptation des tests fonctionnels concernés dans la **même** tâche.
- **Repositories** injectés par chemin de fichier dans `config/services.yaml` sous `%giulia.data_dir%` (= `config/giulia`).

---

### Task 1 : Établissement — champ `order_url` (Click & Collect) + vraies coordonnées

**Files:**
- Modify: `src/Shared/Domain/Establishment.php`
- Modify: `src/Shared/Infrastructure/YamlEstablishmentRepository.php`
- Modify: `config/giulia/establishment.yaml`
- Test: `tests/Shared/Infrastructure/YamlEstablishmentRepositoryTest.php`
- Modify (fixture): `tests/Shared/Infrastructure/fixtures/establishment.yaml`

**Interfaces:**
- Produces: `Establishment::orderUrl(): string` (lien de commande en ligne). Nouveau constructeur avec `$orderUrl` inséré **juste après** `$menuPdfUrl`.

- [ ] **Step 1: Ajouter la clé `order_url` à la fixture de test**

Dans `tests/Shared/Infrastructure/fixtures/establishment.yaml`, ajouter après la ligne `menu_pdf_url` :

```yaml
menu_pdf_url: "/menu.pdf"
order_url: "https://order.example.test/carte"
```

- [ ] **Step 2: Écrire le test qui échoue**

Ajouter à `tests/Shared/Infrastructure/YamlEstablishmentRepositoryTest.php` :

```php
    public function test_reads_order_url(): void
    {
        self::assertSame('https://order.example.test/carte', $this->repo()->get()->orderUrl());
    }
```

- [ ] **Step 3: Lancer le test — il échoue**

Run: `php bin/phpunit --filter test_reads_order_url`
Expected: FAIL (« Call to undefined method … orderUrl() »).

- [ ] **Step 4: Ajouter le champ au VO `Establishment`**

Dans `src/Shared/Domain/Establishment.php`, insérer le paramètre après `$menuPdfUrl` et le getter après `menuPdfUrl()` :

```php
        private string $menuPdfUrl,
        private string $orderUrl,
        private string $directionsUrl,
```

```php
    public function menuPdfUrl(): string { return $this->menuPdfUrl; }
    public function orderUrl(): string { return $this->orderUrl; }
    public function directionsUrl(): string { return $this->directionsUrl; }
```

- [ ] **Step 5: Mapper la clé dans le repository**

Dans `src/Shared/Infrastructure/YamlEstablishmentRepository.php`, passer `$d['order_url']` dans la position correspondante :

```php
        return new Establishment(
            $d['name'], $d['tagline'], $d['address'], $d['phone'], $d['phone_href'],
            $d['email'], $d['menu_pdf_url'], $d['order_url'], $d['directions_url'], $d['google_reviews_url'],
            $d['whatsapp_url'], $links,
            new Announcement((bool) $a['active'], $a['title'], $a['text']),
        );
```

- [ ] **Step 6: Lancer le test — il passe**

Run: `php bin/phpunit --filter test_reads_order_url`
Expected: PASS.

- [ ] **Step 7: Renseigner les vraies valeurs dans `config/giulia/establishment.yaml`**

Remplacer intégralement le contenu par :

```yaml
name: "Giulia"
tagline: "Pizzeria napolitaine · Gorges"
address: "1 rue de la Cité des Sports, Route de Saint-Fiacre, 44190 Gorges"
phone: "02 85 52 87 42"
phone_href: "+33285528742"
email: "hello@giulia-pizza-gorges.fr"
menu_pdf_url: "/menu.pdf"
order_url: "https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"
directions_url: "https://www.google.com/maps/place/Giulia+Pizza/@47.1002227,-1.3092444,17z/data=!3m1!4b1!4m5!3m4!1s0x4805df923be8ea77:0xf414c4590f3252a1!8m2!3d47.1002191!4d-1.3070557?hl=fr"
google_reviews_url: "https://g.page/r/CaFSMg9ZxBT0EB0/review"
whatsapp_url: "https://chat.whatsapp.com/FzecMAws5b81lwGz9Ce7Fa"
social_links:
  - { label: "Instagram", url: "https://www.instagram.com/giulia_pizza_gorges/", icon: "instagram" }
  - { label: "Facebook", url: "https://www.facebook.com/GiuliaPizzaGorges/", icon: "facebook" }
announcement:
  active: false
  title: "À noter"
  text: "Fermeture exceptionnelle le 15 août. Réouverture le lendemain aux horaires habituels."
```

- [ ] **Step 8: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS (aucune régression).

- [ ] **Step 9: Commit**

```bash
git add src/Shared/Domain/Establishment.php src/Shared/Infrastructure/YamlEstablishmentRepository.php config/giulia/establishment.yaml tests/Shared/Infrastructure/YamlEstablishmentRepositoryTest.php tests/Shared/Infrastructure/fixtures/establishment.yaml
git commit -m "feat(establishment): ajoute order_url (Click & Collect) et renseigne les vraies coordonnées"
```

---

### Task 2 : Câbler les CTA « Click & Collect » sur `order_url`

Actuellement les 3 boutons de commande pointent vers le PDF (`e.menuPdfUrl`). On les rebranche vers `e.orderUrl`.

**Files:**
- Modify: `templates/home/index.html.twig` (CTA `#commander`)
- Modify: `templates/menu/index.html.twig` (CTA `#commander`)
- Modify: `templates/menu/show.html.twig` (CTA de commande)
- Test: `tests/Functional/HomePageTest.php`, `tests/Functional/MenuPageTest.php`

**Interfaces:**
- Consumes: `Establishment::orderUrl()` (Task 1).

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à `tests/Functional/HomePageTest.php` :

```php
    public function test_click_and_collect_cta_points_to_order_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorExists('a#commander[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }
```

Ajouter à `tests/Functional/MenuPageTest.php` :

```php
    public function test_menu_cta_points_to_order_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');
        self::assertSelectorExists('a#commander[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }
```

- [ ] **Step 2: Lancer les tests — ils échouent**

Run: `php bin/phpunit --filter test_click_and_collect_cta_points_to_order_url`
Expected: FAIL (le href vaut `/menu.pdf`).

- [ ] **Step 3: Rebrancher les templates**

Dans `templates/home/index.html.twig`, le CTA Click & Collect :

```twig
    <a id="commander" href="{{ e.orderUrl }}" class="cta cta--home">
```

Dans `templates/menu/index.html.twig`, le CTA :

```twig
    <a id="commander" href="{{ e.orderUrl }}" class="cta">
```

Dans `templates/menu/show.html.twig`, le CTA de commande :

```twig
    <a href="{{ e.orderUrl }}" class="cta">
```

- [ ] **Step 4: Lancer les tests — ils passent**

Run: `php bin/phpunit --filter test_menu_cta_points_to_order_url && php bin/phpunit --filter test_click_and_collect_cta_points_to_order_url`
Expected: PASS.

- [ ] **Step 5: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add templates/home/index.html.twig templates/menu/index.html.twig templates/menu/show.html.twig tests/Functional/HomePageTest.php tests/Functional/MenuPageTest.php
git commit -m "fix(ui): les CTA Click & Collect pointent vers la commande en ligne, plus le PDF"
```

---

### Task 3 : PDF de la carte assemblé + cache-busting `menu_pdf_url()`

**Files:**
- Create: `public/menu.pdf` (binaire assemblé)
- Create: `src/Menu/UI/MenuPdfExtension.php`
- Modify: `config/services.yaml`
- Modify: `templates/components/_footer.html.twig`
- Modify: `templates/home/index.html.twig` (link card « Notre menu »)
- Create (test): `tests/Menu/UI/MenuPdfExtensionTest.php`
- Create (fixture): `tests/Menu/UI/fixtures/establishment.yaml`

**Interfaces:**
- Produces: fonction Twig `menu_pdf_url(): string` → `establishment().menuPdfUrl` suffixé de `?v=<mtime>` si `public/<url>` existe, sinon l'URL nue.
- Consumes: `Establishment::menuPdfUrl()`, `EstablishmentRepositoryInterface`.

- [ ] **Step 1: Assembler `public/menu.pdf`**

Depuis la racine du projet, assembler les pages de la carte en un seul PDF (les fichiers sources ont des espaces dans leur nom) :

```bash
SRC="/home/clement/Téléchargements/Carte des Pizzas"
pdfunite "$SRC/page 1.pdf" "$SRC/page 2.pdf" "$SRC/page 3.pdf" "$SRC/page 4 .pdf" public/menu.pdf
pdfinfo public/menu.pdf   # vérifier : 4 pages
```

Si `pdfunite` (poppler) est absent, utiliser `qpdf` :

```bash
qpdf --empty --pages "$SRC/page 1.pdf" "$SRC/page 2.pdf" "$SRC/page 3.pdf" "$SRC/page 4 .pdf" -- public/menu.pdf
```

Vérifier visuellement l'ordre/contenu des pages (page 2 = les pizzas). Vérifier que `public/menu.pdf` n'est pas ignoré : `git check-ignore public/menu.pdf` doit ne rien renvoyer.

- [ ] **Step 2: Créer la fixture de test établissement**

Créer `tests/Menu/UI/fixtures/establishment.yaml` :

```yaml
name: "Giulia"
tagline: "Pizzeria napolitaine · Gorges"
address: "1 rue de la Cité des Sports, 44190 Gorges"
phone: "02 85 52 87 42"
phone_href: "+33285528742"
email: "hello@giulia-pizza-gorges.fr"
menu_pdf_url: "/menu.pdf"
order_url: "https://order.example.test/carte"
directions_url: "https://maps.example.test"
google_reviews_url: "https://reviews.example.test"
whatsapp_url: "https://wa.example.test"
social_links: []
announcement:
  active: false
  title: "À noter"
  text: "—"
```

- [ ] **Step 3: Écrire le test qui échoue**

Créer `tests/Menu/UI/MenuPdfExtensionTest.php` :

```php
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
```

- [ ] **Step 4: Lancer le test — il échoue**

Run: `php bin/phpunit tests/Menu/UI/MenuPdfExtensionTest.php`
Expected: FAIL (classe `MenuPdfExtension` inexistante).

- [ ] **Step 5: Créer l'extension Twig**

Créer `src/Menu/UI/MenuPdfExtension.php` :

```php
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
```

- [ ] **Step 6: Injecter `$publicDir` dans `config/services.yaml`**

Sous la section « Chemins des sources de données », ajouter :

```yaml
    App\Menu\UI\MenuPdfExtension:
        arguments: { $publicDir: '%kernel.project_dir%/public' }
```

- [ ] **Step 7: Lancer le test — il passe**

Run: `php bin/phpunit tests/Menu/UI/MenuPdfExtensionTest.php`
Expected: PASS (2 tests).

- [ ] **Step 8: Utiliser `menu_pdf_url()` là où on sert le PDF**

Dans `templates/components/_footer.html.twig`, le lien « Carte » :

```twig
        <a href="{{ menu_pdf_url() }}">Carte</a>
```

Dans `templates/home/index.html.twig`, la link card « Notre menu » (`href: e.menuPdfUrl`) :

```twig
            href: menu_pdf_url(), icon_variant: 'green', title: 'Notre menu', subtitle: 'Toute la carte en PDF',
```

- [ ] **Step 9: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add public/menu.pdf src/Menu/UI/MenuPdfExtension.php config/services.yaml templates/components/_footer.html.twig templates/home/index.html.twig tests/Menu/UI/MenuPdfExtensionTest.php tests/Menu/UI/fixtures/establishment.yaml
git commit -m "feat(menu): PDF de la carte assemblé et versionné par mtime (cache-busting)"
```

---

### Task 4 : Pizza du moment — VO `MonthlySpecial` + repository (ajout pur)

**Files:**
- Create: `src/Menu/Domain/MonthlySpecial.php`
- Create: `src/Menu/Domain/SpecialRepositoryInterface.php`
- Create: `src/Menu/Infrastructure/YamlSpecialRepository.php`
- Create: `config/giulia/special.yaml`
- Modify: `config/services.yaml`
- Create (test): `tests/Menu/Infrastructure/YamlSpecialRepositoryTest.php`
- Create (fixtures): `tests/Menu/Infrastructure/fixtures/special.yaml`, `tests/Menu/Infrastructure/fixtures/special_inactive.yaml`

**Interfaces:**
- Produces:
  - `MonthlySpecial`: `name(): string`, `period(): ?string`, `pitch(): ?string`, `ingredients(): string[]`, `price(): Money`, `tags(): Tag[]`, `hasTag(Tag): bool`.
  - `SpecialRepositoryInterface::current(): ?MonthlySpecial` (renvoie `null` si `active: false`).

- [ ] **Step 1: Créer les fixtures de test**

`tests/Menu/Infrastructure/fixtures/special.yaml` :

```yaml
active: true
name: "La Fresca"
period: "Édition du moment"
price: 1790
pitch: "Fraîcheur et caractère, en édition limitée."
ingredients:
  - "Sauce tomate San Marzano"
  - "Bresaola"
  - "Roquette"
tags: [spicy]
```

`tests/Menu/Infrastructure/fixtures/special_inactive.yaml` :

```yaml
active: false
name: "Rien ce mois-ci"
price: 0
ingredients: []
```

- [ ] **Step 2: Écrire le test qui échoue**

Créer `tests/Menu/Infrastructure/YamlSpecialRepositoryTest.php` :

```php
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
```

- [ ] **Step 3: Lancer le test — il échoue**

Run: `php bin/phpunit tests/Menu/Infrastructure/YamlSpecialRepositoryTest.php`
Expected: FAIL (classes inexistantes).

- [ ] **Step 4: Créer le VO `MonthlySpecial`**

Créer `src/Menu/Domain/MonthlySpecial.php` :

```php
<?php
namespace App\Menu\Domain;

use App\Shared\Domain\Money;

final readonly class MonthlySpecial
{
    /**
     * @param string[] $ingredients
     * @param Tag[]    $tags
     */
    public function __construct(
        private string $name,
        private ?string $period,
        private ?string $pitch,
        private array $ingredients,
        private Money $price,
        private array $tags,
    ) {}

    public function name(): string { return $this->name; }
    public function period(): ?string { return $this->period; }
    public function pitch(): ?string { return $this->pitch; }
    /** @return string[] */
    public function ingredients(): array { return $this->ingredients; }
    public function price(): Money { return $this->price; }
    /** @return Tag[] */
    public function tags(): array { return $this->tags; }

    public function hasTag(Tag $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
```

- [ ] **Step 5: Créer l'interface du repository**

Créer `src/Menu/Domain/SpecialRepositoryInterface.php` :

```php
<?php
namespace App\Menu\Domain;

interface SpecialRepositoryInterface
{
    public function current(): ?MonthlySpecial;
}
```

- [ ] **Step 6: Créer l'implémentation YAML**

Créer `src/Menu/Infrastructure/YamlSpecialRepository.php` :

```php
<?php
namespace App\Menu\Infrastructure;

use App\Menu\Domain\MonthlySpecial;
use App\Menu\Domain\SpecialRepositoryInterface;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use Symfony\Component\Yaml\Yaml;

final class YamlSpecialRepository implements SpecialRepositoryInterface
{
    public function __construct(private string $file) {}

    public function current(): ?MonthlySpecial
    {
        $d = Yaml::parseFile($this->file);

        if (!($d['active'] ?? false)) {
            return null;
        }

        return new MonthlySpecial(
            $d['name'],
            $d['period'] ?? null,
            $d['pitch'] ?? null,
            $d['ingredients'] ?? [],
            Money::fromCents((int) $d['price']),
            array_map(static fn (string $t) => Tag::from($t), $d['tags'] ?? []),
        );
    }
}
```

- [ ] **Step 7: Lancer le test — il passe**

Run: `php bin/phpunit tests/Menu/Infrastructure/YamlSpecialRepositoryTest.php`
Expected: PASS (2 tests).

- [ ] **Step 8: Créer `config/giulia/special.yaml`**

```yaml
active: true
name: "La Fresca"
period: "Édition du moment"
price: 1790
pitch: "Fraîcheur et caractère, en édition limitée."
ingredients:
  - "Sauce tomate San Marzano"
  - "Mozzarella fior di latte"
  - "Roquette"
  - "Bresaola"
  - "Guacamole d'avocat"
  - "Comté affiné 24 mois"
  - "Basilic"
  - "Huile d'olive"
tags: []
```

- [ ] **Step 9: Enregistrer le service**

Dans `config/services.yaml`, sous « Alias ports → adapters » ajouter :

```yaml
    App\Menu\Domain\SpecialRepositoryInterface: '@App\Menu\Infrastructure\YamlSpecialRepository'
```

Sous « Chemins des sources de données » ajouter :

```yaml
    App\Menu\Infrastructure\YamlSpecialRepository:
        arguments: { $file: '%giulia.data_dir%/special.yaml' }
```

- [ ] **Step 10: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add src/Menu/Domain/MonthlySpecial.php src/Menu/Domain/SpecialRepositoryInterface.php src/Menu/Infrastructure/YamlSpecialRepository.php config/giulia/special.yaml config/services.yaml tests/Menu/Infrastructure/YamlSpecialRepositoryTest.php tests/Menu/Infrastructure/fixtures/special.yaml tests/Menu/Infrastructure/fixtures/special_inactive.yaml
git commit -m "feat(menu): pizza du moment en fichier de config dédié (special.yaml)"
```

---

### Task 5 : Accueil — bloc « Pizza du moment » alimenté par `special.yaml`

L'accueil affiche la pizza du moment (`special`) ; repli sur la pizza mise en avant du menu (`featured`, temporaire — renommée `signature` en Task 6). Le CTA du bloc devient « Commander en Click & Collect » car la pizza du moment n'a **pas** de fiche `menu_show`.

**Files:**
- Modify: `src/Home/UI/HomeController.php`
- Modify: `templates/home/index.html.twig` (bloc `.featured`)
- Test: `tests/Functional/HomePageTest.php`

**Interfaces:**
- Consumes: `SpecialRepositoryInterface::current()` (Task 4), `MenuRepositoryInterface::featured()` (existant), `Establishment::orderUrl()` (Task 1).
- Produces (variables Twig): `special` (`?MonthlySpecial`), `featured` (`?Pizza`), `categories`.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `tests/Functional/HomePageTest.php` :

```php
    public function test_pizza_du_moment_block_shows_special(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorTextContains('.featured', 'Pizza du moment');
        self::assertSelectorTextContains('.featured', 'La Fresca');
        self::assertSelectorExists('.featured a.featured__cta[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }
```

- [ ] **Step 2: Lancer le test — il échoue**

Run: `php bin/phpunit --filter test_pizza_du_moment_block_shows_special`
Expected: FAIL (le CTA pointe vers `menu_show`, pas vers `order_url`).

- [ ] **Step 3: Injecter la pizza du moment dans le controller**

Remplacer `src/Home/UI/HomeController.php` par :

```php
<?php

namespace App\Home\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use App\Menu\Domain\SpecialRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(MenuRepositoryInterface $menu, SpecialRepositoryInterface $special): Response
    {
        return $this->render('home/index.html.twig', [
            'special' => $special->current(),
            'featured' => $menu->featured(),
            'categories' => $menu->categories(),
        ]);
    }
}
```

- [ ] **Step 4: Réécrire le bloc `.featured` du template**

Dans `templates/home/index.html.twig`, remplacer tout le bloc `{# Pizza du moment #}` (`{% if featured %}…{% endif %}`) par :

```twig
    {# Pizza du moment (special.yaml) ; repli sur la signature du menu #}
    {% set spotlight = special ?? featured %}
    {% if spotlight %}
        {% set initials = '' %}{% for w in spotlight.name|split(' ') %}{% set initials = initials ~ (w|first) %}{% endfor %}
        <div class="featured">
            <div class="featured__glow"></div>
            <div class="featured__wm">{{ initials|upper }}</div>
            <div class="featured__body">
                <div class="featured__kicker">
                    <span class="featured__dot"></span>
                    <span class="featured__label">{{ special ? 'Pizza du moment' : 'La signature' }}</span>
                </div>
                <div class="featured__head">
                    <h2 class="featured__name">{{ spotlight.name }}</h2>
                    <span class="featured__price">{{ spotlight.price.format }}</span>
                </div>
                <div class="ing-grid">
                    {% for ingredient in spotlight.ingredients %}
                        <div class="ing"><span class="ing__b">◆</span>{{ ingredient }}</div>
                    {% endfor %}
                </div>
                {% if special %}
                    <a href="{{ e.orderUrl }}" class="featured__cta">Commander en Click &amp; Collect<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d3a273" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
                {% else %}
                    <a href="{{ path('menu_show', { slug: spotlight.slug }) }}" class="featured__cta">Voir la fiche<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d3a273" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
                {% endif %}
            </div>
        </div>
    {% endif %}
```

Note : `special` (`MonthlySpecial`) et `featured` (`Pizza`) exposent tous deux `name`, `price.format`, `ingredients` — le bloc est commun ; seuls le libellé et le CTA diffèrent. Le repli `featured` garde son `slug` (une `Pizza`).

- [ ] **Step 5: Lancer le test — il passe**

Run: `php bin/phpunit --filter test_pizza_du_moment_block_shows_special`
Expected: PASS.

- [ ] **Step 6: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS (le test `test_home_renders_key_blocks` trouve toujours « La Fresca »).

- [ ] **Step 7: Commit**

```bash
git add src/Home/UI/HomeController.php templates/home/index.html.twig tests/Functional/HomePageTest.php
git commit -m "feat(home): bloc pizza du moment alimenté par special.yaml, repli signature"
```

---

### Task 6 : Renommer `featured` → `signature` (domaine Menu, controller, templates, tests unitaires)

Le concept « mis en avant » a migré vers la pizza du moment ; ce qui reste dans le menu est la **signature**. On renomme sans encore réécrire `config/giulia/menu.yaml` (fait en Task 7). La fixture de test menu, elle, passe à `signature`.

**Files:**
- Modify: `src/Menu/Domain/Pizza.php`
- Modify: `src/Menu/Domain/MenuRepositoryInterface.php`
- Modify: `src/Menu/Infrastructure/YamlMenuRepository.php`
- Modify: `src/Home/UI/HomeController.php`
- Modify: `templates/home/index.html.twig`, `templates/menu/index.html.twig`, `templates/menu/show.html.twig`
- Test: `tests/Menu/Domain/PizzaTest.php`, `tests/Menu/Infrastructure/YamlMenuRepositoryTest.php`
- Modify (fixture): `tests/Menu/Infrastructure/fixtures/menu.yaml`

**Interfaces:**
- Produces: `Pizza::isSignature(): bool` (remplace `isFeatured()`), constructeur 7e param `$signature`. `MenuRepositoryInterface::signature(): ?Pizza` (remplace `featured()`).

- [ ] **Step 1: Adapter la fixture de test menu**

Dans `tests/Menu/Infrastructure/fixtures/menu.yaml`, remplacer la clé `featured: true` de La Fresca par `signature: true`.

- [ ] **Step 2: Adapter les tests unitaires (ils échoueront)**

Dans `tests/Menu/Domain/PizzaTest.php`, dernière assertion de `test_exposes_its_data` :

```php
        self::assertFalse($pizza->isSignature());
```

Dans `tests/Menu/Infrastructure/YamlMenuRepositoryTest.php`, remplacer `test_featured_returns_la_fresca` par :

```php
    public function test_signature_returns_la_fresca(): void
    {
        $signature = $this->repo()->signature();
        self::assertNotNull($signature);
        self::assertSame('La Fresca', $signature->name());
        self::assertTrue($signature->isSignature());
    }
```

- [ ] **Step 3: Lancer les tests — ils échouent**

Run: `php bin/phpunit tests/Menu`
Expected: FAIL (`isSignature()` / `signature()` inexistants).

- [ ] **Step 4: Renommer dans le VO `Pizza`**

Dans `src/Menu/Domain/Pizza.php` : le 7e paramètre `private bool $featured` devient `private bool $signature`, et la méthode :

```php
    public function isSignature(): bool { return $this->signature; }
```

(supprimer `isFeatured()`).

- [ ] **Step 5: Renommer dans l'interface**

Dans `src/Menu/Domain/MenuRepositoryInterface.php` :

```php
    public function signature(): ?Pizza;
```

(remplace `featured()`).

- [ ] **Step 6: Renommer dans le repository**

Dans `src/Menu/Infrastructure/YamlMenuRepository.php` : dans la construction de `Pizza`, lire `signature` :

```php
                    (bool) ($p['signature'] ?? false),
```

et renommer la méthode :

```php
    public function signature(): ?Pizza
    {
        foreach ($this->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                if ($pizza->isSignature()) {
                    return $pizza;
                }
            }
        }

        return null;
    }
```

- [ ] **Step 7: Mettre à jour le controller Home**

Dans `src/Home/UI/HomeController.php`, remplacer `'featured' => $menu->featured(),` par :

```php
            'featured' => $menu->signature(),
```

(La variable Twig reste nommée `featured` pour le repli — inchangé côté template.)

- [ ] **Step 8: Mettre à jour les templates (`isFeatured` → `isSignature`, badges)**

Dans `templates/home/index.html.twig` (slider), remplacer les 3 occurrences liées à `pizza.isFeatured` :

```twig
                {% set tone = pizza.isSignature ? 'tone--dark' : ['tone--green', 'tone--blue', 'tone--tan'][loop.index0 % 3] %}
                <a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="pizza-card{{ pizza.isSignature ? ' pizza-card--dark' : '' }}">
                    <div class="pizza-card__photo {{ tone }}">
                        <span class="ph-label">Photo à venir</span>
                        {% if pizza.isSignature %}<span class="ph-badge">Signature</span>{% endif %}
```

Dans `templates/menu/index.html.twig` :

```twig
                    {% set tone = pizza.isSignature ? 'tone--dark' : ['tone--green', 'tone--blue', 'tone--tan'][loop.index0 % 3] %}
                    <a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="pizza-tile{{ pizza.isSignature ? ' pizza-tile--dark' : '' }}">
                        <div class="pizza-tile__photo {{ tone }}">
                            <span class="ph-label">Photo à venir</span>
                            {% if pizza.isSignature %}<span class="ph-badge">Signature</span>{% endif %}
```

Dans `templates/menu/show.html.twig`, le badge et la tagline :

```twig
            {% if pizza.isSignature %}<span class="ph-badge ph-badge--lg">Signature</span>{% endif %}
```

```twig
            <p class="pizza-hero__tagline">
                {%- if pizza.isSignature -%}Notre pizza signature : une base napolitaine relevée d’ingrédients de caractère, préparée à la commande.
                {%- else -%}Pizza napolitaine préparée à la commande, sur notre pâte maturée 48h.{%- endif -%}
            </p>
```

- [ ] **Step 9: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS. (`config/giulia/menu.yaml` a encore les données de démo mais avec `featured:` désormais ignoré → `signature()` renvoie `null`, l'accueil affiche la pizza du moment via `special` : `HomePageTest` reste vert. `MenuPageTest` inchangé.)

- [ ] **Step 10: Commit**

```bash
git add src/Menu/Domain/Pizza.php src/Menu/Domain/MenuRepositoryInterface.php src/Menu/Infrastructure/YamlMenuRepository.php src/Home/UI/HomeController.php templates/home/index.html.twig templates/menu/index.html.twig templates/menu/show.html.twig tests/Menu/Domain/PizzaTest.php tests/Menu/Infrastructure/YamlMenuRepositoryTest.php tests/Menu/Infrastructure/fixtures/menu.yaml
git commit -m "refactor(menu): renomme featured en signature (pizza signature de la carte)"
```

---

### Task 7 : Menu réel — 15 pizzas, 3 bases, signature GIULIA

**Files:**
- Modify: `config/giulia/menu.yaml`
- Test: `tests/Functional/MenuPageTest.php`

**Interfaces:**
- Consumes: structure `menu.yaml` lue par `YamlMenuRepository` (`categories[].{kicker,label,pizzas[]}`, pizza `{name, price, tags, allergens, ingredients, signature}`).

- [ ] **Step 1: Adapter les tests fonctionnels menu (ils échoueront)**

Dans `tests/Functional/MenuPageTest.php`, remplacer les libellés/route de démo :

```php
    public function test_menu_index_lists_categories(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Base sauce tomate');
        self::assertSelectorTextContains('body', 'Margherita');
        self::assertSelectorTextContains('body', 'Base spéciale');
    }

    public function test_pizza_page_shows_details(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/giulia');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Giulia');
        self::assertSelectorTextContains('body', 'confiture de figues');
    }
```

(`test_unknown_pizza_returns_404` et `test_menu_cta_points_to_order_url` restent inchangés.)

- [ ] **Step 2: Lancer les tests — ils échouent**

Run: `php bin/phpunit tests/Functional/MenuPageTest.php`
Expected: FAIL (le menu de démo n'a ni « Base sauce tomate » ni la pizza `giulia`).

- [ ] **Step 3: Réécrire `config/giulia/menu.yaml`**

```yaml
categories:
  - kicker: "Base San Marzano"
    label: "Base sauce tomate"
    pizzas:
      - { name: "Margherita", price: 1000, tags: [veg], allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "basilic"] }
      - { name: "Regina", price: 1450, allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "champignons cuisinés", "prosciutto cotto (jambon cuit)", "olives taggiasche", "basilic"] }
      - { name: "Giulia", price: 1590, signature: true, allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "jambon de Parme", "confiture de figues", "éclats de noisettes", "basilic", "parmesan"] }
      - { name: "Parma", price: 1590, allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "jambon de Parme", "tomates cerises confites", "parmesan", "roquette", "crème de vinaigre balsamique"] }
      - { name: "Diavola", price: 1390, tags: [spicy], allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "spianata piquante", "olives taggiasche", "basilic"] }
      - { name: "Quattro Stagioni", price: 1490, tags: [veg], allergens: [], ingredients: ["San Marzano", "mozzarella fior di latte", "aubergines", "poivrons", "artichauts", "tomates séchées", "stracciatella"] }
      - { name: "Calabrese", price: 1690, tags: [spicy], allergens: [], ingredients: ["mozzarella fior di latte", "spianata piquante", "boulettes de bœuf cuisinées", "scamorza fumée", "oignons rouges confits", "basilic"] }
  - kicker: "Base crème & mozzarella"
    label: "Base crème fraîche"
    pizzas:
      - { name: "Quattro Formaggi", price: 1490, tags: [veg], allergens: [], ingredients: ["mozzarella fior di latte", "ricotta", "gorgonzola", "taleggio"] }
      - { name: "Miele", price: 1250, tags: [veg], allergens: [], ingredients: ["mozzarella fior di latte", "chèvre cendré", "miel"] }
      - { name: "Salmone", price: 1690, allergens: [], ingredients: ["mozzarella fior di latte", "saumon fumé", "stracciatella", "huile d'olive citronnée"] }
      - { name: "Montagna Originale", price: 1590, allergens: [], ingredients: ["mozzarella fior di latte", "pommes de terre cuisinées", "oignons rouges confits", "guanciale", "taleggio"] }
  - kicker: "Nos créations"
    label: "Base spéciale"
    pizzas:
      - { name: "Pollo Rosso", price: 1590, allergens: [], ingredients: ["pesto rosso", "mozzarella fior di latte", "poulet cuisiné", "oignons rouges confits", "scamorza fumée"] }
      - { name: "Tartufo", price: 1690, allergens: [], ingredients: ["crème de truffe", "mozzarella fior di latte", "prosciutto cotto (jambon cuit)", "parmesan", "copeaux de truffes"] }
      - { name: "Camembert Rôti", price: 1550, allergens: [], ingredients: ["camembert entier rôti", "roquette", "jambon de Parme", "miel", "crème de vinaigre balsamique"] }
      - { name: "Pistacchio", price: 1620, allergens: [], ingredients: ["crème de pistaches", "mozzarella fior di latte", "mortadelle", "stracciatella", "pesto de pistaches", "éclats de pistaches", "basilic"] }
```

- [ ] **Step 4: Lancer les tests menu — ils passent**

Run: `php bin/phpunit tests/Functional/MenuPageTest.php`
Expected: PASS (la pizza `giulia` porte le badge Signature, route `/nos-pizzas/giulia` OK).

- [ ] **Step 5: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS. (`HomePageTest` : « La Fresca » vient de `special`, la signature Giulia apparaît dans le slider ; rien ne casse.)

- [ ] **Step 6: Commit**

```bash
git add config/giulia/menu.yaml tests/Functional/MenuPageTest.php
git commit -m "feat(menu): carte réelle (15 pizzas, 3 bases) avec signature Giulia"
```

---

### Task 8 : Contexte `Legal` — VO + repository + données

**Files:**
- Create: `src/Legal/Domain/Host.php`
- Create: `src/Legal/Domain/LegalNotice.php`
- Create: `src/Legal/Domain/LegalRepositoryInterface.php`
- Create: `src/Legal/Infrastructure/YamlLegalRepository.php`
- Create: `config/giulia/legal.yaml`
- Modify: `config/services.yaml`
- Create (test): `tests/Legal/Infrastructure/YamlLegalRepositoryTest.php`
- Create (fixture): `tests/Legal/Infrastructure/fixtures/legal.yaml`

**Interfaces:**
- Produces:
  - `Host`: `name(): string`, `address(): string`, `phone(): string`, `note(): ?string`.
  - `LegalNotice`: `legalName()`, `legalForm()`, `capital()`, `siren()`, `siret()`, `rcs()`, `vat()`, `ape()`, `publicationDirector()` (tous `string`), `host(): Host`.
  - `LegalRepositoryInterface::get(): LegalNotice`.

- [ ] **Step 1: Créer la fixture de test**

Créer `tests/Legal/Infrastructure/fixtures/legal.yaml` :

```yaml
editor:
  legal_name: "GIULIA PIZZAS"
  legal_form: "SARL"
  capital: "10 000 €"
  siren: "918 159 211"
  siret: "918 159 211 00013"
  rcs: "Nantes 918 159 211"
  vat: "FR73918159211"
  ape: "5610C — Restauration de type rapide"
  publication_director: "Clément GAUCI"
host:
  name: "OVH SAS"
  address: "2 rue Kellermann, 59100 Roubaix, France"
  phone: "1007"
  note: "Serveurs hébergés en France (datacenter de Gravelines)."
```

- [ ] **Step 2: Écrire le test qui échoue**

Créer `tests/Legal/Infrastructure/YamlLegalRepositoryTest.php` :

```php
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
```

- [ ] **Step 3: Lancer le test — il échoue**

Run: `php bin/phpunit tests/Legal/Infrastructure/YamlLegalRepositoryTest.php`
Expected: FAIL (classes inexistantes).

- [ ] **Step 4: Créer le VO `Host`**

Créer `src/Legal/Domain/Host.php` :

```php
<?php
namespace App\Legal\Domain;

final readonly class Host
{
    public function __construct(
        private string $name,
        private string $address,
        private string $phone,
        private ?string $note,
    ) {}

    public function name(): string { return $this->name; }
    public function address(): string { return $this->address; }
    public function phone(): string { return $this->phone; }
    public function note(): ?string { return $this->note; }
}
```

- [ ] **Step 5: Créer le VO `LegalNotice`**

Créer `src/Legal/Domain/LegalNotice.php` :

```php
<?php
namespace App\Legal\Domain;

final readonly class LegalNotice
{
    public function __construct(
        private string $legalName,
        private string $legalForm,
        private string $capital,
        private string $siren,
        private string $siret,
        private string $rcs,
        private string $vat,
        private string $ape,
        private string $publicationDirector,
        private Host $host,
    ) {}

    public function legalName(): string { return $this->legalName; }
    public function legalForm(): string { return $this->legalForm; }
    public function capital(): string { return $this->capital; }
    public function siren(): string { return $this->siren; }
    public function siret(): string { return $this->siret; }
    public function rcs(): string { return $this->rcs; }
    public function vat(): string { return $this->vat; }
    public function ape(): string { return $this->ape; }
    public function publicationDirector(): string { return $this->publicationDirector; }
    public function host(): Host { return $this->host; }
}
```

- [ ] **Step 6: Créer l'interface**

Créer `src/Legal/Domain/LegalRepositoryInterface.php` :

```php
<?php
namespace App\Legal\Domain;

interface LegalRepositoryInterface
{
    public function get(): LegalNotice;
}
```

- [ ] **Step 7: Créer l'implémentation YAML**

Créer `src/Legal/Infrastructure/YamlLegalRepository.php` :

```php
<?php
namespace App\Legal\Infrastructure;

use App\Legal\Domain\Host;
use App\Legal\Domain\LegalNotice;
use App\Legal\Domain\LegalRepositoryInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlLegalRepository implements LegalRepositoryInterface
{
    public function __construct(private string $file) {}

    public function get(): LegalNotice
    {
        $d = Yaml::parseFile($this->file);
        $e = $d['editor'];
        $h = $d['host'];

        return new LegalNotice(
            $e['legal_name'], $e['legal_form'], $e['capital'], $e['siren'], $e['siret'],
            $e['rcs'], $e['vat'], $e['ape'], $e['publication_director'],
            new Host($h['name'], $h['address'], $h['phone'], $h['note'] ?? null),
        );
    }
}
```

- [ ] **Step 8: Lancer le test — il passe**

Run: `php bin/phpunit tests/Legal/Infrastructure/YamlLegalRepositoryTest.php`
Expected: PASS (2 tests).

- [ ] **Step 9: Créer `config/giulia/legal.yaml`**

Contenu identique à la fixture de la Step 1.

- [ ] **Step 10: Enregistrer le service**

Dans `config/services.yaml`, sous « Alias ports → adapters » :

```yaml
    App\Legal\Domain\LegalRepositoryInterface: '@App\Legal\Infrastructure\YamlLegalRepository'
```

Sous « Chemins des sources de données » :

```yaml
    App\Legal\Infrastructure\YamlLegalRepository:
        arguments: { $file: '%giulia.data_dir%/legal.yaml' }
```

- [ ] **Step 11: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS.

- [ ] **Step 12: Commit**

```bash
git add src/Legal/Domain/Host.php src/Legal/Domain/LegalNotice.php src/Legal/Domain/LegalRepositoryInterface.php src/Legal/Infrastructure/YamlLegalRepository.php config/giulia/legal.yaml config/services.yaml tests/Legal/Infrastructure/YamlLegalRepositoryTest.php tests/Legal/Infrastructure/fixtures/legal.yaml
git commit -m "feat(legal): contexte Legal + legal.yaml (mentions légales officielles)"
```

---

### Task 9 : Brancher les mentions légales (controller déplacé + template)

**Files:**
- Create: `src/Legal/UI/LegalController.php`
- Delete: `src/Home/UI/LegalController.php`
- Modify: `templates/legal/mentions.html.twig`
- Test: `tests/Functional/LegalPageTest.php`

**Interfaces:**
- Consumes: `LegalRepositoryInterface::get()` (Task 8) → variable Twig `notice` (`LegalNotice`).
- Consumes (déjà dispo) : `establishment()` pour le siège (`address`) et le courriel.

- [ ] **Step 1: Écrire le test qui échoue**

Remplacer `tests/Functional/LegalPageTest.php` par :

```php
<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPageTest extends WebTestCase
{
    public function test_legal_page_renders_official_data(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        self::assertSelectorTextContains('body', 'GIULIA PIZZAS');
        self::assertSelectorTextContains('body', '918 159 211 00013');
        self::assertSelectorTextContains('body', 'OVH SAS');
        self::assertSelectorTextContains('body', 'Clément GAUCI');
    }
}
```

- [ ] **Step 2: Lancer le test — il échoue**

Run: `php bin/phpunit tests/Functional/LegalPageTest.php`
Expected: FAIL (les valeurs officielles ne sont pas dans le template en dur).

- [ ] **Step 3: Créer le controller dans le contexte Legal**

Créer `src/Legal/UI/LegalController.php` :

```php
<?php

namespace App\Legal\UI;

use App\Legal\Domain\LegalRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal', methods: ['GET'])]
    public function index(LegalRepositoryInterface $legal): Response
    {
        return $this->render('legal/mentions.html.twig', [
            'notice' => $legal->get(),
        ]);
    }
}
```

- [ ] **Step 4: Supprimer l'ancien controller**

```bash
git rm src/Home/UI/LegalController.php
```

- [ ] **Step 5: Injecter `notice` dans le template**

Dans `templates/legal/mentions.html.twig`, après `{% set e = establishment() %}`, ajouter :

```twig
    {% set e = establishment() %}
    {% set n = notice %}
```

Remplacer le tableau `editorRows` par (valeurs officielles ; raison sociale = `n.legalName`, siège = `e.address`) :

```twig
                {% set editorRows = [
                    { label: 'Raison sociale', value: n.legalName },
                    { label: 'Forme juridique', value: n.legalForm },
                    { label: 'Capital social', value: n.capital },
                    { label: 'Siège social', value: e.address },
                    { label: 'SIRET', value: n.siret },
                    { label: 'RCS', value: n.rcs },
                    { label: 'N° TVA intracom.', value: n.vat },
                    { label: 'Code APE/NAF', value: n.ape },
                    { label: 'Téléphone', value: e.phone },
                    { label: 'Courriel', value: e.email }
                ] %}
```

Supprimer la phrase `<p class="legal-hint">…à compléter…</p>` (les champs sont désormais renseignés).

Remplacer le paragraphe « Directeur de la publication » par :

```twig
            <p class="legal-text">Le directeur de la publication est <strong>{{ n.publicationDirector }}</strong>, représentant légal de la pizzeria {{ e.name }}. Pour toute question relative au contenu du site, vous pouvez le contacter par courriel à <a href="mailto:{{ e.email }}">{{ e.email }}</a>.</p>
```

Remplacer le paragraphe « Hébergement » par :

```twig
            <p class="legal-text">Ce site est hébergé par <strong>{{ n.host.name }}</strong>, {{ n.host.address }} — téléphone : {{ n.host.phone }}.{% if n.host.note %} {{ n.host.note }}{% endif %}</p>
```

- [ ] **Step 6: Lancer le test — il passe**

Run: `php bin/phpunit tests/Functional/LegalPageTest.php`
Expected: PASS.

- [ ] **Step 7: Lancer toute la suite**

Run: `php bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Legal/UI/LegalController.php templates/legal/mentions.html.twig tests/Functional/LegalPageTest.php
git rm src/Home/UI/LegalController.php
git commit -m "feat(legal): mentions légales branchées sur legal.yaml, controller déplacé dans le contexte Legal"
```

---

## Vérification finale

- [ ] **Suite complète verte** : `php bin/phpunit` (tous les tests passent, zéro deprecation/notice/warning).
- [ ] **Rendu réel** : démarrer l'app et vérifier à l'œil l'accueil (bloc pizza du moment « La Fresca » + CTA Commander), la carte (15 pizzas, badge Signature sur Giulia, CTA Click & Collect → foxorders), le PDF (`/menu.pdf?v=…`) et `/mentions-legales` (données officielles). Voir la skill `run`/`verify`.
- [ ] **Contrôle des données** : `config/giulia/` contient `establishment.yaml`, `hours.yaml`, `menu.yaml`, `special.yaml`, `legal.yaml`.
