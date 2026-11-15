<?php

declare(strict_types=1);

namespace Yawasla\Http;

final class RedirectException extends \RuntimeException
{
    public function __construct(public readonly string $url, public readonly int $status = 301)
    {
        parent::__construct();
    }
}
