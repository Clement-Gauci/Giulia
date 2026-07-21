# Conception — Finition de l'intégration visuelle (CSS propre + fidélité maquettes)

**Date :** 2026-07-21
**Statut :** validé (design), en attente de plan d'implémentation
**Auteur :** Clément GAUCI + Claude
**Contexte parent :** [2026-07-21-site-vitrine-giulia-design.md](2026-07-21-site-vitrine-giulia-design.md) (§8 « Présentation, design & assets »)

## 1. Objectif

Tenir la promesse du §8 du spec parent — *« le style inline des maquettes est porté
en CSS propre et réutilisable »* — que l'implémentation actuelle n'a suivie qu'à
moitié : la plupart des templates portent encore des `style="..."` inline, et
plusieurs pages sont **simplifiées** par rapport aux maquettes de référence.

Deux livrables :
1. **Propreté** — extraire tout le CSS inline vers des classes structurées dans
   `assets/styles/app.css` ; zéro `style="..."` restant dans les templates.
2. **Fidélité** — rapprocher les pages simplifiées de leur maquette, et ajouter la
   **page d'erreur** (maquette `Erreur.dc.html`, ajoutée lors de la dernière synchro).

> **État au démarrage (working tree)** : l'utilisateur a déjà porté **la fidélité** de
> `contact/index.html.twig` et `legal/mentions.html.twig` (breadcrumb, chips, formulaire
> habillé, bloc « Nous trouver », 7 sections légales) — **en inline**, non commité. On
> reprend ces versions comme base : il ne leur reste que l'extraction inline → classes.
> Restent à faire côté **fidélité** : la fiche pizza et la page d'erreur.

Source de vérité visuelle : `.claude/design-system/*.dc.html` (miroir 1:1 de Claude
Design). **On ne modifie pas** ce miroir.

## 2. Décisions (validées)

| Décision | Choix |
|---|---|
| Périmètre | Nettoyage CSS **+** fidélité maquettes (pas seulement le refactor) |
| Organisation CSS | **Un seul** `assets/styles/app.css` réorganisé en sections (AssetMapper, pas de build Sass) |
| Convention | Classes composant **BEM léger** ; tokens en variables CSS `:root` |
| SVG récurrents | Macro Twig `components/_icons.html.twig` (un SVG = markup, pas du style) |
| Formulaire contact | Reste **serveur** (Form + Mailer) ; habillage via **thème de formulaire Twig** + CSS |
| JS | Inchangé (`live-status`, `pizza-slider`) ; ajout du seul `char-counter` (compteur Contact) |
| Page d'erreur | Override `templates/bundles/TwigBundle/Exception/error.html.twig` |

## 3. Architecture CSS

`app.css` réorganisé en sections commentées, dans cet ordre :

1. `@font-face` (inchangé)
2. **Tokens `:root`** — étendus avec les valeurs récurrentes de l'inline :
   `--ink-panel:#343b42`, `--text-strong:#3a4148`, `--terracotta-soft:#c9a27a`,
   `--terracotta-link:#b3743f` (déjà là), `--blue:#5786a0`, `--green-card:#a9c39a`,
   `--sand:#a08d72`, `--border-soft:#e0d5c1`, `--field-bg:#fbf6ec`,
   `--r-card:18px`, `--r-panel:20px`, `--r-pill:100px`,
   `--shadow-panel`, `--shadow-cta`.
3. **Base / reset** (body, a, headings) — inchangé
4. **Layout** — `.page`, `.container`, plus une classe d'espacement de section si utile
5. **Composants** (un en-tête de commentaire par composant) :
   - Barre + marque + nav : `.topbar`, `.brand`, `.brand__kicker`, `.nav`
   - Badge statut : `.badge*` (déjà là)
   - Hero accueil : `.hero`, `.hero__title`, `.hero__lede`
   - MOTD : `.motd`
   - Pizza du moment : `.featured`, `.featured__watermark`, `.featured__meta`
   - Slider : `.slider`, `.slider__head`, `.slider__nav`, `.slider__track`
   - Carte pizza : `.pizza-card` (+ `--dark`), `.pizza-card__photo`, `.pizza-card__body`,
     tons via `.pizza-card--tone-green|blue|tan` (cyclés `loop.index0 % 3`)
   - Carte du menu (grille) : `.menu-cat`, `.pizza-grid`, `.pizza-tile`
   - Fiche pizza : `.pizza-hero`, `.meta-chips`, `.meta-chip`, `.allergens`
   - Liens link-in-bio : `.link-card` (+ `--accent`), `.link-grid`
   - CTA Click & Collect : `.cta-cc` (déjà là, enrichi)
   - Panneau horaires : `.hours`, `.hours__row`, `.hours__note`, `.hours__coords`
   - Encart d'info : `.note`
   - Formulaire : `.field`, `.field__label`, `.field__input`, `.contact-chips`,
     `.map`, `.form-success`, `.form-error`
   - Mentions légales : `.legal-section`, `.legal-section__head`, `.legal-table`
   - Page d'erreur : `.error-card`, `.error-card__emblem`, `.help-strip`
   - Pied : `.footer`
6. **Animations** : `@keyframes gPulse` (là), `gRise`, `gFloat`
7. **Utilitaires** courts si nécessaires

## 4. Templates — refactor propre (rendu identique)

Passage **zéro `style=`** en réutilisant les classes ci-dessus, sans changer le visuel :
`base.html.twig`, `components/_header`, `_footer`, `_hours`, `_status_badge`,
`_link_card`, `home/index.html.twig`, `menu/index.html.twig`.

