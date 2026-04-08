<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Config $config,
    ) {
    }

    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->config->get('session_name', 'WG_PANEL_SESSION'));
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }
    }

    public function login(string $login, string $password): bool
    {
        $user = $this->users->findByLogin($login);
        if ($user === null) {
            return false;
        }

        if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
            return false;
        }

        $_SESSION['user'] = [
            'login' => $user['login'],
            'created_at' => $user['created_at'] ?? null,
        ];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function check(): bool
    {
        return isset($_SESSION['user']['login']);
    }

    public function user(): ?array
    {
        return $this->check() ? $_SESSION['user'] : null;
    }
}
