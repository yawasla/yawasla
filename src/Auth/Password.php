<?php

declare(strict_types=1);

namespace Yawasla\Auth;

final class Password
{
    public const MIN_LENGTH = 7;

    private const MAX_BYTES = 72;

    private const COST = 12;

    private const DUMMY = '$2y$12$RLNi39LbpCoHcyU3NLMlveZsJxXNCfJ0ksz6MP.sZPE3IL2a7Xn6G';

    public static function isValid(#[\SensitiveParameter] string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH
            && strlen($password) <= self::MAX_BYTES
            && !str_contains($password, "\0")
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
    }

    public static function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    public static function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function verifyDummy(#[\SensitiveParameter] string $password): void
    {
        password_verify($password, self::DUMMY);
    }
}
