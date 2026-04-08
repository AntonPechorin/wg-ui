<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Support\Shell;

final class ClientService
{
    public function listWithRuntime(): array
    {
        $repo = new ClientRepository();
        $clients = $repo->all();

        $runtime = $this->runtimeMap();
        foreach ($clients as &$client) {
            $key = (string)($client['public_key'] ?? '');
            $client['runtime'] = $runtime[$key] ?? [
                'latest_handshake' => '-',
                'rx' => 0,
                'tx' => 0,
                'endpoint' => '-',
            ];
        }

        return $clients;
    }

    public function runtimeMap(): array
    {
        $server = (require dirname(__DIR__, 2) . '/config/app.php')['paths']['state'] . '/server.json';
        $iface = (string)((json_decode((string)file_get_contents($server), true)['interface'] ?? 'wg0'));
        $res = Shell::run('/usr/bin/wg', ['show', $iface, 'dump']);
        if ($res['code'] !== 0 || trim($res['output']) === '') {
            return [];
        }

        $map = [];
        $lines = explode("\n", trim($res['output']));
        foreach (array_slice($lines, 1) as $line) {
            if ($line === '') {
                continue;
            }
            $p = explode("\t", $line);
            $map[$p[0]] = [
                'endpoint' => $p[2] !== '' ? $p[2] : '-',
                'latest_handshake' => $p[4] !== '0' ? gmdate('Y-m-d H:i:s', (int)$p[4]) . ' UTC' : '-',
                'rx' => (int)$p[5],
                'tx' => (int)$p[6],
            ];
        }

        return $map;
    }
}
