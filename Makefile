SHELL := /bin/bash

COMPOSE := docker compose -f docker-compose.yml -f docker-compose.dev.yml
EXEC_APP := $(COMPOSE) exec app
EXEC_FRONTEND := $(COMPOSE) exec frontend

# Override e.g. `make db-cli DB_NAME=other_db`.
DB_CONTAINER := pouch_db
DB_USER      := app
DB_NAME      := app

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
	@echo '    lint            - biome lint'
	@echo '    lint-fix        - biome lint --write'
	@echo '  --- both ---'
	@echo '    test            - run backend + frontend tests'
	@echo '  --- database (postgres) ---'
	@echo '    db-cli          - open a psql shell'
	@echo '    db-dump         - pg_dump to a timestamped .sql file in the repo root'
	@echo '    db-restore <f>  - drop, recreate and restore db from a .sql file'
	@echo '    admin <email>   - grant ROLE_ADMIN to an existing user'

## --- stack ---

# Safe to re-run — env files and the JWT keypair are only created if missing.
start: env-setup up composer-install jwt-keypair test-env-jwt migrate-dev
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
		printf 'STORAGE_ENDPOINT=http://minio:9000\nSTORAGE_BUCKET=pouch\nSTORAGE_KEY=pouch\nSTORAGE_SECRET=pouch-dev-secret\n' >> backend/.env.local; \
	}
	@test -f backend/.env.test.local || { \
		printf 'POSTGRES_PASSWORD=app\nDATABASE_URL=postgresql://app:app@db:5432/app?serverVersion=16&charset=utf8\n' > backend/.env.test.local; \
		printf 'STORAGE_ENDPOINT=http://minio:9000\nSTORAGE_BUCKET=pouch\nSTORAGE_KEY=pouch\nSTORAGE_SECRET=pouch-dev-secret\n' >> backend/.env.test.local; \
	}

composer-install:
	$(EXEC_APP) composer install

jwt-keypair:
	@test -f backend/config/jwt/private.pem || $(EXEC_APP) php bin/console lexik:jwt:generate-keypair

test-env-jwt:
	$(EXEC_APP) php bin/generate-test-jwt.php

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

# make branch feature/uploads
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
	# Full wipe, not cache:clear: a dev container warmed via cache:clear can leave
	# debug:container's XML dump missing bundle parameters (e.g.
	# lexik_jwt_authentication.token_ttl), which makes phpstan-symfony fall back to
	# a generic ParameterBagInterface::get() return type and misfire on ConfigService.
	# Only a dump from a fully cold cache has reliably included them.
	$(EXEC_APP) rm -rf var/cache/dev
	$(EXEC_APP) mkdir -p var/cache/dev
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

# catch-all so trailing args (branch/email/filename) don't error as unknown targets
%:
	@:
