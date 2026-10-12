DC  := docker compose
APP := $(DC) exec app
UID := $(shell id -u)
GID := $(shell id -g)
export UID GID

.PHONY: setup start stop restart shell test test-php test-js lint fix analyse build e2e migrate fresh logs maps maps-smoke maps-style

setup: ## First-time setup: build images, install deps, migrate and seed
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d
	$(APP) composer install
	@grep -q '^APP_KEY=base64' .env || $(APP) php artisan key:generate
	$(APP) php artisan migrate --seed --force
	npm install
	@echo "Map data is not downloaded by setup: run 'make maps' (about 400 MB, see docker/maps/README.md)."

start: ## Start all services
	$(DC) up -d

stop: ## Stop all services
	$(DC) down

restart: stop start

shell: ## Shell into the PHP container
	$(APP) bash

test: test-php test-js ## Run backend and frontend tests

test-php:
	$(APP) ./vendor/bin/pest

test-js:
	npm run test

lint: ## Check formatting and lint rules
	$(APP) ./vendor/bin/pint --test
	npm run lint

fix: ## Auto-fix formatting
	$(APP) ./vendor/bin/pint
	npx eslint . --fix

analyse: ## Static analysis and type checks
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=1G
	npm run typecheck

build: ## Production frontend build
	npm run build
	npm run check:maps

e2e: ## Playwright end-to-end tests (added in stage 6)
	npx playwright test

maps: ## Download pinned map assets and the low-zoom dev tileset, activate it
	docker/maps/scripts/fetch-assets.sh
	docker/maps/scripts/fetch-tiles.sh $(or $(MAX_ZOOM),8)
	@. docker/maps/versions.env && docker/maps/scripts/switch.sh "$$OSM_SNAPSHOT.z0-$(or $(MAX_ZOOM),8).versatiles"

maps-smoke: ## Check map tiles, glyphs and sprites through nginx
	docker/maps/scripts/smoke.sh

maps-style: ## Regenerate resources/js/config/map-style.json
	npm run maps:style

migrate:
	$(APP) php artisan migrate

fresh: ## Recreate the database with seed data
	$(APP) php artisan migrate:fresh --seed

logs:
	$(DC) logs -f --tail=100

help:
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'
