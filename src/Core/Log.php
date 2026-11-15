<?php

declare(strict_types=1);

namespace Yawasla\Core;

final class Log
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const FILES = 10;

    public static function rotate(string $file): void
    {
        clearstatcache(true, $file);

        if (!is_file($file) || filesize($file) < self::MAX_BYTES) {
            return;
        }

        $lock = fopen($file . '.lock', 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }

        clearstatcache(true, $file);

        if (is_file($file) && filesize($file) >= self::MAX_BYTES) {
            for ($i = self::FILES - 1; $i >= 1; $i--) {
                $from = $i === 1 ? $file : $file . '.' . ($i - 1);

                if (is_file($from)) {
                    rename($from, $file . '.' . $i);
                }
            }
        }

        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
