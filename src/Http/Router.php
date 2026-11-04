<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Yawasla\Controller\AbstractAdminController;
use Yawasla\Core\Config;
use Yawasla\Core\Modules;
use Yawasla\Debug\Debug;
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

    private ?string $canonical = null;

    private ?string $adminLocale = null;

    private string $cacheFile;

    private string $basePath;

    private function __construct()
    {
        $this->cacheFile = dirname(__DIR__, 2) . '/data/cache/routes.php';
        $this->basePath = substr($_SERVER['SCRIPT_NAME'], 0, (int) strrpos($_SERVER['SCRIPT_NAME'], '/'));

        if (Debug::getInstance()->enabled() || !is_file($this->cacheFile)) {
            $data = $this->compile();
            $this->writeCache($data);
        } else {
            $data = require $this->cacheFile;
        }

        ['routes' => $this->routes, 'names' => $this->names] = $data;
    }

    /** @return array{class-string, string, list<string>} */
    public function match(string $method, string $path): array
    {
        if ($this->basePath !== '' && ($path === $this->basePath || str_starts_with($path, $this->basePath . '/'))) {
            $path = substr($path, strlen($this->basePath));
        }

        $trimmed = rtrim($path, '/') ?: '/';

        if ($path !== '' && $trimmed !== $path) {
            $target = $this->basePath . $trimmed;

            if (!preg_match('#^/(?![/\\\\])[^\x00-\x20\x7f\\\\]*$#', $target)) {
                throw new NotFoundException();
            }

            throw new RedirectException($target);
        }

        $path = $trimmed;
        $canonical = $this->basePath . $path;

        if (preg_match('#^/admin/(' . implode('|', Translator::LOCALES) . ')(?=/|$)#', $path, $m)) {
            $this->adminLocale = $m[1];
            $path = '/admin' . substr($path, strlen($m[0]));
        } elseif ($path === '/admin' || str_starts_with($path, '/admin/')) {
            $this->adminLocale = 'en';
        }

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if (!in_array($method, $route['methods'], true)) {
                    array_push($allowed, ...$route['methods']);
                    continue;
                }

                $params = array_map('rawurldecode', array_slice($matches, 1));

                foreach ($params as $param) {
                    if (preg_match('#[/\\\\\x00-\x1f\x7f]|\.\.#', $param)) {
                        throw new NotFoundException();
                    }
                }

                $this->current = $route['name'];
                $this->canonical = $canonical;

                return [$route['class'], $route['action'], $params];
            }
        }

        if ($allowed !== []) {
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            throw new MethodNotAllowedException(array_values(array_unique($allowed)));
        }

        throw new NotFoundException();
    }

    public function current(): string
    {
        return $this->current;
    }

    public function canonical(): ?string
    {
        return $this->canonical;
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
            throw new \InvalidArgumentException('Unknown route: ' . $name);
        }

        $path = $this->names[$name];

        $locale = $this->adminLocale ?? 'en';

        if ($locale !== 'en' && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $path = '/admin/' . $locale . substr($path, strlen('/admin'));
        }

        return $this->basePath . preg_replace_callback(
            '#\{(\w+)\}#',
            fn (array $m): string => rawurlencode((string) ($params[$m[1]] ?? throw new \InvalidArgumentException("Missing route parameter: {$m[1]}"))),
            $path,
        );
    }

    /** @return array{routes: list<array<string, mixed>>, names: array<string, string>} */
    private function compile(): array
    {
        $routes = [];
        $names = [];

        foreach ($this->controllers() as $class) {
            $prefix = ($class->getAttributes(Route::class)[0] ?? null)?->newInstance()->path ?? '';

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    $path = rtrim($prefix . $route->path, '/') ?: '/';

                    if (($path === '/admin' || str_starts_with($path, '/admin/')) && !$class->isSubclassOf(AbstractAdminController::class)) {
                        throw new \LogicException($class->getName() . ' must extend ' . AbstractAdminController::class);
                    }

                    if ($route->name !== '' && isset($names[$route->name])) {
                        throw new \LogicException('Duplicate route: ' . $route->name);
                    }

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

    /** @return list<ReflectionClass<object>> */
    private function controllers(): array
    {
        $classes = [];

        foreach (Modules::getInstance()->enabled() as $module) {
            $dir = $module['dir'] . '/src/Controller';

            if (!is_dir($dir)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($dir) + 1, -4);
                $class = new ReflectionClass($module['namespace'] . 'Controller\\' . str_replace('/', '\\', $relative));

                if (!$class->isAbstract()) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
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
