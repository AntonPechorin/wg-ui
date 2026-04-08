<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private array $items;

    public function __construct()
    {
        $this->items = require __DIR__ . '/../../config/app.php';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
}
