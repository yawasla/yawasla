<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Throwable;
use Yawasla\Core\Migrator;

final class MigrateCommand implements Command
{
    public function description(): string
    {
        return 'Run the pending migrations of every enabled module';
    }

    public function run(array $args): int
    {
        try {
            $migrated = (new Migrator())->run();
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . ($e->getPrevious() ? ': ' . $e->getPrevious()->getMessage() : '') . "\n");

            return 1;
        }

        echo $migrated === [] ? "Nothing to migrate.\n" : 'Migrated: ' . implode(', ', $migrated) . "\n";

        return 0;
    }
}
