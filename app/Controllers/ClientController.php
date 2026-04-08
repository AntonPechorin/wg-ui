<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ClientService;
use App\Support\Csrf;
use App\Support\Shell;

final class ClientController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $clients = (new ClientService())->listWithRuntime();
        $this->view('clients/index', [
            'title' => 'Clients',
            'clients' => $clients,
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
        if ($name !== '') {
            Shell::run('/usr/local/bin/wireguard-install.sh', ['add-client', $name]);
        }
        $this->redirect('/clients');
    }

    public function runAction(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $client = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['client'] ?? ''));
        $action = (string)($_POST['action'] ?? '');

        if ($client === '') {
            $this->redirect('/clients');
        }

        $map = [
            'show' => ['show-client', $client],
            'regen' => ['add-client', $client],
        ];

        if (isset($map[$action])) {
            Shell::run('/usr/local/bin/wireguard-install.sh', $map[$action]);
        }

        $this->redirect('/clients');
    }
}
