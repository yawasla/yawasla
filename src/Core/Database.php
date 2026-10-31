<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use PDO;
use Yawasla\Debug\Debug;
use Yawasla\Trait\Singleton;

final class Database
{
    use Singleton;

    private Medoo $medoo;

    private function __construct()
    {
        $config = Config::getInstance();
        $logging = Debug::getInstance()->enabled();

        if ($config->get('DB_DRIVER', 'sqlite') === 'mysql') {
            $this->medoo = new Medoo([
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
            Debug::getInstance()->database($this->medoo);

            return;
        }

        $this->medoo = new Medoo(['type' => 'sqlite', 'database' => dirname(__DIR__, 2) . '/data/database.sqlite', 'logging' => $logging]);
        Debug::getInstance()->database($this->medoo);
        $this->medoo->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function medoo(): Medoo
    {
        return $this->medoo;
    }
}
