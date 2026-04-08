<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\RateLimiter;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', ['title' => 'Login', 'csrf' => Csrf::token(), 'error' => null]);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $max = (int)$config['security']['login_rate_limit_attempts'];
        $window = (int)$config['security']['login_rate_limit_window'];

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login:' . $ip;

        if (RateLimiter::tooManyAttempts($key, $max, $window)) {
            $this->view('auth/login', ['title' => 'Login', 'csrf' => Csrf::token(), 'error' => 'Too many attempts']);
            return;
        }

        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (!Auth::login($login, $password)) {
            RateLimiter::hit($key);
            $this->view('auth/login', ['title' => 'Login', 'csrf' => Csrf::token(), 'error' => 'Invalid credentials']);
            return;
        }

        RateLimiter::clear($key);
        $this->redirect('/');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        $this->redirect('/login');
    }
}
