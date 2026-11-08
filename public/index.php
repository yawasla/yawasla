<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . explode('?', $_SERVER['REQUEST_URI'], 2)[0])) {
    return false;
}

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 or later is required.');
}

require __DIR__ . '/../bootstrap.php';
