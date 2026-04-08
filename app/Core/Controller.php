<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Auth;
use App\Support\Csrf;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        extract($data, EXTR_SKIP);
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        include dirname(__DIR__, 2) . '/templates/layout/base.php';
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!Csrf::validate((string)$token)) {
            http_response_code(419);
            echo 'Invalid CSRF token';
            exit;
        }
    }
}
