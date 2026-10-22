<?php

declare(strict_types=1);

namespace Yawasla\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CacheClearCommand implements Command
{
    public function __construct(private string $root)
    {
    }

    public function description(): string
    {
        return 'Clear data/cache (routes, Twig templates)';
    }

    public function run(array $args): int
    {
        $dir = $this->root . '/data/cache';

        if (is_dir($dir)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($dir);
        }

        echo "Cache cleared.\n";

        return 0;
    }
}
