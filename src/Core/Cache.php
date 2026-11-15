<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Cache
{
    public static function clear(): void
    {
        $dir = dirname(__DIR__, 2) . '/data/cache';

        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
