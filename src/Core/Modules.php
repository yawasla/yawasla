<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Composer\Autoload\ClassLoader;
use LogicException;
use Yawasla\Trait\Singleton;

final class Modules
{
    use Singleton;

    private const GROUPS = ['Core', 'Extension'];

    /** @var array<string, array{name: string, id: string, dir: string, namespace: string, core: bool, version: string, locked: bool, requires: list<string>}> */
    private array $modules = [];

    private function __construct()
    {
        foreach (self::GROUPS as $group) {
            foreach (glob(dirname(__DIR__, 2) . '/modules/' . $group . '/*/Module.php') ?: [] as $file) {
                $dir = dirname($file);
                $name = basename($dir);
                $id = strtolower($name);

                if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                    throw new LogicException('Invalid module name: ' . $name);
                }

                if (isset($this->modules[$id])) {
                    throw new LogicException('Duplicate module: ' . $name);
                }

                $config = require $file;

                $this->modules[$id] = [
                    'name' => $name,
                    'id' => $id,
                    'dir' => $dir,
                    'namespace' => 'Yawasla\\Module\\' . $group . '\\' . $name . '\\',
                    'core' => $group === 'Core',
                    'version' => $config['version'] ?? '',
                    'locked' => $group === 'Core' && ($config['locked'] ?? false),
                    'requires' => $config['requires'] ?? [],
                ];
            }
        }

        foreach ($this->modules as $module) {
            foreach ($module['requires'] as $required) {
                if (!isset($this->modules[strtolower($required)])) {
                    throw new LogicException($module['name'] . ' requires the missing module ' . $required);
                }
            }
        }
    }

    public function autoload(ClassLoader $loader): void
    {
        foreach ($this->modules as $module) {
            $loader->addPsr4($module['namespace'], $module['dir'] . '/src');
        }
    }

    /** @return array<string, array{name: string, id: string, dir: string, namespace: string, core: bool, version: string, locked: bool, requires: list<string>}> */
    public function enabled(): array
    {
        return $this->modules;
    }

    /** @return array{name: string, id: string, dir: string, namespace: string, core: bool, version: string, locked: bool, requires: list<string>}|null */
    public function get(string $name): ?array
    {
        return $this->enabled()[strtolower($name)] ?? null;
    }
}
