<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\DashboardController;

final class App
{
    public function run(): void
    {
        $router = new Router();
        (require dirname(__DIR__, 2) . '/routes/web.php')($router);

        $route = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
        if ($route === null) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        [$controller, $method] = $route;
        $instance = match ($controller) {
            AuthController::class => new AuthController(),
            DashboardController::class => new DashboardController(),
            ClientController::class => new ClientController(),
            default => null,
        };

        if ($instance === null || !method_exists($instance, $method)) {
            http_response_code(500);
            echo 'Handler error';
            return;
        }

        $instance->$method();
    }
}
