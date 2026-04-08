<?php

declare(strict_types=1);

return [
    'panel_title' => 'WireGuard Simple Panel',
    'session_name' => 'WG_PANEL_SESSION',
    'storage_path' => __DIR__ . '/../storage',
    'wg_interface' => 'wg0',
    'wg_network' => '10.66.66.0/24',
    'wg_server_ip' => '10.66.66.1/24',
    'wg_port' => 51820,
    'wg_dns' => ['1.1.1.1', '1.0.0.1'],
    'wg_allowed_ips' => '0.0.0.0/0',
    'wg_keepalive' => 25,
    'root_wrapper' => '/usr/local/bin/wg-panel-root-wrapper.sh',
    'client_config_dir' => '/etc/wireguard/clients',
];
