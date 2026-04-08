#!/usr/bin/env bash
set -euo pipefail

WG_IFACE="${WG_IFACE:-wg0}"
WG_PORT="${WG_PORT:-51820}"
WG_NET_BASE_V4="${WG_NET_BASE_V4:-10.77.0}"
WG_SERVER_IPV4="${WG_SERVER_IPV4:-10.77.0.1}"
WG_V4_SUBNET="${WG_V4_SUBNET:-10.77.0.0/24}"
WG_CLIENT_ALLOWED_IPS="${WG_CLIENT_ALLOWED_IPS:-0.0.0.0/0,::/0}"
WG_CLIENT_DNS="${WG_CLIENT_DNS:-1.1.1.1,1.0.0.1}"
PERSISTENT_KEEPALIVE="${PERSISTENT_KEEPALIVE:-25}"
INITIAL_CLIENT_NAME="${INITIAL_CLIENT_NAME:-client1}"
WAN_IFACE="${WAN_IFACE:-}"
ENDPOINT="${ENDPOINT:-}"
FIREWALL_BACKEND="${FIREWALL_BACKEND:-auto}"
AUTO_START="${AUTO_START:-true}"
FORCE="${FORCE:-false}"
ENABLE_IPV6="${ENABLE_IPV6:-false}"
WG_NET_BASE_V6="${WG_NET_BASE_V6:-fd42:42:42}"
WG_SERVER_IPV6="${WG_SERVER_IPV6:-fd42:42:42::1}"
WG_V6_SUBNET="${WG_V6_SUBNET:-fd42:42:42::/64}"
NAT_IPV6="${NAT_IPV6:-false}"
ALLOW_VPN_TO_SERVER="${ALLOW_VPN_TO_SERVER:-true}"
MTU="${MTU:-1420}"

WG_DIR="/etc/wireguard"
CLIENT_DIR="$WG_DIR/clients"
BACKUP_DIR="$WG_DIR/backups"
CONF_FILE="$WG_DIR/$WG_IFACE.conf"

require_root() { [[ "$EUID" -eq 0 ]] || { echo "Run as root"; exit 1; }; }
require_ubuntu() { grep -qi ubuntu /etc/os-release || { echo "Ubuntu required"; exit 1; }; }
need_cmd() { command -v "$1" >/dev/null 2>&1 || { echo "Missing $1"; exit 1; }; }

check_deps() {
  need_cmd ip
  need_cmd wg
  need_cmd wg-quick
  need_cmd systemctl
  need_cmd curl
  need_cmd qrencode
}

ensure_dirs() {
  install -d -m 700 "$WG_DIR" "$CLIENT_DIR" "$BACKUP_DIR"
}

auto_detect_wan() {
  if [[ -n "$WAN_IFACE" ]]; then return; fi
  WAN_IFACE="$(ip route get 1.1.1.1 | awk '{for(i=1;i<=NF;i++) if ($i=="dev") {print $(i+1); exit}}')"
  [[ -n "$WAN_IFACE" ]] || { echo "Cannot detect WAN_IFACE"; exit 1; }
}

auto_detect_endpoint() {
  if [[ -n "$ENDPOINT" ]]; then return; fi
  ENDPOINT="$(curl -4 -s --max-time 5 ifconfig.me || true)"
  [[ -n "$ENDPOINT" ]] || ENDPOINT="$(hostname -I | awk '{print $1}')"
  [[ -n "$ENDPOINT" ]] || { echo "Cannot detect endpoint"; exit 1; }
}

backup_conf() {
  if [[ -f "$CONF_FILE" ]]; then
    cp "$CONF_FILE" "$BACKUP_DIR/${WG_IFACE}.conf.$(date +%Y%m%d-%H%M%S).bak"
  fi
}

enable_forwarding() {
  cat >/etc/sysctl.d/99-wireguard-forward.conf <<EOF
net.ipv4.ip_forward=1
net.ipv6.conf.all.forwarding=1
EOF
  sysctl --system >/dev/null
}

setup_firewall() {
  local post_up post_down
  post_up="iptables -A FORWARD -i $WG_IFACE -j ACCEPT; iptables -A FORWARD -o $WG_IFACE -j ACCEPT; iptables -t nat -A POSTROUTING -o $WAN_IFACE -j MASQUERADE"
  post_down="iptables -D FORWARD -i $WG_IFACE -j ACCEPT; iptables -D FORWARD -o $WG_IFACE -j ACCEPT; iptables -t nat -D POSTROUTING -o $WAN_IFACE -j MASQUERADE"

  if command -v ufw >/dev/null 2>&1 && [[ "$FIREWALL_BACKEND" != "iptables" ]]; then
    ufw allow "$WG_PORT"/udp || true
  fi

  echo "$post_up|$post_down"
}

