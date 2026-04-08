<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public static function login(string $login, string $password): bool
    {
        $repo = new UserRepository();
        $user = $repo->admin();

        if (($user['login'] ?? '') !== $login) {
            return false;
        }

        if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
            return false;
        }

        $_SESSION['user'] = [
            'login' => $login,
            'ts' => time(),
        ];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
