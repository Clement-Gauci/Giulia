# Bannière cookies + librairie analytics GA4 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une bannière de consentement cookies RGPD sur tout le site, une librairie Google Analytics 4 qui ne s'active que sur consentement + identifiant `.env`, le tracking d'événements métier, et un bouton de réouverture sur les mentions légales.

**Architecture :** Trois briques découplées par l'événement DOM `giulia:consent-changed` : un module de consentement pur (`consent.js`), une librairie analytics pure (`analytics.js`), et une UI de bannière (contrôleur Stimulus + composant Twig). Le backend Symfony n'expose que l'identifiant GA via un global Twig rendu dans une meta tag.

**Tech Stack :** Symfony 7 (AssetMapper, Twig, PHPUnit), Stimulus + Turbo (Hotwired), Google Analytics 4 (gtag.js).

## Global Constraints

- **Opt-in strict** : catégories audience et marketing **OFF par défaut** ; essentiels toujours ON.
- **Rien chargé sans accord** : `gtag.js` n'est injecté qu'après consentement audience. ID `.env` vide → **no-op total, définitif**.
- **Nom de variable d'env** : `GA_MEASUREMENT_ID` (format GA4 `G-XXXXXXXXXX`).
- **Mémorisation du choix** : 6 mois (accord comme refus), versionné (`v: 1`) ; re-sollicitation au-delà.
- **`anonymize_ip: true`** sur toute config GA4.
- **Événement de découplage** : `giulia:consent-changed` dispatché sur `document`, `detail = { analytics, marketing }`.
- **Pas de runner JS dans le projet** : les tâches JS (2, 3, 4, 5) se valident par une **procédure de vérification manuelle navigateur** décrite dans chaque tâche (le projet n'a aucune infra de test JS ; ajouter Vitest est hors périmètre). Les modules JS restent conçus purs pour rester testables ultérieurement. Les tâches PHP (1, 6) suivent un vrai cycle TDD PHPUnit.
- **Commande de test PHP** : `php bin/phpunit`.
- **Style visuel** : tokens existants (crème `#f4ede0`, cartes `#fffdf8`, encre `#2a3138`, terracotta `#d3a273`/`#b3743f`, bordures `#e7ddca`), polices Bricolage Grotesque (titres) / DM Sans (texte). Pas de styles inline dans les templates : classes CSS dans `assets/styles/app.css`.

---

### Task 1 : Configuration GA (env → param → global Twig → meta tag)

**Files:**
- Modify: `.env`
- Modify: `.env.test`
- Modify: `config/services.yaml`
- Modify: `config/packages/twig.yaml`
- Modify: `templates/base.html.twig`
- Test: `tests/Functional/AnalyticsConfigTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: global Twig `ga_measurement_id` (String) et `<meta name="ga-measurement-id" content="…">` dans le `<head>`. Toutes les briques JS lisent cette meta.

- [ ] **Step 1: Écrire le test fonctionnel qui échoue**

Créer `tests/Functional/AnalyticsConfigTest.php` :

```php
<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnalyticsConfigTest extends WebTestCase
{
    public function test_measurement_id_meta_is_rendered(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // .env.test définit GA_MEASUREMENT_ID=G-TEST00000
        self::assertSelectorExists('meta[name="ga-measurement-id"][content="G-TEST00000"]');
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier l'échec**

Run: `php bin/phpunit tests/Functional/AnalyticsConfigTest.php`
Expected: FAIL (la meta n'existe pas encore).

- [ ] **Step 3: Ajouter la variable d'env**

Dans `.env`, à la suite des variables applicatives (après `CONTACT_TO_EMAIL`), ajouter :

```dotenv
###> giulia/analytics ###
# Identifiant Google Analytics 4 (G-XXXXXXXXXX). Vide = analytics totalement désactivé.
GA_MEASUREMENT_ID=
###< giulia/analytics ###
```

Dans `.env.test`, ajouter une valeur déterministe pour les tests :

```dotenv
GA_MEASUREMENT_ID=G-TEST00000
```

- [ ] **Step 4: Déclarer le paramètre Symfony**

Dans `config/services.yaml`, sous `parameters:`, ajouter la ligne :

```yaml
    giulia.ga_measurement_id: '%env(default::GA_MEASUREMENT_ID)%'
```

- [ ] **Step 5: Exposer le global Twig**

Dans `config/packages/twig.yaml`, ajouter le bloc `globals` :

```yaml
twig:
    file_name_pattern: '*.twig'
    globals:
        ga_measurement_id: '%giulia.ga_measurement_id%'

when@test:
    twig:
        strict_variables: true
```

- [ ] **Step 6: Rendre la meta tag**

Dans `templates/base.html.twig`, dans le `<head>` juste après la balise `<link rel="icon" …>` (ligne 8), ajouter :

```twig
    <meta name="ga-measurement-id" content="{{ ga_measurement_id }}">
```

- [ ] **Step 7: Lancer le test pour vérifier le succès**

Run: `php bin/phpunit tests/Functional/AnalyticsConfigTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add .env .env.test config/services.yaml config/packages/twig.yaml templates/base.html.twig tests/Functional/AnalyticsConfigTest.php
git commit -m "feat(analytics): expose GA_MEASUREMENT_ID via meta tag

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2 : Module de consentement (`consent.js`)

**Files:**
- Create: `assets/consent/consent.js`

**Interfaces:**
- Consumes: rien.
- Produces (exports ES):
  - `getConsent(): {analytics: boolean, marketing: boolean} | null`
  - `setConsent({analytics, marketing}): void` — persiste puis dispatch `giulia:consent-changed`.
  - `CONSENT_EVENT: string` = `'giulia:consent-changed'`.

- [ ] **Step 1: Écrire le module**

Créer `assets/consent/consent.js` :

```js
// Source de vérité du consentement cookies. Logique pure : localStorage + CustomEvent.
const KEY = 'giulia_consent';
const VERSION = 1;
const MAX_AGE_MS = 1000 * 60 * 60 * 24 * 30 * 6; // ~6 mois

export const CONSENT_EVENT = 'giulia:consent-changed';

export function getConsent() {
    try {
        const raw = localStorage.getItem(KEY);
        if (!raw) return null;
        const data = JSON.parse(raw);
        if (data.v !== VERSION) return null;
        if (typeof data.ts !== 'number' || Date.now() - data.ts > MAX_AGE_MS) return null;
        return { analytics: !!data.analytics, marketing: !!data.marketing };
    } catch (e) {
        return null;
    }
}

export function setConsent({ analytics, marketing }) {
    const value = { analytics: !!analytics, marketing: !!marketing, ts: Date.now(), v: VERSION };
    try {
        localStorage.setItem(KEY, JSON.stringify(value));
    } catch (e) {
        // stockage indisponible : on continue sans persister
    }
    document.dispatchEvent(new CustomEvent(CONSENT_EVENT, {
        detail: { analytics: value.analytics, marketing: value.marketing },
    }));
}
```

- [ ] **Step 2: Vérification manuelle (console navigateur)**

Démarrer le site (ex. `symfony server:start` ou vhost `giulia-pizza-gorges.local`), ouvrir n'importe quelle page, puis dans la console DevTools :

```js
const m = await import('/assets/consent/consent.js'); // chemin AssetMapper
console.log(m.getConsent());                 // attendu : null (aucun choix)
document.addEventListener('giulia:consent-changed', e => console.log('event', e.detail));
m.setConsent({ analytics: true, marketing: false });
// attendu : log "event {analytics:true, marketing:false}"
console.log(m.getConsent());                 // attendu : {analytics:true, marketing:false}
console.log(JSON.parse(localStorage.giulia_consent)); // attendu : {analytics:true, marketing:false, ts:…, v:1}
```

Puis vérifier l'expiration : `localStorage.setItem('giulia_consent', JSON.stringify({analytics:true,marketing:true,ts:0,v:1})); m.getConsent()` → attendu `null`. Nettoyer : `localStorage.removeItem('giulia_consent')`.

- [ ] **Step 3: Commit**

```bash
git add assets/consent/consent.js
git commit -m "feat(cookies): module de consentement (localStorage + event)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3 : Librairie analytics (`analytics.js`) + wiring `app.js`

**Files:**
- Create: `assets/analytics/analytics.js`
- Modify: `assets/app.js`

**Interfaces:**
- Consumes: `getConsent`, `CONSENT_EVENT` de `assets/consent/consent.js` ; meta `ga-measurement-id` (Task 1).
- Produces:
  - export `initAnalytics(): void` — à appeler une fois au boot.
  - `window.giuliaAnalytics = { track(name, params), pageview() }` — utilisé par le contrôleur bannière (Task 4) et la délégation de clics (Task 5).

- [ ] **Step 1: Écrire la librairie**

Créer `assets/analytics/analytics.js` :

```js
// Librairie Google Analytics 4, activée uniquement si un identifiant est présent
// dans la meta ga-measurement-id ET que l'utilisateur a consenti à la mesure d'audience.
import { getConsent, CONSENT_EVENT } from '../consent/consent.js';

let gaLoaded = false;
let currentId = null;

function measurementId() {
    const meta = document.querySelector('meta[name="ga-measurement-id"]');
    const id = meta ? (meta.getAttribute('content') || '').trim() : '';
    return id ? id : null;
}

function loadGa(id) {
    if (gaLoaded) return;
    gaLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    // send_page_view:false — les pages vues sont émises manuellement (site en SPA Turbo)
    window.gtag('config', id, { anonymize_ip: true, send_page_view: false });
    const s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
}

function disableGa(id) {
    window['ga-disable-' + id] = true;
    document.cookie.split(';').forEach((c) => {
        const name = c.split('=')[0].trim();
        if (name.indexOf('_ga') === 0) {
            document.cookie = name + '=; Max-Age=0; path=/';
        }
    });
}

export function track(name, params = {}) {
    if (!gaLoaded || typeof window.gtag !== 'function') return;
    window.gtag('event', name, params);
}

export function pageview() {
    if (!gaLoaded || typeof window.gtag !== 'function') return;
    window.gtag('event', 'page_view', {
        page_path: window.location.pathname + window.location.search,
        page_location: window.location.href,
        page_title: document.title,
    });
}

function onConsentChanged(consent) {
    if (!currentId) return;
    if (consent && consent.analytics) {
        if (gaLoaded) {
            window['ga-disable-' + currentId] = false; // ré-autorise après un retrait
        } else {
            loadGa(currentId);
            pageview(); // activation par interaction : compter la page courante
        }
    } else if (gaLoaded) {
        disableGa(currentId);
    }
}

function onClickDelegate(e) {
    const el = e.target.closest('[data-ga-event]');
    if (!el) return;
    const name = el.getAttribute('data-ga-event');
    if (!name) return;
    const params = {};
    for (const attr of el.attributes) {
        if (attr.name.startsWith('data-ga-') && attr.name !== 'data-ga-event') {
            params[attr.name.slice('data-ga-'.length)] = attr.value;
        }
    }
    track(name, params);
}

export function initAnalytics() {
    currentId = measurementId();
    if (!currentId) return; // no-op total : aucun identifiant configuré

    document.addEventListener('click', onClickDelegate, true);
    document.addEventListener('turbo:load', () => { if (gaLoaded) pageview(); });
    document.addEventListener(CONSENT_EVENT, (e) => onConsentChanged(e.detail));

    // Activation au chargement si consentement pré-existant : pas de pageview ici,
    // le turbo:load courant s'en charge (évite le doublon).
    const existing = getConsent();
    if (existing && existing.analytics) loadGa(currentId);

    window.giuliaAnalytics = { track, pageview };
}
```

- [ ] **Step 2: Câbler l'init dans `app.js`**

Modifier `assets/app.js` — ajouter l'import et l'appel après l'import des styles :

```js
import './stimulus_bootstrap.js';
import './styles/app.css';
import { initAnalytics } from './analytics/analytics.js';

initAnalytics();
```

(supprimer la ligne `console.log('This log comes from assets/app.js …')` au passage.)

- [ ] **Step 3: Vérification manuelle — no-op quand ID vide**

Temporairement, tester le cas ID vide : dans la console, `document.querySelector('meta[name="ga-measurement-id"]').content` doit refléter `.env`. En dev, `GA_MEASUREMENT_ID` est vide → `window.giuliaAnalytics` est **undefined** et aucune requête vers `googletagmanager.com` n'apparaît dans l'onglet Network. C'est le comportement attendu.

- [ ] **Step 4: Vérification manuelle — activation sur consentement**

Poser un ID de test en dev le temps de la vérif : dans `.env`, `GA_MEASUREMENT_ID=G-TEST00000`, vider le cache (`php bin/console cache:clear`), recharger. Puis console :

```js
localStorage.removeItem('giulia_consent'); location.reload();
// window.giuliaAnalytics défini, mais pas de requête gtag (pas de consentement)
window.giuliaAnalytics; // objet {track, pageview}
// simuler un accord :
const c = await import('/assets/consent/consent.js');
c.setConsent({ analytics: true, marketing: false });
// attendu : requête vers https://www.googletagmanager.com/gtag/js?id=G-TEST00000 (onglet Network)
// attendu : window.dataLayer contient un event page_view
```

Remettre ensuite `GA_MEASUREMENT_ID=` vide dans `.env` + `cache:clear` (on ne commite jamais d'ID réel). Nettoyer : `localStorage.removeItem('giulia_consent')`.

- [ ] **Step 5: Commit**

```bash
git add assets/analytics/analytics.js assets/app.js
git commit -m "feat(analytics): librairie GA4 conditionnelle (consentement + env)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4 : Bannière de consentement (CSS + composant Twig + contrôleur Stimulus)

**Files:**
- Modify: `assets/styles/app.css` (ajout en fin de fichier)
- Create: `templates/components/_cookie_banner.html.twig`
- Create: `assets/controllers/cookie_consent_controller.js`
- Modify: `templates/base.html.twig` (monter le contrôleur + inclure le composant)

**Interfaces:**
- Consumes: `getConsent`, `setConsent` de `consent.js` ; `window.giuliaAnalytics.track` (Task 3).
- Produces: contrôleur Stimulus `cookie-consent` avec action publique `reopen` (consommée par Task 6) et targets `banner`, `chip`, `prefs`, `analyticsToggle`, `marketingToggle`.

- [ ] **Step 1: Ajouter les styles**

À la fin de `assets/styles/app.css`, ajouter :

```css
/* ============ Bannière cookies ============ */
@keyframes ckRise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
@keyframes ckFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

.cookie-zone { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1000;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
    padding: 16px; pointer-events: none; }
.cookie-zone > * { pointer-events: auto; }

.cookie-banner { width: 100%; max-width: 560px; position: relative; background: #fffdf8;
    border: 1px solid #e7ddca; border-radius: 24px;
    box-shadow: 0 26px 60px -24px rgba(42,49,56,.5); padding: 24px 24px 22px;
    animation: ckRise .45s cubic-bezier(.2,.8,.25,1) both; overflow: hidden; }
.cookie-banner__ghost { position: absolute; top: -26px; right: 14px; font-size: 150px;
    line-height: 1; user-select: none; pointer-events: none; opacity: .06; }
.cookie-banner__head { position: relative; display: flex; gap: 16px; align-items: flex-start; }
.cookie-banner__head-body { flex: 1; min-width: 0; }
.cookie-banner__badge { width: 52px; height: 52px; flex-shrink: 0; border-radius: 16px;
    background: #f6ece1; display: flex; align-items: center; justify-content: center;
    font-size: 28px; animation: ckFloat 4s ease-in-out infinite; }
.cookie-banner__eyebrow { display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
    font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700; font-size: 11px;
    letter-spacing: 2px; text-transform: uppercase; color: #a08d72; }
.cookie-banner__eyebrow::before { content: ""; width: 7px; height: 7px; border-radius: 50%;
    background: #d3a273; }
.cookie-banner__title { margin: 0; font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800; font-size: 22px; line-height: 1.1; letter-spacing: -.4px; }
.cookie-banner__text { margin: 8px 0 0; font-size: 14.5px; line-height: 1.6; color: #6b6459; }

.cookie-prefs { position: relative; margin-top: 18px; display: flex; flex-direction: column; gap: 9px; }
.cookie-pref { display: flex; align-items: center; gap: 13px; background: #fffdf8;
    border: 1px solid #e7ddca; border-radius: 14px; padding: 13px 15px; width: 100%;
    text-align: left; cursor: pointer; }
.cookie-pref--locked { background: #f7f1e6; border-color: #ece2cf; cursor: default; }
.cookie-pref__emoji { font-size: 20px; flex-shrink: 0; }
.cookie-pref__body { flex: 1; min-width: 0; }
.cookie-pref__name { font-weight: 600; font-size: 14.5px; }
.cookie-pref__desc { font-size: 12.5px; color: #8a8377; margin-top: 1px; }
.cookie-pref__tag { flex-shrink: 0; font-size: 12px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: #8a9a82; background: #eef2ea; border: 1px solid #d8e3d0;
    padding: 6px 11px; border-radius: 100px; }

.ck-switch { flex-shrink: 0; width: 44px; height: 26px; border-radius: 100px;
    background: #d8ceba; position: relative; transition: background .18s ease; border: none; }
.ck-switch::after { content: ""; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px;
    border-radius: 50%; background: #fffdf8; box-shadow: 0 2px 5px rgba(0,0,0,.2);
    transition: left .18s ease; }
.ck-switch.is-on { background: #5c8a49; }
.ck-switch.is-on::after { left: 21px; }

.cookie-actions { position: relative; margin-top: 18px; display: flex; flex-wrap: wrap;
    gap: 10px; align-items: center; }
.cookie-btn { flex: 1; min-width: 150px; cursor: pointer; border: none; padding: 15px 20px;
    border-radius: 14px; font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700;
    font-size: 15px; transition: transform .15s ease, background .15s ease; }
.cookie-btn:hover { transform: translateY(-2px); }
.cookie-btn--accept { background: #d3a273; color: #2a3138; }
.cookie-btn--accept:hover { background: #e0b184; }
.cookie-btn--refuse { background: #2a3138; color: #f4ede0; }
.cookie-btn--refuse:hover { background: #20262c; }
.cookie-link { display: block; text-align: center; margin-top: 12px; cursor: pointer;
    background: none; border: none; font-family: 'DM Sans', sans-serif; font-size: 13.5px;
    font-weight: 600; color: #8a8377; text-decoration: underline; text-underline-offset: 3px;
    text-decoration-color: #d9cdb7; width: 100%; }

.cookie-chip { display: inline-flex; align-items: center; gap: 9px; background: #fffdf8;
    border: 1px solid #e7ddca; border-radius: 100px; padding: 11px 17px 11px 14px;
    box-shadow: 0 14px 30px -18px rgba(42,49,56,.5); font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 13.5px; color: #5f584d; cursor: pointer;
    transition: transform .15s ease; }
.cookie-chip:hover { transform: translateY(-2px); }
.cookie-chip__emoji { font-size: 18px; }

/* Bouton "Gérer mes cookies" de la page mentions légales */
.cookie-manage-btn { margin-top: 16px; display: inline-flex; align-items: center; gap: 9px;
    padding: 12px 18px; border: none; border-radius: 13px; background: #d3a273; color: #2a3138;
    font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700; font-size: 14.5px;
    cursor: pointer; transition: transform .15s ease, background .15s ease; }
.cookie-manage-btn:hover { transform: translateY(-2px); background: #e0b184; }
```

- [ ] **Step 2: Créer le composant Twig**

Créer `templates/components/_cookie_banner.html.twig` :

```twig
<div class="cookie-zone" data-controller="cookie-consent">
    {# Bandeau — masqué au départ, révélé par le contrôleur si aucun choix enregistré #}
    <div class="cookie-banner" data-cookie-consent-target="banner" hidden>
        <div class="cookie-banner__ghost" aria-hidden="true">🍪</div>
        <div class="cookie-banner__head">
            <div class="cookie-banner__badge" aria-hidden="true">🍪</div>
            <div class="cookie-banner__head-body">
                <div class="cookie-banner__eyebrow">Chez Giulia, on aime les cookies</div>
                <h2 class="cookie-banner__title">Un p'tit cookie avec ça&nbsp;?</h2>
                <p class="cookie-banner__text">Comme à la maison, on utilise quelques cookies pour que le site tourne bien et pour savoir quelles pizzas vous font de l'œil. Rien de piquant, promis&nbsp;— c'est vous qui choisissez la garniture. <a href="{{ path('legal') }}">En savoir plus</a></p>
            </div>
        </div>

        <div class="cookie-prefs" data-cookie-consent-target="prefs" hidden>
            <div class="cookie-pref cookie-pref--locked">
                <span class="cookie-pref__emoji" aria-hidden="true">🧀</span>
                <span class="cookie-pref__body">
                    <span class="cookie-pref__name">La pâte de base</span>
                    <span class="cookie-pref__desc">Indispensables au bon fonctionnement du site.</span>
                </span>
                <span class="cookie-pref__tag">Toujours actif</span>
            </div>
            <button type="button" class="cookie-pref" data-action="cookie-consent#toggleAnalytics">
                <span class="cookie-pref__emoji" aria-hidden="true">📊</span>
                <span class="cookie-pref__body">
                    <span class="cookie-pref__name">La garniture (mesure d'audience)</span>
                    <span class="cookie-pref__desc">Pour voir quelles pages vous régalent le plus.</span>
                </span>
                <span class="ck-switch" role="switch" aria-checked="false" data-cookie-consent-target="analyticsToggle"></span>
            </button>
            <button type="button" class="cookie-pref" data-action="cookie-consent#toggleMarketing">
                <span class="cookie-pref__emoji" aria-hidden="true">🌶️</span>
                <span class="cookie-pref__body">
                    <span class="cookie-pref__name">Le supplément piquant (marketing)</span>
                    <span class="cookie-pref__desc">Des offres et nouveautés qui vous ressemblent.</span>
                </span>
                <span class="ck-switch" role="switch" aria-checked="false" data-cookie-consent-target="marketingToggle"></span>
            </button>
        </div>

        <div class="cookie-actions">
            <button type="button" class="cookie-btn cookie-btn--accept" data-action="cookie-consent#acceptAll">Tout accepter, régalez-moi&nbsp;!</button>
            <button type="button" class="cookie-btn cookie-btn--refuse" data-action="cookie-consent#refuseAll">Le strict nécessaire</button>
        </div>
        <button type="button" class="cookie-link" data-cookie-consent-target="prefsButton" data-action="cookie-consent#openPrefs">Composer ma recette</button>
        <button type="button" class="cookie-link" data-cookie-consent-target="saveButton" data-action="cookie-consent#saveChoices" hidden>Enregistrer mes choix</button>
    </div>

    {# Pastille permanente de réouverture #}
    <button type="button" class="cookie-chip" data-cookie-consent-target="chip" data-action="cookie-consent#reopen" hidden>
        <span class="cookie-chip__emoji" aria-hidden="true">🍪</span>
        Gérer les cookies
    </button>
</div>
```

- [ ] **Step 3: Créer le contrôleur Stimulus**

Créer `assets/controllers/cookie_consent_controller.js` :

```js
import { Controller } from '@hotwired/stimulus';
import { getConsent, setConsent } from '../consent/consent.js';

export default class extends Controller {
    static targets = ['banner', 'chip', 'prefs', 'prefsButton', 'saveButton', 'analyticsToggle', 'marketingToggle'];

    connect() {
        this.analytics = false;
        this.marketing = false;
        const consent = getConsent();
        if (consent) {
            this.analytics = consent.analytics;
            this.marketing = consent.marketing;
            this._showChipOnly();
        } else {
            this._showBanner();
        }
        this._syncToggles();
    }

    openPrefs() {
        this.prefsTarget.hidden = false;
        this.prefsButtonTarget.hidden = true;
        this.saveButtonTarget.hidden = false;
    }

    toggleAnalytics() { this.analytics = !this.analytics; this._syncToggles(); }
    toggleMarketing() { this.marketing = !this.marketing; this._syncToggles(); }

    acceptAll() {
        this.analytics = true; this.marketing = true;
        setConsent({ analytics: true, marketing: true });
        this._trackChoice('all');
        this._showChipOnly();
    }

    refuseAll() {
        this.analytics = false; this.marketing = false;
        setConsent({ analytics: false, marketing: false });
        this._showChipOnly();
    }

    saveChoices() {
        setConsent({ analytics: this.analytics, marketing: this.marketing });
        this._trackChoice('custom');
        this._showChipOnly();
    }

    reopen() { this._showBanner(); }

    _showBanner() {
        this.bannerTarget.hidden = false;
        this.chipTarget.hidden = true;
    }

    _showChipOnly() {
        this.bannerTarget.hidden = true;
        this.chipTarget.hidden = false;
        this.prefsTarget.hidden = true;
        this.prefsButtonTarget.hidden = false;
        this.saveButtonTarget.hidden = true;
    }

    _syncToggles() {
        this._sync(this.analyticsToggleTarget, this.analytics);
        this._sync(this.marketingToggleTarget, this.marketing);
    }

    _sync(el, on) {
        el.classList.toggle('is-on', on);
        el.setAttribute('aria-checked', String(on));
    }

    _trackChoice(kind) {
        if (window.giuliaAnalytics && typeof window.giuliaAnalytics.track === 'function') {
            window.giuliaAnalytics.track('cookie_consent', { choice: kind });
        }
    }
}
```

- [ ] **Step 4: Monter le composant dans `base.html.twig`**

Modifier `templates/base.html.twig` — remplacer les lignes 13-17 (le bloc `.page`/`.container`) pour inclure la bannière dans `.page`, hors `.container` :

```twig
    <div class="page">
        <div class="container">
            {% block header %}{{ include('components/_header.html.twig') }}{% endblock %}
            {% block body %}{% endblock %}
            {% block footer %}{{ include('components/_footer.html.twig') }}{% endblock %}
        </div>
        {{ include('components/_cookie_banner.html.twig') }}
    </div>
```

- [ ] **Step 5: Vérification manuelle (visuel + interactions)**

Site en dev, `localStorage.removeItem('giulia_consent')` puis recharger. Vérifier :
1. Le **bandeau** apparaît en bas, style crème/terracotta fidèle au comp.
2. « Composer ma recette » déplie les préférences ; les 2 toggles basculent (piste verte / pastille à droite).
3. « Tout accepter » → bandeau remplacé par la **pastille** « 🍪 Gérer les cookies » ; `localStorage.giulia_consent` = `{analytics:true, marketing:true, …}`.
4. Recharger → seule la pastille est visible (pas de bandeau). Clic pastille → le bandeau revient.
5. « Le strict nécessaire » → `{analytics:false, marketing:false}` en storage.
6. Navigation Turbo vers une autre page → la pastille reste présente.

- [ ] **Step 6: Commit**

```bash
git add assets/styles/app.css templates/components/_cookie_banner.html.twig assets/controllers/cookie_consent_controller.js templates/base.html.twig
git commit -m "feat(cookies): bannière de consentement (Stimulus + Twig + CSS)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5 : Instrumentation des événements (`data-ga-event`)

**Files:**
- Modify: `templates/components/_link_card.html.twig`
- Modify: `templates/home/index.html.twig`
- Modify: `templates/menu/index.html.twig`
- Modify: `templates/menu/show.html.twig`

**Interfaces:**
- Consumes: délégation de clics `[data-ga-event]` de `analytics.js` (Task 3).
- Produces: rien (attributs HTML).

- [ ] **Step 1: Ajouter le support `ga_event` au composant link-card**

Modifier `templates/components/_link_card.html.twig` — mettre à jour le commentaire de props et la balise `<a>` (ligne 2) :

```twig
{# props : href, icon (svg brut), icon_variant (green|blue|warm|wa), title, subtitle, external?, accent?, ga_event?, ga_label? #}
<a href="{{ href }}"{% if external|default(false) %} target="_blank" rel="noopener"{% endif %}{% if ga_event is defined %} data-ga-event="{{ ga_event }}"{% if ga_label is defined %} data-ga-label="{{ ga_label }}"{% endif %}{% endif %} class="link-card{{ accent|default(false) ? ' link-card--accent' : '' }}">
```

- [ ] **Step 2: Instrumenter l'accueil (`home/index.html.twig`)**

Appliquer ces ajouts d'attributs :

- Ligne 58, CTA « Commander en Click & Collect » (bloc `special`) :
  `<a href="{{ e.orderUrl }}" class="featured__cta" data-ga-event="click_and_collect" data-ga-location="featured">`
- Ligne 60, CTA « Voir la fiche » (bloc signature) :
  `<a href="{{ path('menu_show', { slug: spotlight.slug }) }}" class="featured__cta" data-ga-event="pizza_view" data-ga-slug="{{ spotlight.slug }}">`
- Ligne 84, carte pizza du slider :
  `<a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="pizza-card{{ pizza.isSignature ? ' pizza-card--dark' : '' }}" data-ga-event="pizza_view" data-ga-slug="{{ pizza.slug }}">`
- Ligne 97, « Voir toute la carte » :
  `<a href="{{ path('menu_index') }}" class="slider__more" data-ga-event="menu_index">`
- Ligne 101, CTA Click & Collect principal :
  `<a id="commander" href="{{ e.orderUrl }}" class="cta cta--home" data-ga-event="click_and_collect" data-ga-location="home">`
- Ligne 113, lien téléphone dans la note :
  `<a href="tel:{{ e.phoneHref }}" data-ga-event="phone">{{ e.phone }}</a>`

Puis, dans les 6 `include('components/_link_card.html.twig', {…})`, ajouter la clé `ga_event` (et `ga_label` si utile) au tableau de props :
- menu PDF (ligne 119) : `ga_event: 'menu_pdf',`
- itinéraire (ligne 123) : `ga_event: 'directions',`
- téléphone (ligne 127) : `ga_event: 'phone',`
- avis Google (ligne 131) : `ga_event: 'google_review',`
- email (ligne 135) : `ga_event: 'email',`
- WhatsApp anti-gaspi (ligne 139) : `ga_event: 'whatsapp_antigaspi',`

Enfin, lien social (ligne 145) :
`<a href="{{ link.url }}" target="_blank" rel="noopener" class="social" data-ga-event="social" data-ga-label="{{ link.label }}">`

- [ ] **Step 3: Instrumenter la carte (`menu/index.html.twig`)**

- Ligne 34, tuile pizza :
  `<a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="pizza-tile{{ pizza.isSignature ? ' pizza-tile--dark' : '' }}" data-ga-event="pizza_view" data-ga-slug="{{ pizza.slug }}">`
- Ligne 65, CTA Click & Collect :
  `<a id="commander" href="{{ e.orderUrl }}" class="cta" data-ga-event="click_and_collect" data-ga-location="menu">`
- Ligne 77, lien téléphone :
  `<a href="tel:{{ e.phoneHref }}" data-ga-event="phone">{{ e.phone }}</a>`

- [ ] **Step 4: Instrumenter la fiche pizza (`menu/show.html.twig`)**

- Ligne 53, CTA Click & Collect :
  `<a href="{{ e.orderUrl }}" class="cta" data-ga-event="click_and_collect" data-ga-location="pizza">`

- [ ] **Step 5: Vérification manuelle (dataLayer)**

Site en dev avec `GA_MEASUREMENT_ID=G-TEST00000` (voir Task 3, remis à vide après). Accepter les cookies, puis dans la console :

```js
window.dataLayer.length; // noter la valeur
```

Cliquer (clic droit → « Ouvrir dans un nouvel onglet » pour ne pas quitter la page) sur : CTA Click & Collect, carte pizza, itinéraire, WhatsApp anti-gaspi. Après chaque clic, vérifier qu'une entrée `event` correspondante est poussée dans `window.dataLayer` (nom attendu : `click_and_collect`, `pizza_view`, `directions`, `whatsapp_antigaspi`). Remettre `GA_MEASUREMENT_ID=` vide + `cache:clear`.

- [ ] **Step 6: Commit**

```bash
git add templates/components/_link_card.html.twig templates/home/index.html.twig templates/menu/index.html.twig templates/menu/show.html.twig
git commit -m "feat(analytics): instrumentation des évènements métier (data-ga-event)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6 : Page mentions légales (texte + bouton de réouverture)

**Files:**
- Modify: `templates/legal/mentions.html.twig:88-95` (section Cookies)
- Test: `tests/Functional/LegalPageTest.php`

**Interfaces:**
- Consumes: action `cookie-consent#reopen` du contrôleur (Task 4), disponible car le contrôleur est monté sur `.page` (ancêtre commun).
- Produces: rien.

- [ ] **Step 1: Étendre le test fonctionnel (échec attendu)**

Dans `tests/Functional/LegalPageTest.php`, ajouter une méthode de test :

```php
    public function test_cookies_section_has_reopen_button(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'mesure d’audience anonyme');
        self::assertSelectorExists('button.cookie-manage-btn[data-action="cookie-consent#reopen"]');
    }
```

- [ ] **Step 2: Lancer le test pour vérifier l'échec**

Run: `php bin/phpunit tests/Functional/LegalPageTest.php::test_cookies_section_has_reopen_button`
Expected: FAIL (ni le texte ni le bouton n'existent).

- [ ] **Step 3: Mettre à jour la section Cookies**

Dans `templates/legal/mentions.html.twig`, remplacer le paragraphe de la section Cookies (ligne 94) et ajouter le bouton juste après :

```twig
            <p class="legal-text">Ce site utilise uniquement des cookies techniques nécessaires à son bon fonctionnement et, le cas échéant, à la mesure d’audience anonyme. Aucun cookie publicitaire ni de traçage tiers n’est déposé sans votre consentement. Vous pouvez à tout moment revoir vos choix ci-dessous ou configurer votre navigateur pour refuser les cookies.</p>
            <button type="button" class="cookie-manage-btn" data-action="cookie-consent#reopen">
                <span aria-hidden="true">🍪</span>
                Gérer mes cookies
            </button>
```

- [ ] **Step 4: Lancer le test pour vérifier le succès**

Run: `php bin/phpunit tests/Functional/LegalPageTest.php`
Expected: PASS (les deux méthodes).

- [ ] **Step 5: Vérification manuelle (réouverture)**

Sur `/mentions-legales`, cookies déjà acceptés (seule la pastille visible) : cliquer sur « 🍪 Gérer mes cookies » → le bandeau se rouvre par-dessus la page, sans navigation. Idem depuis le clic sur la pastille.

- [ ] **Step 6: Commit**

```bash
git add templates/legal/mentions.html.twig tests/Functional/LegalPageTest.php
git commit -m "feat(legal): bouton de réouverture de la bannière cookies sur les mentions

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7 : Vérification globale, CSP et conformité

**Files:**
- Read: `public/.htaccess` (vérification CSP)

**Interfaces:**
- Consumes: tout le travail précédent.
- Produces: rien (garde-fou de non-régression).

- [ ] **Step 1: Lancer la suite PHP complète**

Run: `php bin/phpunit`
Expected: PASS sur l'ensemble (les ~67 tests existants + `AnalyticsConfigTest` + le nouveau test de `LegalPageTest`). L'ajout de la bannière et de la meta ne doit casser aucun test fonctionnel existant.

- [ ] **Step 2: Vérifier l'absence de CSP bloquante**

Lire `public/.htaccess`. S'il contient un en-tête `Content-Security-Policy`, vérifier que `script-src` autorise `https://www.googletagmanager.com` et que `connect-src`/`img-src` autorisent `https://www.google-analytics.com` (et `https://*.google-analytics.com`). Si une CSP restrictive est présente sans ces hôtes, ajouter les directives ; si aucune CSP n'est définie, ne rien changer et noter « pas de CSP — RAS ».

- [ ] **Step 3: Checklist conformité (manuelle)**

Confirmer, `GA_MEASUREMENT_ID` vide (état livré) :
1. Aucune requête vers `googletagmanager.com` / `google-analytics.com` sur aucune page (Network) — no-op total.
2. `window.giuliaAnalytics` est `undefined` tant qu'aucun ID n'est configuré.
3. Bandeau : « Le strict nécessaire » au même niveau visuel que « Tout accepter » ; toggles OFF par défaut ; pastille de réouverture permanente présente sur toutes les pages.
4. Le choix persiste après rechargement et navigation Turbo.

- [ ] **Step 4: Commit (si la CSP a été modifiée)**

```bash
git add public/.htaccess
git commit -m "chore(csp): autorise googletagmanager/google-analytics pour GA4

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

Sinon, aucune modification : passer cette étape.

---

## Notes de conformité (rappel)

- Le stockage `giulia_consent` est un état essentiel (autorisé sans consentement préalable).
- Audience/marketing OFF par défaut ; refus aussi accessible que l'accord ; réouverture permanente (pastille + bouton mentions) → retrait aussi simple que le don du consentement.
- `anonymize_ip` actif ; `gtag.js` chargé uniquement après accord ; retrait ⇒ `ga-disable-<ID>` + purge des cookies `_ga*`.
- Toggle marketing préparatoire : aucun pixel publicitaire n'est branché (hors périmètre).
