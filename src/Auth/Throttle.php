<?php

declare(strict_types=1);

namespace Yawasla\Auth;

final class Throttle
{
    private const MAX = 5;

    private const WINDOW = 3600;

    private string $file;

    public function __construct()
    {
        $this->file = dirname(__DIR__, 2) . '/data/cache/throttle/' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    public function blocked(): bool
    {
        return count($this->attempts()) >= self::MAX;
    }

    public function hit(): void
    {
        $dir = dirname($this->file);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->file, implode("\n", [...$this->attempts(), time()]), LOCK_EX);
    }

    /** @return list<int> */
    private function attempts(): array
    {
        $content = is_file($this->file) ? (string) file_get_contents($this->file) : '';
        $since = time() - self::WINDOW;

        return array_values(array_filter(array_map('intval', explode("\n", $content)), fn (int $time): bool => $time > $since));
    }
}
