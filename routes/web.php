<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;

return static function (AuthController $authController, DashboardController $dashboardController, bool $authorized): void {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!$authorized && !in_array($uri, ['/login'], true)) {
        redirect('/login');
    }

    if ($method === 'GET' && $uri === '/login') {
        $authController->showLogin();
        return;
    }

    if ($method === 'POST' && $uri === '/login') {
        $authController->login();
        return;
    }

    if ($method === 'POST' && $uri === '/logout') {
        $authController->logout();
        return;
    }

    if ($method === 'GET' && $uri === '/') {
        $dashboardController->index();
        return;
    }

    if ($method === 'POST' && $uri === '/clients/create') {
        $dashboardController->createClient();
        return;
    }

    if ($method === 'POST' && preg_match('#^/clients/(delete|block|unblock)$#', $uri, $m) === 1) {
        $dashboardController->actionClient($m[1]);
        return;
    }

    if ($method === 'POST' && preg_match('#^/server/(restart|reboot)$#', $uri, $m) === 1) {
        $dashboardController->serverAction($m[1]);
        return;
    }

    if ($method === 'GET' && preg_match('#^/clients/([a-zA-Z0-9_-]+)/download$#', $uri, $m) === 1) {
        $dashboardController->downloadConfig($m[1]);
        return;
    }

    if ($method === 'GET' && preg_match('#^/clients/([a-zA-Z0-9_-]+)/qr$#', $uri, $m) === 1) {
        $dashboardController->showQr($m[1]);
        return;
    }

    http_response_code(404);
    echo 'Not found';
};
