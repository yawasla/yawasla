<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RuntimeException;

final class ModuleException extends RuntimeException
{
    /**
     * @param array<string, string> $params
     * @param array{string, array<string, string>}|null $reason
     */
    public function __construct(string $message, public readonly array $params = [], public readonly ?array $reason = null)
    {
        parent::__construct($message);
    }

    public function text(): string
    {
        return strtr($this->getMessage(), $this->params);
    }
}
