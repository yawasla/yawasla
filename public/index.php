<?php

declare(strict_types=1);

// Serveur de dev (php -S) : les fichiers existants sont servis tels quels
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../bootstrap.php';
