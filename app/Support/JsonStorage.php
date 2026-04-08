<?php

declare(strict_types=1);

namespace App\Support;

final class JsonStorage
{
    public static function read(string $path, array $fallback = []): array
    {
        if (!is_file($path)) {
            return $fallback;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return $fallback;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function write(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($tmp, 0640);
        rename($tmp, $path);
    }
}
