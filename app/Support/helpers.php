<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function csrf_check(string $token): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', $token);
}

function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require base_path('templates/' . $template . '.php');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
