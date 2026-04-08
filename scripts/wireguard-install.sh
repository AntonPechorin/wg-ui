#!/usr/bin/env bash
set -euo pipefail

WG_IFACE="${WG_IFACE:-wg0}"
WG_NETWORK="${WG_NETWORK:-10.66.66.0/24}"
WG_SERVER_IP="${WG_SERVER_IP:-10.66.66.1/24}"
WG_PORT="${WG_PORT:-51820}"
WG_DNS1="${WG_DNS1:-1.1.1.1}"
WG_DNS2="${WG_DNS2:-1.0.0.1}"
WG_ALLOWED_IPS="${WG_ALLOWED_IPS:-0.0.0.0/0}"
WG_KEEPALIVE="${WG_KEEPALIVE:-25}"
WG_CLIENTS_DIR="/etc/wireguard/clients"
PANEL_ROOT="/opt/wg-ui"
CLIENTS_JSON="${PANEL_ROOT}/storage/clients/clients.json"
STATE_JSON="${PANEL_ROOT}/storage/state/system.json"

log(){ echo "[wireguard] $*"; }

ensure_dirs(){
  mkdir -p /etc/wireguard "${WG_CLIENTS_DIR}" "${PANEL_ROOT}/storage/clients" "${PANEL_ROOT}/storage/state" "${PANEL_ROOT}/storage/logs"
  chmod 700 /etc/wireguard
}

calc_client_ip(){
  local count
  count="$(jq '.clients | length' "${CLIENTS_JSON}" 2>/dev/null || echo 0)"
  echo "10.66.66.$((count + 2))/32"
}

save_client_json(){
  local name="$1" ip="$2" pub="$3" priv="$4" psk="$5" blocked="$6"
  local tmp
  tmp="$(mktemp)"
  jq --arg n "$name" --arg ip "$ip" --arg pub "$pub" --arg priv "$priv" --arg psk "$psk" --argjson blocked "$blocked" '
    .clients += [{name:$n, ip:$ip, public_key:$pub, private_key:$priv, preshared_key:$psk, blocked:$blocked, created_at:now|todate}]' "${CLIENTS_JSON}" > "$tmp"
  mv "$tmp" "${CLIENTS_JSON}"
}

rebuild_server_conf(){
  local server_private server_public ext_if ext_ip
  server_private="$(cat /etc/wireguard/server_private.key)"
  server_public="$(cat /etc/wireguard/server_public.key)"
  ext_if="$(jq -r '.external_interface' "${STATE_JSON}")"
  ext_ip="$(jq -r '.external_ip' "${STATE_JSON}")"

  cat > "/etc/wireguard/${WG_IFACE}.conf" <<CONF
[Interface]
Address = ${WG_SERVER_IP}
ListenPort = ${WG_PORT}
PrivateKey = ${server_private}
SaveConfig = false
PostUp = iptables -A FORWARD -i ${WG_IFACE} -j ACCEPT; iptables -A FORWARD -o ${WG_IFACE} -j ACCEPT; iptables -t nat -A POSTROUTING -o ${ext_if} -j MASQUERADE
PostDown = iptables -D FORWARD -i ${WG_IFACE} -j ACCEPT; iptables -D FORWARD -o ${WG_IFACE} -j ACCEPT; iptables -t nat -D POSTROUTING -o ${ext_if} -j MASQUERADE
CONF

  jq -c '.clients[]' "${CLIENTS_JSON}" | while read -r row; do
    local blocked
    blocked="$(echo "$row" | jq -r '.blocked')"
    if [[ "$blocked" == "true" ]]; then
      continue
    fi

    cat >> "/etc/wireguard/${WG_IFACE}.conf" <<PEER

[Peer]
PublicKey = $(echo "$row" | jq -r '.public_key')
PresharedKey = $(echo "$row" | jq -r '.preshared_key')
AllowedIPs = $(echo "$row" | jq -r '.ip')
PEER
  done

  chmod 600 "/etc/wireguard/${WG_IFACE}.conf"
  jq --arg pub "$server_public" --arg ip "$ext_ip" '.server_public_key=$pub | .external_ip=$ip' "${STATE_JSON}" > "${STATE_JSON}.tmp"
  mv "${STATE_JSON}.tmp" "${STATE_JSON}"
}

