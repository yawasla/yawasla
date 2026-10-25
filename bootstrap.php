<?php

declare(strict_types=1);

use Yawasla\Controller\Front\ErrorController;
use Yawasla\Debug\Debug;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\Router;
use Yawasla\I18n\Translator;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/logs/php.log');
error_reporting(E_ALL);

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");

require __DIR__ . '/vendor/autoload.php';

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);
    try {
        Debug::getInstance()->exception($e);
        echo (new ErrorController())->serverError();
    } catch (Throwable) {
        try {
            echo Translator::getInstance()->trans('An error occurred.');
        } catch (Throwable) {
            echo 'An error occurred.';
        }
    }
});

if (Debug::getInstance()->enabled()) {
    set_error_handler(function (int $level, string $message, string $file, int $line): bool {
        if (error_reporting() & $level) {
            Debug::getInstance()->error($level, $message, $file, $line);
        }

        return false;
    });
}

try {
    [$class, $action, $params] = Router::getInstance()->match($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    Debug::getInstance()->route($class, $action, $params);
    echo (new $class())->$action(...$params);
} catch (NotFoundException) {
    echo (new ErrorController())->notFound();
}
