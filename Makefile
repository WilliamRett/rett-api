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

init: up env ensure-storage composer-install key wait-db wait-redis migrate-install migrate seed swagger optimize
	@if [ -f $(INIT_SENTINEL) ]; then \
		echo "✅ Já inicializado anteriormente (($(INIT_SENTINEL)))."; \
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

psql:
	$(DC) exec db sh -lc 'psql -U $$POSTGRES_USER -d $$POSTGRES_DB -h 127.0.0.1 -p 5432'

redis-cli:
	$(DC) exec redis sh -lc 'redis-cli'

status:
	$(DC) -f $(COMPOSE_FILE) ps

# ====== DEV ======
dev:
	@mkdir -p scripts
	@if command -v uname >/dev/null 2>&1; then \
		OS=$$(uname | tr '[:upper:]' '[:lower:]'); \
	else \
		OS="unknown"; \
	fi; \
	if echo $$OS | grep -qi "mingw\\|msys\\|cygwin"; then \
		$(MAKE) dev-ps1 HOST=$(HOST) PORT=$(PORT); \
	elif echo $$OS | grep -qi "darwin\\|linux"; then \
		$(MAKE) dev-sh HOST=$(HOST) PORT=$(PORT); \
	else \
		echo "SO não identificado; rodando em background..."; \
		nohup $(ARTISAN) serve --host=$(HOST) --port=$(PORT) > storage/logs/server.log 2>&1 & \
		&& echo "serve em background (logs: storage/logs/server.log)"; \
		nohup $(ARTISAN) queue:work --tries=3 --backoff=3 > storage/logs/queue.log 2>&1 & \
		&& echo "queue:work em background (logs: storage/logs/queue.log)"; \
	fi

dev-sh:
	@chmod +x scripts/dev.sh 2>/dev/null || true
	@printf "%s\n" '#!/usr/bin/env bash' > scripts/dev.sh
	@printf "%s\n" 'set -e' >> scripts/dev.sh
	@printf "%s\n" 'HOST="${HOST:-127.0.0.1}"' >> scripts/dev.sh
	@printf "%s\n" 'PORT="${PORT:-8000}"' >> scripts/dev.sh
	@cat >> scripts/dev.sh << "EOF"
if command -v tmux >/dev/null 2>&1; then
  SESSION="laravel-dev"
  tmux has-session -t "$SESSION" 2>/dev/null && tmux kill-session -t "$SESSION" || true
  tmux new-session -d -s "$SESSION" -n "app"
  tmux send-keys -t "$SESSION:app" "php artisan serve --host=$HOST --port=$PORT" C-m
  tmux new-window -t "$SESSION" -n "queue"
  tmux send-keys -t "$SESSION:queue" "php artisan queue:work --tries=3 --backoff=3" C-m
  echo "🖥️  tmux session '$SESSION' criada (janelas: app, queue)."
  echo "   Dica: tmux attach -t $SESSION"
  exit 0
fi

# tenta alguns terminais comuns
for TERM_APP in gnome-terminal konsole kitty alacritty xfce4-terminal mate-terminal tilix wezterm x-terminal-emulator; do
  if command -v "$TERM_APP" >/dev/null 2>&1; then
    case "$TERM_APP" in
      gnome-terminal|xfce4-terminal|mate-terminal|tilix|x-terminal-emulator)
        "$TERM_APP" -- bash -lc "php artisan serve --host=$HOST --port=$PORT; exec bash" &
        "$TERM_APP" -- bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &
        ;;
      konsole)
        "$TERM_APP" -e bash -lc "php artisan serve --host=$HOST --port=$PORT; exec bash" &
        "$TERM_APP" -e bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &
        ;;
      kitty|alacritty|wezterm)
        "$TERM_APP" -e bash -lc "php artisan serve --host=$HOST --port=$PORT; exec bash" &
        "$TERM_APP" -e bash -lc "php artisan queue:work --tries=3 --backoff=3; exec bash" &
        ;;
    esac
    echo "🖥️  Abrindo duas janelas em $TERM_APP (serve e queue)."
    exit 0
  fi
done

# fallback: background + logs
nohup php artisan serve --host="$HOST" --port="$PORT" > storage/logs/server.log 2>&1 &
nohup php artisan queue:work --tries=3 --backoff=3 > storage/logs/queue.log 2>&1 &
echo "🔧 Sem terminal detectado — rodando em background."
echo "   - server.log: storage/logs/server.log"
echo "   - queue.log : storage/logs/queue.log"
EOF
	@scripts/dev.sh

dev-ps1:
	@printf "%s\n" 'param([string]$HostName="'"$(HOST)"'", [string]$Port="'"$(PORT)"'")' > scripts/dev.ps1
	@cat >> scripts/dev.ps1 << "EOF"
$serveCmd = "php artisan serve --host=$HostName --port=$Port"
$queueCmd = "php artisan queue:work --tries=3 --backoff=3"

function Start-In-WindowsTerminal {
  if (Get-Command wt -ErrorAction SilentlyContinue) {
    wt -w 0 nt PowerShell -NoExit "$serveCmd" `
       ; nt PowerShell -NoExit "$queueCmd"
    Write-Host "🖥️  Abrindo duas tabs no Windows Terminal (serve/queue)."
    exit 0
  }
}

function Start-In-PowerShellWindows {
  Start-Process powershell -ArgumentList "-NoExit", $serveCmd | Out-Null
  Start-Process powershell -ArgumentList "-NoExit", $queueCmd | Out-Null
  Write-Host "🖥️  Abrindo duas janelas do PowerShell (serve/queue)."
  exit 0
}

try { Start-In-WindowsTerminal } catch {}
try { Start-In-PowerShellWindows } catch {}

# fallback: background + logs
$serverLog = "storage/logs/server.log"
$queueLog  = "storage/logs/queue.log"
Start-Process powershell -ArgumentList "-NoProfile", "-WindowStyle Hidden", "-Command `"$serveCmd *> $serverLog`""
Start-Process powershell -ArgumentList "-NoProfile", "-WindowStyle Hidden", "-Command `"$queueCmd  *> $queueLog`""
Write-Host "🔧 Sem terminal detectado — rodando em background."
Write-Host "   - server.log: $serverLog"
Write-Host "   - queue.log : $queueLog"
EOF
	@powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -HostName "$(HOST)" -Port "$(PORT)"
