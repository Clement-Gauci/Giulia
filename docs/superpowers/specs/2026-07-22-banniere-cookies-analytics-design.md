# Bannière cookies + librairie analytics GA4 — Design

**Date** : 2026-07-22
**Statut** : approuvé (brainstorming)
**Contexte projet** : site vitrine Giulia (Symfony + AssetMapper + Stimulus + Turbo, DDD par contexte, pas de base de données, emails synchrones).

## Objectif

1. Intégrer la **bannière de consentement cookies** définie dans le design system
   (`.claude/design-system/Cookies.dc.html`) sur l'ensemble du site.
2. Ajouter sur la page **Mentions légales** un bouton de **réouverture** de la bannière
   (design mis à jour dans `.claude/design-system/Mentions légales.dc.html`).
3. Construire une **librairie analytics propre** (Google Analytics 4) qui ne s'active
   que si un identifiant est présent dans le `.env` **et** que l'utilisateur a consenti
   à la mesure d'audience, et qui trace pages vues + événements métier.

## Principe directeur

Trois briques indépendantes, à responsabilité unique, découplées par un seul événement
DOM (`giulia:consent-changed`). La bannière ne connaît pas GA ; l'analytics ne connaît
pas le DOM de la bannière. Chaque brique est compréhensible et modifiable isolément.

| Brique | Rôle | Emplacement |
|---|---|---|
| Consentement (module JS pur) | Source de vérité du choix : lit/écrit `localStorage`, expose `get()/set()`, émet `giulia:consent-changed`. Aucun DOM. | `assets/consent/consent.js` |
| Analytics (module JS pur) | Charge GA4 uniquement si `measurementId` présent **et** audience accordée. Expose `track()`. No-op sinon. S'abonne au consentement. | `assets/analytics/analytics.js` |
| Bannière (Stimulus + Twig) | UI bandeau + pastille permanente. Appelle uniquement `consent.set()`. | `assets/controllers/cookie_consent_controller.js` + `templates/components/_cookie_banner.html.twig` |

## 1. Backend Symfony — configuration GA

- `.env` : ajout de `GA_MEASUREMENT_ID=` (vide par défaut → analytics totalement désactivé).
- `config/services.yaml` : `parameters.giulia.ga_measurement_id: '%env(default::GA_MEASUREMENT_ID)%'`.
- `config/packages/twig.yaml` : global Twig `ga_measurement_id: '%giulia.ga_measurement_id%'`.
- `templates/base.html.twig` : dans le `<head>`, `<meta name="ga-measurement-id" content="{{ ga_measurement_id }}">`.

**Décision** : global Twig plutôt qu'un contexte DDD `Analytics`. L'identifiant GA4 n'est
que de la configuration d'infrastructure, pas du métier (la logique réelle vit côté JS).
L'ID GA4 (`G-XXXXXXXXXX`) n'est pas un secret : il est de toute façon exposé dans le
script GA.

## 2. Module consentement (`assets/consent/consent.js`)

Logique pure, sans dépendance DOM au-delà de `localStorage` et de la répartition d'un
`CustomEvent`. Contrat :

- `getConsent()` → `{ analytics: boolean, marketing: boolean }` ou `null` si aucun choix
  valide enregistré.
- `setConsent({ analytics, marketing })` → persiste puis émet `giulia:consent-changed`
  sur `document` avec le détail du consentement.
- Stockage : `localStorage.giulia_consent = { analytics, marketing, ts, v: 1 }`.
- Validité : le choix est considéré expiré si absent, si `v` diffère de la version
  courante, ou si `ts` remonte à plus de **6 mois** → la bannière se réaffiche.
- Essentiels : toujours actifs, non stockés comme option (implicites).

Le stockage du choix lui-même est un état essentiel (autorisé sans consentement préalable).

## 3. Librairie analytics (`assets/analytics/analytics.js`)

- À l'import (dans `assets/app.js`) : lit `<meta name="ga-measurement-id">` et l'état de
  consentement.
  - ID vide → **no-op total, définitif**. Aucun script, aucun cookie.
  - ID présent, audience refusée/inconnue → en attente, s'abonne à `giulia:consent-changed`.
  - ID présent, audience accordée → charge GA4.
- Chargement GA4 : injection dynamique de `gtag.js` (`https://www.googletagmanager.com/gtag/js?id=<ID>`)
  **seulement après consentement** (approche stricte « rien chargé sans accord », pas de
  Consent Mode v2 denied-by-default). `gtag('config', <ID>, { anonymize_ip: true })`.
- Retrait du consentement (audience repassée à OFF) : pose `window['ga-disable-<ID>'] = true`
  et purge les cookies `_ga*`. Plus aucune collecte à la page suivante.
- API publique : `window.giuliaAnalytics.track(name, params)` et `pageview()`.
  - `track()`/`pageview()` sont **no-op** tant que GA n'est pas actif.
- Pages vues : envoyées à l'activation puis sur chaque `turbo:load` (navigation Turbo).

## 4. Instrumentation des événements (pack complet)

