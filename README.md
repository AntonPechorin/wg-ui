# WG-UI: простой production-ready WireGuard + PHP panel (без Docker и без БД)

Self-hosted проект для Ubuntu Server с двумя сценариями:

- **Сценарий A (новый сервер):** сначала `wireguard-install.sh`, потом `panel-install.sh`.
- **Сценарий B (WireGuard уже есть):** только `panel-install.sh`, без поломки сетевой части.

Проект принципиально:

- без Docker;
- без MySQL/PostgreSQL/SQLite;
- без Laravel/Symfony/React/Vue;
- bash-first для WireGuard;
- JSON-only storage для панели.

---

## 1) Архитектура

### Состав

1. **WireGuard installer** — `scripts/wireguard-install.sh`
   - устанавливает и настраивает WireGuard по классической Linux/systemd модели (`wg`, `wg-quick`).
   - поддерживает команды: `install`, `add-client`, `show-client`, `doctor`.

2. **Panel-only installer** — `scripts/panel-install.sh` (алиас: `scripts/start2.sh`)
   - ставит только веб-слой (nginx + php-fpm + fail2ban + certbot и т.д.),
   - разворачивает проект в `/opt/wg-panel`,
   - считывает состояние существующего WireGuard и создаёт JSON state.

3. **Панель на чистом PHP**
   - роутинг + контроллеры + сервисы,
   - авторизация, CSRF, rate limit на логин,
   - управление клиентами через скрипт WireGuard,
   - runtime-статистика берётся напрямую из `wg show ... dump`.

### Данные (только JSON)

- `storage/clients/<id>.json`
- `storage/users/admin.json`
- `storage/state/server.json`
- `storage/state/settings.json`
- `storage/state/ipam.json`

Runtime-данные (`latest handshake`, `rx/tx`, `endpoint`) не пишутся в БД и не «кешируются навсегда» — берутся live из `wg`.

---

## 2) Полная структура каталогов

```text
app/
  Core/
  Controllers/
  Services/
  Repositories/
  Support/
bootstrap/
config/
public/
  assets/
routes/
scripts/
storage/
  clients/
  users/
  state/
  logs/
  cache/
  sessions/
  backups/
  tmp/
  locks/
systemd/
templates/
```

---

## 3) Требования

- Ubuntu Server (22.04/24.04+)
- root-доступ
- установленный `wireguard-tools` для сценария A (или уже готовый WG для сценария B)
- открытый UDP порт WireGuard
- домен (опционально, для Let’s Encrypt)

---

## 4) Быстрый запуск одной командой

> Ниже — **one-liner** для полного сценария (новый сервер).

### 4.1 Новый сервер (WireGuard + Panel) одной командой

```bash
sudo bash -lc '
set -e
cd /path/to/wg-ui
export WG_IFACE=wg0 WG_PORT=51820 WG_NET_BASE_V4=10.77.0 WG_SERVER_IPV4=10.77.0.1 WG_V4_SUBNET=10.77.0.0/24 INITIAL_CLIENT_NAME=admin-phone AUTO_START=true
scripts/wireguard-install.sh install
scripts/panel-install.sh --domain vpn.example.com --email admin@example.com --wg-interface wg0 --wg-port 51820 --vpn-cidr 10.77.0.0/24 --listen-ip 0.0.0.0 --ssh-port 22 --enable-root-wrapper true
'
```

### 4.2 Уже существующий WireGuard (только Panel) одной командой

```bash
sudo bash -lc '
set -e
cd /path/to/wg-ui
scripts/panel-install.sh --domain panel.example.com --email admin@example.com --wg-interface wg0 --wg-port 51820 --vpn-cidr 10.77.0.0/24 --listen-ip 0.0.0.0 --ssh-port 22 --enable-root-wrapper true
'
```

---

## 5) Пошаговый запуск

## 5.1 Сценарий A: новый сервер

### Шаг 1. Установка WireGuard

```bash
cd /path/to/wg-ui
sudo WG_IFACE=wg0 \
  WG_PORT=51820 \
  WG_NET_BASE_V4=10.77.0 \
  WG_SERVER_IPV4=10.77.0.1 \
  WG_V4_SUBNET=10.77.0.0/24 \
  WG_CLIENT_ALLOWED_IPS='0.0.0.0/0,::/0' \
  WG_CLIENT_DNS='1.1.1.1,1.0.0.1' \
  PERSISTENT_KEEPALIVE=25 \
  INITIAL_CLIENT_NAME=admin-phone \
  AUTO_START=true \
  scripts/wireguard-install.sh install
```

### Шаг 2. Установка панели

```bash
cd /path/to/wg-ui
sudo scripts/panel-install.sh \
  --domain vpn.example.com \
  --email admin@example.com \
  --external-iface eth0 \
  --wg-interface wg0 \
  --wg-port 51820 \
  --vpn-cidr 10.77.0.0/24 \
  --server-address 203.0.113.10 \
  --listen-ip 0.0.0.0 \
  --ssh-port 22 \
  --enable-root-wrapper true
```

