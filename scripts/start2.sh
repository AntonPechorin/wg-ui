#!/usr/bin/env bash
set -euo pipefail

PANEL_ROOT="/opt/wg-ui"
PHP_SOCKET=""

log(){ echo "[panel] $*"; }

detect_php_socket(){
  PHP_SOCKET="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' | head -n1 || true)"
  if [[ -z "$PHP_SOCKET" ]]; then
    echo "php-fpm socket not found in /run/php" >&2
    exit 1
  fi
}

install_files(){
  log "Copy panel files to ${PANEL_ROOT}"
  mkdir -p "${PANEL_ROOT}"
  rsync -a --delete --exclude '.git' --exclude '.github' ./ "${PANEL_ROOT}/"
  mkdir -p "${PANEL_ROOT}/storage/clients" "${PANEL_ROOT}/storage/users" "${PANEL_ROOT}/storage/state" "${PANEL_ROOT}/storage/logs"
  chown -R www-data:www-data "${PANEL_ROOT}/storage"
  chmod -R 750 "${PANEL_ROOT}/storage"
}

install_nginx(){
  detect_php_socket
  log "Configure nginx"

  cat > /etc/nginx/sites-available/wg-ui.conf <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${PANEL_ROOT}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

  rm -f /etc/nginx/sites-enabled/default
  ln -sf /etc/nginx/sites-available/wg-ui.conf /etc/nginx/sites-enabled/wg-ui.conf
  nginx -t
  systemctl enable nginx --now
}

create_admin(){
  local login="admin" password hash
  if [[ ! -f "${PANEL_ROOT}/storage/users/admin.json" ]]; then
    password="$(openssl rand -base64 12 | tr -d '=+/ ' | cut -c1-14)"
    hash="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;' "$password")"

    cat > "${PANEL_ROOT}/storage/users/admin.json" <<JSON
{
  "login": "${login}",
  "password_hash": "${hash}",
  "created_at": "$(date -Iseconds)"
}
JSON

    cat > /root/wg-ui-credentials.txt <<CREDS
WG UI credentials
IP: http://$(curl -4fsS https://api.ipify.org || hostname -I | awk '{print $1}')/
Login: ${login}
Password: ${password}
Client config: /etc/wireguard/clients/test-client.conf
CREDS
    chmod 600 /root/wg-ui-credentials.txt
  fi
}

install_sudoers(){
  cat > /etc/sudoers.d/wg-ui <<'SUDO'
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh restart
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh status
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh service-status
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh add-client *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh delete-client *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh block-client *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh unblock-client *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh show-config *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh show-qr *
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-panel-root-wrapper.sh reboot
SUDO
  chmod 440 /etc/sudoers.d/wg-ui
}

restart_services(){
  systemctl enable php*-fpm --now || true
  systemctl restart nginx
}

install_files
install_nginx
create_admin
install_sudoers
restart_services
log "Panel installation complete"
