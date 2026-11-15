<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Throwable;
use Yawasla\Core\ModuleException;
use Yawasla\Core\ModuleManager;
use Yawasla\Core\Modules;

final class ModuleCommand implements Command
{
    private const USAGE = "Usage:\n  php bin/console module list\n  php bin/console module enable|disable <Module>\n";

    public function description(): string
    {
        return 'List the modules, or enable / disable one';
    }

    public function run(array $args): int
    {
        try {
            return match (true) {
                $args === ['list'] => $this->list(),
                count($args) === 2 && $args[0] === 'enable' => $this->change($args[1], true),
                count($args) === 2 && $args[0] === 'disable' => $this->change($args[1], false),
                default => $this->usage(),
            };
        } catch (ModuleException $e) {
            fwrite(STDERR, $e->text() . "\n");

            return 1;
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . ($e->getPrevious() ? ': ' . $e->getPrevious()->getMessage() : '') . "\n");

            return 1;
        }
    }

    private function list(): int
    {
        $modules = Modules::getInstance();
        $modules->discover();

        foreach ($modules->all() as $module) {
            [$reason, $params] = Modules::reason($module);
            $version = $module['version'] . ($module['installed'] !== null && $module['installed'] !== $module['version'] ? ' (installed ' . $module['installed'] . ')' : '');
            printf("  %-14s %-10s %-22s %-9s %s\n", $module['name'], $module['core'] ? 'core' : 'extension', $version, $module['active'] ? 'enabled' : 'disabled', strtr($reason, $params));
        }

        return 0;
    }

    private function change(string $name, bool $enable): int
    {
        $manager = new ModuleManager();
        $enable ? $manager->enable($name) : $manager->disable($name);
        echo $name . ($enable ? ' enabled.' : ' disabled.') . "\n";

        return 0;
    }

    private function usage(): int
    {
        fwrite(STDERR, self::USAGE);

        return 1;
    }
}
