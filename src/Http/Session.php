<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Yawasla\Core\Config;
use Yawasla\Trait\Singleton;

final class Session
{
    use Singleton;

    private function __construct()
    {
        $dir = dirname(__DIR__, 2) . '/data/sessions';

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path($dir);
        session_name('yawasla');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Router::getInstance()->basePath() ?: '/',
            'secure' => Config::getInstance()->get('APP_ENV') !== 'dev',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
