<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Yawasla\Controller\Admin\AdminController;
use Yawasla\Core\Config;
use Yawasla\I18n\Translator;
use Yawasla\Trait\Singleton;

final class Router
{
    use Singleton;

    /** @var list<array{methods: list<string>, regex: string, class: class-string, action: string, name: string}> */
    private array $routes;

    /** @var array<string, string> */
    private array $names;

    private string $current = '';

    private ?string $adminLocale = null;

    private string $controllerDir;

    private string $cacheFile;

    private string $basePath;

    private function __construct()
    {
        $this->controllerDir = dirname(__DIR__) . '/Controller';
        $this->cacheFile = dirname(__DIR__, 2) . '/data/cache/routes.php';
        $this->basePath = substr($_SERVER['SCRIPT_NAME'], 0, (int) strrpos($_SERVER['SCRIPT_NAME'], '/'));

        if (!is_file($this->cacheFile)) {
            $this->writeCache($this->compile());
        }

        ['routes' => $this->routes, 'names' => $this->names] = require $this->cacheFile;
    }

    /** @return array{class-string, string, list<string>} */
    public function match(string $method, string $path): array
    {
        if ($this->basePath !== '' && ($path === $this->basePath || str_starts_with($path, $this->basePath . '/'))) {
            $path = substr($path, strlen($this->basePath));
        }

        $path = rtrim($path, '/') ?: '/';

        if (preg_match('#^/admin/(' . implode('|', Translator::LOCALES) . ')(?=/|$)#', $path, $m)) {
            $this->adminLocale = $m[1];
            $path = '/admin' . substr($path, strlen($m[0]));
        } elseif ($path === '/admin' || str_starts_with($path, '/admin/')) {
            $this->adminLocale = 'en';
        }

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes as $route) {
            if (in_array($method, $route['methods'], true) && preg_match($route['regex'], $path, $matches)) {
                $params = array_map('rawurldecode', array_slice($matches, 1));

                foreach ($params as $param) {
                    if (preg_match('#[/\\\\\x00-\x1f\x7f]|\.\.#', $param)) {
                        throw new NotFoundException();
                    }
                }

                $this->current = $route['name'];

                return [$route['class'], $route['action'], $params];
            }
        }

        throw new NotFoundException();
    }

    public function current(): string
    {
        return $this->current;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function locale(): string
    {
        return $this->adminLocale ?? Config::getInstance()->get('APP_LANG', 'en');
    }

    /** @param array<string, string|int> $params */
    public function path(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new \InvalidArgumentException('Route inconnue : ' . $name);
        }

        $path = $this->names[$name];

        $locale = $this->adminLocale ?? 'en';

        if ($locale !== 'en' && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $path = '/admin/' . $locale . substr($path, strlen('/admin'));
        }

        return $this->basePath . preg_replace_callback(
            '#\{(\w+)\}#',
            fn (array $m): string => rawurlencode((string) ($params[$m[1]] ?? throw new \InvalidArgumentException("Paramètre manquant : {$m[1]}"))),
            $path,
        );
    }

    /** @return array{routes: list<array<string, mixed>>, names: array<string, string>} */
    private function compile(): array
    {
        $routes = [];
        $names = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->controllerDir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->controllerDir) + 1, -4);
            $class = new ReflectionClass('Yawasla\\Controller\\' . str_replace('/', '\\', $relative));

            if ($class->isAbstract()) {
                continue;
            }

            if (str_starts_with($relative, 'Admin/') && !$class->isSubclassOf(AdminController::class)) {
                throw new \LogicException($class->getName() . ' doit hériter de ' . AdminController::class);
            }

            $prefix = ($class->getAttributes(Route::class)[0] ?? null)?->newInstance()->path ?? '';

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    $path = rtrim($prefix . $route->path, '/') ?: '/';

                    $routes[] = [
                        'methods' => $route->methods,
                        'regex' => '#^' . preg_replace('#\\\\\{(\w+)\\\\\}#', '([^/]+)', preg_quote($path, '#')) . '$#',
                        'class' => $class->getName(),
                        'action' => $method->getName(),
                        'name' => $route->name,
                    ];

                    if ($route->name !== '') {
                        $names[$route->name] = $path;
                    }
                }
            }
        }

        return ['routes' => $routes, 'names' => $names];
    }

    /** @param array<string, mixed> $data */
    private function writeCache(array $data): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
        rename($tmp, $this->cacheFile);
    }
}
