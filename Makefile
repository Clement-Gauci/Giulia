# Makefile de déploiement — Giulia (site vitrine Symfony)
# À exécuter sur le serveur : prépare l'application pour la production
# (dépendances, cache, assets). Aucune base de données ni worker Messenger.

APP_ENV  ?= prod
export APP_ENV

PHP      ?= php
COMPOSER ?= composer
CONSOLE   = $(PHP) bin/console

.DEFAULT_GOAL := help

.PHONY: help deploy vendor cache assets

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-8s\033[0m %s\n", $$1, $$2}'

deploy: vendor cache assets ## Déploiement complet : dépendances + cache + assets

vendor: ## Installe les dépendances PHP en mode production
	$(COMPOSER) install --no-dev --optimize-autoloader --no-interaction

cache: ## Vide et préchauffe le cache Symfony
	$(CONSOLE) cache:clear
	$(CONSOLE) cache:warmup

assets: ## Installe l'importmap et compile les assets (asset-mapper)
	$(CONSOLE) importmap:install
	$(CONSOLE) asset-map:compile
