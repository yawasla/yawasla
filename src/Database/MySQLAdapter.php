<?php

declare(strict_types=1);

namespace Yawasla\Database;

final class MySQLAdapter extends AbstractAdapter
{
    public function execute(string $sql): void
    {
        $this->pdo->exec($this->charset($sql));
    }

    private function charset(string $sql): string
    {
        $this->strings = [];
        $masked = $this->mask($sql, true);

        if (
            !preg_match('/^\s*CREATE\s+(?:TEMPORARY\s+)?TABLE\b/i', $masked)
            || preg_match('/\b(?:CHARSET|CHARACTER\s+SET|COLLATE|SELECT|LIKE)\b/i', $masked)
            || !preg_match('/\)[^()]*$/', $masked)
        ) {
            return $sql;
        }

        return rtrim($sql, "; \t\n\r") . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
}
