<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\DashboardController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [DashboardController::class, 'index']);
    $router->post('/actions', [DashboardController::class, 'action']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/clients', [ClientController::class, 'index']);
    $router->post('/clients/create', [ClientController::class, 'create']);
    $router->post('/clients/action', [ClientController::class, 'runAction']);
};
