<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class RootCommandService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function run(string $action, array $args = []): array
    {
        $wrapper = $this->config->get('root_wrapper');
        $parts = ['sudo', escapeshellarg($wrapper), escapeshellarg($action)];

        foreach ($args as $arg) {
            $parts[] = escapeshellarg((string)$arg);
        }

        $cmd = implode(' ', $parts) . ' 2>&1';
        $output = [];
        $code = 1;
        exec($cmd, $output, $code);

        return [
            'ok' => $code === 0,
            'code' => $code,
            'output' => trim(implode(PHP_EOL, $output)),
        ];
    }
}
