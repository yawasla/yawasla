<?php

declare(strict_types=1);

namespace Yawasla\Core;

final class Config
{
    /** @param array<string, string> $values */
    private function __construct(private array $values)
    {
    }

    /**
     * Lit le .env (format INI, valeurs brutes : rien n'est converti). Absent → prod.
     */
    public static function load(string $file): self
    {
        $values = is_file($file) ? parse_ini_file($file, false, INI_SCANNER_RAW) : [];

        if ($values === false) {
            throw new \RuntimeException('Fichier .env illisible : ' . $file);
        }

        return new self($values);
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function isDev(): bool
    {
        return $this->get('APP_ENV', 'prod') === 'dev';
    }
}
