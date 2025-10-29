#!/usr/bin/env bash
# install-make.sh
# Portable installer to ensure `make` is available on Linux/macOS (and WSL/Git Bash/MSYS2).
set -euo pipefail

cyan()  { printf "\033[36m%s\033[0m\n" "$*"; }
green() { printf "\033[32m%s\033[0m\n" "$*"; }
yellow(){ printf "\033[33m%s\033[0m\n" "$*"; }
red()   { printf "\033[31m%s\033[0m\n" "$*"; }

have() { command -v "$1" >/dev/null 2>&1; }

run() {
  if [ "${EUID:-$(id -u)}" -ne 0 ] && have sudo; then
    sudo "$@"
  else
    "$@"
  fi
}

show_make_version() {
  if have make; then
    green "make installed: $(make --version | head -n1)"
  else
    red "make is still not available on PATH."
    exit 1
  fi
}

if have make; then
  green "OK: make already installed."
  show_make_version
  exit 0
fi

cyan "Installing make..."

uname_s="$(uname -s 2>/dev/null || echo unknown)"
case "$uname_s" in
  Linux)   os_family="linux" ;;
  Darwin)  os_family="darwin";;
  MINGW*|MSYS*|CYGWIN*) os_family="msys";;
  *)       os_family="other" ;;
esac

try_apt()    { have apt-get && { yellow "Using apt-get"; run apt-get update -y -qq; DEBIAN_FRONTEND=noninteractive run apt-get install -y -qq make; }; }
try_dnf()    { have dnf     && { yellow "Using dnf"; run dnf install -y make; }; }
try_yum()    { have yum     && { yellow "Using yum"; run yum install -y make; }; }
try_zypper() { have zypper  && { yellow "Using zypper"; run zypper --non-interactive install -y make || run zypper --non-interactive install make; }; }
try_pacman() { have pacman  && { yellow "Using pacman"; run pacman -Sy --noconfirm make; }; }
try_apk()    { have apk     && { yellow "Using apk"; run apk add --no-cache make; }; }
try_brew()   {
  if have brew; then
    yellow "Using Homebrew"
    run brew update || true
    run brew install make || true
    if ! have make && have gmake; then
      yellow "Linking gmake -> make (optional)"
      run ln -sf "$(command -v gmake)" /usr/local/bin/make 2>/dev/null || true
    fi
  fi
}

installed=false
case "$os_family" in
  linux) ( try_apt || try_dnf || try_yum || try_zypper || try_pacman || try_apk ) && installed=true || installed=false ;;
  darwin) try_brew; installed=true || installed=false ;;
  msys) ( try_pacman ) && installed=true || installed=false ;;
  *) ( try_apt || try_dnf || try_yum || try_zypper || try_pacman || try_apk || try_brew ) && installed=true || installed=false ;;
esac

if [ "$installed" != true ] && ! have make; then
  red "Could not install make automatically."
  cat <<'EOF'
Next steps:
  • Install `make` using your OS package manager manually, e.g.:
      - Debian/Ubuntu:    sudo apt-get update && sudo apt-get install -y make
      - Fedora:           sudo dnf install -y make
      - RHEL/CentOS:      sudo yum install -y make
      - openSUSE:         sudo zypper install -y make
      - Arch:             sudo pacman -Sy --noconfirm make
      - Alpine:           sudo apk add --no-cache make
      - macOS (Homebrew): brew install make
  • Then re-run this script or your project setup.
EOF
  exit 1
fi

show_make_version
green "Installation finished successfully."
