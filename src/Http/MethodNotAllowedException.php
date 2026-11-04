<?php

declare(strict_types=1);

namespace Yawasla\Http;

final class MethodNotAllowedException extends \RuntimeException
{
    /** @param list<string> $methods */
    public function __construct(public readonly array $methods)
    {
        parent::__construct();
    }
}
