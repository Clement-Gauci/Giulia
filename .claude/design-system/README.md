# Design system — Giulia (miroir Claude Design)

Miroir local **fidèle** du design de la pizzeria **Giulia** (Gorges), dont la
**source de vérité vit sur Claude Design**. Ce dossier est une copie 1:1 de
l'arborescence distante, versionnée dans le dépôt, qui sert de référence pour
porter le design dans l'app Symfony (`templates/`, `assets/`).

## Projet Claude Design relié

| | |
|---|---|
| **Nom** | Giulia |
| **projectId** | `6472f33c-5a17-42ab-a1f9-8c7cdabd2622` |
| **URL** | https://claude.ai/design/p/6472f33c-5a17-42ab-a1f9-8c7cdabd2622 |
| **Type** | `PROJECT_TYPE_PROJECT` (projet classique — **pas** un design-system) |

## Sens de la synchronisation : **unidirectionnel** (pull only)

Claude Design → miroir local → app. **On ne pousse jamais** vers Claude Design :
le projet est un projet classique (pas un design-system, donc `write_files` est
de toute façon impossible), et la source de vérité reste côté Claude Design.

Accès via l'outil **`DesignSync`** (MCP `claude_design`,
`https://api.anthropic.com/v1/design/mcp`, auth `/design-login` — déjà active
sur ce poste via la connexion claude.ai).

### Re-synchroniser (mise à jour du design)

1. `DesignSync list_files` sur le projectId → comparer à `.sync/manifest.json`
   (repérer ajouts / suppressions / renommages).
2. `DesignSync get_file` sur **chaque** fichier concerné. Les résultats
   volumineux sont persistés dans `…/<session>/tool-results/toolu_*.txt`, les
   petits reviennent inline (donc journalisés dans le `.jsonl` de session).
3. **Extraire le `content` par décodage JSON, jamais à la main** (échappements
   `\"` / `\uXXXX`). Pour les binaires, `content` est en **base64**
   (`isBase64:true`) → `base64_decode` avant écriture. Voir le script
   `scratchpad/extract2.php` de la session d'import (à recréer dans la même
   commande Bash, le scratchpad se vide entre les tours).
4. Contrôles post-écriture : pages `.dc.html` → commencent par `<!DOCTYPE html>`
   et finissent par `</html>` ; binaires → octets magiques (PNG `\x89PNG`,
   JPEG `\xFF\xD8`, PDF `%PDF`) + flag `truncated:false`.
5. Régénérer `.sync/manifest.json` (empreintes sha1).
6. **Lire intégralement** la source de chaque page touchée avant de porter :
   ne jamais supposer qu'un bloc (header/footer/horaires…) est « juste recoloré ».

> ⚠️ **Plafond `get_file` = 256 Kio** de contenu (soit ~192 Kio après
> `base64_decode`). Au-delà, le fichier revient **tronqué** (`truncated:true`) et
> donc corrompu. Voir « Fichiers non rapatriés » plus bas.

## Contenu du miroir

Arborescence **identique** au projet distant (les pages se référencent en
relatif : `assets/…`, `./support.js`, `Nos pizzas.dc.html`), donc le miroir
reste cohérent tel quel.

### Pages (`*.dc.html`) — composants « Design Comp »
| Fichier | Rôle |
|---|---|
| `Giulia.dc.html` | Accueil façon link-in-bio : statut ouvert/fermé **en direct** (fuseau Europe/Paris), pizza du moment, slider de pizzas auto, Click & Collect, liens (menu PDF, itinéraire, tél, avis Google, WhatsApp anti-gaspi, réseaux), horaires. Encart annonce (MOTD) paramétrable. |
| `Nos pizzas.dc.html` | Carte complète, 3 catégories (Les rouges / Les blanches / La signature), tags végé/piquant/signature. |
| `Pizza - La Fresca.dc.html` | Gabarit **fiche pizza** paramétrable (props + query string : `name`, `price`, `ingredients`, `allergens`, `signature`). |
| `Contact.dc.html` | Formulaire de contact (compose un `mailto:`), carte, horaires. |
| `Mentions légales.dc.html` | Mentions légales, champs éditeur/hébergeur en props (SIRET/RCS/TVA à compléter). |
| `Erreur.dc.html` | Page d'erreur paramétrable (prop `code` : 404/403/500/503), titre + message adaptés, CTA retour accueil / carte. |

