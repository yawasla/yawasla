<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;

final class Database
{
    public static function connect(Config $config, string $sqliteFile): Medoo
    {
        if ($config->get('DB_DRIVER', 'sqlite') === 'mysql') {
            return new Medoo([
                'type' => 'mysql',
                'host' => $config->get('DB_HOST'),
                'port' => (int) $config->get('DB_PORT', '3306'),
                'database' => $config->get('DB_NAME'),
                'username' => $config->get('DB_USER'),
                'password' => $config->get('DB_PASSWORD'),
                'charset' => 'utf8mb4',
            ]);
        }

        $db = new Medoo(['type' => 'sqlite', 'database' => $sqliteFile]);
        // SQLite ignore les clés étrangères sans ce pragma, à chaque connexion
        $db->pdo->exec('PRAGMA foreign_keys = ON');

        return $db;
    }
}
