# Design — Makefile de déploiement (Giulia)

Date : 2026-07-21

## Objectif

Fournir une commande unique, exécutée **sur le serveur**, pour préparer le site
vitrine Symfony à la production : installation des dépendances, régénération du
cache et compilation des assets.

## Contexte

- Application Symfony 8.1, servie par Apache depuis `/srv/http/`.
- **Pas de base de données** au final → pas de migrations Doctrine.
- **Pas de worker Messenger** : les emails (formulaire de contact) partent en
  synchrone pendant la requête HTTP. Aucune cible ni service systemd Messenger.
- Assets gérés par `symfony/asset-mapper` (importmap + compilation).

## Décisions

- **Exécution sur place** : `make deploy` tourne sur le serveur cible. Le code y
  arrive par ailleurs (git pull / rsync manuel). Pas d'orchestration SSH/rsync
  dans le Makefile.
- **`APP_ENV ?= prod`** exporté en tête, de sorte que toutes les commandes
  console s'exécutent en environnement de production (paramétrable en ligne de
  commande).
- Découpage en sous-cibles idempotentes pour pouvoir les relancer isolément.

## Cibles

| Cible    | Rôle |
|----------|------|
| `help`   | Liste les cibles (cible par défaut). |
| `deploy` | Enchaîne `vendor` → `cache` → `assets`. Cible principale. |
| `vendor` | `composer install --no-dev --optimize-autoloader --no-interaction`. |
| `cache`  | `cache:clear` puis `cache:warmup`. |
| `assets` | `importmap:install` puis `asset-map:compile`. |

## Notes

- Toutes les cibles sont `.PHONY` (pas de fichiers cibles réels).
- Recouvrement assumé : les auto-scripts de composer relancent déjà
  `cache:clear` / `assets:install` / `importmap:install` en post-install ; ces
  commandes sont idempotentes, donc sans effet de bord.
