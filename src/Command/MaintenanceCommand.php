<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Yawasla\Core\Maintenance;

final class MaintenanceCommand implements Command
{
    public function description(): string
    {
        return 'Put the site in maintenance: on, off, or no argument for the status';
    }

    public function run(array $args): int
    {
        $action = $args[0] ?? null;

        if ($action === 'on') {
            Maintenance::enable();
        } elseif ($action === 'off') {
            Maintenance::disable();
        } elseif ($action !== null) {
            fwrite(STDERR, "Usage: php bin/console maintenance [on|off]\n");

            return 1;
        }

        echo 'Maintenance: ' . (Maintenance::enabled() ? 'on' : 'off') . "\n";

        return 0;
    }
}
