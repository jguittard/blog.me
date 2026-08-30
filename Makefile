SHELL := /bin/bash
DC  := docker compose
PHP := $(DC) exec -u www-data php

.DEFAULT_GOAL := help
.PHONY: help certs build up down destroy logs ps install sh test test-integration \
        db-migrate db-rollback db-status covers seed seed-fresh hosts

help: ## List available targets
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n",$$1,$$2}'

certs: ## Generate locally-trusted TLS certs with mkcert
	./bin/setup-certs.sh

build: ## Build the PHP image
	$(DC) build

up: ## Start the stack (detached)
	$(DC) up -d

down: ## Stop the stack
	$(DC) down

destroy: ## Stop the stack and delete named volumes (redis, caddy)
	$(DC) down -v
	@echo "note: MariaDB + MinIO data persists in ./data/ — remove by hand if intended"

logs: ## Follow logs
	$(DC) logs -f

ps: ## Show service status
	$(DC) ps

install: ## Run composer install inside the container
	$(PHP) composer install

sh: ## Open a shell in the PHP container
	$(PHP) sh

test: ## Run the unit test suite inside the container
	$(PHP) composer test

test-integration: ## Run the DB integration tests (stack must be up)
	$(PHP) composer test-integration

db-migrate: ## Apply pending database migrations
	$(PHP) php bin/migrate.php migrate

db-rollback: ## Revert the last migration (make db-rollback n=3 for more)
	$(PHP) php bin/migrate.php rollback $(or $(n),1)

db-status: ## Show migration status
	$(PHP) php bin/migrate.php status

covers: ## Generate post cover SVGs and upload them to MinIO
	$(PHP) php bin/covers.php
	docker run --rm --network blog-me_default -v "$(PWD)/data/covers:/covers:ro" \
		--entrypoint /bin/sh minio/mc:latest -c \
		'mc alias set local http://minio:9000 minio minio12345 >/dev/null && \
		 mc cp --recursive /covers/ local/uploads/covers/'

seed: ## Populate the blog with sample data (idempotent; run `make covers` too)
	$(PHP) php bin/seed.php

seed-fresh: ## Wipe the blog tables and reseed
	$(PHP) php bin/seed.php --fresh

hosts: ## Print the /etc/hosts entries you need
	@echo "127.0.0.1 blog.me www.blog.me mail.blog.me s3.blog.me minio.blog.me"
