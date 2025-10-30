SHELL := /bin/sh

# ===== Variáveis =====
DC              := docker compose
PHP             := php
ARTISAN         := $(PHP) artisan
COMPOSER        := composer
ENV_FILE        := .env
COMPOSE_FILE    := docker-compose.yml
INIT_SENTINEL   := .make_init_done

# Host/porta do serve (pode sobrescrever via "make dev HOST=0.0.0.0 PORT=8080")
HOST ?= 127.0.0.1
PORT ?= 8000
RUN_DEV ?= 1

# ===== Alvos =====
.PHONY: help init init-only up down logs wait-db wait-redis ensure-storage composer-install env key migrate-install migrate seed swagger optimize fresh reset-db psql redis-cli status dev dev-sh dev-ps1

help:
	@echo ""
	@echo "Alvos principais:"
	@echo "  make init            -> Setup completo + abre 'serve' e 'queue:work' em 2 terminais"
	@echo "  make init-only       -> Só prepara (não abre terminais) [RUN_DEV=0]"
	@echo "  make dev             -> Abre os 2 terminais (ou roda em background)"
	@echo "  make up/down         -> Sobe/derruba Docker (db/redis)"
	@echo "  make migrate/seed    -> Migrations / seed"
	@echo "  make fresh           -> migrate:fresh --seed (zera o banco)"
	@echo "  make swagger         -> Gera OpenAPI (se L5 Swagger instalado)"
	@echo "  make reset-db        -> Derruba e recria volumes (db/redis) e refaz migrate+seed"
	@echo ""
	@echo "Utilitários:"
	@echo "  make status          -> Status dos serviços docker"
	@echo "  make logs            -> Logs do Postgres (follow)"
	@echo "  make psql            -> Entra no psql do container"
	@echo "  make redis-cli       -> Entra no redis-cli do container"
	@echo ""
	@echo "Parâmetros úteis:"
	@echo "  PORT=<porta> HOST=<host> RUN_DEV=0|1"
	@echo "  ex: make init PORT=8080 HOST=0.0.0.0"
	@echo ""

# ===== Fluxo completo =====
init: up env ensure-storage composer-install key wait-db wait-redis migrate-install migrate seed swagger optimize
	@if [ -f $(INIT_SENTINEL) ]; then \
		echo "✅ Já inicializado anteriormente ($(INIT_SENTINEL))."; \
	else \
		touch $(INIT_SENTINEL); \
		echo "✅ Inicialização concluída e marcada em $(INIT_SENTINEL)."; \
	fi
	@if [ "$(RUN_DEV)" = "1" ]; then \
		$(MAKE) dev HOST=$(HOST) PORT=$(PORT); \
	else \
		echo "ℹ️  RUN_DEV=0 — não abrindo terminais (use 'make dev' quando quiser)."; \
	fi

# Versão sem abrir terminais
init-only: RUN_DEV=0
init-only: init

# ===== Docker =====
up:
	$(DC) -f $(COMPOSE_FILE) up -d

down:
	$(DC) -f $(COMPOSE_FILE) down

logs:
	$(DC) -f $(COMPOSE_FILE) logs -f db

wait-db:
	@echo "⏳ Aguardando Postgres (db) ficar pronto..."
	@$(DC) exec -T db sh -lc 'for i in $$(seq 1 60); do pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB -h 127.0.0.1 -p 5432 && exit 0; sleep 1; done; exit 1'
	@echo "🟢 DB pronto."

wait-redis:
	@echo "⏳ Aguardando Redis (redis) ficar pronto..."
	@$(DC) exec -T redis sh -lc 'for i in $$(seq 1 60); do redis-cli ping | grep -q PONG && exit 0; sleep 1; done; exit 1'
	@echo "🟢 Redis pronto."

status:
	$(DC) -f $(COMPOSE_FILE) ps

psql:
	$(DC) exec db sh -lc 'psql -U $$POSTGRES_USER -d $$POSTGRES_DB -h 127.0.0.1 -p 5432'

redis-cli:
	$(DC) exec redis sh -lc 'redis-cli'

# ===== Laravel setup =====
ensure-storage:
	@mkdir -p storage/framework/{cache,data,sessions,testing,views} bootstrap/cache storage/logs
	@touch storage/logs/laravel.log
	@chmod -R ug+rwX storage bootstrap/cache || true
	@echo "🗂️  storage/ e bootstrap/cache ok."

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
			printf "APP_NAME=Laravel\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost:8000\n" > $(ENV_FILE); \
		fi; \
	else \
		echo "✅ $(ENV_FILE) já existe — ok."; \
	fi

key:
	@$(PHP) -r '($$e=parse_ini_file("$(ENV_FILE)"))!==false && (!isset($$e["APP_KEY"])||$$e["APP_KEY"]==="") ? exit(0): exit(1);' \
	&& echo "🔑 APP_KEY já existe — ok." \
	|| (echo "🔑 Gerando APP_KEY..." && $(ARTISAN) key:generate --force)

migrate-install:
	$(ARTISAN) migrate:install --force || true

migrate:
	$(ARTISAN) migrate --force

seed:
	$(ARTISAN) db:seed --force

