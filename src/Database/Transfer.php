<?php

declare(strict_types=1);

namespace Yawasla\Database;

use LogicException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Transfer
{
    public function __construct(
        private PDO $source,
        private string $sourceDriver,
        private PDO $target,
        private string $targetDriver,
    ) {
    }

    /** @return array<string, int> */
    public function run(): array
    {
        if ($this->sourceDriver === $this->targetDriver) {
            throw new LogicException('The database is already on ' . $this->targetDriver);
        }

        if ((new Migrator($this->source, $this->sourceDriver))->pending() !== []) {
            throw new LogicException('The current database has pending migrations: run php bin/console db:migrate first');
        }

        if ($this->tables($this->target, $this->targetDriver) !== []) {
            throw new LogicException('The target database is not empty');
        }

        try {
            (new Migrator($this->target, $this->targetDriver))->run();

            return $this->data();
        } catch (Throwable $e) {
            $this->clear();

            throw $e;
        }
    }

    /** @return array<string, int> */
    private function data(): array
    {
        $tables = array_values(array_diff($this->tables($this->target, $this->targetDriver), ['migration']));
        $missing = array_diff($tables, $this->tables($this->source, $this->sourceDriver));

        if ($missing !== []) {
            throw new LogicException('Missing tables in the current database: ' . implode(', ', $missing));
        }

        $counts = [];
        $this->foreignKeys(false);

        try {
            $this->target->beginTransaction();

            foreach ($tables as $table) {
                $expected = (int) $this->source->query('SELECT COUNT(*) FROM ' . $this->quote($table, $this->sourceDriver))->fetchColumn();
                $rows = $this->source->query('SELECT * FROM ' . $this->quote($table, $this->sourceDriver));
                $insert = null;

                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $insert ??= $this->target->prepare(sprintf(
                        'INSERT INTO %s (%s) VALUES (%s)',
                        $this->quote($table, $this->targetDriver),
                        implode(', ', array_map(fn (string $column): string => $this->quote($column, $this->targetDriver), array_keys($row))),
                        implode(', ', array_fill(0, count($row), '?')),
                    ));

                    try {
                        $insert->execute(array_values($row));
                    } catch (PDOException $e) {
                        throw new RuntimeException($table . (isset($row['id']) ? ' id ' . $row['id'] : '') . ': ' . $e->getMessage(), 0, $e);
                    }
                }

                $copied = (int) $this->target->query('SELECT COUNT(*) FROM ' . $this->quote($table, $this->targetDriver))->fetchColumn();

                if ($copied !== $expected) {
                    throw new RuntimeException($table . ': ' . $expected . ' rows expected, ' . $copied . ' copied');
                }

                $counts[$table] = $copied;
            }

            if ($this->targetDriver === 'sqlite') {
                $violation = $this->target->query('PRAGMA foreign_key_check')->fetch(PDO::FETCH_ASSOC);

                if ($violation !== false) {
                    throw new RuntimeException('Foreign key violation: ' . $violation['table'] . ' row ' . $violation['rowid'] . ' references ' . $violation['parent']);
                }
            }

            $this->target->commit();
        } catch (Throwable $e) {
            if ($this->target->inTransaction()) {
                $this->target->rollBack();
            }

            throw $e;
        } finally {
            $this->foreignKeys(true);
        }

        return $counts;
    }

    private function clear(): void
    {
        $this->foreignKeys(false);

        try {
            foreach ($this->tables($this->target, $this->targetDriver) as $table) {
                $this->target->exec('DROP TABLE ' . $this->quote($table, $this->targetDriver));
            }
        } finally {
            $this->foreignKeys(true);
        }
    }

    private function foreignKeys(bool $enabled): void
    {
        $this->target->exec($this->targetDriver === 'mysql'
            ? 'SET FOREIGN_KEY_CHECKS = ' . (int) $enabled
            : 'PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF'));
    }

    /** @return list<string> */
    private function tables(PDO $pdo, string $driver): array
    {
        return $pdo->query($driver === 'mysql'
            ? 'SHOW TABLES'
            : "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\'")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function quote(string $name, string $driver): string
    {
        return $driver === 'mysql'
            ? '`' . str_replace('`', '``', $name) . '`'
            : '"' . str_replace('"', '""', $name) . '"';
    }
}