### Runtime support (`*.js`)
| Fichier | Rôle |
|---|---|
| `support.js` | Runtime `dc-runtime` (rendu React de `<x-dc>` : `DCLogic`, `sc-if`, `sc-for`, interpolation `{{ }}`). Généré, ne pas éditer. |
| `image-slot.js` | Composant `<image-slot>` (emplacements image remplissables). Scaffold « omelette starter ». |

> Ces pages sont des **comps** du runtime Claude Design : elles ont besoin de
> `window.React`/`ReactDOM` injectés par l'hôte. Ouvertes seules dans un
> navigateur, elles ne s'hydratent pas — c'est normal. Elles servent de
> **spécification visuelle**, à porter en Twig/Stimulus côté Symfony.

### Ressources
- `assets/` — logos/icônes **utilisés** par les pages : `giulia-icon.png`
  (500×500), `giulia-logo.png` (1000×425), `giulia-wordmark.png` (1000×315).
- `uploads/` — sources fournies : `logo.png` (= `assets/giulia-logo.png`),
  `logo.jpg` (1400×571), `icon-giulia-no-bg.png` (= `assets/giulia-icon.png`).
- `scraps/` — captures de la charte graphique (référence) : `charte-p3-1.png`,
  `charte-p4-2.png` (identique au précédent), `charte-p4-3.png`.

## Tokens de design (extraits des comps)

**Typographie** (Google Fonts, chargées dans le `<helmet>` de chaque page)
- Titres : **Bricolage Grotesque** (500/600/700/800)
- Texte : **DM Sans** (400/500/600/700)

**Couleurs**
| Rôle | Hex |
|---|---|
| Fond crème | `#f4ede0` |
| Cartes | `#fffdf8` · `#fbf6ec` |
| Encre / panneaux sombres | `#2a3138` (hover `#20262c`, alt `#343b42`) |
| Textes | `#4a4339` · `#5f584d` · `#6b6459` · `#8a8377` |
| Accent terracotta | `#d3a273` · `#b3743f` (liens) · `#a08d72` |
| Vert (ouvert / végé) | `#5c8a49` · `#4a6a3f` · `#a9c39a` · `#9aab93` |
| Bleu | `#5786a0` |
| Rouge (piquant / erreur) | `#a24b32` |
| Bordures | `#e7ddca` · `#e0d5c1` · `#e2d8c5` |

## Données métier (embarquées dans les comps)

- **Giulia — Pizzeria napolitaine**, 1 rue de la cité des sports, 44190 Gorges
  (à 5 min de Clisson, 25 min de Nantes)
- Tél **02 85 52 87 42** · **hello@giulia-pizza-gorges.fr**
- **Horaires** (Europe/Paris) : Mar–Jeu 10h–14h30 / 17h–21h30 · Ven–Sam
  10h–14h30 / 17h–22h · Dim 18h–21h30 · **Lun fermé**
- Vente **Click & Collect**, grandes pizzas à la demande (11h30–14h / 18h→ferm.),
  groupe **WhatsApp anti-gaspi** pour les invendus

## Fichiers non rapatriés (à télécharger manuellement)

Ces fichiers dépassent le plafond `get_file` de 256 Kio et reviennent tronqués.
Les récupérer depuis Claude Design (bouton de téléchargement du fichier) et les
déposer aux chemins indiqués :

- `scraps/charte-p1-0.png` — capture longue de la charte (1009×2070)
- `uploads/GAUCI CHARTE GRAPHIQUE.pdf` — charte graphique complète (PDF, 4 pages)

Purement **référence** : aucun de ces deux fichiers n'est requis par les pages.

## Métadonnées de synchro

`.sync/manifest.json` — inventaire horodaté (chemin, taille, sha1, type) +
liste des fichiers non rapatriés. Sert de base de comparaison à la prochaine synchro.
