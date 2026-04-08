<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\JsonStorage;
use App\Support\Shell;

final class WireGuardService
{
    public function state(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $server = JsonStorage::read($config['paths']['state'] . '/server.json', []);

        $iface = (string)($server['interface'] ?? 'wg0');
        $show = Shell::run('/usr/bin/wg', ['show', $iface, 'dump']);
        $service = Shell::run('/usr/bin/systemctl', ['is-active', '--quiet', 'wg-quick@' . $iface]);

        return [
            'interface' => $iface,
            'port' => $server['listen_port'] ?? '-',
            'endpoint' => $server['endpoint'] ?? '-',
            'service_active' => $service['code'] === 0,
            'dump' => $show['output'],
        ];
    }

    public function restart(): array
    {
        return Shell::run('/usr/bin/sudo', ['/usr/local/bin/wg-root-wrapper', 'restart-wg']);
    }

    public function reboot(): array
    {
        return Shell::run('/usr/bin/sudo', ['/usr/local/bin/wg-root-wrapper', 'reboot-server']);
    }
}
