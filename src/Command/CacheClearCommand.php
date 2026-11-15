<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Yawasla\Core\Cache;

final class CacheClearCommand implements Command
{
    public function description(): string
    {
        return 'Clear data/cache (routes, Twig templates)';
    }

    public function run(array $args): int
    {
        Cache::clear();

        echo "Cache cleared.\n";

        return 0;
    }
}
