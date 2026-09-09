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

## Mise à jour — 2026-09-09 : arrivée d'une base de données

La décision « pas de base de données » ci-dessus est **caduque**. Le projet se dote
d'un dashboard d'administration (pizzas, horaires, congés), ce qui impose de
persister le contenu éditorial et les accès du back-office. Moteur retenu :
**PostgreSQL**.

Conséquences sur ce Makefile :

- nouvelle cible `db` — `doctrine:migrations:migrate --no-interaction
  --allow-no-migration` — insérée dans `deploy` entre `cache` et `assets` ;
- `deploy` enchaîne donc `vendor` → `cache` → `db` → `assets` ;
- `.env.local.dist` documente la création du rôle et de la base sur le serveur,
  le réglage de `serverVersion` et une ligne de crontab `pg_dump` pour la
  sauvegarde.

En revanche, l'envoi **synchrone** des e-mails reste la règle : aucun worker
Messenger n'est nécessaire, aucun service systemd à ajouter.
