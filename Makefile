SHELL := /bin/bash

COMPOSE := docker compose -f docker-compose.yml -f docker-compose.dev.yml
EXEC_APP := $(COMPOSE) exec app
EXEC_FRONTEND := $(COMPOSE) exec frontend

# Postgres connection used by the db-* targets below. Override on the command
# line if you point backend/.env.local at a different db/user, e.g.:
#   make db-cli DB_NAME=other_db
DB_CONTAINER := pouch_db
DB_USER      := app
DB_NAME      := app

# Targets below that take a trailing word (branch name, console command,
# email...) read it via `$(filter-out $@,$(MAKECMDGOALS))`. Without the
# catch-all "%" rule at the bottom of this file, make would otherwise error
# with "No rule to make target" for that trailing word.

.DEFAULT_GOAL := help

help:
	@echo "make [option]"
	@echo "OPTIONS:"
	@echo '  --- stack ---'
	@echo '    start           - one-shot bootstrap: env files, build, up, composer install, JWT keys, migrate'
	@echo '    branch <name>   - checkout/create branch, pull, and re-bootstrap the stack'
	@echo '    up              - build & start the whole stack (backend, frontend, db, minio)'
	@echo '    down            - stop the stack'
	@echo '    logs [service]  - tail logs (all services, or just the one given)'
	@echo '    ps              - list running services'
	@echo '    bash <service>  - shell into a running container'
	@echo '  --- backend ---'
	@echo '    console <cmd>   - run a bin/console command, e.g. make console debug:router'
	@echo '    composer <cmd>  - run a composer command'
	@echo '    cc              - cache:clear'
	@echo '    migration       - create doctrine migration'
	@echo '    migrate         - migrate database'
	@echo '    entity          - create entity'
	@echo '    test-backend    - run backend tests'
	@echo '    fixtures        - run fixtures'
	@echo '    rector          - rector preview'
	@echo '    rector-fix      - rector apply fixes'
	@echo '    cs              - cs preview'
	@echo '    cs-fix          - cs apply fixes'
	@echo '    phpstan         - phpstan list of issues'
	@echo '  --- frontend ---'
	@echo '    install         - npm install'
	@echo '    npm <cmd>       - run an npm command in the frontend container'
	@echo '    test-frontend   - run frontend tests'
	@echo '    lint            - eslint'
	@echo '    lint-fix        - eslint --fix'
	@echo '  --- both ---'
	@echo '    test            - run backend + frontend tests'
	@echo '  --- database (postgres) ---'
	@echo '    db-cli          - open a psql shell'
	@echo '    db-dump         - pg_dump to a timestamped .sql file in the repo root'
	@echo '    db-restore <f>  - drop, recreate and restore db from a .sql file'
	@echo '    admin <email>   - grant ROLE_ADMIN to an existing user'

## --- stack ---

# One command to get from a fresh checkout to a running app.
# Safe to re-run: env files and the JWT keypair are only created if missing.
start: env-setup up composer-install jwt-keypair migrate-dev
	@echo ""
	@echo "Pouch is up:"
	@echo "  frontend:      http://localhost:5173"
	@echo "  backend:       http://localhost:8111  (docs: http://localhost:8111/api/doc)"
	@echo "  minio console: http://localhost:9001"

env-setup:
	@test -f .env || cp .env.example .env
	@test -f backend/.env.local || { \
		cp backend/.env backend/.env.local; \
		printf '\nPOSTGRES_PASSWORD=app\nDATABASE_URL=postgresql://app:app@db:5432/app?serverVersion=16&charset=utf8\n' >> backend/.env.local; \
	}

composer-install:
	$(EXEC_APP) composer install

jwt-keypair:
	@test -f backend/config/jwt/private.pem || $(EXEC_APP) php bin/console lexik:jwt:generate-keypair

migrate-dev:
	$(EXEC_APP) php bin/console doctrine:migrations:migrate -n

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f $(filter-out $@,$(MAKECMDGOALS))

ps:
	$(COMPOSE) ps

bash:
	@if [ -z "$(filter-out $@,$(MAKECMDGOALS))" ]; then echo "Usage: make bash <service>"; exit 1; fi
	$(COMPOSE) exec -it $(filter-out $@,$(MAKECMDGOALS)) sh -c 'command -v bash >/dev/null 2>&1 && exec bash || exec sh'

# Checkout (or create) a branch, pull, and bring the whole stack back to a
# working state on it: `make branch feature/uploads`. Safe on the current
# branch too — every step here is idempotent.
branch:
	@if [ -z "$(filter-out $@,$(MAKECMDGOALS))" ]; then echo "Usage: make branch <name>"; exit 1; fi
	git fetch --prune
	git checkout $(filter-out $@,$(MAKECMDGOALS)) 2>/dev/null || git checkout -b $(filter-out $@,$(MAKECMDGOALS))
	git pull || true
	$(MAKE) start
	$(EXEC_FRONTEND) npm install
	$(COMPOSE) restart frontend

