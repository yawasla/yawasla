<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Install;

use Yawasla\Core\Config;
use Yawasla\Database\Connection;
use Yawasla\Database\ConnectionException;

final class Requirements
{
    private const SQLITE_VERSION = '3.26.0';

    private bool $databaseMissing = false;

    public function prepareStorage(): bool
    {
        $data = dirname(__DIR__, 4) . '/data';

        if (is_dir($data) && !is_dir($data . '/logs')) {
            @mkdir($data . '/logs', 0700);
        }

        foreach ([$data, $data . '/cache', $data . '/logs', $data . '/sessions'] as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                return false;
            }
        }

        return is_dir($data);
    }

    /** @return list<string> */
    public function errors(): array
    {
        $errors = [];
        $driver = Connection::driver();

        foreach (['pdo_' . $driver, 'mbstring', 'openssl'] as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = 'Missing PHP extension: ' . $extension;
            }
        }

        if (!$this->isHttps()) {
            $errors[] = 'HTTPS is required.';
        }

        if ($errors !== []) {
            return $errors;
        }

        try {
            $pdo = Connection::getInstance()->medoo()->pdo;

            if ($driver === 'sqlite' && version_compare((string) $pdo->query('SELECT sqlite_version()')->fetchColumn(), self::SQLITE_VERSION, '<')) {
                $errors[] = 'SQLite 3.26 or later is required.';
            }
        } catch (ConnectionException $e) {
            $errors[] = $this->connectionError($e);
        }

        return $errors;
    }

    public function databaseMissing(): bool
    {
        return $this->databaseMissing;
    }

    private function connectionError(ConnectionException $e): string
    {
        $message = $e->getPrevious()?->getMessage() ?? '';

        if (str_contains($message, '[1049]')) {
            $this->databaseMissing = true;

            return 'The database set in .env does not exist yet.';
        }

        error_log((string) $e);

        return str_contains($message, '[2002]') && Config::getInstance()->get('DB_HOST') === 'localhost'
            ? 'The database server cannot be reached through localhost: try DB_HOST=127.0.0.1 in .env'
            : 'Unable to connect to the database: check .env';
    }

    private function isHttps(): bool
    {
        if (Config::getInstance()->get('APP_ENV') === 'dev') {
            return true;
        }

        return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}
