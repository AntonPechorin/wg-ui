<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/../app/Support/helpers.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Services\RootCommandService;
use App\Services\WireGuardService;

$config = new Config();
$store = new JsonStore();
$userRepo = new UserRepository($store, $config);
$clientRepo = new ClientRepository($store, $config);
$auth = new Auth($userRepo, $config);
$auth->initSession();
$root = new RootCommandService($config);
$wg = new WireGuardService($config, $clientRepo, $root);

$authController = new AuthController($auth);
$dashboardController = new DashboardController($wg);

$router = require __DIR__ . '/../routes/web.php';
$router($authController, $dashboardController, $auth->check());