add_client(){
  local name="$1"
  [[ "$name" =~ ^[a-zA-Z0-9_-]+$ ]] || { echo "Invalid name"; exit 1; }

  if jq -e --arg n "$name" '.clients[] | select(.name==$n)' "${CLIENTS_JSON}" >/dev/null; then
    echo "Client exists"; exit 1
  fi

  local server_public server_ip client_ip client_private client_public psk endpoint
  server_public="$(cat /etc/wireguard/server_public.key)"
  server_ip="$(echo "${WG_SERVER_IP}" | cut -d'/' -f1)"
  client_ip="$(calc_client_ip)"
  endpoint="$(jq -r '.external_ip' "${STATE_JSON}"):${WG_PORT}"

  client_private="$(wg genkey)"
  client_public="$(printf '%s' "$client_private" | wg pubkey)"
  psk="$(wg genpsk)"

  cat > "${WG_CLIENTS_DIR}/${name}.conf" <<CCONF
[Interface]
PrivateKey = ${client_private}
Address = ${client_ip}
DNS = ${WG_DNS1},${WG_DNS2}

[Peer]
PublicKey = ${server_public}
PresharedKey = ${psk}
AllowedIPs = ${WG_ALLOWED_IPS}
Endpoint = ${endpoint}
PersistentKeepalive = ${WG_KEEPALIVE}
CCONF

  chmod 600 "${WG_CLIENTS_DIR}/${name}.conf"
  save_client_json "$name" "$client_ip" "$client_public" "$client_private" "$psk" false
  rebuild_server_conf
  systemctl restart "wg-quick@${WG_IFACE}"
}

delete_client(){
  local name="$1" tmp
  tmp="$(mktemp)"
  jq --arg n "$name" '.clients |= map(select(.name != $n))' "${CLIENTS_JSON}" > "$tmp"
  mv "$tmp" "${CLIENTS_JSON}"
  rm -f "${WG_CLIENTS_DIR}/${name}.conf"
  rebuild_server_conf
  systemctl restart "wg-quick@${WG_IFACE}"
}

set_block(){
  local name="$1" blocked="$2" tmp
  tmp="$(mktemp)"
  jq --arg n "$name" --argjson b "$blocked" '.clients |= map(if .name == $n then .blocked = $b else . end)' "${CLIENTS_JSON}" > "$tmp"
  mv "$tmp" "${CLIENTS_JSON}"
  rebuild_server_conf
  systemctl restart "wg-quick@${WG_IFACE}"
}

install_base(){
  log "Generate keys"
  wg genkey | tee /etc/wireguard/server_private.key | wg pubkey > /etc/wireguard/server_public.key
  chmod 600 /etc/wireguard/server_private.key /etc/wireguard/server_public.key

  if [[ ! -f "${CLIENTS_JSON}" ]]; then
    echo '{"clients":[]}' > "${CLIENTS_JSON}"
  fi

  local ext_if ext_ip
  ext_if="$(ip route get 1.1.1.1 | awk '{for(i=1;i<=NF;i++) if ($i=="dev") {print $(i+1); exit}}')"
  ext_ip="$(curl -4fsS https://api.ipify.org || true)"
  if [[ -z "$ext_ip" ]]; then
    ext_ip="$(ip -4 addr show "$ext_if" | awk '/inet /{print $2}' | cut -d/ -f1 | head -n1)"
  fi

  cat > "${STATE_JSON}" <<JSON
{
  "external_interface": "${ext_if}",
  "external_ip": "${ext_ip}",
  "wireguard_interface": "${WG_IFACE}",
  "wireguard_port": ${WG_PORT},
  "wireguard_network": "${WG_NETWORK}",
  "updated_at": "$(date -Iseconds)"
}
JSON

  log "Enable IP forwarding"
  cat > /etc/sysctl.d/99-wireguard-forward.conf <<SYS
net.ipv4.ip_forward=1
net.ipv6.conf.all.forwarding=1
SYS
  sysctl --system >/dev/null

  rebuild_server_conf

  systemctl enable "wg-quick@${WG_IFACE}" --now

  if ! jq -e '.clients[] | select(.name=="test-client")' "${CLIENTS_JSON}" >/dev/null; then
    add_client "test-client"
  fi
}

usage(){
  cat <<TXT
Usage: $0 {install|rebuild|add-client|delete-client|block-client|unblock-client}
TXT
}

ensure_dirs

case "${1:-}" in
  install) install_base ;;
  rebuild) rebuild_server_conf ;;
  add-client) add_client "${2:-}" ;;
  delete-client) delete_client "${2:-}" ;;
  block-client) set_block "${2:-}" true ;;
  unblock-client) set_block "${2:-}" false ;;
  *) usage; exit 1 ;;
esac
