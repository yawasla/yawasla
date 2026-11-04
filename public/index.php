<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . explode('?', $_SERVER['REQUEST_URI'], 2)[0])) {
    return false;
}

require __DIR__ . '/../bootstrap.php';
