<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts, int $window): bool
    {
        $items = $_SESSION['_rate_limit'][$key] ?? [];
        $now = time();
        $filtered = array_values(array_filter($items, static fn(int $ts): bool => ($now - $ts) <= $window));
        $_SESSION['_rate_limit'][$key] = $filtered;

        return count($filtered) >= $maxAttempts;
    }

    public static function hit(string $key): void
    {
        $_SESSION['_rate_limit'][$key] ??= [];
        $_SESSION['_rate_limit'][$key][] = time();
    }

    public static function clear(string $key): void
    {
        unset($_SESSION['_rate_limit'][$key]);
    }
}
