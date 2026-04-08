<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\JsonStore;

final class UserRepository
{
    private string $file;

    public function __construct(
        private readonly JsonStore $store,
        Config $config,
    ) {
        $this->file = $config->get('storage_path') . '/users/admin.json';
    }

    public function findByLogin(string $login): ?array
    {
        $data = $this->store->read($this->file);
        if (($data['login'] ?? '') !== $login) {
            return null;
        }
        return $data;
    }

    public function createOrReplace(string $login, string $passwordHash): void
    {
        $this->store->write($this->file, [
            'login' => $login,
            'password_hash' => $passwordHash,
            'created_at' => date(DATE_ATOM),
        ]);
    }
}
