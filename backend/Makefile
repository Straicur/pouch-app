SHELL := /bin/bash

start:
	@echo "make [option]"
	@echo "OPTIONS:"
	@echo '    migration       - create doctrine migration'
	@echo '    migrate         - migrate database'
	@echo '    entity          - create entity'
	@echo '    test            - runs tests'
	@echo '    fixtures        - runs fixtures'
	@echo '    rector          - rector preview'
	@echo '    rector-fix      - rector apply fixes'
	@echo '    cs              - cs preview'
	@echo '    cs-fix          - cs apply fixes'
	@echo '    phpstan         - phpstan list of issues'
migration:
	php bin/console make:migration
migrate:
	php bin/console doctrine:migrations:migrate
	APP_ENV=test php bin/console doctrine:migrations:migrate
entity:
	php bin/console make:entity
test:
	php bin/phpunit
fixtures:
	php bin/console doctrine:fixtures:load -n
rector:
	composer rector
rector-fix:
	composer rector:fix
cs:
	composer cs
cs-fix:
	composer cs:fix
phpstan:
	composer phpstan
