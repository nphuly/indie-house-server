# ============================================================
# indie_house App - Makefile
# Usage: make <command>
# ============================================================

APP_ENV ?= development
SERVICE ?=

ifeq ($(APP_ENV),production)
# Same project, files and profile as infrastructure/scripts/deploy-release.sh.
DC := docker compose --env-file .env --profile stream -f docker-compose.yml -f docker-compose.production.yml
else
DC := docker compose --project-name indie-house -f docker-compose.yml -f docker-compose.$(APP_ENV).yml
endif

# ============================================================
# HELP
# ============================================================

help: ## Display this help message
	@echo ""
	@echo "Indie House App - Available Commands"
	@echo "-----------------------------------------------------------"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  %-22s %s\n", $$1, $$2}'
	@echo ""

# ============================================================
# DOCKER - start/stop/build
# ============================================================

start: ## Start tất cả containers
	@echo "Starting containers..."
	@$(DC) up -d
	@echo "Containers started"

stop: ## Stop all containers
	@echo "Stopping containers..."
	@$(DC) down
	@echo "Containers stopped"

restart: ## Restart all containers
	@$(MAKE) stop
	@$(MAKE) start	

build: ## Build images (uses the layer cache)
	@echo "Building Docker images..."
	@$(DC) build
	@echo "Build complete"

rebuild: ## Rebuild images without cache and restart
	@$(MAKE) stop
	@$(DC) build --no-cache
	@$(MAKE) start

logs: ## Show logs (SERVICE=control-plane for one service)
	@$(DC) logs -f $(SERVICE)

logs-db: ## Show logs of PostgreSQL
	@$(DC) logs -f postgres

# ============================================================
# LARAVEL - artisan commands
# ============================================================

migrate: ## Run database migrations
	@echo "Running migrations..."
	@$(DC) exec control-plane php artisan migrate --force
	@echo "Migrations complete"

create-super-admin: ## Create or promote a super-admin account interactively
	@$(DC) exec control-plane php artisan user:create-super-admin

backup-run: ## Queue a database and local-storage backup
	@$(DC) exec control-plane php artisan backup:run

backup-clean: ## Apply the configured backup retention policy
	@$(DC) exec control-plane php artisan backup:clean

composer-install: ## Install Composer packages into the shared development vendor volume
	@$(DC) exec control-plane composer install

test: ## Run the Laravel test suite in the development container
	@$(DC) exec -e XDEBUG_MODE=off control-plane composer test
