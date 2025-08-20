DOCKER_COMPOSE ?= docker compose

.DEFAULT_GOAL := help

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

phpunit: ## Lance les tests PHPUnit
	vendor/bin/phpunit

phpstan: ## Analyse statique du code avec PHPStan
	vendor/bin/phpstan analyse

ecs: ## Vérifie et corrige le code style
	vendor/bin/ecs check --fix

install: ## Installe les dépendances PHP
	composer install --no-interaction --no-scripts

backend: ## Configure le backend (DB + Sylius)
	$(DOCKER_COMPOSE) up -d
	@until $(DOCKER_COMPOSE) ps mysql | grep -q "healthy"; do sleep 1; done
	bin/console sylius:install -s ai_video_suite --no-interaction

frontend: ## Compile les assets frontend
	yarn install --pure-lockfile
	yarn encore production

fixtures: ## Charge les fixtures de test
	bin/console sylius:fixtures:load ai_video_suite --no-interaction

# Development
serve: ## Lance le serveur de développement
	symfony serve

dev: ## Lance webpack en mode watch
	yarn encore dev --watch

# Infrastructure
up: ## Démarre les conteneurs Docker
	$(DOCKER_COMPOSE) up -d

down: ## Arrête les conteneurs Docker
	$(DOCKER_COMPOSE) down

clean: ## Supprime les conteneurs et volumes Docker
	$(DOCKER_COMPOSE) down -v

# Composite commands
init: install backend frontend ## Installation complète du projet

lint:
	bin/console lint:twig templates/
	bin/console lint:yaml config/

static: install lint phpstan ecs ## Analyse statique complète

ci: init static phpunit ## Pipeline CI complète


# Messenger
messenger: ## Lance le consumer des messages async
	bin/console messenger:consume video_async -v
