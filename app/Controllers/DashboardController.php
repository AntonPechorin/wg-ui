<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\WireGuardService;

final class DashboardController
{
    public function __construct(private readonly WireGuardService $wg)
    {
    }

    public function index(?string $flash = null): void
    {
        render('dashboard', [
            'clients' => $this->wg->listClientsWithRuntime(),
            'wgStatus' => $this->wg->panelStatus(),
            'flash' => $flash,
        ]);
    }

    public function createClient(): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
        if (!csrf_check($_POST['_csrf'] ?? '') || $name === '') {
            $this->index('Ошибка создания клиента: проверьте имя/CSRF.');
            return;
        }

        $result = $this->wg->createClient($name);
        $this->index($result['ok'] ? "Клиент {$name} создан." : "Ошибка: {$result['output']}");
    }

    public function actionClient(string $action): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
        if (!csrf_check($_POST['_csrf'] ?? '') || $name === '') {
            $this->index('Ошибка операции: проверьте имя/CSRF.');
            return;
        }

        $result = match ($action) {
            'delete' => $this->wg->deleteClient($name),
            'block' => $this->wg->blockClient($name),
            'unblock' => $this->wg->unblockClient($name),
            default => ['ok' => false, 'output' => 'Неизвестное действие'],
        };

        $this->index($result['ok'] ? "Действие {$action} выполнено для {$name}." : "Ошибка: {$result['output']}");
    }

    public function serverAction(string $action): void
    {
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->index('Ошибка CSRF.');
            return;
        }

        $result = match ($action) {
            'restart' => $this->wg->restart(),
            'reboot' => $this->wg->rebootServer(),
            default => ['ok' => false, 'output' => 'Неизвестное действие'],
        };

        $this->index($result['ok'] ? "Выполнено: {$action}" : "Ошибка: {$result['output']}");
    }

    public function downloadConfig(string $name): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $path = '/etc/wireguard/clients/' . $safe . '.conf';

        if (!is_file($path)) {
            http_response_code(404);
            echo 'Config not found';
            return;
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $safe . '.conf"');
        readfile($path);
    }

    public function showQr(string $name): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $file = '/etc/wireguard/clients/' . $safe . '.conf';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Config not found';
            return;
        }

        $cmd = 'qrencode -t ANSIUTF8 < ' . escapeshellarg($file) . ' 2>/dev/null';
        $qr = shell_exec($cmd) ?: 'Не удалось сгенерировать QR.';
        render('qr', ['name' => $safe, 'qr' => $qr]);
    }
}
