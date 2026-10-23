<?php

declare(strict_types=1);

use Yawasla\Controller\Front\ErrorController;
use Yawasla\Core\Config;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\Router;

require __DIR__ . '/vendor/autoload.php';

$config = Config::load(__DIR__ . '/.env');
$router = null;

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/logs/php.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (!filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOL)) {
    set_exception_handler(function (Throwable $e) use ($config, &$router): void {
        error_log((string) $e);
        try {
            echo (new ErrorController($config, $router ?? throw $e))->serverError();
        } catch (Throwable) {
            // Erreur avant le routeur ou dans le rendu de la page d'erreur : texte brut
            http_response_code(500);
            echo 'Une erreur est survenue.';
        }
    });
}

$basePath = substr($_SERVER['SCRIPT_NAME'], 0, (int) strrpos($_SERVER['SCRIPT_NAME'], '/'));
$router = new Router(__DIR__ . '/src/Controller', __DIR__ . '/data/cache/routes.php', $basePath);

try {
    [$class, $action, $params] = $router->match($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    echo (new $class($config, $router))->$action(...$params);
} catch (NotFoundException) {
    echo (new ErrorController($config, $router))->notFound();
}
