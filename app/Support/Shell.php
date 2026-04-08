<?php

declare(strict_types=1);

namespace App\Support;

final class Shell
{
    public static function run(string $command, array $args = []): array
    {
        $cmd = escapeshellcmd($command);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg((string)$arg);
        }

        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);

        return ['code' => $code, 'output' => implode("\n", $output)];
    }
}
