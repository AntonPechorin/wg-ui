<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

final class AuthController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function showLogin(?string $error = null): void
    {
        render('login', ['error' => $error]);
    }

    public function login(): void
    {
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->showLogin('Неверный CSRF токен.');
            return;
        }

        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($this->auth->login($login, $password)) {
            redirect('/');
        }

        $this->showLogin('Неверный логин или пароль.');
    }

    public function logout(): void
    {
        $this->auth->logout();
        redirect('/login');
    }
}
