<?php

declare(strict_types=1);

$base = dirname(__DIR__);

return [
    'app_name' => 'WG Panel',
    'base_path' => $base,
    'paths' => [
        'storage' => $base . '/storage',
        'clients' => $base . '/storage/clients',
        'users' => $base . '/storage/users',
        'state' => $base . '/storage/state',
        'logs' => $base . '/storage/logs',
        'cache' => $base . '/storage/cache',
        'sessions' => $base . '/storage/sessions',
        'backups' => $base . '/storage/backups',
        'tmp' => $base . '/storage/tmp',
        'locks' => $base . '/storage/locks',
    ],
    'security' => [
        'login_rate_limit_attempts' => 5,
        'login_rate_limit_window' => 300,
        'csrf_ttl' => 3600,
    ],
];
