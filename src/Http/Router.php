<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Cache des routes régénéré seulement s'il n'existe pas : il est supprimé à chaque mise à jour.
 */
final class Router
{
    /** @var list<array{methods: list<string>, regex: string, class: class-string, action: string, name: string}> */
    private array $routes;

    /** @var array<string, string> nom → chemin */
    private array $names;

    private string $current = '';

    /** @param string $basePath préfixe d'une installation en sous-dossier ("/yawasla"), vide à la racine */
    public function __construct(private string $controllerDir, private string $cacheFile, private string $basePath = '')
    {
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

        foreach ($this->routes as $route) {
            if (in_array($method, $route['methods'], true) && preg_match($route['regex'], $path, $matches)) {
                $this->current = $route['name'];

                return [$route['class'], $route['action'], array_map('rawurldecode', array_slice($matches, 1))];
            }
        }

        throw new NotFoundException();
    }

    public function current(): string
    {
        return $this->current;
    }

    /**
     * URL d'une route nommée, paramètres encodés.
     *
     * @param array<string, string|int> $params
     */
    public function path(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new \InvalidArgumentException('Route inconnue : ' . $name);
        }

        return $this->basePath . preg_replace_callback(
            '#\{(\w+)\}#',
            fn (array $m): string => rawurlencode((string) ($params[$m[1]] ?? throw new \InvalidArgumentException("Paramètre manquant : {$m[1]}"))),
            $this->names[$name],
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

        // Écriture atomique : une requête concurrente ne lit jamais un fichier à moitié écrit
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
        rename($tmp, $this->cacheFile);
    }
}
