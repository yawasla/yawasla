<?php

declare(strict_types=1);

use Yawasla\Module\Core\Main\Controller\ErrorController;
use Yawasla\Core\Installation;
use Yawasla\Core\Modules;
use Yawasla\Database\ConnectionException;
use Yawasla\Debug\Debug;
use Yawasla\Http\MethodNotAllowedException;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\RedirectException;
use Yawasla\Http\Router;
use Yawasla\Module\Core\Install\Controller\InstallController;
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

$loader = require __DIR__ . '/vendor/autoload.php';

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

Modules::getInstance()->autoload($loader);

if (Debug::getInstance()->enabled()) {
    set_error_handler(function (int $level, string $message, string $file, int $line): bool {
        if (error_reporting() & $level) {
            Debug::getInstance()->error($level, $message, $file, $line);
        }

        return false;
    });
}

try {
    [$class, $action, $params] = Router::getInstance()->match($_SERVER['REQUEST_METHOD'], explode('?', $_SERVER['REQUEST_URI'], 2)[0] ?: '/');
    Debug::getInstance()->route($class, $action, $params);
    echo $class === InstallController::class || Installation::getInstance()->installed()
        ? (new $class())->$action(...$params)
        : (new ErrorController())->maintenance();
} catch (ConnectionException $e) {
    error_log((string) $e);
    echo (new ErrorController())->maintenance();
} catch (NotFoundException) {
    echo (new ErrorController())->notFound();
} catch (MethodNotAllowedException $e) {
    echo (new ErrorController())->methodNotAllowed($e->methods);
} catch (RedirectException $e) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $e->url . ($query !== '' && !preg_match('/[\x00-\x20\x7f]/', $query) ? '?' . $query : ''), true, 301);
}
