<?php

declare(strict_types=1);

namespace Yawasla\Database;

use InvalidArgumentException;
use Medoo\Medoo;
use PDO;
use PDOException;
use Yawasla\Core\Config;
use Yawasla\Debug\Debug;
use Yawasla\Trait\Singleton;

final class Connection
{
    use Singleton;

    public const CHARSET = 'utf8mb4';

    public const COLLATION = 'utf8mb4_unicode_ci';

    private Medoo $medoo;

    private function __construct()
    {
        try {
            $this->medoo = self::connect(self::driver());
        } catch (PDOException | InvalidArgumentException $e) {
            throw new ConnectionException('Database connection failed', 0, $e);
        }

        Debug::getInstance()->database($this->medoo);
    }

    public static function driver(): string
    {
        return Config::getInstance()->get('DB_DRIVER', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    }

    public static function prefix(): string
    {
        $prefix = Config::getInstance()->get('DB_PREFIX');

        if (!preg_match('/^\w*$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid DB_PREFIX');
        }

        return $prefix === '' ? '' : rtrim($prefix, '_') . '_';
    }

    public static function hasTable(PDO $pdo, string $driver, string $table): bool
    {
        $statement = $pdo->prepare($driver === 'mysql' ? 'SHOW TABLES LIKE ?' : "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
        $statement->execute([$driver === 'mysql' ? addcslashes($table, '_%\\') : $table]);

        return $statement->fetchColumn() !== false;
    }

    public static function createDatabase(): void
    {
        $config = Config::getInstance();
        $pdo = new PDO(
            'mysql:host=' . $config->get('DB_HOST') . ';port=' . (int) $config->get('DB_PORT', '3306') . ';charset=utf8mb4',
            $config->get('DB_USER'),
            $config->get('DB_PASSWORD'),
            self::mysqlOptions(),
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $config->get('DB_NAME')) . '` CHARACTER SET ' . self::CHARSET . ' COLLATE ' . self::COLLATION);
    }

    public static function connect(string $driver): Medoo
    {
        $config = Config::getInstance();
        $logging = Debug::getInstance()->enabled();

        if ($driver === 'mysql') {
            return new Medoo([
                'type' => 'mysql',
                'host' => $config->get('DB_HOST'),
                'port' => (int) $config->get('DB_PORT', '3306'),
                'database' => $config->get('DB_NAME'),
                'username' => $config->get('DB_USER'),
                'password' => $config->get('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'prefix' => self::prefix(),
                'logging' => $logging,
                'option' => self::mysqlOptions(),
                'command' => ["SET SQL_MODE = 'ANSI_QUOTES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"],
            ]);
        }

        $medoo = new Medoo(['type' => 'sqlite', 'database' => dirname(__DIR__, 2) . '/data/database.sqlite', 'prefix' => self::prefix(), 'logging' => $logging]);
        $medoo->pdo->exec('PRAGMA foreign_keys = ON');

        return $medoo;
    }

    /** @return array<int, bool> */
    private static function mysqlOptions(): array
    {
        return [constant(PHP_VERSION_ID >= 80400 ? 'Pdo\Mysql::ATTR_MULTI_STATEMENTS' : 'PDO::MYSQL_ATTR_MULTI_STATEMENTS') => false];
    }

    public function medoo(): Medoo
    {
        return $this->medoo;
    }
}
