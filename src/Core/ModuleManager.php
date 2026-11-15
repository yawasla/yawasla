<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Yawasla\Database\Migrator;

final class ModuleManager
{
    public function enable(string $name): void
    {
        $modules = Modules::getInstance();
        $module = $this->find($name);

        if ($module['locked']) {
            throw new ModuleException('%module% is locked.', ['%module%' => $module['name']]);
        }

        if (in_array($module['reason'], ['invalid', 'cycle'], true)) {
            [$reason, $params] = Modules::reason($module);

            throw new ModuleException('%module% cannot be enabled: %reason%.', ['%module%' => $module['name'], '%reason%' => strtr($reason, $params)], [$reason, $params]);
        }

        $missing = array_values(array_filter($module['requires'], fn (string $required): bool => $modules->get($required) === null));

        if ($missing !== []) {
            throw new ModuleException('Enable first: %modules%.', ['%modules%' => implode(', ', $missing)]);
        }

        (new Migrator())->run($modules->enabled() + [$module['id'] => $module]);

        $state = Modules::state();
        $state[$module['name']] = ['version' => $module['version'], 'active' => true];
        $this->save($state);
    }

    public function disable(string $name): void
    {
        $module = $this->find($name);

        if ($module['locked']) {
            throw new ModuleException('%module% is locked.', ['%module%' => $module['name']]);
        }

        $dependents = $this->dependents($module, true);

        if ($dependents !== []) {
            throw new ModuleException('Disable first: %modules%.', ['%modules%' => implode(', ', $dependents)]);
        }

        $state = Modules::state();
        $state[$module['name']] = ['active' => false] + ($state[$module['name']] ?? []);
        $this->save($state);
    }

    public function uninstall(string $name): void
    {
        $module = $this->find($name);

        if ($module['core']) {
            throw new ModuleException('Yawasla modules cannot be uninstalled.');
        }

        if ($module['wanted']) {
            throw new ModuleException('Disable %module% first.', ['%module%' => $module['name']]);
        }

        $dependents = $this->dependents($module, false);

        if ($dependents !== []) {
            throw new ModuleException('Required by %modules%.', ['%modules%' => implode(', ', $dependents)]);
        }

        $root = dirname(__DIR__, 2);
        $parent = realpath($root . '/modules/Extension');

        if ($parent === false || realpath(dirname($module['dir'])) !== $parent || !is_writable($module['dir']) || !is_writable($parent) || !is_writable($root . '/data')) {
            throw new ModuleException('The folder of %module% cannot be moved: check the permissions.', ['%module%' => $module['name']]);
        }

        $this->sameDisk($module['name']);

        (new Migrator())->uninstall($module);

        $state = Modules::state();
        unset($state[$module['name']]);
        Modules::saveState($state);
        Trash::put($module['dir'], $module['name']);
        Cache::clear();
        Modules::getInstance()->refresh();
    }

    public function restore(string $entry): string
    {
        $found = null;

        foreach (Trash::list() as $item) {
            if ($item['entry'] === $entry) {
                $found = $item;
            }
        }

        if ($found === null) {
            throw new ModuleException('Unknown trash entry.');
        }

        $name = $found['name'];
        $existing = Modules::getInstance()->find($name);

        if ($existing !== null && $existing['core']) {
            throw new ModuleException('%module% is the name of a Yawasla module.', ['%module%' => $name]);
        }

        $root = dirname(__DIR__, 2);
        $target = $root . '/modules/Extension/' . $name;

        if ($existing !== null || file_exists($target) || is_link($target)) {
            throw new ModuleException('A folder named %module% already exists in modules/Extension.', ['%module%' => $name]);
        }

        $this->sameDisk($name);

        if (!is_writable($root . '/modules/Extension') || !is_writable($found['path']) || !rename($found['path'], $target)) {
            throw new ModuleException('The folder of %module% cannot be moved: check the permissions.', ['%module%' => $name]);
        }

        Modules::getInstance()->refresh();

        return $name;
    }

    /** @return list<string> */
    public function finishUpdate(): array
    {
        $modules = Modules::getInstance();
        $targets = $modules->updateTargets();

        $migrated = (new Migrator())->run($targets);

        $state = Modules::state();

        foreach ($modules->all() as $module) {
            if ($module['pending'] === 'update' && ($module['core'] || isset($targets[$module['id']]))) {
                $state[$module['name']] = ['version' => $module['version']] + ($state[$module['name']] ?? []);
            }
        }

        $this->save($state);

        return $migrated;
    }

    private function sameDisk(string $name): void
    {
        $root = dirname(__DIR__, 2);
        $modules = stat($root . '/modules/Extension');
        $data = stat($root . '/data');

        if ($modules === false || $data === false || $modules['dev'] !== $data['dev']) {
            throw new ModuleException('The folder of %module% cannot be moved: modules/Extension and data are not on the same disk.', ['%module%' => $name]);
        }
    }

    /** @return array<string, mixed> */
    private function find(string $name): array
    {
        $modules = Modules::getInstance();
        $modules->discover();
        $module = preg_match(Modules::NAME, $name) ? $modules->find($name) : null;

        return $module ?? throw new ModuleException('Unknown module: %module%.', ['%module%' => $name]);
    }

    /**
     * @param array<string, mixed> $module
     * @return list<string>
     */
    private function dependents(array $module, bool $activeOnly): array
    {
        $dependents = [];

        foreach (Modules::getInstance()->all() as $other) {
            if ((!$activeOnly || $other['active']) && in_array($module['name'], $other['requires'], true)) {
                $dependents[] = $other['name'];
            }
        }

        return $dependents;
    }

    /** @param array<string, array{version?: string, active?: bool}> $state */
    private function save(array $state): void
    {
        Modules::saveState($state);
        Cache::clear();
        Modules::getInstance()->refresh();
    }
}
