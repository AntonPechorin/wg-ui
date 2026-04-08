<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonStorage;

final class UserRepository
{
    public function admin(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return JsonStorage::read($config['paths']['users'] . '/admin.json', []);
    }
}
