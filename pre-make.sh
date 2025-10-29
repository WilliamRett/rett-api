#!/usr/bin/env bash
set -e

echo "[pre-make] Detectando SO e gerenciador de pacotes..."

if command -v make >/dev/null 2>&1; then
  echo "[pre-make] 'make' já está instalado."
  exit 0
fi

if [[ "$OSTYPE" == "linux-gnu"* ]]; then
  if command -v apt-get >/dev/null 2>&1; then
    sudo apt-get update
    sudo apt-get install -y make
  elif command -v dnf >/dev/null 2>&1; then
    sudo dnf install -y make
  elif command -v yum >/dev/null 2>&1; then
    sudo yum install -y make
  elif command -v pacman >/dev/null 2>&1; then
    sudo pacman -Sy --noconfirm make
  elif command -v zypper >/dev/null 2>&1; then
    sudo zypper install -y make
  elif command -v apk >/dev/null 2>&1; then
    sudo apk add --no-cache make
  else
    echo "[pre-make] Não consegui detectar o gerenciador de pacotes. Instale 'make' manualmente."
    exit 1
  fi
elif [[ "$OSTYPE" == "darwin"* ]]; then
  if command -v brew >/dev/null 2>&1; then
    brew install make
  else
    echo "[pre-make] Homebrew não encontrado. Instale em https://brew.sh e rode de novo."
    exit 1
  fi
else
  echo "[pre-make] SO não suportado por este script. Use o pre-make.ps1 no Windows."
  exit 1
fi

echo "[pre-make] 'make' instalado com sucesso."