После установки:
- панель в `/opt/wg-panel`;
- nginx + php-fpm запущены;
- логин: `admin`, пароль выводится в stdout installer.

## 5.2 Сценарий B: WireGuard уже настроен

```bash
cd /path/to/wg-ui
sudo scripts/panel-install.sh --wg-interface wg0 --wg-port 51820 --vpn-cidr 10.77.0.0/24
```

Installer:
- **не переустанавливает WireGuard**;
- **не переписывает по умолчанию** `/etc/wireguard/<iface>.conf`;
- **не рестартует по умолчанию** `wg-quick@<iface>`;
- **не трогает по умолчанию** firewall/sysctl.

---

## 6) Переменные/аргументы

## 6.1 `wireguard-install.sh` (через env)

- `WG_IFACE`
- `WG_PORT`
- `WG_NET_BASE_V4`
- `WG_SERVER_IPV4`
- `WG_V4_SUBNET`
- `WG_CLIENT_ALLOWED_IPS`
- `WG_CLIENT_DNS`
- `PERSISTENT_KEEPALIVE`
- `INITIAL_CLIENT_NAME`
- `WAN_IFACE`
- `ENDPOINT`
- `FIREWALL_BACKEND`
- `AUTO_START`
- `FORCE`
- `ENABLE_IPV6`
- `WG_NET_BASE_V6`
- `WG_SERVER_IPV6`
- `WG_V6_SUBNET`
- `NAT_IPV6`
- `ALLOW_VPN_TO_SERVER`
- `MTU`

Команды:

```bash
scripts/wireguard-install.sh install
scripts/wireguard-install.sh add-client <name>
scripts/wireguard-install.sh show-client <name>
scripts/wireguard-install.sh doctor
```

## 6.2 `panel-install.sh` (через args и env fallback)

Аргументы:

- `--domain`
- `--email`
- `--external-iface`
- `--wg-interface`
- `--wg-port`
- `--vpn-cidr`
- `--server-address`
- `--listen-ip`
- `--ssh-port`
- `--enable-root-wrapper`

Fallback env:

- `WG_PANEL_DOMAIN`
- `WG_PANEL_EMAIL`
- `WG_PANEL_EXTERNAL_IFACE`
- `WG_PANEL_INTERFACE`
- `WG_PANEL_WG_PORT`
- `WG_PANEL_VPN_CIDR`
- `WG_PANEL_SERVER_ADDRESS`
- `WG_PANEL_LISTEN_IP`
- `WG_PANEL_SSH_PORT`
- `WG_PANEL_ENABLE_ROOT_WRAPPER`

---

## 7) Безопасность

- Чувствительные файлы не лежат в `public/`.
- Storage имеет ограниченные права (`www-data`, 750/640).
- Пароли: `password_hash/password_verify`.
- Сессии: `httponly`, `samesite=strict`.
- CSRF-токены на POST.
- Защита brute-force через rate limit логина.
- Root-операции только через узкий wrapper + ограниченный sudoers.

---

## 8) Диагностика

### WireGuard

```bash
sudo scripts/wireguard-install.sh doctor
sudo wg show
sudo systemctl status wg-quick@wg0 --no-pager
sudo journalctl -u wg-quick@wg0 -n 200 --no-pager
```

### Panel / Nginx / PHP-FPM

```bash
sudo nginx -t
sudo systemctl status nginx --no-pager
sudo systemctl status php*-fpm --no-pager
sudo tail -n 200 /var/log/nginx/error.log
sudo tail -n 200 /var/log/nginx/access.log
```

### Fail2ban

```bash
sudo systemctl status fail2ban --no-pager
sudo fail2ban-client status
```

---

## 9) Типовые ошибки

1. **`Run as root`**
   - Запускать installer через `sudo`.

2. **`Ubuntu only` / `Ubuntu required`**
   - Скрипты рассчитаны на Ubuntu.

3. **`WireGuard config ... not found` в panel installer**
   - Для panel-only сценария сначала проверьте, что есть `/etc/wireguard/<iface>.conf`.

4. **Nginx не стартует после certbot**
   - Проверить `nginx -t` и DNS домена.

5. **Нет handshake у клиента**
   - Проверить endpoint/порт, firewall на сервере и NAT на внешнем интерфейсе.

---

## 10) Почему panel-only не ломает существующий WG

`panel-install.sh` работает как web/state слой:
- читает существующий `/etc/wireguard/<iface>.conf`;
- извлекает ключи/адреса/интерфейс;
- формирует JSON state;
- поднимает nginx/php-fpm/fail2ban;
- не перезаписывает сетевые правила и не перезапускает WG автоматически.

---

## 11) Roadmap v2 (без усложнения)

- Страница смены пароля admin при первом входе.
- Блокировка/разблокировка клиентов как отдельные действия в UI.
- Ротация логов панели в JSONL.
- Небольшой audit trail для действий админа.
- Экспорт/импорт JSON state для бэкапа и миграции.

---

## 12) Лицензия и эксплуатация

Рекомендуется использовать проект внутри приватной инфраструктуры, регулярно обновлять Ubuntu пакеты и иметь внешний мониторинг доступности (`wg`, nginx, certbot renew).
