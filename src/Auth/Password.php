<?php

declare(strict_types=1);

namespace Yawasla\Auth;

final class Password
{
    public const MIN_LENGTH = 7;

    private const MAX_BYTES = 72;

    public static function isValid(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH
            && strlen($password) <= self::MAX_BYTES
            && !str_contains($password, "\0")
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
