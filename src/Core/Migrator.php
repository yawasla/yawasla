<?php

declare(strict_types=1);

namespace Yawasla\Core;

use LogicException;
use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    private PDO $pdo;

    private string $driver;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->medoo()->pdo;
        $this->driver = Config::getInstance()->get('DB_DRIVER', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    }

    /** @return list<string> */
    public function run(): array
    {
        $done = $this->done();
        $migrated = [];

        foreach ($this->files() as [$module, $name, $file]) {
            if (isset($done[$module][$name])) {
                continue;
            }

            $this->execute($module, $name, (require $file)->up($this->driver), 'INSERT INTO migration (name, module, created_at) VALUES (?, ?, ?)', [$name, $module, date('Y-m-d H:i:s')]);
            $migrated[] = $module . '/' . $name;
        }

        return $migrated;
    }

    /** @return list<string> */
    public function uninstall(string $name): array
    {
        $modules = Modules::getInstance()->enabled();
        $module = $modules[strtolower($name)] ?? throw new LogicException('Unknown module: ' . $name);

        if ($module['core']) {
            throw new LogicException('Core modules cannot be uninstalled: ' . $module['name']);
        }

        foreach ($modules as $other) {
            if (in_array($module['name'], $other['requires'], true)) {
                throw new LogicException($other['name'] . ' requires ' . $module['name']);
            }
        }

        $done = $this->done()[$module['id']] ?? [];
        $files = array_reverse(array_filter($this->files(), fn (array $file): bool => $file[0] === $module['id'] && isset($done[$file[1]])));
        $reverted = [];

        foreach ($files as [$id, $migration, $file]) {
            $this->execute($id, $migration, (require $file)->down($this->driver), 'DELETE FROM migration WHERE module = ? AND name = ?', [$id, $migration]);
            $reverted[] = $id . '/' . $migration;
        }

        return $reverted;
    }

    /**
     * @param list<string> $queries
     * @param list<string> $params
     */
    private function execute(string $module, string $name, array $queries, string $record, array $params): void
    {
        try {
            $this->pdo->beginTransaction();

            foreach ($queries as $sql) {
                $this->pdo->exec($sql);
            }

            $this->pdo->prepare($record)->execute($params);

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Migration failed: ' . $module . '/' . $name, 0, $e);
        }
    }

    /** @return list<array{string, string, string}> */
    private function files(): array
    {
        $modules = Modules::getInstance()->enabled();
        $modules = ['main' => $modules['main']] + $modules;
        $files = [];

        foreach ($modules as $module) {
            $paths = glob($module['dir'] . '/migrations/*.php') ?: [];
            sort($paths);

            foreach ($paths as $path) {
                $name = basename($path, '.php');

                if (!preg_match('/^\d{8}-\d{2}$/', $name)) {
                    throw new LogicException('Invalid migration name: ' . $module['id'] . '/' . basename($path));
                }

                $files[] = [$module['id'], $name, $path];
            }
        }

        return $files;
    }

    /** @return array<string, array<string, true>> */
    private function done(): array
    {
        $exists = $this->driver === 'mysql'
            ? $this->pdo->query("SHOW TABLES LIKE 'migration'")->fetchColumn()
            : $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'migration'")->fetchColumn();

        if ($exists === false) {
            return [];
        }

        $done = [];

        foreach ($this->pdo->query('SELECT module, name FROM migration')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $done[$row['module']][$row['name']] = true;
        }

        return $done;
    }
}
