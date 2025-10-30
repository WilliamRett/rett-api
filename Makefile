SHELL := /bin/sh

DC              ?= docker compose
COMPOSE_FILE    ?= docker-compose.yml
ENV_FILE        ?= .env
INIT_SENTINEL   ?= .make_init_done

# host/porta pra quando usar artisan serve (modo sem docker)
HOST ?= 127.0.0.1
PORT ?= 8000

# 1 = roda comandos dentro do container "app"
USE_DOCKER ?= 1
APP_SERVICE ?= app

ifeq ($(USE_DOCKER),1)
    PHP      := $(DC) -f $(COMPOSE_FILE) exec -T $(APP_SERVICE) php
    COMPOSER := $(DC) -f $(COMPOSE_FILE) exec -T $(APP_SERVICE) composer
else
    PHP      := php
    COMPOSER := composer
endif
ARTISAN := $(PHP) artisan

.PHONY: help init init-only up down logs wait-db wait-redis ensure-storage composer-install env key migrate-install migrate seed swagger optimize fresh reset-db psql redis-cli status dev dev-sh dev-ps1

help:
	@echo ""
	@echo "make init              -> sobe docker, prepara .env, instala deps, migra, seed, swagger"
	@echo "make init USE_DOCKER=0 -> faz o mesmo mas rodando php/composer na máquina"
	@echo "make dev               -> abre serve + queue (apenas fora do docker)"
	@echo "make up / make down    -> sobe/derruba stack docker"
	@echo ""

init: up env ensure-storage composer-install key wait-db wait-redis migrate-install migrate seed swagger optimize
	@if [ -f $(INIT_SENTINEL) ]; then \
		echo "✅ Já inicializado anteriormente ($(INIT_SENTINEL))."; \
	else \
		touch $(INIT_SENTINEL); \
		echo "✅ Inicialização concluída e marcada em $(INIT_SENTINEL)."; \
	fi
	@if [ "$(USE_DOCKER)" = "0" ]; then \
		$(MAKE) dev HOST=$(HOST) PORT=$(PORT); \
	else \
		echo "ℹ️  USE_DOCKER=1 — app está rodando no container (nginx em :8000, queue em container)."; \
	fi

init-only: USE_DOCKER=1
init-only: init

up:
	$(DC) -f $(COMPOSE_FILE) up -d

down:
	$(DC) -f $(COMPOSE_FILE) down

logs:
	$(DC) -f $(COMPOSE_FILE) logs -f

wait-db:
	@echo "⏳ Aguardando Postgres (db) ficar pronto..."
	@$(DC) -f $(COMPOSE_FILE) exec -T db sh -lc 'for i in $$(seq 1 60); do pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB -h 127.0.0.1 -p 5432 && exit 0; sleep 1; done; exit 1'
	@echo "🟢 DB pronto."

wait-redis:
	@echo "⏳ Aguardando Redis (redis) ficar pronto..."
	@$(DC) -f $(COMPOSE_FILE) exec -T redis sh -lc 'for i in $$(seq 1 60); do redis-cli ping | grep -q PONG && exit 0; sleep 1; done; exit 1'
	@echo "🟢 Redis pronto."

ensure-storage:
	@mkdir -p storage/framework/{cache,data,sessions,testing,views} bootstrap/cache storage/logs || true
	@chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
	@-touch storage/logs/laravel.log 2>/dev/null || true
	@echo "🗂️  storage/ e bootstrap/cache ok (host). Se não deu permissão, o container vai ajustar."

composer-install:
	@if [ -f composer.json ]; then \
		echo "📦 composer install"; \
		$(COMPOSER) install --no-interaction --prefer-dist --optimize-autoloader; \
	else \
		echo "composer.json não encontrado — pulando."; \
	fi

env:
	@if [ ! -f $(ENV_FILE) ]; then \
		if [ -f .env.example ]; then \
			echo "📝 Criando .env a partir de .env.example"; \
			cp .env.example $(ENV_FILE); \
		else \
			echo "⚠️  .env e .env.example não existem; gerando .env mínimo"; \
			printf "APP_NAME=Laravel\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost:8000\nDB_CONNECTION=pgsql\nDB_HOST=db\nDB_PORT=5432\nDB_DATABASE=rett\nDB_USERNAME=rett\nDB_PASSWORD=rett1234\n" > $(ENV_FILE); \
		fi; \
	else \
		echo "✅ $(ENV_FILE) já existe — ok."; \
	fi

key:
	@$(PHP) -r '($$e=@parse_ini_file("$(ENV_FILE)"))!==false && (!isset($$e["APP_KEY"])||$$e["APP_KEY"]==="") ? exit(1): exit(0);' \
	&& echo "🔑 APP_KEY já existe — ok." \
	|| (echo "🔑 Gerando APP_KEY..." && $(ARTISAN) key:generate --force)

migrate-install:
	@$(ARTISAN) migrate:install || true

migrate:
	@$(ARTISAN) migrate --force

seed:
	@$(ARTISAN) db:seed --force

swagger:
	@if [ -f config/l5-swagger.php ]; then \
		echo "📘 Gerando OpenAPI (l5-swagger)"; \
		$(ARTISAN) l5-swagger:generate; \
	else \
		echo "l5-swagger não configurado — pulando."; \
	fi

optimize:
	@$(ARTISAN) optimize || true
	@$(ARTISAN) config:cache || true
	@$(ARTISAN) route:cache || true
	@$(ARTISAN) view:cache || true
	@$(ARTISAN) storage:link || true

fresh:
	@$(ARTISAN) migrate:fresh --force --seed

reset-db: down
	@echo "🧹 Limpando volumes do Postgres/Redis..."
	@$(DC) -f $(COMPOSE_FILE) down -v
	@$(DC) -f $(COMPOSE_FILE) up -d
	@$(MAKE) wait-db wait-redis migrate-install migrate seed

# ===== DEV FORA DO DOCKER =====
dev:
	@echo "⚠️  dev só faz sentido com USE_DOCKER=0 (rodando php artisan serve local)."
	@echo "⚠️  se estiver usando docker, acesse: http://127.0.0.1:8000"

