<?php

declare(strict_types=1);

namespace Yawasla\Model;

use Medoo\Medoo;
use Yawasla\Database\Connection;

final class Token
{
    public function create(int $userId, string $type, int $minutes): string
    {
        $token = bin2hex(random_bytes(32));
        $now = time();

        $this->db()->insert('token', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s', $now),
            'expired_at' => date('Y-m-d H:i:s', $now + $minutes * 60),
        ]);

        return $token;
    }

    public function userId(string $token, string $type): ?int
    {
        $id = $this->db()->get('token', 'user_id', $this->valid($token, $type));

        return $id === null ? null : (int) $id;
    }

    public function use(string $token, string $type): ?int
    {
        $userId = $this->userId($token, $type);

        if ($userId === null) {
            return null;
        }

        $statement = $this->db()->update('token', ['used_at' => date('Y-m-d H:i:s')], $this->valid($token, $type));

        return $statement !== null && $statement->rowCount() === 1 ? $userId : null;
    }

    public function cancel(int $userId): void
    {
        $this->db()->update('token', ['used_at' => date('Y-m-d H:i:s')], [
            'user_id' => $userId,
            'used_at' => null,
            'expired_at[>]' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed> */
    private function valid(string $token, string $type): array
    {
        return [
            'token_hash' => hash('sha256', $token),
            'type' => $type,
            'used_at' => null,
            'expired_at[>]' => date('Y-m-d H:i:s'),
            'user_id[!]' => null,
        ];
    }

    private function db(): Medoo
    {
        return Connection::getInstance()->medoo();
    }
}
