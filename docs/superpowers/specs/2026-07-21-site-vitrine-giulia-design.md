# Conception — Site vitrine Giulia (Symfony, DDD léger)

**Date :** 2026-07-21
**Statut :** validé (design), en attente de plan d'implémentation
**Auteur :** Clément GAUCI + Claude

## 1. Contexte & objectif

Remplacer le site vitrine actuel de la **pizzeria Giulia** (napolitaine, 1 rue de la
cité des sports, 44190 Gorges) par une application **Symfony 8.1 / PHP 8.4** neuve,
construite en **DDD léger sans persistance**.

Le site est une **vitrine « link-in-bio »** : contenu essentiellement figé (carte,
horaires, coordonnées), enrichi de deux poches de logique réelle — le **statut
d'ouverture calculé en direct** et un **formulaire de contact** avec envoi serveur.

La spécification visuelle de référence est le design system versionné dans
[.claude/design-system/](../../../.claude/design-system/) (maquettes `*.dc.html`,
charte, logos). Ces maquettes sont des *comps* du runtime Claude Design : elles
servent de **spec visuelle à porter en Twig/CSS**, pas de code à exécuter.

## 2. Décisions structurantes (validées)

| Décision | Choix retenu |
|---|---|
| Périmètre | Vitrine statique (pas de commande en ligne, pas de back-office) |
| Style d'architecture | DDD léger **sans persistance** (données en fichiers YAML) |
| Organisation de `src/` | **Modular monolith** : un dossier par contexte métier, couches internes |
| Formulaire de contact | **Envoi serveur** (Symfony Form + Validator → Mailer) |
| Statut « live » | Calcul serveur au rendu **+** rafraîchissement Stimulus via `GET /api/status` (60 s) |
| Google Fonts | **Self-host** via AssetMapper (woff2 locaux, pas d'appel tiers) |
| Fiche pizza | Routée par **slug** (`/nos-pizzas/{slug}`), pas de query string |
| Tests | **TDD** complet sur le domaine + tests fonctionnels « smoke » sur chaque route |
| Langue | Français uniquement |

**Principe directeur :** le domaine ne dépend ni de Symfony, ni de Twig, ni de YAML.
Toute la mécanique d'infrastructure (chargement YAML, Mailer) est cachée derrière des
interfaces déclarées dans les couches `Domain`/`Application`. Passer un jour à une base
de données ou à un back-office ne doit toucher **que** la couche `Infrastructure`.

## 3. Architecture générale

Modular monolith orienté DDD. Quatre contextes : trois métier (**Menu**, **Opening**,
**Contact**) et un socle partagé (**Shared**). Chaque contexte expose ses objets de
domaine et masque sa source de données derrière une interface.

```
src/
  Menu/
    Domain/          Pizza, Category, Tag, MenuRepositoryInterface
    Infrastructure/  YamlMenuRepository
    UI/              MenuController
  Opening/
    Domain/          WeeklySchedule, TimeRange, OpeningStatus, Clock, ScheduleRepositoryInterface
    Infrastructure/  YamlScheduleRepository, SystemClock (Europe/Paris)
    UI/              StatusController (/api/status), OpeningStatusView (helper)
  Contact/
    Domain/          ContactMessage, Subject, ContactMailerInterface
    Application/     SendContactMessage (handler)
    Infrastructure/  SymfonyContactMailer
    UI/              ContactController, ContactType
  Shared/
    Domain/          Establishment, Money, Weekday, SocialLink, Announcement (MOTD)
    Infrastructure/  YamlEstablishmentRepository
config/giulia/
  menu.yaml · hours.yaml · establishment.yaml    ← contenu éditable sans toucher au code
```

Le mapping PSR-4 `App\` → `src/` reste inchangé (ex. `App\Menu\Domain\Pizza`).

## 4. Modèle de domaine

### 4.1 Opening (le contexte le plus riche)

- **`TimeRange`** — value object : minute d'ouverture / minute de fermeture (0–1440),
  invariants (`open < close`). Helper de formatage `10h`, `14h30`.
- **`WeeklySchedule`** — 7 jours (`Weekday`) → liste de `TimeRange` triée. Construit
  depuis `hours.yaml`.
- **`Clock`** (interface) → `SystemClock` renvoie l'instant courant en **Europe/Paris**.
  Injecté partout où « maintenant » est nécessaire → **tests déterministes**.
- **`OpeningStatus::compute(WeeklySchedule, \DateTimeImmutable $now): OpeningStatus`** —
  reproduit fidèlement la logique de la maquette :
  - dans un créneau → `open=true`, `label="Ouvert"`, `detail="Ouvert jusqu'à 21h30"` ;
  - avant un créneau plus tard le jour même → `"Ouvre aujourd'hui à 17h"` ;
  - sinon, recherche du prochain jour ouvré → `"Ouvre demain à 10h"` /
    `"Ouvre samedi à 10h"` ;
  - expose `open` (bool), `label`, `detail`.

### 4.2 Menu

- **`Money`** (Shared) — montant en centimes + devise EUR, formatage `11,90 €`
  (espace insécable).
- **`Tag`** — enum : `Vegetarian` (🌱), `Spicy` (🌶️). Chaque tag porte son libellé et
  son icône.
- **`Pizza`** — value object : `name`, `slug` (dérivé du nom), `ingredients` (liste),
  `price` (`Money`), `tags` (`Tag[]`), `allergens` (liste, optionnelle — champ explicite
  dans `menu.yaml`, plus la dérivation en dur de la maquette), `featured` (bool = « du
  moment » / signature).
- **`Category`** — `kicker` (ex. « Base tomate San Marzano »), `label` (ex. « Les
  rouges »), `pizzas` (`Pizza[]`).
- **`MenuRepositoryInterface`** — `categories(): Category[]`,
  `findBySlug(string): ?Pizza`, `featured(): ?Pizza`. Implémentation `YamlMenuRepository`.

### 4.3 Contact

- **`Subject`** — enum : `question générale`, `commande click & collect`,
  `événement / grande commande`, `allergie ou régime`, `autre`.
- **`ContactMessage`** — value object validé : `name`, `email`, `phone` (optionnel),
  `Subject`, `message`. Contraintes de validation (`NotBlank`, `Email`, longueurs).
- **`ContactMailerInterface`** (port) → `SymfonyContactMailer` (adapter Mailer).
- **`SendContactMessage`** (Application) — reçoit un `ContactMessage`, construit
  l'e-mail (`hello@giulia-pizza-gorges.fr`, sujet préfixé `[<Subject>]`, reply-to =
  e-mail visiteur) et l'envoie via le port.

### 4.4 Shared

- **`Establishment`** — nom, adresse, `tel`, e-mail, `SocialLink[]`, liens link-in-bio
  (menu PDF, itinéraire Maps, avis Google, WhatsApp anti-gaspi), `Announcement` (MOTD).
- **`Announcement`** — MOTD : `active` (bool), `title`, `text`.
- **`Weekday`** — enum ordonné lundi→dimanche, avec libellés FR.

## 5. Sources de données (YAML)

Contenu métier extrait des maquettes, recopié en YAML dans `config/giulia/` :

- **`hours.yaml`** — horaires (Europe/Paris) : Mar–Jeu 10h–14h30 / 17h–21h30 · Ven–Sam
  10h–14h30 / 17h–22h · Dim 18h–21h30 · Lun fermé.
- **`menu.yaml`** — 3 catégories, ~12 pizzas (données exactes dans
  `Nos pizzas.dc.html`) : Les rouges (Margherita, Regina, Napoli, Diavola, Capricciosa,
  Vegetariana, Calabrese), Les blanches (Quattro Formaggi, Boscaiola, Burratina,
  Tartufo), La signature (La Fresca — `featured`).
- **`establishment.yaml`** — coordonnées, réseaux, liens link-in-bio, MOTD par défaut.

Les repositories YAML parsent ces fichiers vers les objets de domaine (jamais de tableau
associatif brut qui fuiterait vers la vue).

## 6. Pages & routes

| Route | Méthode | Page | Contexte(s) |
|---|---|---|---|
| `/` | GET | Accueil link-in-bio (statut, MOTD, pizza du moment, slider, C&C, liens, horaires) | Opening + Menu + Shared |
| `/nos-pizzas` | GET | Carte complète (3 catégories, légende tags) | Menu + Opening |
| `/nos-pizzas/{slug}` | GET | Fiche pizza (nom, prix, ingrédients, allergènes, tags) | Menu |
| `/contact` | GET · POST | Formulaire serveur (Form + Validator → Mailer), carte, horaires | Contact + Shared |
| `/mentions-legales` | GET | Mentions légales (statique) | Shared |
| `/api/status` | GET | Statut live (JSON `{open, label, detail}`) | Opening |

Slug de pizza dérivé du nom (`la-fresca`, `quattro-formaggi`, …) via `symfony/string`.

## 7. Statut « en direct »

- **Source de vérité = serveur** (PHP). `OpeningStatus::compute` est appelé au rendu de
  chaque page affichant le badge.
- **Effet live** : un contrôleur **Stimulus** (`live-status`) interroge `GET /api/status`
  toutes les 60 s et met à jour le badge (label + point coloré). Fidèle à la maquette qui
  rafraîchit toutes les 30 s, sans dépendre d'un runtime React.
- **Cache** : l'accueil et l'endpoint status ne sont pas mis en cache HTTP long (le statut
  doit rester frais).

## 8. Présentation, design & assets

- **`base.html.twig`** portant la charte : fond crème `#f4ede0`, cartes `#fffdf8`/`#fbf6ec`,
  encre `#2a3138`, accent terracotta `#d3a273`/`#b3743f`, vert ouvert/végé `#5c8a49`, rouge
  piquant `#a24b32`. Tokens exposés en **variables CSS** dans `assets/styles/`.
- **Typographies self-hostées** via AssetMapper : **Bricolage Grotesque** (titres,
  500–800) + **DM Sans** (texte, 400–700), fichiers woff2 locaux.
- Le style inline des maquettes est **porté en CSS propre et réutilisable**. Composants
  identifiés : badge de statut, carte pizza, chip de tag, panneau horaires, encart MOTD,
  bloc CTA Click & Collect, liens link-in-bio.
- **Stimulus** : `live-status` (badge), `pizza-slider` (slider auto de l'accueil, respecte
  `prefers-reduced-motion`). Turbo activé (déjà présent).
- **Logos/icônes** : `giulia-icon.png`, `giulia-logo.png`, `giulia-wordmark.png` depuis le
  design system → `assets/`.
- **SEO** : `<title>` et meta description par page (contenus repris des maquettes), favicon
  Giulia.

## 9. Stratégie de tests (TDD)

- **Domaine (couverture à fond, TDD) :**
  - `OpeningStatus` — tous les cas via `Clock` figée : ouvert, ouvre plus tard aujourd'hui,
    ouvre demain, ouvre un autre jour, lundi fermé, bornes de créneaux (min incluse,
    fermeture exclue).
  - `TimeRange` / `WeeklySchedule` — invariants, tri, formatage.
  - `Money` — formatage FR (espace insécable), égalité.
  - Repositories YAML — parsing fichier → objets de domaine (fixtures YAML de test).
  - `SendContactMessage` — construction de l'e-mail, envoi via `ContactMailerInterface`
    espion, cas de validation.
- **Fonctionnel (smoke) :** chaque route répond `200` (ou `302` après POST contact valide)
  et contient le contenu clé (titre, badge, nom de pizza…). `/api/status` renvoie un JSON
  bien formé. POST contact invalide → réaffiche le formulaire avec erreurs ; valide →
  e-mail capturé par le collector de test.

## 10. Hors périmètre (YAGNI)

- Pas de base de données, pas de migrations Doctrine (bundles présents mais inutilisés
  pour ce MVP).
- Pas de back-office / administration.
- Pas de commande en ligne, panier, paiement, créneaux de retrait (Click & Collect =
  liens sortants uniquement).
- Pas de multilingue.
- Pas d'authentification (security-bundle présent mais non configuré au-delà du défaut).

## 11. Arborescence cible (récapitulatif)

```
config/giulia/{menu,hours,establishment}.yaml
src/
  Menu/{Domain,Infrastructure,UI}
  Opening/{Domain,Infrastructure,UI}
  Contact/{Domain,Application,Infrastructure,UI}
  Shared/{Domain,Infrastructure}
templates/
  base.html.twig
  home/index.html.twig
  menu/{index,show}.html.twig
  contact/index.html.twig
  legal/mentions.html.twig
  components/…            (badge statut, carte pizza, tag, horaires, MOTD, CTA)
assets/
  styles/…                (tokens + composants)
  fonts/…                 (Bricolage Grotesque, DM Sans — woff2)
  controllers/{live_status,pizza_slider}_controller.js
tests/
  Menu/… Opening/… Contact/… (unitaires domaine)
  Functional/…               (smoke routes)
```
