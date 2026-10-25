<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Yawasla\Trait\Singleton;

final class Config
{
    use Singleton;

    /** @var array<string, string> */
    private array $values;

    private function __construct()
    {
        $file = dirname(__DIR__, 2) . '/.env';
        $values = is_file($file) ? parse_ini_file($file, false, INI_SCANNER_RAW) : [];

        if ($values === false) {
            throw new \RuntimeException('Fichier .env illisible : ' . $file);
        }

        $this->values = $values;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }
}
