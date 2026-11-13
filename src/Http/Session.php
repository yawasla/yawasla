<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Yawasla\Core\Config;
use Yawasla\Trait\Singleton;

final class Session
{
    use Singleton;

    public const NAME = 'yawasla';

    private const LIFETIME = 86400;

    private function __construct()
    {
        $dir = dirname(__DIR__, 2) . '/data/sessions';

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        session_save_path($dir);
        session_name(self::NAME);
        session_set_cookie_params($this->cookie(self::LIFETIME));
        session_start();

        if (($_COOKIE[self::NAME] ?? null) === session_id()) {
            setcookie(self::NAME, session_id(), $this->cookie(time() + self::LIFETIME, 'expires'));
        }
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

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
        setcookie(self::NAME, '', $this->cookie(1, 'expires'));
    }

    /** @return array{lifetime?: int, expires?: int, path: string, secure: bool, httponly: bool, samesite: string} */
    private function cookie(int $time, string $key = 'lifetime'): array
    {
        return [
            $key => $time,
            'path' => Router::getInstance()->basePath() ?: '/',
            'secure' => Config::getInstance()->get('APP_ENV') !== 'dev',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
