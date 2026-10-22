<?php

declare(strict_types=1);

namespace Yawasla\Command;

final class ServeCommand implements Command
{
    public function __construct(private string $root)
    {
    }

    public function description(): string
    {
        return 'Start the dev server (default port 8000: serve [port])';
    }

    public function run(array $args): int
    {
        $port = $args[0] ?? '8000';

        if (!ctype_digit($port)) {
            fwrite(STDERR, "Invalid port: {$port}\n");
            return 1;
        }

        echo "http://localhost:{$port} (Ctrl+C to stop)\n";
        passthru(sprintf(
            '%s -S localhost:%s -t %s %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($this->root . '/public'),
            escapeshellarg($this->root . '/public/index.php'),
        ), $code);

        return $code;
    }
}
