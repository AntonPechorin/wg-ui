<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\ClientRepository;

final class WireGuardService
{
    public function __construct(
        private readonly Config $config,
        private readonly ClientRepository $clients,
        private readonly RootCommandService $root,
    ) {
    }

    public function listClientsWithRuntime(): array
    {
        $clients = $this->clients->all();
        $dump = $this->statusDump();

        foreach ($clients as &$client) {
            $pub = $client['public_key'] ?? '';
            $runtime = $dump[$pub] ?? null;
            $client['runtime_status'] = $runtime['status'] ?? ($client['blocked'] ? 'blocked' : 'offline');
            $client['latest_handshake'] = $runtime['latest_handshake'] ?? '-';
            $client['rx'] = $runtime['rx'] ?? '0 B';
            $client['tx'] = $runtime['tx'] ?? '0 B';
            $client['endpoint'] = $runtime['endpoint'] ?? '-';
        }

        return $clients;
    }

    public function statusDump(): array
    {
        $result = $this->root->run('status');
        if (!$result['ok']) {
            return [];
        }

        $lines = array_filter(explode("\n", $result['output']));
        $map = [];
        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) < 8) {
                continue;
            }
            [$iface, $pub, $psk, $endpoint, $allowedIps, $handshakeTs, $rx, $tx] = $parts;
            $map[$pub] = [
                'interface' => $iface,
                'endpoint' => $endpoint !== '(none)' ? $endpoint : '-',
                'allowed_ips' => $allowedIps,
                'latest_handshake' => ((int)$handshakeTs > 0) ? date('Y-m-d H:i:s', (int)$handshakeTs) : '-',
                'rx' => $this->humanBytes((int)$rx),
                'tx' => $this->humanBytes((int)$tx),
                'status' => ((int)$handshakeTs > (time() - 180)) ? 'online' : 'offline',
            ];
        }

        return $map;
    }

    public function createClient(string $name): array
    {
        return $this->root->run('add-client', [$name]);
    }

    public function deleteClient(string $name): array
    {
        return $this->root->run('delete-client', [$name]);
    }

    public function blockClient(string $name): array
    {
        return $this->root->run('block-client', [$name]);
    }

    public function unblockClient(string $name): array
    {
        return $this->root->run('unblock-client', [$name]);
    }

    public function restart(): array
    {
        return $this->root->run('restart');
    }

    public function rebootServer(): array
    {
        return $this->root->run('reboot');
    }

    public function panelStatus(): string
    {
        $result = $this->root->run('service-status');
        return $result['ok'] ? trim($result['output']) : 'unknown';
    }

    public function getClientConfig(string $name): array
    {
        return $this->root->run('show-config', [$name]);
    }

    public function getClientQr(string $name): array
    {
        return $this->root->run('show-qr', [$name]);
    }


    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float)$bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return sprintf($i === 0 ? '%.0f %s' : '%.2f %s', $value, $units[$i]);
    }
}
