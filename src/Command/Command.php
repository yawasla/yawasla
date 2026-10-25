<?php

declare(strict_types=1);

namespace Yawasla\Command;

interface Command
{
    public function description(): string;

    /** @param list<string> $args */
    public function run(array $args): int;
}
