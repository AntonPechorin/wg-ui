#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${WG_PANEL_DOMAIN:-}"
EMAIL="${WG_PANEL_EMAIL:-}"
EXTERNAL_IFACE="${WG_PANEL_EXTERNAL_IFACE:-}"
WG_INTERFACE="${WG_PANEL_INTERFACE:-wg0}"
WG_PORT="${WG_PANEL_WG_PORT:-51820}"
VPN_CIDR="${WG_PANEL_VPN_CIDR:-10.77.0.0/24}"
SERVER_ADDRESS="${WG_PANEL_SERVER_ADDRESS:-}"
LISTEN_IP="${WG_PANEL_LISTEN_IP:-0.0.0.0}"
SSH_PORT="${WG_PANEL_SSH_PORT:-22}"
ENABLE_ROOT_WRAPPER="${WG_PANEL_ENABLE_ROOT_WRAPPER:-true}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2 ;;
    --email) EMAIL="$2"; shift 2 ;;
    --external-iface) EXTERNAL_IFACE="$2"; shift 2 ;;
    --wg-interface) WG_INTERFACE="$2"; shift 2 ;;
    --wg-port) WG_PORT="$2"; shift 2 ;;
    --vpn-cidr) VPN_CIDR="$2"; shift 2 ;;
    --server-address) SERVER_ADDRESS="$2"; shift 2 ;;
    --listen-ip) LISTEN_IP="$2"; shift 2 ;;
    --ssh-port) SSH_PORT="$2"; shift 2 ;;
    --enable-root-wrapper) ENABLE_ROOT_WRAPPER="$2"; shift 2 ;;
    *) echo "Unknown arg $1"; exit 1 ;;
  esac
done

[[ "$EUID" -eq 0 ]] || { echo "Run as root"; exit 1; }
grep -qi ubuntu /etc/os-release || { echo "Ubuntu only"; exit 1; }

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[[ "$PROJECT_ROOT" != "/" ]] || { echo "PROJECT_ROOT cannot be /"; exit 1; }
[[ -d "$PROJECT_ROOT/app" && -d "$PROJECT_ROOT/public" ]] || { echo "Run from project root with app/ and public/"; exit 1; }

if [[ ! -f "/etc/wireguard/${WG_INTERFACE}.conf" ]]; then
  echo "WireGuard config /etc/wireguard/${WG_INTERFACE}.conf not found"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx php-fpm php-cli php-mbstring php-xml php-curl php-zip php-opcache qrencode unzip curl jq rsync openssl fail2ban certbot python3-certbot-nginx

PHP_FPM_SERVICE="$(systemctl list-units --type=service --all | awk '/php.*fpm.service/{print $1; exit}')"
[[ -n "$PHP_FPM_SERVICE" ]] || { echo "php-fpm service not found"; exit 1; }
PHP_VERSION="$(echo "$PHP_FPM_SERVICE" | sed -E 's/php([0-9]+\.[0-9]+)-fpm.service/\1/')"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

install -d -m 750 /opt/wg-panel
rsync -a --delete --exclude '.git' --exclude 'storage/sessions/*' "$PROJECT_ROOT/" /opt/wg-panel/

install -d -m 750 /opt/wg-panel/storage/{clients,users,state,logs,cache,sessions,backups,tmp,locks}
install -d -m 750 /var/log/wg-panel
chown -R www-data:www-data /opt/wg-panel/storage /var/log/wg-panel
find /opt/wg-panel/storage -type d -exec chmod 750 {} \;
find /opt/wg-panel/storage -type f -exec chmod 640 {} \; 2>/dev/null || true

