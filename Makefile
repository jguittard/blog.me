SHELL := /bin/bash
DC  := docker compose
PHP := $(DC) exec -u www-data php

.DEFAULT_GOAL := help
.PHONY: help certs build up down destroy logs ps install sh test hosts

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

test: ## Run the test suite inside the container
	$(PHP) composer test

hosts: ## Print the /etc/hosts entries you need
	@echo "127.0.0.1 blog.me www.blog.me mail.blog.me s3.blog.me minio.blog.me"