Les SVG récurrents (flèches, chevrons, icônes pin/tél/mail…) sont sortis dans
`components/_icons.html.twig` (macros `icon_*`), appelées depuis les templates.

## 5. Templates — fidélité maquettes

### 5.1 Fiche pizza (`menu/show.html.twig`) → `Pizza - La Fresca.dc.html`
Reconstruire : fil d'Ariane, **hero sombre** (`.pizza-hero` : nom, prix, accroche,
grille d'ingrédients ◆), **meta-chips** (33 cm / 48 h maturation / 1–2 personnes),
encart **allergènes** (`.allergens`), **CTA Click & Collect**, bouton retour, panneau
**horaires**. L'accroche par défaut vient du domaine (pizza `featured` → accroche
signature ; sinon accroche générique). Données déjà disponibles sur `Pizza`
(name, price, ingredients, allergens, tags, featured).

### 5.2 Contact (`contact/index.html.twig`) — **fidélité déjà faite (WIP user)**
La version working-tree est déjà fidèle à `Contact.dc.html` (breadcrumb, chips
appel/email, formulaire habillé, état succès, bloc « Nous trouver », horaires) et
l'envoi serveur est conservé. **Reste uniquement le nettoyage** :
- Extraire l'inline vers classes `.crumbs`, `.chip`, `.panel`, `.field*`, `.map`,
  `.form-success`, `.back-cta`.
- Remplacer le `form_widget(..., { attr: { style: input_style } })` par un rendu
  propre : soit `attr: { class: 'field__input' }`, soit un **thème de formulaire**
  `templates/form/giulia_theme.html.twig` (déclaré dans `config/packages/twig.yaml`).
- **Compteur de caractères** (présent dans la maquette, absent du WIP) : petit
  contrôleur Stimulus `char-counter` sur le textarea (`x/600`) — cosmétique, la limite
  reste garantie côté serveur (Validator).

### 5.3 Mentions légales (`legal/mentions.html.twig`) — **fidélité déjà faite (WIP user)**
La version working-tree a déjà les **7 sections** fidèles (Éditeur en tableau,
Directeur, Hébergeur, PI, Données perso, Cookies, Médiation), avec les champs non
connus en « — / à compléter ». **Reste uniquement le nettoyage** : extraire l'inline
vers `.crumbs`, `.legal-section`(+`__head`), `.legal-table`, `.back-cta`, en
factorisant l'en-tête de section à icône commun avec le Contact (`.panel__head`).

### 5.4 Page d'erreur (nouveau) → `Erreur.dc.html`
`templates/bundles/TwigBundle/Exception/error.html.twig` : `.error-card` (emblème
animé `gFloat`, filigrane du code, code + titre + message, CTA « Retour accueil » /
« Voir la carte », bandeau `.help-strip` « une faim pressante »). Le template reçoit
`status_code`/`status_text` ; mapping des messages **404 / 403 / 500 / 503** repris de
la maquette, défaut = 404. Réutilise `_header`/`_footer` via `base.html.twig`.

## 6. JS

Rien à refaire (`live-status`, `pizza-slider` déjà propres et fidèles). Seul ajout :
`assets/controllers/char_counter_controller.js` (Stimulus), ~15 lignes, sans
dépendance. Enregistré via le bootstrap Stimulus existant.

## 7. Tests & vérification

- **Non-régression** : les 49 tests actuels visent du **texte** et les sélecteurs
  `.badge`, `form`, `h1` — préservés par le refactor. `bin/phpunit` doit rester vert
  avant/après chaque étape.
- **Ajout** : un test smoke page d'erreur — une requête vers une route inexistante
  renvoie `404` et la page brandée (`assertSelectorTextContains` sur un libellé de la
  maquette). Détail à cadrer dans le plan : rendu de l'`error.html.twig` en env de test
  (debug off / `TwigErrorRenderer`).
- **Vérification visuelle réelle** (skill run/verify) : comparer chaque page rendue à
  sa maquette `.dc.html` (mêmes couleurs, espacements, composants).

## 8. Découpage d'exécution (incrémental, testé à chaque étape)

1. **Fondations CSS** : tokens étendus + squelette de sections + macro `_icons`.
2. **Composants partagés** : `_header`, `_footer`, `_hours`, `_status_badge`,
   `_link_card` → classes (rendu identique).
3. **Accueil** → classes.
4. **Carte** (`menu/index`) → classes + fidélité maquette `Nos pizzas.dc.html` :
   légende à 3 tags (Végétarienne 🌱 / Piquante 🌶️ / Signature ★), tuile signature
   sombre, encart **note supplément** (+1,50 € / +2,50 €), **CTA Click & Collect**,
   panneau horaires.
5. **Fiche pizza** → reconstruction fidèle (§5.1).
6. **Contact** → thème de formulaire + fidélité (§5.2) + `char-counter`.
7. **Mentions légales** → 7 sections (§5.3).
8. **Page d'erreur** → nouveau template (§5.4) + test smoke.
9. **Passe finale** : `bin/phpunit` complet + vérification visuelle.

Chaque étape est committée séparément (messages `refactor(css)` / `feat(ui)`), sur
une branche dédiée — l'utilisateur travaille en parallèle sur `feat/*`, on ne pousse
pas sur son travail.

## 9. Hors périmètre (YAGNI)

- Pas de modification du domaine, des repositories YAML, ni des routes.
- Pas de nouvelle dépendance front (pas de Sass/Tailwind/PostCSS).
- Pas de refonte du miroir `.claude/design-system/` (référence intacte).
- Pas de fonctionnalité nouvelle (le compteur de caractères est purement cosmétique ;
  la validation reste serveur).
- Pas de récupération des 2 fichiers de charte tronqués (hors sujet).
