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

        $user = (new User())->findActive($id);

        if ($user === null) {
            $session->remove(self::SESSION_KEY);
        }

        return $user;
    }
}
