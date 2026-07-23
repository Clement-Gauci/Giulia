# SEO & Sitemap — Design

**Date :** 2026-07-23
**Contexte :** site vitrine Symfony (DDD) de la pizzeria Giulia. Il manque un sitemap, un
robots.txt et plusieurs balises SEO. Cet audit + design couvre la mise en place.

## Objectifs

1. Exposer un **sitemap.xml** listant toutes les pages indexables (dont une par pizza).
2. Ajouter les briques SEO manquantes identifiées à l'audit : **robots.txt**, **JSON-LD
   Restaurant**, **canonical**, **Open Graph / Twitter Cards**.
3. Rendre les URLs absolues correctes en production (domaine canonique).

## Audit — état des lieux

Pages indexables : `/` (home), `/nos-pizzas` (menu_index), `/nos-pizzas/{slug}`
(menu_show, une par pizza), `/contact`, `/mentions-legales`.
À exclure de l'indexation : `/api/status` (JSON), `/join-whatsapp-group` (redirection tracking).

Déjà en place : `<title>` + `<meta description>` uniques par page, `lang="fr"`, viewport,
favicon, URLs sémantiques.

Manquant (traité par ce design) : sitemap.xml, robots.txt, JSON-LD LocalBusiness/Restaurant,
canonical, Open Graph / Twitter Cards, domaine de prod pour URLs absolues.

## Décisions

- **Sitemap dynamique** via une route `/sitemap.xml` (toujours à jour ; pas de fichier à
  régénérer). **Pas de commande console** : la génération dynamique rend inutile toute
  régénération manuelle (YAGNI). Une notification moteurs (IndexNow) pourra être ajoutée
  plus tard, une fois le site en ligne.
- **robots.txt statique** (`public/robots.txt`) : aucun besoin de dynamisme.
- **Domaine canonique** : `https://giulia-pizza-gorges.fr` (sans www).
- **Pas d'`aggregateRating`** dans le JSON-LD : aucune note chiffrée fiable en config ; on ne
  fabrique pas de données structurées (risque de pénalité Google).

## Architecture — nouveau contexte `src/Seo/`

Le SEO est transversal (agrège Menu + Opening + config établissement). On crée un bounded
context dédié, cohérent avec l'organisation DDD existante.

```
src/Seo/
  Domain/SitemapUrl.php               # value object : loc, changefreq, priority, lastmod?
  Application/SitemapUrlProvider.php   # construit la liste des SitemapUrl
  Application/StructuredDataBuilder.php# construit le tableau JSON-LD Restaurant
  UI/SitemapController.php             # GET /sitemap.xml
  UI/SeoExtension.php                  # fonction Twig giulia_structured_data()
```

### SitemapUrl (Domain)

Value object immuable décrivant une entrée : `loc` (chemin relatif, ex. `/nos-pizzas`),
`changefreq` (string), `priority` (float). `lastmod` optionnel (omis faute de source de date
fiable). Getters simples.

### SitemapUrlProvider (Application)

- Dépend de `MenuRepositoryInterface`.
- `urls(): SitemapUrl[]` renvoie :
  - routes statiques : `/` (priority 1.0), `/nos-pizzas` (0.9), `/contact` (0.6),
    `/mentions-legales` (0.3) ;
  - une entrée `/nos-pizzas/{slug}` par pizza (priority 0.7), en itérant
    `categories()` → `pizzas()` → `slug()`.
- `/api/status` et `/join-whatsapp-group` ne sont jamais ajoutés (exclusion par construction).
- Ne génère pas d'URL absolue : le préfixe `site_url` est appliqué à l'affichage.

### SitemapController (UI)

- Route `#[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]`.
- Injecte `SitemapUrlProvider`, rend `templates/seo/sitemap.xml.twig` avec `Content-Type:
  application/xml; charset=UTF-8`.
- Le template concatène `site_url` + `loc` pour produire des URLs absolues canoniques
  (indépendantes du host de la requête).

### StructuredDataBuilder (Application) + SeoExtension (UI)

- `StructuredDataBuilder` dépend de la config établissement (paramètres) et de
  `ScheduleRepositoryInterface` (horaires).
- `build(): array` produit un schema.org `Restaurant` :
  `@context`, `@type=Restaurant`, `name`, `url` (=site_url), `telephone`,
  `address` (PostalAddress : rue, code postal, ville, pays), `geo` (GeoCoordinates lat/long),
  `openingHoursSpecification` (générées depuis `WeeklySchedule` : un bloc par plage horaire et
  par jour), `servesCuisine` ("Pizza napolitaine"), `priceRange` ("€€"),
  `hasMenu` (URL du menu), `acceptsReservations` (false), `image` (logo absolu),
  `sameAs` (liens Instagram + Facebook depuis `social_links`).
