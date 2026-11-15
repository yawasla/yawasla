<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Yawasla\Core\Cache;
use Yawasla\Core\Trash;

final class CacheClearCommand implements Command
{
    public function description(): string
    {
        return 'Clear data/cache (routes, Twig templates) and empty data/trash';
    }

    public function run(array $args): int
    {
        Cache::clear();
        Trash::clear();

        echo "Cache cleared.\n";

        return 0;
    }
}
