#!/usr/bin/env bash
set -euo pipefail

WG_IFACE="wg0"
WIREGUARD_SCRIPT="/opt/wg-ui/scripts/wireguard-install.sh"

safe_name(){
  [[ "${1:-}" =~ ^[a-zA-Z0-9_-]+$ ]]
}

case "${1:-}" in
  restart)
    systemctl restart "wg-quick@${WG_IFACE}"
    ;;
  status)
    wg show all dump
    ;;
  service-status)
    systemctl is-active "wg-quick@${WG_IFACE}" || true
    ;;
  reboot)
    systemctl reboot
    ;;
  add-client|delete-client|block-client|unblock-client)
    safe_name "${2:-}" || { echo "invalid client name"; exit 1; }
    "$WIREGUARD_SCRIPT" "$1" "$2"
    ;;
  rebuild)
    "$WIREGUARD_SCRIPT" rebuild
    systemctl restart "wg-quick@${WG_IFACE}"
    ;;
  *)
    echo "Allowed: rebuild|restart|status|service-status|reboot|add-client|delete-client|block-client|unblock-client"
    exit 1
    ;;
esac