generate_server_keys() {
  [[ -f "$WG_DIR/server_private.key" ]] || wg genkey | tee "$WG_DIR/server_private.key" | wg pubkey > "$WG_DIR/server_public.key"
  chmod 600 "$WG_DIR/server_private.key"
}

next_client_ip() {
  local used last
  used="$(awk -F'[ /]' '/AllowedIPs/{print $4}' "$CONF_FILE" 2>/dev/null | awk -F. '{print $4}' | sort -n | tail -n1)"
  last="${used:-1}"
  echo "$WG_NET_BASE_V4.$((last+1))"
}

create_server_conf() {
  local fw up down
  fw="$(setup_firewall)"
  up="${fw%%|*}"; down="${fw##*|}"
  cat >"$CONF_FILE" <<EOF
[Interface]
Address = $WG_SERVER_IPV4/24
ListenPort = $WG_PORT
PrivateKey = $(cat "$WG_DIR/server_private.key")
MTU = $MTU
PostUp = $up
PostDown = $down
EOF
  chmod 600 "$CONF_FILE"
}

add_peer_to_server() {
  local name="$1" pub="$2" ip4="$3"
  cat >>"$CONF_FILE" <<EOF

[Peer]
# $name
PublicKey = $pub
AllowedIPs = $ip4/32
EOF
}

create_client() {
  local name="$1" cdir="$CLIENT_DIR/$name" cpriv cpub cpsk cip conf
  [[ "$name" =~ ^[a-zA-Z0-9_-]+$ ]] || { echo "Bad client name"; exit 1; }
  mkdir -p "$cdir"

  cpriv="$(wg genkey)"
  cpub="$(printf '%s' "$cpriv" | wg pubkey)"
  cpsk="$(wg genpsk)"
  cip="$(next_client_ip)"

  add_peer_to_server "$name" "$cpub" "$cip"

  conf="$cdir/$name.conf"
  cat >"$conf" <<EOF
[Interface]
PrivateKey = $cpriv
Address = $cip/32
DNS = $WG_CLIENT_DNS

[Peer]
PublicKey = $(cat "$WG_DIR/server_public.key")
PresharedKey = $cpsk
AllowedIPs = $WG_CLIENT_ALLOWED_IPS
Endpoint = $ENDPOINT:$WG_PORT
PersistentKeepalive = $PERSISTENT_KEEPALIVE
EOF
  chmod 600 "$conf"

  cat >"$cdir/$name.json" <<EOF
{
  "id": "$name",
  "name": "$name",
  "address": "$cip/32",
  "status": "active",
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "public_key": "$cpub",
  "conf_path": "$conf"
}
EOF

  qrencode -t ansiutf8 <"$conf" || true
  echo "Client config: $conf"
}

apply_live() {
  if wg show "$WG_IFACE" >/dev/null 2>&1; then
    wg syncconf "$WG_IFACE" <(wg-quick strip "$WG_IFACE")
  fi
}

show_client() {
  local name="$1" conf="$CLIENT_DIR/$name/$name.conf"
  [[ -f "$conf" ]] || { echo "Client not found"; exit 1; }
  cat "$conf"
  echo
  qrencode -t ansiutf8 <"$conf" || true
}

doctor() {
  echo "=== WireGuard doctor ==="
  echo "Interface: $WG_IFACE"
  echo "Conf file: $CONF_FILE"
  ip -br a || true
  wg show || true
  systemctl status "wg-quick@$WG_IFACE" --no-pager || true
  ip route || true
}

install_wg() {
  require_root; require_ubuntu; check_deps; ensure_dirs
  auto_detect_wan; auto_detect_endpoint
  backup_conf
  generate_server_keys
  enable_forwarding

  if [[ -f "$CONF_FILE" && "$FORCE" != "true" ]]; then
    echo "$CONF_FILE exists. Set FORCE=true to overwrite."
    exit 1
  fi

  create_server_conf
  create_client "$INITIAL_CLIENT_NAME"

  if [[ "$AUTO_START" == "true" ]]; then
    systemctl enable --now "wg-quick@$WG_IFACE"
  fi

  apply_live
  echo "Installed WireGuard on $WG_IFACE"
}

cmd="${1:-}"
case "$cmd" in
  install) install_wg ;;
  add-client) require_root; require_ubuntu; ensure_dirs; auto_detect_endpoint; create_client "${2:?client name required}"; apply_live ;;
  show-client) show_client "${2:?client name required}" ;;
  doctor) doctor ;;
  *)
    echo "Usage: $0 {install|add-client <name>|show-client <name>|doctor}"
    exit 1
    ;;
esac
