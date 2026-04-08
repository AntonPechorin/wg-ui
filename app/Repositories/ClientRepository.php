<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\JsonStore;

final class ClientRepository
{
    private string $file;

    public function __construct(
        private readonly JsonStore $store,
        Config $config,
    ) {
        $this->file = $config->get('storage_path') . '/clients/clients.json';
    }

    public function all(): array
    {
        $data = $this->store->read($this->file, ['clients' => []]);
        return $data['clients'] ?? [];
    }

    public function saveAll(array $clients): void
    {
        $this->store->write($this->file, ['clients' => array_values($clients)]);
    }

    public function find(string $name): ?array
    {
        foreach ($this->all() as $client) {
            if (($client['name'] ?? '') === $name) {
                return $client;
            }
        }

        return null;
    }
}
