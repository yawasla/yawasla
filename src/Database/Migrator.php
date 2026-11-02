<?php

declare(strict_types=1);

namespace Yawasla\Database;

use LogicException;
use PDO;
use RuntimeException;
use Throwable;
use Yawasla\Core\Modules;

final class Migrator
{
    private PDO $pdo;

    private string $driver;

    private string $root;

    public function __construct(?PDO $pdo = null, ?string $driver = null)
    {
        $this->pdo = $pdo ?? Connection::getInstance()->medoo()->pdo;
        $this->driver = $driver ?? Connection::driver();
        $this->root = dirname(__DIR__, 2);
    }

    /** @return list<string> */
    public function pending(): array
    {
        $done = $this->done();

        return array_values(array_map(
            fn (array $file): string => $file[0] . '/' . $file[1],
            array_filter($this->files(), fn (array $file): bool => !isset($done[$file[0]][$file[1]])),
        ));
    }

    /** @return list<string> */
    public function run(): array
    {
        $lock = $this->lock();

        try {
            $done = $this->done();
            $migrated = [];

            foreach ($this->files() as [$module, $name, $file]) {
                if (isset($done[$module][$name])) {
                    continue;
                }

                $migration = $this->load($module, $name, $file);
                $migration->up();
                $this->execute($module, $name, $migration->queries(), 'INSERT INTO migration (name, module, created_at) VALUES (?, ?, ?)', [$name, $module, date('Y-m-d H:i:s')]);
                $migrated[] = $module . '/' . $name;
            }

            return $migrated;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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

        $lock = $this->lock();

        try {
            $done = $this->done()[$module['id']] ?? [];
            $files = array_reverse(array_filter($this->files(), fn (array $file): bool => $file[0] === $module['id'] && isset($done[$file[1]])));
            $reverted = [];

            foreach ($files as [$id, $migration, $file]) {
                $instance = $this->load($id, $migration, $file);
                $instance->down();
                $this->execute($id, $migration, $instance->queries(), 'DELETE FROM migration WHERE module = ? AND name = ?', [$id, $migration]);
                $reverted[] = $id . '/' . $migration;
            }

            return $reverted;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param list<string> $queries
     * @param list<string> $params
     */
    private function execute(string $module, string $name, array $queries, string $record, array $params): void
    {
        $adapter = $this->driver === 'mysql' ? new MySQLAdapter($this->pdo) : new SQLiteAdapter($this->pdo);
        $adapter->begin();

        try {
            $this->pdo->beginTransaction();

            foreach ($queries as $sql) {
                $adapter->execute($sql);
            }

            $this->pdo->prepare($record)->execute($params);
            $adapter->finish();

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Migration failed: ' . $module . '/' . $name, 0, $e);
        } finally {
            $adapter->end();
        }
    }

    private function load(string $module, string $name, string $file): AbstractMigration
    {
        $migration = require $file;

        if (!$migration instanceof AbstractMigration) {
            throw new LogicException('A migration must return an instance of ' . AbstractMigration::class . ': ' . $module . '/' . $name);
        }

        return $migration;
    }

    /** @return resource */
    private function lock()
    {
        $lock = fopen($this->root . '/data/migration.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Cannot open data/migration.lock');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            throw new RuntimeException('Another migration is running');
        }

        return $lock;
    }

    /** @return list<array{string, string, string}> */
    private function files(): array
    {
        $modules = Modules::getInstance()->enabled();
        $sorted = [];
        $seen = [];
        $visit = function (array $module) use (&$visit, &$sorted, &$seen, $modules): void {
            if (isset($seen[$module['id']])) {
                return;
            }

            $seen[$module['id']] = true;

            foreach ($module['requires'] as $required) {
                $visit($modules[strtolower($required)]);
            }

            $sorted[$module['id']] = $module;
        };

        $visit($modules['main']);
        array_map($visit, array_filter($modules, fn (array $module): bool => $module['core']));
        array_map($visit, array_filter($modules, fn (array $module): bool => !$module['core']));
        $files = [];

        foreach ($sorted as $module) {
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