if rg -n "php8\.3-fpm|php8\.3" /opt/wg-panel >/dev/null 2>&1; then
  sed -i "s/php8\.3-fpm/${PHP_FPM_SERVICE%.service}/g; s/php8\.3/php${PHP_VERSION}/g" /opt/wg-panel/scripts/*.sh || true
fi

if [[ -z "$EXTERNAL_IFACE" ]]; then
  EXTERNAL_IFACE="$(ip route get 1.1.1.1 | awk '{for(i=1;i<=NF;i++) if ($i=="dev") {print $(i+1); exit}}')"
fi
if [[ -z "$SERVER_ADDRESS" ]]; then
  SERVER_ADDRESS="$(awk -F' = ' '/Endpoint/{print $2}' "/etc/wireguard/${WG_INTERFACE}.conf" | head -n1 | cut -d: -f1)"
  [[ -n "$SERVER_ADDRESS" ]] || SERVER_ADDRESS="$(curl -4 -s --max-time 5 ifconfig.me || hostname -I | awk '{print $1}')"
fi
SERVER_PUBLIC_KEY="$(wg show "$WG_INTERFACE" public-key 2>/dev/null || true)"
SERVER_PRIVATE_KEY_FILE="/etc/wireguard/server_private.key"
if [[ -f "$SERVER_PRIVATE_KEY_FILE" ]]; then
  SERVER_PRIVATE_KEY="$(cat "$SERVER_PRIVATE_KEY_FILE")"
else
  SERVER_PRIVATE_KEY="$(awk -F' = ' '/^PrivateKey/{print $2; exit}' "/etc/wireguard/${WG_INTERFACE}.conf")"
fi

cat >/opt/wg-panel/storage/state/server.json <<EOF
{
  "interface": "$WG_INTERFACE",
  "listen_port": $WG_PORT,
  "vpn_cidr": "$VPN_CIDR",
  "server_address": "$SERVER_ADDRESS",
  "endpoint": "$SERVER_ADDRESS:$WG_PORT",
  "external_iface": "$EXTERNAL_IFACE",
  "public_key": "$SERVER_PUBLIC_KEY",
  "private_key": "$SERVER_PRIVATE_KEY"
}
EOF

cat >/opt/wg-panel/storage/state/settings.json <<EOF
{
  "domain": "$DOMAIN",
  "email": "$EMAIL",
  "listen_ip": "$LISTEN_IP",
  "ssh_port": "$SSH_PORT",
  "enable_root_wrapper": "$ENABLE_ROOT_WRAPPER"
}
EOF

cat >/opt/wg-panel/storage/state/ipam.json <<EOF
{
  "cidr": "$VPN_CIDR",
  "next_ipv4": "",
  "allocated": []
}
EOF

ADMIN_PASS="$(openssl rand -base64 18 | tr -d '=+/ ' | cut -c1-16)"
ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASS")"
cat >/opt/wg-panel/storage/users/admin.json <<EOF
{
  "login": "admin",
  "password_hash": "$ADMIN_HASH",
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "must_change_password": true
}
EOF

cat >/etc/nginx/sites-available/wg-panel.conf <<EOF
server {
  listen 80;
  listen [::]:80;
  server_name ${DOMAIN:-_};
  root /opt/wg-panel/public;
  index index.php;

  location / {
    try_files \$uri /index.php?\$query_string;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:${PHP_SOCK};
    fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
  }

  location ~ /\. {
    deny all;
  }
}
EOF
ln -sf /etc/nginx/sites-available/wg-panel.conf /etc/nginx/sites-enabled/wg-panel.conf
rm -f /etc/nginx/sites-enabled/default

if [[ -n "$DOMAIN" && -n "$EMAIL" ]]; then
  certbot --nginx -d "$DOMAIN" --agree-tos -m "$EMAIL" --non-interactive || true
else
  install -d -m 700 /etc/nginx/ssl
  openssl req -x509 -nodes -newkey rsa:2048 -keyout /etc/nginx/ssl/wg-panel.key -out /etc/nginx/ssl/wg-panel.crt -days 825 -subj "/CN=${SERVER_ADDRESS}"
  cat >/etc/nginx/snippets/wg-panel-ssl.conf <<EOF
ssl_certificate /etc/nginx/ssl/wg-panel.crt;
ssl_certificate_key /etc/nginx/ssl/wg-panel.key;
EOF
fi

cat >/etc/fail2ban/jail.d/wg-panel.conf <<EOF
[wg-panel-auth]
enabled = true
port = http,https
filter = nginx-http-auth
logpath = /var/log/nginx/access.log
maxretry = 7
findtime = 600
bantime = 3600
EOF

if [[ "$ENABLE_ROOT_WRAPPER" == "true" ]]; then
  install -m 750 /opt/wg-panel/scripts/root-wrapper.sh /usr/local/bin/wg-root-wrapper
  cat >/etc/sudoers.d/wg-panel <<EOF
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-root-wrapper restart-wg
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-root-wrapper reboot-server
www-data ALL=(root) NOPASSWD: /usr/local/bin/wg-root-wrapper wg-show
EOF
  chmod 440 /etc/sudoers.d/wg-panel
fi

install -m 750 /opt/wg-panel/scripts/wireguard-install.sh /usr/local/bin/wireguard-install.sh

systemctl daemon-reload
systemctl enable --now "$PHP_FPM_SERVICE" nginx fail2ban
nginx -t
systemctl reload nginx

echo "WG panel installed at /opt/wg-panel"
echo "Admin login: admin"
echo "Admin password: $ADMIN_PASS"