swagger:
	@if [ -f config/l5-swagger.php ]; then \
		echo "📘 Gerando OpenAPI (l5-swagger)"; \
		$(ARTISAN) l5-swagger:generate; \
	else \
		echo "l5-swagger não configurado — pulando."; \
	fi

optimize:
	$(ARTISAN) optimize || true
	$(ARTISAN) config:cache || true
	$(ARTISAN) route:cache || true
	$(ARTISAN) view:cache || true
	$(ARTISAN) storage:link || true

fresh:
	$(ARTISAN) migrate:fresh --force --seed

reset-db: down
	@echo "🧹 Limpando volumes do Postgres/Redis..."
	@$(DC) -f $(COMPOSE_FILE) down -v
	@$(DC) -f $(COMPOSE_FILE) up -d
	@$(MAKE) wait-db wait-redis migrate-install migrate seed

# ===== DEV (serve + queue) =====
dev:
	@mkdir -p scripts
	@OS=$$(uname 2>/dev/null | tr '[:upper:]' '[:lower:]' || echo unknown); \
	case "$$OS" in \
		*mingw*|*msys*|*cygwin*) \
			$(MAKE) dev-ps1 HOST=$(HOST) PORT=$(PORT) ; \
			;; \
		*darwin*|*linux*) \
			$(MAKE) dev-sh HOST=$(HOST) PORT=$(PORT) ; \
			;; \
		*) \
			echo "SO não identificado; vou rodar em background..."; \
			nohup $(ARTISAN) serve --host=$(HOST) --port=$(PORT) > storage/logs/server.log 2>&1 & \
			echo "serve em background (logs: storage/logs/server.log)"; \
			nohup $(ARTISAN) queue:work --tries=3 --backoff=3 > storage/logs/queue.log 2>&1 & \
			echo "queue:work em background (logs: storage/logs/queue.log)"; \
			;; \
	esac

# Linux / macOS
dev-sh:
	@mkdir -p scripts
	@echo "🔧 Gerando scripts/dev.sh..."
	@printf '%s\n' '#!/usr/bin/env bash' > scripts/dev.sh
	@printf '%s\n' 'set -e' >> scripts/dev.sh
	@printf '%s\n' '' >> scripts/dev.sh
	@printf '%s\n' 'HOST="${HOST:-$(HOST)}"' >> scripts/dev.sh
	@printf '%s\n' 'PORT="${PORT:-$(PORT)}"' >> scripts/dev.sh
	@printf '%s\n' '' >> scripts/dev.sh
	@printf '%s\n' '# se tiver tmux, é a melhor experiência' >> scripts/dev.sh
	@printf '%s\n' 'if command -v tmux >/dev/null 2>&1; then' >> scripts/dev.sh
	@printf '%s\n' '  SESSION="laravel-dev"' >> scripts/dev.sh
	@printf '%s\n' '  tmux has-session -t "$$SESSION" 2>/dev/null && tmux kill-session -t "$$SESSION" || true' >> scripts/dev.sh
	@printf '%s\n' '  tmux new-session -d -s "$$SESSION" -n "app"' >> scripts/dev.sh
	@printf '%s\n' '  tmux send-keys -t "$$SESSION:app" "php artisan serve --host=$$HOST --port=$$PORT" C-m' >> scripts/dev.sh
	@printf '%s\n' '  tmux new-window -t "$$SESSION" -n "queue"' >> scripts/dev.sh
	@printf '%s\n' '  tmux send-keys -t "$$SESSION:queue" "php artisan queue:work --tries=3 --backoff=3" C-m' >> scripts/dev.sh
	@printf '%s\n' '  echo "🖥️  tmux session '\''$$SESSION'\'' criada (janelas: app, queue)."' >> scripts/dev.sh
	@printf '%s\n' '  echo "   Dica: tmux attach -t $$SESSION"' >> scripts/dev.sh
	@printf '%s\n' '  exit 0' >> scripts/dev.sh
	@printf '%s\n' 'fi' >> scripts/dev.sh
	@printf '%s\n' '' >> scripts/dev.sh
	@printf '%s\n' '# tenta alguns terminais comuns (Linux desktop)' >> scripts/dev.sh
	@printf '%s\n' 'for TERM_APP in gnome-terminal konsole kitty alacritty xfce4-terminal mate-terminal tilix wezterm x-terminal-emulator; do' >> scripts/dev.sh
	@printf '%s\n' '  if command -v "$$TERM_APP" >/dev/null 2>&1; then' >> scripts/dev.sh
	@printf '%s\n' '    case "$$TERM_APP" in' >> scripts/dev.sh
	@printf '%s\n' '      gnome-terminal|xfce4-terminal|mate-terminal|tilix|x-terminal-emulator)' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -- bash -lc "php artisan serve --host=$$HOST --port=$$PORT; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -- bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        ;;' >> scripts/dev.sh
	@printf '%s\n' '      konsole)' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -e bash -lc "php artisan serve --host=$$HOST --port=$$PORT; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -e bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        ;;' >> scripts/dev.sh
	@printf '%s\n' '      kitty|alacritty|wezterm)' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -e bash -lc "php artisan serve --host=$$HOST --port=$$PORT; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        "$$TERM_APP" -e bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &' >> scripts/dev.sh
	@printf '%s\n' '        ;;' >> scripts/dev.sh
	@printf '%s\n' '    esac' >> scripts/dev.sh
	@printf '%s\n' '    echo "🖥️  Abrindo duas janelas em $$TERM_APP (serve e queue)."' >> scripts/dev.sh
	@printf '%s\n' '    exit 0' >> scripts/dev.sh
	@printf '%s\n' '  fi' >> scripts/dev.sh
	@printf '%s\n' 'done' >> scripts/dev.sh
	@printf '%s\n' '' >> scripts/dev.sh
	@printf '%s\n' '# fallback: roda em background e grava logs' >> scripts/dev.sh
	@printf '%s\n' 'nohup php artisan serve --host="$$HOST" --port="$$PORT" > storage/logs/server.log 2>&1 &' >> scripts/dev.sh
	@printf '%s\n' 'nohup php artisan queue:work --tries=3 --backoff=3 > storage/logs/queue.log 2>&1 &' >> scripts/dev.sh
	@printf '%s\n' 'echo "🔧 Sem terminal detectado — rodando em background."' >> scripts/dev.sh
	@printf '%s\n' 'echo "   - server.log: storage/logs/server.log"' >> scripts/dev.sh
	@printf '%s\n' 'echo "   - queue.log : storage/logs/queue.log"' >> scripts/dev.sh
	@chmod +x scripts/dev.sh
	@HOST=$(HOST) PORT=$(PORT) scripts/dev.sh

