# Simple WireGuard JSON Panel (Ubuntu, one-command install)

Очень простой self-hosted проект для Ubuntu Server:
- автоматически ставит и настраивает WireGuard (`wg`, `wg-quick`, `systemd`);
- автоматически поднимает nginx + PHP-FPM;
- устанавливает лёгкую панель по IP сервера (без домена, без HTTPS, без Docker, без БД);
- хранит данные только в JSON;
- разворачивается одной командой.

---

## 1) Что делает проект

После установки вы получаете:
1. Рабочий WireGuard сервер `wg0`.
2. Первый тестовый клиент `test-client` + его `.conf`.
3. Веб-панель на чистом PHP (авторизация + управление клиентами).
4. Root-wrapper для безопасных root-операций через узкий `sudoers`.

---

## 2) Что нужно для запуска

Минимум:
- Ubuntu Server 22.04/24.04 (рекомендуется чистый VPS);
- root/sudo доступ;
- белый IP сервера.

---

## 3) Одна команда установки

```bash
wget <RAW_URL_INSTALL_SH> -O install.sh && sudo bash install.sh
```

Пример (после загрузки в ваш GitHub репозиторий):
```bash
wget https://raw.githubusercontent.com/<you>/<repo>/main/install.sh -O install.sh && sudo bash install.sh
```

> Установка **полностью автоматическая**: без вопросов, без мастера, без `export` переменных.

---

## 4) Что будет установлено

Скрипт устанавливает пакеты:
- `wireguard`
- `wireguard-tools`
- `nginx`
- `php-fpm`
- `php-cli`
- `php-mbstring`
- `php-xml`
- `php-curl`
- `php-zip`
- `php-opcache`
- `qrencode`
- `unzip`
- `curl`
- `jq`
- `rsync`
- `openssl`
- `iptables`
- `ca-certificates`

---

## 5) Где открыть панель

После установки:
- `http://<IP_СЕРВЕРА>/`

Никакого домена и HTTPS не требуется.

---

## 6) Где взять логин и пароль

Скрипт выводит данные в конце установки и пишет их в:
- `/root/wg-ui-credentials.txt`

По умолчанию логин:
- `admin`

---

## 7) Где лежит клиентский конфиг

Первый тестовый клиент:
- `/etc/wireguard/clients/test-client.conf`

---

## 8) Как добавить клиента

### Через панель
1. Откройте `http://<IP_СЕРВЕРА>/`
2. Войдите как `admin`.
3. В поле имени укажите, например, `user1`.
4. Нажмите `Создать клиента`.

### Через shell
```bash
sudo /usr/local/bin/wg-panel-root-wrapper.sh add-client user1
```

---

## 9) Как удалить клиента

### Через панель
Кнопка `Удалить` в строке клиента.

### Через shell
```bash
sudo /usr/local/bin/wg-panel-root-wrapper.sh delete-client user1
```

---

## 10) Как перезапустить WireGuard

### Через панель
Кнопка `Restart WireGuard`.

### Через shell
```bash
sudo systemctl restart wg-quick@wg0
```

или

```bash
sudo /usr/local/bin/wg-panel-root-wrapper.sh restart
```

---

## 11) Как удалить проект

```bash
sudo systemctl disable --now wg-quick@wg0 nginx
sudo rm -f /etc/nginx/sites-enabled/wg-ui.conf /etc/nginx/sites-available/wg-ui.conf
sudo rm -f /etc/sudoers.d/wg-ui /usr/local/bin/wg-panel-root-wrapper.sh
sudo rm -rf /opt/wg-ui /etc/wireguard/clients
sudo apt-get remove -y wireguard wireguard-tools nginx php-fpm php-cli php-mbstring php-xml php-curl php-zip php-opcache qrencode jq
sudo apt-get autoremove -y
```

---

## 12) Диагностика типовых проблем

### Панель не открывается
```bash
sudo systemctl status nginx --no-pager
sudo nginx -t
sudo ss -tulpn | grep ':80'
```

### Ошибка PHP
```bash
sudo systemctl status 'php*-fpm' --no-pager
ls -lah /run/php
```

### WireGuard не поднимается
```bash
sudo systemctl status wg-quick@wg0 --no-pager
sudo journalctl -u wg-quick@wg0 -n 100 --no-pager
sudo wg show
```

### Нет интернета у клиентов
```bash
sudo sysctl net.ipv4.ip_forward
sudo iptables -t nat -S
sudo ip route
```

---

## 13) Как проверить, что WireGuard работает

```bash
sudo systemctl is-active wg-quick@wg0
sudo wg show
```

Ожидается:
- `active` для сервиса;
- интерфейс `wg0` и peers в `wg show`.

---

## 14) Как проверить, что панель работает

```bash
curl -I http://127.0.0.1/
sudo systemctl is-active nginx
sudo systemctl is-active 'php*-fpm'
```

---

## Структура хранения JSON

- `storage/clients/clients.json` — список VPN клиентов;
- `storage/users/admin.json` — админ панели;
- `storage/state/system.json` — состояние инсталляции (IP, интерфейс и т.д.);
- `storage/logs/` — технические логи.

---

## Важно

- Проект intentionally простой.
- Без БД, без Docker, без сертификатов.
- Управление только по IP.
- Все root-действия панели ограничены root-wrapper и узким sudoers.
