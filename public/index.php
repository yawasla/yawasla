<?php

declare(strict_types=1);

ini_set('display_errors', '0');

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../bootstrap.php';
