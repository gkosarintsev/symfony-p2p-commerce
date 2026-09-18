.PHONY: up down restart build logs php worker test phpstan cs-fix fixtures migrate status

DC=docker compose
EXEC=$(DC) exec php

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

build:
	$(DC) build --no-cache

logs:
	$(DC) logs -f

php:
	$(DC) exec php bash

worker:
	$(DC) logs -f worker

migrate:
	$(EXEC) php bin/console doctrine:migrations:migrate -n

fixtures:
	$(EXEC) php bin/console doctrine:fixtures:load -n

test:
	$(EXEC) php bin/phpunit

phpstan:
	$(EXEC) vendor/bin/phpstan analyse -l 8 src tests

cs-fix:
	$(EXEC) vendor/bin/php-cs-fixer fix

status:
	$(DC) ps
