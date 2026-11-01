<?php

declare(strict_types=1);

namespace Yawasla\Database;

use Medoo\Medoo;
use PDO;
use Yawasla\Core\Config;
use Yawasla\Debug\Debug;
use Yawasla\Trait\Singleton;

final class Database
{
    use Singleton;

    private Medoo $medoo;

    private function __construct()
    {
        $this->medoo = self::connect(self::driver());
        Debug::getInstance()->database($this->medoo);
    }

    public static function driver(): string
    {
        return Config::getInstance()->get('DB_DRIVER', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
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
                'logging' => $logging,
                'option' => [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false],
            ]);
        }

        $medoo = new Medoo(['type' => 'sqlite', 'database' => dirname(__DIR__, 2) . '/data/database.sqlite', 'logging' => $logging]);
        $medoo->pdo->exec('PRAGMA foreign_keys = ON');

        return $medoo;
    }

    public function medoo(): Medoo
    {
        return $this->medoo;
    }
}