- **Sans** `aggregateRating`.
- `SeoExtension` expose une fonction Twig `giulia_structured_data()` qui retourne le JSON
  encodé, appelée dans `base.html.twig`.

## Templates

### `templates/base.html.twig` (modifié)

Dans `<head>`, ajouter :
- `<link rel="canonical" href="{{ site_url }}{{ app.request.pathInfo }}">` (bloc surchargeable).
- Open Graph : `og:type` (bloc, défaut `website`), `og:site_name` ("Giulia"), `og:locale`
  (`fr_FR`), `og:title` (=title), `og:description` (=description), `og:url` (=canonical),
  `og:image` (défaut : `giulia-logo.png` en URL absolue, bloc surchargeable).
- Twitter : `twitter:card=summary_large_image`, `twitter:title`, `twitter:description`,
  `twitter:image`.
- `<script type="application/ld+json">{{ giulia_structured_data()|raw }}</script>`.

Les blocs `title`/`description` existants sont réutilisés (pas de duplication). De nouveaux
blocs `og_type` et `og_image` permettent une surcharge par page (ex. `article` pour les
mentions légales).

### `templates/seo/sitemap.xml.twig` (nouveau)

`<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">` bouclant sur les
`SitemapUrl` : `<loc>`, `<changefreq>`, `<priority>`. Pas de whitespace parasite avant
`<?xml`.

## Fichiers statiques & config

- `public/robots.txt` : `User-agent: *`, `Allow: /`, `Disallow: /api/`,
  `Sitemap: https://giulia-pizza-gorges.fr/sitemap.xml`.
- Paramètre `app.site_url` (valeur `%env(DEFAULT_URI)%`) exposé en variable globale Twig
  `site_url` (via `config/packages/twig.yaml`).
- `.env` : `DEFAULT_URI=http://localhost` reste (dev). `.env.local.dist` : documenter
  `DEFAULT_URI=https://giulia-pizza-gorges.fr` pour la prod.

## Flux de données

```
GET /sitemap.xml
  → SitemapController
    → SitemapUrlProvider::urls()  (MenuRepositoryInterface → pizzas)
    → render sitemap.xml.twig (site_url + loc)  → application/xml

toute page HTML
  → base.html.twig
    → giulia_structured_data()  → StructuredDataBuilder::build()
        (config établissement + ScheduleRepositoryInterface)
    → canonical + OG + Twitter depuis title/description/site_url/pathInfo
```

## Gestion d'erreurs / cas limites

- Menu vide : sitemap contient uniquement les routes statiques (pas d'erreur).
- Slug avec caractères spéciaux : échappement XML par Twig (`|escape` implicite en contexte).
- `site_url` avec ou sans slash final : normalisé (pas de double `//`).
- JSON-LD : encodage `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` pour des URLs et accents
  propres.

## Tests (cohérent avec la culture DDD du projet)

- `SitemapUrlProviderTest` (unit) : avec un `MenuRepositoryInterface` factice (2 pizzas),
  vérifie la présence des 4 routes statiques + 2 URLs de pizzas et l'absence de
  `/api/status` / `/join-whatsapp-group`.
- `SitemapControllerTest` (fonctionnel) : `GET /sitemap.xml` → 200, `Content-Type`
  `application/xml`, contient `<urlset>` et des URLs absolues `https://giulia-pizza-gorges.fr`.
- `StructuredDataBuilderTest` (unit) : le tableau contient `@type=Restaurant`, `address`,
  `geo`, `telephone`, un nombre attendu d'`openingHoursSpecification`, `sameAs`, et **pas**
  d'`aggregateRating`.
- `SeoHeadTest` (fonctionnel) : `GET /` contient `rel="canonical"`, des balises `og:` et un
  `<script type="application/ld+json">`.

## Hors-scope (à faire plus tard)

- Image sociale dédiée **1200×630** (photo de pizza) pour `og:image` : à fournir par le
  propriétaire du site ; en attendant on utilise `giulia-logo.png`.
- Notification moteurs (IndexNow / Search Console) : pertinent une fois le site en ligne.
- `aggregateRating` : possible si une source de note fiable (API Google) est un jour intégrée.
