<?php

declare(strict_types=1);

namespace Yawasla\Model;

use Medoo\Medoo;
use Yawasla\Database\Connection;

final class User
{
    private const FIELDS = ['id', 'organization_id', 'email', 'first_name', 'last_name', 'roles'];

    /** @return array<string, mixed>|null */
    public function findActive(int $id): ?array
    {
        return $this->find(['id' => $id, 'is_activated' => 1]);
    }

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->find(['email' => $email, 'is_activated' => 1]);
    }

    public function passwordHash(string $email): ?string
    {
        $hash = $this->db()->get('user', 'password_hash', ['email' => $email, 'is_activated' => 1]);

        return is_string($hash) ? $hash : null;
    }

    public function connected(int $id): void
    {
        $this->db()->update('user', ['connected_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public function changePassword(int $id, string $hash): void
    {
        $this->db()->update('user', ['password_hash' => $hash], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null
     */
    private function find(array $where): ?array
    {
        $user = $this->db()->get('user', self::FIELDS, $where);

        if (!is_array($user)) {
            return null;
        }

        $user['id'] = (int) $user['id'];
        $roles = json_decode((string) $user['roles'], true);
        $user['roles'] = is_array($roles) ? $roles : [];

        return $user;
    }

    private function db(): Medoo
    {
        return Connection::getInstance()->medoo();
    }
}