dev-ps1:
	@mkdir -p scripts
	@echo "🔧 Gerando scripts/dev.ps1..."
	# primeira linha: assinatura do script
	@printf "param([string]\$$HostName=\"%s\", [string]\$$Port=\"%s\")\n" "$(HOST)" "$(PORT)" > scripts/dev.ps1
	# comandos
	@printf "\$$serveCmd = \"php artisan serve --host=\$$HostName --port=\$$Port\"\n" >> scripts/dev.ps1
	@printf "\$$queueCmd = \"php artisan queue:work --tries=3 --backoff=3\"\n" >> scripts/dev.ps1
	@printf "\n" >> scripts/dev.ps1
	@printf "function Start-In-WindowsTerminal {\n" >> scripts/dev.ps1
	@printf "  if (Get-Command wt -ErrorAction SilentlyContinue) {\n" >> scripts/dev.ps1
	@printf "    wt -w 0 nt PowerShell -NoExit \"\$$serveCmd\" ; nt PowerShell -NoExit \"\$$queueCmd\"\n" >> scripts/dev.ps1
	@printf "    Write-Host \"🖥️  Abrindo duas tabs no Windows Terminal (serve/queue).\"\n" >> scripts/dev.ps1
	@printf "    exit 0\n" >> scripts/dev.ps1
	@printf "  }\n" >> scripts/dev.ps1
	@printf "}\n" >> scripts/dev.ps1
	@printf "\n" >> scripts/dev.ps1
	@printf "function Start-In-PowerShellWindows {\n" >> scripts/dev.ps1
	@printf "  Start-Process powershell -ArgumentList \"-NoExit\", \$$serveCmd | Out-Null\n" >> scripts/dev.ps1
	@printf "  Start-Process powershell -ArgumentList \"-NoExit\", \$$queueCmd | Out-Null\n" >> scripts/dev.ps1
	@printf "  Write-Host \"🖥️  Abrindo duas janelas do PowerShell (serve/queue).\"\n" >> scripts/dev.ps1
	@printf "  exit 0\n" >> scripts/dev.ps1
	@printf "}\n" >> scripts/dev.ps1
	@printf "\n" >> scripts/dev.ps1
	@printf "try { Start-In-WindowsTerminal } catch {}\n" >> scripts/dev.ps1
	@printf "try { Start-In-PowerShellWindows } catch {}\n" >> scripts/dev.ps1
	@printf "\n" >> scripts/dev.ps1
	@printf "\$$serverLog = \"storage/logs/server.log\"\n" >> scripts/dev.ps1
	@printf "\$$queueLog  = \"storage/logs/queue.log\"\n" >> scripts/dev.ps1
	@printf "Start-Process powershell -ArgumentList \"-NoProfile\", \"-WindowStyle\", \"Hidden\", \"-Command `\"\$$serveCmd *> \$$serverLog`\"\"\n" >> scripts/dev.ps1
	@printf "Start-Process powershell -ArgumentList \"-NoProfile\", \"-WindowStyle\", \"Hidden\", \"-Command `\"\$$queueCmd  *> \$$queueLog`\"\"\n" >> scripts/dev.ps1
	@printf "Write-Host \"🔧 Sem terminal detectado — rodando em background.\"\n" >> scripts/dev.ps1
	@printf "Write-Host \"   - server.log: \$$serverLog\"\n" >> scripts/dev.ps1
	@printf "Write-Host \"   - queue.log : \$$queueLog\"\n" >> scripts/dev.ps1
	@powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -HostName "$(HOST)" -Port "$(PORT)"

