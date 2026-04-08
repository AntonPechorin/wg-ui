<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonStorage;

final class ClientRepository
{
    public function all(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $dir = $config['paths']['clients'];
        $list = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $item = JsonStorage::read($file, []);
            if ($item !== []) {
                $list[] = $item;
            }
        }

        usort($list, static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));
        return $list;
    }

    public function find(string $id): ?array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        $path = $config['paths']['clients'] . '/' . $safe . '.json';
        if (!is_file($path)) {
            return null;
        }
        return JsonStorage::read($path, []);
    }
}
