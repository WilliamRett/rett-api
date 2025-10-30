#!/usr/bin/env bash
set -e

HOST=""
PORT=""

# se tiver tmux, é a melhor experiência
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

# tenta alguns terminais comuns (Linux desktop)
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

# fallback: roda em background e grava logs
nohup php artisan serve --host="$HOST" --port="$PORT" > storage/logs/server.log 2>&1 &
nohup php artisan queue:work --tries=3 --backoff=3 > storage/logs/queue.log 2>&1 &
echo "🔧 Sem terminal detectado — rodando em background."
echo "   - server.log: storage/logs/server.log"
echo "   - queue.log : storage/logs/queue.log"
