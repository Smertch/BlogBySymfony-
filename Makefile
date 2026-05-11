.DEFAULT_GOAL := help
SHELL := /bin/bash

PHP        ?= php
COMPOSER   ?= composer
CONSOLE    ?= $(PHP) bin/console
COMPOSE    ?= docker compose
DC_PHP     ?= $(COMPOSE) exec php-fpm

.PHONY: help install up down build logs ps shell migrate migrate-diff \
        worker test lint cs cs-fix phpstan ci clean clean-cache

help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install PHP dependencies
	$(COMPOSER) install --prefer-dist --no-interaction --no-progress

up: ## Start the docker stack
	$(COMPOSE) up -d --build

down: ## Stop the docker stack
	$(COMPOSE) down

build: ## Rebuild the docker images
	$(COMPOSE) build --pull

logs: ## Tail docker logs
	$(COMPOSE) logs -f --tail=200

ps: ## Show docker containers
	$(COMPOSE) ps

shell: ## Enter the php-fpm container
	$(DC_PHP) bash

migrate: ## Run Doctrine migrations
	$(DC_PHP) $(PHP) bin/console doctrine:migrations:migrate --no-interaction --ansi

migrate-diff: ## Generate a new Doctrine migration from entity changes
	$(DC_PHP) $(PHP) bin/console doctrine:migrations:diff --ansi

worker: ## Start the Messenger Kafka worker (foreground)
	$(DC_PHP) $(PHP) bin/console messenger:consume async -vv

test: ## Run PHPUnit
	vendor/bin/phpunit --colors=always

lint: ## Lint Twig, YAML and the DI container
	$(CONSOLE) lint:twig templates --ansi
	$(CONSOLE) lint:yaml config translations --parse-tags --ansi
	$(CONSOLE) lint:container --ansi

cs: ## Check coding style (PHP-CS-Fixer dry-run)
	vendor/bin/php-cs-fixer fix --dry-run --diff --ansi

cs-fix: ## Apply coding style fixes
	vendor/bin/php-cs-fixer fix --ansi

phpstan: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse --ansi --memory-limit=512M

ci: composer-validate lint cs phpstan test ## Run the full local CI suite

composer-validate: ## Validate composer.json
	$(COMPOSER) validate --strict --no-check-publish

clean: clean-cache ## Remove caches and logs
	rm -rf var/log/*

clean-cache: ## Clear the Symfony cache
	$(CONSOLE) cache:clear --no-warmup
