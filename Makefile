.DEFAULT_GOAL := help
DC  = docker compose
APP = $(DC) exec app

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-12s %s\n", $$1, $$2}'

up: ## Démarre la stack
	$(DC) up -d --build

down: ## Arrête la stack
	$(DC) down

sh: ## Shell dans le conteneur app
	$(APP) bash

console: ## make console c="cache:clear"
	$(APP) bin/console $(c)

migrate: ## Applique les migrations
	$(APP) bin/console doctrine:migrations:migrate -n --allow-no-migration

fixtures: ## Recharge les données de démo (vide la base)
	$(APP) bin/console foundry:load-fixtures -n

test: ## Lance les tests
	$(APP) bin/phpunit

stan: ## Analyse statique
	$(APP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Corrige le style
	$(APP) vendor/bin/php-cs-fixer fix

qa: cs stan test ## Tout vérifier
