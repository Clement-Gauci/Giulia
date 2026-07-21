# Données de configuration réelles & contexte Legal — Design

**Date :** 2026-07-22
**Statut :** validé (brainstorming), en attente de plan d'implémentation

## 1. Contexte & objectif

Le site vitrine Giulia (Symfony, **sans base de données**) stocke ses données dans des
fichiers YAML sous `config/giulia/`, consommés par des repositories via un pattern DDD
(1 contexte = 1 fichier = 1 repository). Trois fichiers existent déjà et fonctionnent :
`establishment.yaml`, `hours.yaml`, `menu.yaml`.

Cette itération poursuit deux objectifs :

1. **Renseigner les vraies valeurs** (coordonnées, réseaux, menu réel, mentions légales)
   à la place des données de démonstration.
2. **Externaliser les mentions légales** aujourd'hui codées en dur dans le template,
   via un nouveau bounded context `Legal`.

En chemin, la collecte des données réelles a révélé des éléments structurels à traiter :

- La distinction **pizza signature** (permanente, sur la carte) vs **pizza du moment**
  (éphémère, mensuelle, hors carte) — que le menu de démo mélangeait sous un flag `featured`.
- Un **bug de câblage** : les 3 CTA « Click & Collect » pointent vers le PDF de la carte
  (`menuPdfUrl`) au lieu de la plateforme de commande en ligne.
- Un besoin de **cache-busting** du PDF de la carte, remplacé régulièrement.

## 2. Décisions actées

| Sujet | Décision |
|---|---|
| Architecture mentions légales | Nouveau bounded context `Legal` (domain + repository + controller), cohérent avec `Menu`/`Opening`/`Shared`. `legal.yaml` ne porte **que** les champs juridiques ; les champs communs (raison sociale, siège, tél, email) restent lus ailleurs → zéro duplication. |
| Source des données légales | Récupérées sur societe.com (SIREN 918 159 211) + hébergeur/directeur fournis par le client. |
| Cache-busting PDF | Version **automatique** dérivée de la date de modification du fichier : `/menu.pdf?v=<mtime>`. Aucune version à gérer à la main. |
| Carte téléchargeable | Un seul PDF `public/menu.pdf` assemblé à partir des pages de la carte. |
| Périmètre `menu.yaml` | **Pizzas seules** (pas de suppléments ni glossaire) — 15 pizzas réelles, 3 bases. |
| Pizza du moment | **Fichier dédié** `config/giulia/special.yaml`, mis en avant sur l'accueil. Repli sur la signature GIULIA si `active: false`. |
| Pizza signature | GIULIA, sur la carte, flag `signature: true` dans `menu.yaml`. |
| Horaires | Inchangés (validés). |
| Tagline & `phone_href` | Conservés tels quels. |

## 3. Architecture cible

### 3.1 Contexte `Shared` (établissement)

- **`Establishment`** (VO) : ajout du champ **`orderUrl`** (lien Click & Collect).
- **`YamlEstablishmentRepository`** : mappe la nouvelle clé `order_url`.
- **`establishment.yaml`** : nouvelle clé `order_url` + toutes les valeurs réelles (§5.1).
- Les données WhatsApp/réseaux/avis/itinéraire passent aux vraies URLs.

### 3.2 Contexte `Menu`

**Renommage sémantique `featured` → `signature`** (le concept « mis en avant » quitte le
menu pour devenir la pizza du moment ; ce qui reste dans le menu est la *signature*) :

- `Pizza` : `featured` → `signature`, `isFeatured()` → `isSignature()`.
- `MenuRepositoryInterface` : `featured()` → `signature()`.
- `YamlMenuRepository` : lit la clé `signature`, expose `signature()`.

