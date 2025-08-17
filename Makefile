DOCKER_COMPOSE ?= docker compose

phpunit:
	vendor/bin/phpunit

phpstan:
	vendor/bin/phpstan analyse

ecs:
	vendor/bin/ecs check --fix

install:
	composer install --no-interaction --no-scripts

backend:
	$(DOCKER_COMPOSE) up -d
	@until $(DOCKER_COMPOSE) ps mysql | grep -q "healthy"; do sleep 1; done
	bin/console sylius:install -s ai_video_suite --no-interaction

frontend:
	yarn install --pure-lockfile
	yarn encore production

fixtures:
	bin/console sylius:fixtures:load ai_video_suite --no-interaction

# Development
serve:
	symfony serve

dev:
	yarn encore dev --watch

# Infrastructure  
up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

clean:
	$(DOCKER_COMPOSE) down -v

# Composite commands
init: install backend frontend

static: install phpstan ecs

ci: init static phpunit

integration: init phpunit