<?php

declare(strict_types=1);

namespace Yawasla\Database;

abstract class AbstractMigration
{
    /** @var list<string> */
    private array $queries = [];

    abstract public function up(): void;

    abstract public function down(): void;

    /** @return list<string> */
    public function queries(): array
    {
        return $this->queries;
    }

    protected function sql(string $query): void
    {
        $this->queries[] = preg_replace('/\{(\w+)\}/', Connection::prefix() . '$1', $query);
    }
}
