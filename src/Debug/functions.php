<?php

declare(strict_types=1);

use Yawasla\Debug\Debug;

if (!function_exists('dump')) {
    function dump(mixed ...$vars): mixed
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        foreach ($vars as $var) {
            Debug::getInstance()->dump($var, $caller['file'] ?? '', $caller['line'] ?? 0);
        }

        return $vars[0] ?? null;
    }
}
