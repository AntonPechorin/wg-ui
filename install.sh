#!/usr/bin/env bash
set -euo pipefail

PANEL_ROOT="/opt/wg-ui"
REPO_TMP="/tmp/wg-ui-install"

log(){ echo "[install] $*"; }

require_root(){
  if [[ ${EUID} -ne 0 ]]; then
    echo "Run with sudo: sudo bash install.sh"
    exit 1
  fi
}

check_ubuntu(){
  if [[ ! -f /etc/os-release ]]; then
    echo "Cannot detect OS"
    exit 1
  fi
  . /etc/os-release
  if [[ "${ID:-}" != "ubuntu" ]]; then
    echo "This installer supports Ubuntu only"
    exit 1
  fi
}

install_packages(){
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y wireguard wireguard-tools nginx php-fpm php-cli php-mbstring php-xml php-curl php-zip php-opcache qrencode unzip curl jq rsync openssl
}

prepare_project_files(){
  local script_dir
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

  if [[ -d "${script_dir}/app" ]]; then
    log "Using local project files"
    rsync -a --delete --exclude '.git' "${script_dir}/" "${PANEL_ROOT}/"
    return
  fi

  if [[ -n "${WG_UI_REPO_URL:-}" ]]; then
    log "Fetching project from ${WG_UI_REPO_URL}"
    rm -rf "${REPO_TMP}"
    mkdir -p "${REPO_TMP}"
    curl -fsSL "${WG_UI_REPO_URL}" -o "${REPO_TMP}/archive.tar.gz"
    tar -xzf "${REPO_TMP}/archive.tar.gz" -C "${REPO_TMP}"
    local extracted
    extracted="$(find "${REPO_TMP}" -mindepth 1 -maxdepth 1 -type d | head -n1)"
    rsync -a --delete --exclude '.git' "${extracted}/" "${PANEL_ROOT}/"
    return
  fi

  echo "Project files not found. Use raw install.sh from repository root or set WG_UI_REPO_URL." >&2
  exit 1
}

run_wireguard_install(){
  chmod +x "${PANEL_ROOT}/scripts/wireguard-install.sh"
  "${PANEL_ROOT}/scripts/wireguard-install.sh" install
}

install_root_wrapper(){
  install -m 0750 -o root -g root "${PANEL_ROOT}/scripts/root-wrapper.sh" /usr/local/bin/wg-panel-root-wrapper.sh
}

run_panel_install(){
  chmod +x "${PANEL_ROOT}/scripts/start2.sh"
  (cd "${PANEL_ROOT}" && ./scripts/start2.sh)
}

print_summary(){
  local ip login password conf
  ip="$(curl -4fsS https://api.ipify.org || hostname -I | awk '{print $1}')"
  login="$(jq -r '.login' ${PANEL_ROOT}/storage/users/admin.json)"
  password="$(awk -F': ' '/Password:/{print $2}' /root/wg-ui-credentials.txt 2>/dev/null || echo 'See /root/wg-ui-credentials.txt')"
  conf="/etc/wireguard/clients/test-client.conf"

  echo
  echo "========================================="
  echo "WG UI installed successfully"
  echo "Panel: http://${ip}/"
  echo "Login: ${login}"
  echo "Password: ${password}"
  echo "Client config: ${conf}"
  echo "========================================="
}

require_root
check_ubuntu
install_packages
prepare_project_files
install_root_wrapper
run_wireguard_install
run_panel_install
print_summary