## --- backend (proxied into the app container) ---
console:
	$(EXEC_APP) php bin/console $(filter-out $@,$(MAKECMDGOALS))
composer:
	$(EXEC_APP) composer $(filter-out $@,$(MAKECMDGOALS))
cc:
	$(EXEC_APP) php bin/console cache:clear
migration:
	$(EXEC_APP) php bin/console make:migration
migrate:
	$(EXEC_APP) php bin/console doctrine:migrations:migrate
	$(EXEC_APP) bash -c "APP_ENV=test php bin/console doctrine:migrations:migrate"
entity:
	$(EXEC_APP) php bin/console make:entity
test-backend:
	$(EXEC_APP) php bin/phpunit
fixtures:
	$(EXEC_APP) php bin/console doctrine:fixtures:load -n
rector:
	$(EXEC_APP) composer rector
rector-fix:
	$(EXEC_APP) composer rector:fix
cs:
	$(EXEC_APP) composer cs
cs-fix:
	$(EXEC_APP) composer cs:fix
phpstan:
	# phpstan-symfony reads the container dump (see backend/phpstan.neon.dist
	# containerXmlPath) to know service types — regenerate it first so results
	# reflect the current container, not a stale one.
	$(EXEC_APP) bash -c "php bin/console debug:container --format=xml --env=dev > var/cache/dev/App_KernelDevDebugContainer.xml"
	$(EXEC_APP) composer phpstan

## --- frontend (proxied into the frontend container) ---
install:
	$(EXEC_FRONTEND) npm install
npm:
	$(EXEC_FRONTEND) npm $(filter-out $@,$(MAKECMDGOALS))
test-frontend:
	$(EXEC_FRONTEND) npm test
lint:
	$(EXEC_FRONTEND) npm run lint
lint-fix:
	$(EXEC_FRONTEND) npm run lint -- --fix

## --- both ---
test: test-backend test-frontend

## --- database (postgres, direct docker exec — no compose rebuild needed) ---
db-cli:
	docker exec -it $(DB_CONTAINER) psql -U $(DB_USER) $(DB_NAME)

db-dump:
	$(eval FILE := $(DB_NAME)_$(shell date +%Y%m%d_%H%M%S).sql)
	docker exec $(DB_CONTAINER) pg_dump -U $(DB_USER) $(DB_NAME) > $(FILE)
	@echo "Dumped to: $(FILE)"

# make db-restore <file.sql>
db-restore:
	$(eval FILE := $(filter-out $@,$(MAKECMDGOALS)))
	@if [ -z "$(FILE)" ]; then echo "Usage: make db-restore <file.sql>"; exit 1; fi
	@if [ ! -f "$(FILE)" ]; then echo "Error: $(FILE) not found"; exit 1; fi
	docker exec $(DB_CONTAINER) psql -U $(DB_USER) -d postgres -c "DROP DATABASE IF EXISTS \"$(DB_NAME)\";"
	docker exec $(DB_CONTAINER) psql -U $(DB_USER) -d postgres -c "CREATE DATABASE \"$(DB_NAME)\";"
	docker exec -i $(DB_CONTAINER) psql -U $(DB_USER) $(DB_NAME) < $(FILE)
	@echo "Restored $(DB_NAME) from $(FILE)"

# Grants ROLE_ADMIN to an existing user by email: make admin someone@example.com
admin:
	$(eval EMAIL := $(filter-out $@,$(MAKECMDGOALS)))
	@if [ -z "$(EMAIL)" ]; then echo "Usage: make admin <email>"; exit 1; fi
	@EXISTS=$$(docker exec $(DB_CONTAINER) psql -U $(DB_USER) -d $(DB_NAME) -tAc "SELECT COUNT(*) FROM \"user\" WHERE email = '$(EMAIL)'"); \
	if [ "$$EXISTS" != "1" ]; then echo "No user with email $(EMAIL) in $(DB_NAME)"; exit 1; fi
	docker exec $(DB_CONTAINER) psql -U $(DB_USER) -d $(DB_NAME) -c \
		"UPDATE \"user\" SET roles = '[\"ROLE_ADMIN\"]' WHERE email = '$(EMAIL)';"
	@echo "Done: $(EMAIL) now has ROLE_ADMIN."

.PHONY: help start env-setup composer-install jwt-keypair migrate-dev branch up down logs ps bash console composer cc migration migrate entity test-backend fixtures rector rector-fix cs cs-fix phpstan install npm test-frontend lint lint-fix test db-cli db-dump db-restore admin

# Catch-all: lets targets above take a trailing word (branch name, console
# command, email, filename...) via $(filter-out $@,$(MAKECMDGOALS)) without
# make complaining "No rule to make target 'that-word'".
%:
	@:
