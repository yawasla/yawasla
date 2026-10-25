<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Route
{
    /** @param list<string> $methods */
    public function __construct(
        public string $path,
        public string $name = '',
        public array $methods = ['GET'],
    ) {
    }
}
