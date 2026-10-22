<?php

declare(strict_types=1);

namespace Yawasla\Command;

interface Command
{
    public function description(): string;

    /**
     * @param list<string> $args arguments après le nom de la commande
     * @return int code de sortie (0 = succès)
     */
    public function run(array $args): int;
}