Attribut explicite `data-ga-event="<nom>"` (+ éventuels `data-ga-*` pour les paramètres,
ex. `data-ga-label`) posé sur les CTA existants. `analytics.js` écoute les clics par
**délégation** au niveau `document` : à chaque clic sur un élément portant `data-ga-event`,
il appelle `track()`. Aucun contrôleur Stimulus par lien.

Événements (sources : `config/giulia/establishment.yaml`, `templates/home/index.html.twig`,
`templates/components/_link_card.html.twig`, templates menu) :

| Nom | Déclencheur |
|---|---|
| `click_and_collect` | liens `order_url` (hero « featured » + CTA accueil) |
| `pizza_view` | ouverture d'une fiche pizza (`menu_show`) |
| `menu_index` | lien « Voir toute la carte » |
| `menu_pdf` | téléchargement carte PDF |
| `directions` | itinéraire Google Maps |
| `phone` | liens `tel:` |
| `google_review` | lien avis Google |
| `email` | liens `mailto:` |
| `whatsapp_antigaspi` | groupe WhatsApp anti-gaspi |
| `social` | réseaux sociaux (paramètre `label` = Instagram/Facebook) |
| `cookie_consent` | choix de consentement (paramètre : `all` / `necessary` / `custom`) |

## 5. Bannière UI

Composant `templates/components/_cookie_banner.html.twig`, inclus **une fois** dans
`base.html.twig` après le footer, piloté par `cookie_consent_controller`. Fidèle au comp
`Cookies.dc.html` :

- Bandeau ancré en bas, affiché si `getConsent()` renvoie `null` : bandeau « Un p'tit
  cookie avec ça ? », intro, lien « En savoir plus » → mentions légales.
- Préférences dépliables (« Composer ma recette ») : *pâte de base* (toujours actif),
  *garniture* (audience, toggle), *supplément piquant* (marketing, toggle).
- Actions : « Tout accepter, régalez-moi ! » (audience+marketing ON) / « Le strict
  nécessaire » (tout OFF) / « Enregistrer mes choix » (état des toggles).
- **Pastille flottante permanente** « 🍪 Gérer les cookies » en bas de chaque page.
  L'auto-masquage après 5 s présent dans le comp est un artefact de démo : **retiré**,
  la pastille reste visible en permanence (accès permanent au retrait du consentement).
- Style : classes CSS ajoutées à `assets/styles/app.css` dans le langage visuel existant
  (tokens crème/terracotta), pas de styles inline. Toggles OFF par défaut (opt-in strict).

Le contrôleur Stimulus expose une action `reopen` (rouvre le bandeau) et met à jour
l'UI selon l'état du consentement au `connect()`.

## 6. Page mentions légales

`templates/legal/mentions.html.twig`, section Cookies :

- Texte aligné sur le comp : « Ce site utilise uniquement des cookies techniques
  nécessaires à son bon fonctionnement **et, le cas échéant, à la mesure d'audience
  anonyme**. Aucun cookie publicitaire… ».
- Bouton **« 🍪 Gérer mes cookies »** avec `data-action="cookie-consent#reopen"` (rouvre
  la bannière, **pas** de navigation). Le composant bannière étant dans `base.html.twig`,
  le contrôleur est disponible sur cette page.

## 7. Conformité CNIL

- Essentiels toujours ON ; audience + marketing **OFF par défaut** (opt-in strict).
- Refus aussi simple que l'accord (« Le strict nécessaire » au même niveau visuel que
  « Tout accepter » ; pastille de réouverture permanente).
- Mémorisation du choix (accord comme refus) pendant **6 mois**, puis re-sollicitation.
- `anonymize_ip` activé sur GA4.

## 8. Tests

- **PHP** : test que le global Twig expose l'identifiant (rendu présent quand défini, vide
  sinon). Cohérent avec la suite existante (67 tests).
- **JS** : la logique pure de `consent.js` (set/get/expiration/versioning) est isolée pour
  être testable. Le projet n'a pas de runner JS ; **arbitrage reporté au plan** : ajouter
  un runner léger (Vitest) pour `consent.js` **ou** vérification manuelle documentée.

## 9. Hors périmètre (YAGNI)

- Pas de pixel marketing réel (toggle préparatoire uniquement).
- Pas de Consent Mode v2.
- Pas de bandeau multi-langue.
- Pas de journalisation serveur des consentements.
- Pas de contexte DDD `Analytics` côté PHP (global Twig suffit).

## Fichiers touchés

**Créés**
- `assets/consent/consent.js`
- `assets/analytics/analytics.js`
- `assets/controllers/cookie_consent_controller.js`
- `templates/components/_cookie_banner.html.twig`
- (éventuel) test PHP du global Twig
- (éventuel) `assets/consent/consent.test.js` + config runner

**Modifiés**
- `.env`
- `config/services.yaml`
- `config/packages/twig.yaml`
- `assets/app.js`
- `assets/styles/app.css`
- `templates/base.html.twig`
- `templates/legal/mentions.html.twig`
- `templates/home/index.html.twig`, `templates/components/_link_card.html.twig`,
  templates menu (attributs `data-ga-event`)

## Vérification CSP

Le dépôt vient d'ajouter `public/.htaccess`. Vérifier au plan qu'aucune CSP restrictive ne
bloque `googletagmanager.com` / `google-analytics.com` ; ajouter les directives si besoin.
