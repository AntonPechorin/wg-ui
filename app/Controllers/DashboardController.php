<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ClientRepository;
use App\Services\WireGuardService;
use App\Support\Csrf;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $wg = (new WireGuardService())->state();
        $clients = (new ClientRepository())->all();

        $active = count(array_filter($clients, static fn(array $c): bool => ($c['status'] ?? 'active') === 'active'));
        $blocked = count($clients) - $active;

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'wg' => $wg,
            'client_count' => count($clients),
            'active_count' => $active,
            'blocked_count' => $blocked,
            'csrf' => Csrf::token(),
        ]);
    }

    public function action(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $action = (string)($_POST['action'] ?? '');
        $service = new WireGuardService();
        if ($action === 'restart-wg') {
            $service->restart();
        } elseif ($action === 'reboot-server') {
            $service->reboot();
        }

        $this->redirect('/');
    }
}
