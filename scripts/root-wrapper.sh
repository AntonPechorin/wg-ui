#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-}"
WG_INTERFACE="${WG_INTERFACE:-wg0}"
SERVER_JSON="/opt/wg-panel/storage/state/server.json"

if [[ -f "$SERVER_JSON" ]]; then
  WG_INTERFACE="$(jq -r '.interface // "wg0"' "$SERVER_JSON")"
fi

case "$ACTION" in
  restart-wg)
    exec /usr/bin/systemctl restart "wg-quick@${WG_INTERFACE}"
    ;;
  reboot-server)
    exec /usr/bin/systemctl reboot
    ;;
  wg-show)
    exec /usr/bin/wg show "$WG_INTERFACE"
    ;;
  *)
    echo "Usage: $0 {restart-wg|reboot-server|wg-show}"
    exit 1
    ;;
esac
