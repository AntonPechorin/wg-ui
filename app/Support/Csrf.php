<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_ts'] = time();
        }

        return $_SESSION['_csrf'];
    }

    public static function validate(string $token): bool
    {
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            return false;
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $ttl = (int)$config['security']['csrf_ttl'];
        $created = (int)($_SESSION['_csrf_ts'] ?? 0);

        return (time() - $created) <= $ttl;
    }
}
