<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Yawasla\Database\Connection;
use Yawasla\Trait\Singleton;

final class Installation
{
    use Singleton;

    private string $marker;

    private function __construct()
    {
        $this->marker = dirname(__DIR__, 2) . '/data/installed';
    }

    public function installed(): bool
    {
        if (is_file($this->marker)) {
            return true;
        }

        if (!$this->hasUser()) {
            return false;
        }

        $this->complete();

        return true;
    }

    public function complete(): void
    {
        file_put_contents($this->marker, date('Y-m-d H:i:s') . PHP_EOL);
    }

    private function hasUser(): bool
    {
        $pdo = Connection::getInstance()->medoo()->pdo;
        $table = Connection::prefix() . 'user';

        return Connection::hasTable($pdo, Connection::driver(), $table)
            && $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetchColumn() !== false;
    }
}
