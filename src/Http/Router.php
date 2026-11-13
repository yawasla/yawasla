<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Yawasla\Auth\Auth;
use Yawasla\Controller\AbstractAdminController;
use Yawasla\Core\Config;
use Yawasla\Core\Modules;
use Yawasla\Debug\Debug;
use Yawasla\I18n\Translator;
use Yawasla\Trait\Singleton;

final class Router
{
    use Singleton;

    private const PARAMS = [
        'string' => '[^/]+',
        'int' => '[1-9]\d{0,17}',
        'bool' => 'true|false|1|0',
        'float' => '-?(?:0|[1-9]\d{0,14})(?:\.\d{1,15})?',
    ];

    private const STRICT_PARAMS = ['string', 'int'];

    private const LOCALIZED = ['/admin', '/login'];

    /** @var list<array{methods: list<string>, regex: string, class: class-string, action: string, name: string, auth: string, params: list<array{string, string}>}> */
    private array $routes;

    /** @var array<string, string> */
    private array $names;

    private string $current = '';

    private ?string $canonical = null;

    private ?string $urlLocale = null;

    private ?string $localizedPath = null;

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

    /** @return array{class-string, string, array<string, string|int|float|bool>} */
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

        $prefix = $this->localizedPrefix($path);

        if ($prefix !== null) {
            $this->urlLocale = 'en';

            if (preg_match('#^' . $prefix . '/(' . implode('|', Translator::LOCALES) . ')(?=/|$)#', $path, $m)) {
                $this->urlLocale = $m[1];
                $path = $prefix . substr($path, strlen($m[0]));
            }
        }

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['auth'] !== 'guest' && !Auth::getInstance()->allows($route['auth'])) {
                    continue;
                }

                if (!in_array($method, $route['methods'], true)) {
                    array_push($allowed, ...$route['methods']);
                    continue;
                }

                $params = [];

                foreach ($route['params'] as $i => [$name, $type]) {
                    $params[$name] = $this->convert($matches[$i + 1], $type);
                }

                $this->current = $route['name'];
                $this->canonical = $canonical;
                $this->localizedPath = $prefix !== null ? $path : null;

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
        return $this->urlLocale ?? Config::getInstance()->get('APP_LANG', 'en');
    }

    /** @return array<string, string> */
    public function localeUrls(): array
    {
        if ($this->localizedPath === null) {
            return [];
        }

        $prefix = (string) $this->localizedPrefix($this->localizedPath);
        $urls = [];

        foreach (Translator::LOCALES as $locale) {
            $urls[$locale] = $this->basePath . ($locale === 'en' ? $this->localizedPath : $prefix . '/' . $locale . substr($this->localizedPath, strlen($prefix)));
        }

        return $urls;
    }

    public function localize(string $path): string
    {
        $prefix = $this->localizedPrefix($path);
        $locale = $this->locale();

        if ($prefix === null || $locale === 'en' || !in_array($locale, Translator::LOCALES, true)) {
            return $path;
        }

        return $prefix . '/' . $locale . substr($path, strlen($prefix));
    }

    /** @param array<string, string|int|float|bool> $params */
    public function path(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new \InvalidArgumentException('Unknown route: ' . $name);
        }

        $path = $this->localize($this->names[$name]);

        return $this->basePath . preg_replace_callback(
            '#\{(\w+)\}#',
            fn (array $m): string => rawurlencode(self::stringify($params[$m[1]] ?? throw new \InvalidArgumentException("Missing route parameter: {$m[1]}"))),
            $path,
        );
    }

    /** @return array{routes: list<array<string, mixed>>, names: array<string, string>} */
    private function compile(): array
    {
        $routes = [];
        $names = [];

        foreach ($this->controllers() as $class) {
            $parent = ($class->getAttributes(Route::class)[0] ?? null)?->newInstance();
            $prefix = $parent->path ?? '';
            $strict = $class->getConstant('STRICT_ROUTES') === true;

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    $path = rtrim($prefix . $route->path, '/') ?: '/';
                    $auth = $route->auth ?? $parent?->auth ?? ($strict ? null : 'guest');

                    if (($path === '/admin' || str_starts_with($path, '/admin/')) && !$class->isSubclassOf(AbstractAdminController::class)) {
                        throw new \LogicException($class->getName() . ' must extend ' . AbstractAdminController::class);
                    }

                    if ($auth === null) {
                        throw new \LogicException('Missing auth on route ' . $path . ' (' . $class->getName() . ')');
                    }

                    if (!in_array($auth, Auth::LEVELS, true)) {
                        throw new \LogicException('Unknown auth "' . $auth . '" on route ' . $path . ' (' . $class->getName() . ')');
                    }

                    if ($route->name !== '' && isset($names[$route->name])) {
                        throw new \LogicException('Duplicate route: ' . $route->name);
                    }

                    $params = $this->params($method, $path, $strict);

                    $routes[] = [
                        'methods' => $route->methods,
                        'regex' => '#^' . preg_replace_callback(
                            '#\\\\\{(\w+)\\\\\}#',
                            fn (array $m): string => '(' . self::PARAMS[$params[$m[1]]] . ')',
                            preg_quote($path, '#'),
                        ) . '$#',
                        'class' => $class->getName(),
                        'action' => $method->getName(),
                        'name' => $route->name,
                        'auth' => $auth,
                        'params' => array_map(null, array_keys($params), array_values($params)),
                    ];

                    if ($route->name !== '') {
                        $names[$route->name] = $path;
                    }
                }
            }
        }

        return ['routes' => $routes, 'names' => $names];
    }

    /** @return array<string, string> */
    private function params(ReflectionMethod $method, string $path, bool $strict): array
    {
        preg_match_all('#\{(\w+)\}#', $path, $m);
        $types = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $types[$parameter->getName()] = $type instanceof ReflectionNamedType && !$type->allowsNull() ? $type->getName() : '';

            if (!in_array($parameter->getName(), $m[1], true) && !$parameter->isOptional()) {
                throw new \LogicException('Parameter $' . $parameter->getName() . ' is not in route ' . $path . ' (' . $method->class . '::' . $method->getName() . ')');
            }
        }

        $params = [];

        foreach ($m[1] as $name) {
            if (!isset($types[$name])) {
                throw new \LogicException('Route parameter {' . $name . '} has no $' . $name . ' in ' . $method->class . '::' . $method->getName());
            }

            if (!isset(self::PARAMS[$types[$name]]) || ($strict && !in_array($types[$name], self::STRICT_PARAMS, true))) {
                throw new \LogicException('Type not allowed for $' . $name . ' in ' . $method->class . '::' . $method->getName());
            }

            $params[$name] = $types[$name];
        }

        return $params;
    }

    private function localizedPrefix(string $path): ?string
    {
        foreach (self::LOCALIZED as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $prefix;
            }
        }

        return null;
    }

    private static function stringify(string|int|float|bool $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private function convert(string $value, string $type): string|int|float|bool
    {
        if ($type !== 'string') {
            return match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                default => $value === 'true' || $value === '1',
            };
        }

        $value = rawurldecode($value);

        if (preg_match('#[/\\\\\x00-\x1f\x7f]|\.\.#', $value)) {
            throw new NotFoundException();
        }

        return $value;
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