**Nouveau : pizza du moment**, dans le même contexte `Menu` (c'est une pizza) :

- `MonthlySpecial` (VO) : `name`, `period` (nullable), `pitch` (nullable),
  `ingredients: string[]`, `price: Money`, `tags: Tag[]`.
- `SpecialRepositoryInterface` : `current(): ?MonthlySpecial` (renvoie `null` si `active: false`).
- `YamlSpecialRepository` : lit `special.yaml`, renvoie `null` quand inactif.

**Nouveau : cache-busting PDF** :

- Extension Twig `MenuPdfExtension` exposant `menu_pdf_url()` :
  retourne `establishment().menuPdfUrl ~ '?v=' ~ filemtime(<public>/menu.pdf)`.
  Fallback : si le fichier est absent, renvoie l'URL sans suffixe de version.
  Reçoit `%kernel.project_dir%` par injection pour résoudre le chemin physique.

### 3.3 Contexte `Legal` (nouveau)

- **`legal.yaml`** (§5.4) : sections `editor` (champs juridiques) et `host` (hébergeur).
- `src/Legal/Domain/LegalNotice.php` (VO éditeur), `src/Legal/Domain/Host.php` (VO hébergeur),
  `src/Legal/Domain/LegalRepositoryInterface.php` (`get(): LegalNotice`).
- `src/Legal/Infrastructure/YamlLegalRepository.php`.
- `src/Legal/UI/LegalController.php` — **déplacé** depuis `src/Home/UI/LegalController.php`
  (l'ancien est supprimé). Injecte `LegalRepositoryInterface`, passe `notice` au template.
  Route inchangée : `/mentions-legales`, name `legal`.

### 3.4 Contexte `Home`

- `HomeController` : injecte `SpecialRepositoryInterface` **et** `MenuRepositoryInterface`.
  Passe au template : `special` (`?MonthlySpecial`), `signature` (`?Pizza` via `signature()`),
  `categories`.
- Suppression de `src/Home/UI/LegalController.php` (déplacé en §3.3).

### 3.5 `config/services.yaml`

- Alias + chemin pour `YamlSpecialRepository` (`%giulia.data_dir%/special.yaml`).
- Alias + chemin pour `YamlLegalRepository` (`%giulia.data_dir%/legal.yaml`).
- Argument `%kernel.project_dir%` pour `MenuPdfExtension`.

## 4. Impacts templates

| Template | Changement |
|---|---|
| `components/_footer.html.twig` | Lien « Carte » : `e.menuPdfUrl` → `menu_pdf_url()`. |
| `home/index.html.twig` | Bloc « Pizza du moment » alimenté par `special` (repli `signature`) ; CTA du bloc « Voir la fiche » → **« Commander en Click & Collect »** (`e.orderUrl`), car la pizza du moment n'a pas de fiche `menu_show`. Slider : `pizza.isFeatured` → `pizza.isSignature`, badge « Du moment » → « Signature ». CTA Click & Collect (`menuPdfUrl`) → `e.orderUrl`. Link card « Notre menu » → `menu_pdf_url()`. |
| `menu/index.html.twig` | `pizza.isFeatured` → `pizza.isSignature` ; badge « Du moment » → « Signature ★ » (aligne enfin le code sur la légende existante). CTA Click & Collect → `e.orderUrl`. |
| `menu/show.html.twig` | `pizza.isFeatured` → `pizza.isSignature` ; badge/tagline « du moment » → formulation « signature ». CTA → `e.orderUrl`. Affichage allergènes : déjà conditionné à `is not empty` → OK avec allergènes vides. |
| `legal/mentions.html.twig` | Remplace les valeurs en dur et les `—` par `notice.*` (raison sociale = `notice.legalName`, forme, capital, SIRET, RCS, TVA, directeur de publication, hébergeur `notice.host.*`). Le siège reste `establishment().address`. |

## 5. Données réelles (source d'implémentation)

### 5.1 `establishment.yaml`

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
  active: false          # conserver le bloc annonce existant tel quel (inactif)
  title: "À noter"
  text: "Fermeture exceptionnelle le 15 août. Réouverture le lendemain aux horaires habituels."
```

### 5.2 `menu.yaml` (15 pizzas, 3 bases)

Prix en centimes. Tags : `veg` selon les marqueurs de la carte (Margherita, Quattro
Stagioni, Quattro Formaggi, Miele) ; `spicy` **déduit** pour Diavola et Calabrese
(spianata piquante). Allergènes : **absents de la carte** → `allergens: []` partout
(champ conservé, le template gère le vide). Les ingrédients « après cuisson » de la carte
sont fusionnés dans la liste plate d'ingrédients, en conservant l'ordre.

**Base sauce tomate** (kicker : « Base San Marzano », label : « Base sauce tomate ») :

| Pizza | Prix | Tags | Ingrédients |
|---|---|---|---|
| Margherita | 1000 | veg | San Marzano, mozzarella fior di latte, basilic |
| Regina | 1450 | — | San Marzano, mozzarella fior di latte, champignons cuisinés, prosciutto cotto (jambon cuit), olives taggiasche, basilic |
| **Giulia** *(signature)* | 1590 | — | San Marzano, mozzarella fior di latte, jambon de Parme, confiture de figues, éclats de noisettes, basilic, parmesan |
| Parma | 1590 | — | San Marzano, mozzarella fior di latte, jambon de Parme, tomates cerises confites, parmesan, roquette, crème de vinaigre balsamique |
| Diavola | 1390 | spicy | San Marzano, mozzarella fior di latte, spianata piquante, olives taggiasche, basilic |
| Quattro Stagioni | 1490 | veg | San Marzano, mozzarella fior di latte, aubergines, poivrons, artichauts, tomates séchées, stracciatella |
| Calabrese | 1690 | spicy | Mozzarella fior di latte, spianata piquante, boulettes de bœuf cuisinées, scamorza fumée, oignons rouges confits, basilic |

**Base crème fraîche** (kicker : « Base crème & mozzarella », label : « Base crème fraîche ») :

| Pizza | Prix | Tags | Ingrédients |
|---|---|---|---|
| Quattro Formaggi | 1490 | veg | Mozzarella fior di latte, ricotta, gorgonzola, taleggio |
| Miele | 1250 | veg | Mozzarella fior di latte, chèvre cendré, miel |
| Salmone | 1690 | — | Mozzarella fior di latte, saumon fumé, stracciatella, huile d'olive citronnée |
| Montagna Originale | 1590 | — | Mozzarella fior di latte, pommes de terre cuisinées, oignons rouges confits, guanciale, taleggio |

**Base spéciale** (kicker : « Nos créations », label : « Base spéciale ») :

| Pizza | Prix | Tags | Ingrédients |
|---|---|---|---|
| Pollo Rosso | 1590 | — | Pesto rosso, mozzarella fior di latte, poulet cuisiné, oignons rouges confits, scamorza fumée |
| Tartufo | 1690 | — | Crème de truffe, mozzarella fior di latte, prosciutto cotto (jambon cuit), parmesan, copeaux de truffes |
| Camembert Rôti | 1550 | — | Camembert entier rôti, roquette, jambon de Parme, miel, crème de vinaigre balsamique |
| Pistacchio | 1620 | — | Crème de pistaches, mozzarella fior di latte, mortadelle, stracciatella, pesto de pistaches, éclats de pistaches, basilic |

Chaque pizza porte `signature: true` uniquement pour Giulia (sinon `false`/absent).

### 5.3 `special.yaml` (pizza du moment — amorcée avec La Fresca)

```yaml
active: true
name: "La Fresca"
period: "Édition du moment"      # libellé libre, à mettre à jour chaque mois (optionnel)
price: 1790
pitch: "Fraîcheur et caractère, en édition limitée."   # optionnel
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

### 5.4 `legal.yaml`

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

Le siège social affiché dans les mentions reste `establishment().address`. La raison
sociale affichée devient `notice.legalName` (« GIULIA PIZZAS »), distincte de la marque
`establishment().name` (« Giulia »).

### 5.5 PDF de la carte

Assembler `public/menu.pdf` à partir des pages sources
(`/home/clement/Téléchargements/Carte des Pizzas/page {1..4}.pdf`) via `pdfunite`
(ou `qpdf --empty --pages …`). Vérifier le contenu et l'ordre des pages 1/3/4 au moment
de l'assemblage. Versionné à l'affichage par `menu_pdf_url()` (mtime).

## 6. Tests

- **À adapter** : `PizzaTest`, `YamlMenuRepositoryTest` (+ `fixtures/menu.yaml`) pour
  `featured` → `signature` ; `YamlEstablishmentRepositoryTest` (+ fixture) pour `order_url` ;
  `HomePageTest`, `MenuPageTest`, `LegalPageTest` selon les assertions impactées.
- **Nouveaux** : `YamlSpecialRepositoryTest` (+ `fixtures/special.yaml`, dont le cas
  `active: false` → `null`), `YamlLegalRepositoryTest` (+ `fixtures/legal.yaml`),
  test de `MenuPdfExtension` (présence du suffixe `?v=`).
- La suite complète (`make test` / `phpunit`) doit rester verte.

## 7. Points ouverts / hors périmètre

- **Allergènes** : non fournis par la carte → non affichés pour l'instant. Peuvent être
  renseignés ultérieurement dans `menu.yaml` sans changement de structure.
- **Suppléments & glossaire** de la carte : volontairement exclus de cette itération
  (nécessiteraient de nouvelles sections d'UI).
- **`period` de la pizza du moment** : libellé libre géré manuellement par le client.
- **Dispositif `/join-whatsapp-group`** (tracking des scans QR) du site actuel : hors
  périmètre — l'accueil pointe directement vers le groupe.

## 8. Note de périmètre

Cette itération dépasse la « simple mise en place de fichiers de config » : elle inclut un
nouveau contexte `Legal`, un refactoring `featured → signature`, l'ajout de la pizza du
moment, la correction du câblage Click & Collect et le versioning du PDF. L'ensemble reste
**cohérent** (rendre les données réelles opérationnelles) et tient dans un seul plan
d'implémentation, découpable en lots si besoin : (1) établissement + Click & Collect + PDF,
(2) menu réel + signature/pizza du moment, (3) contexte Legal.
