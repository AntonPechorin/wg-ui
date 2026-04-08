<?php

declare(strict_types=1);

namespace App\Core;

final class JsonStore
{
    public function read(string $file, array $default = []): array
    {
        if (!is_file($file)) {
            return $default;
        }

        $json = file_get_contents($file);
        if ($json === false || trim($json) === '') {
            return $default;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function write(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }
}
