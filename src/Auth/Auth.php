<?php

declare(strict_types=1);

namespace Yawasla\Auth;

use Yawasla\Http\Session;
use Yawasla\Model\User;
use Yawasla\Trait\Singleton;

final class Auth
{
    use Singleton;

    public const LEVELS = ['guest', 'user', 'admin'];

    private const SESSION_KEY = 'user_id';

    private const FINGERPRINT_KEY = 'user_fingerprint';

    /** @var array<string, mixed>|false|null */
    private array|false|null $user = null;

    public function allows(string $level): bool
    {
        return match ($level) {
            'guest' => true,
            'user' => $this->user() !== null,
            'admin' => in_array('admin', $this->user()['roles'] ?? [], true),
            default => false,
        };
    }

    public function login(int $userId): void
    {
        $session = Session::getInstance();
        $session->regenerate();
        $session->set(self::SESSION_KEY, $userId);
        $session->set(self::FINGERPRINT_KEY, (new User())->findActive($userId)['fingerprint'] ?? '');
        (new User())->connected($userId);
        $this->user = null;
    }

    public function logout(): void
    {
        Session::getInstance()->destroy();
        $this->user = false;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->user === null) {
            $this->user = $this->load() ?? false;
        }

        return $this->user ?: null;
    }

    /** @return array<string, mixed>|null */
    private function load(): ?array
    {
        if (!isset($_COOKIE[Session::NAME])) {
            return null;
        }

        $session = Session::getInstance();
        $id = $session->get(self::SESSION_KEY);

        if (!is_int($id)) {
            return null;
        }

        try {
            $user = (new User())->findActive($id);
        } catch (\PDOException) {
            return null;
        }

        $fingerprint = $session->get(self::FINGERPRINT_KEY);

        if ($user === null || !is_string($fingerprint) || !hash_equals($user['fingerprint'], $fingerprint)) {
            $session->remove(self::SESSION_KEY);
            $session->remove(self::FINGERPRINT_KEY);

            return null;
        }

        return $user;
    }
}
