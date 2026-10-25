<?php

declare(strict_types=1);

namespace Yawasla\Debug;

use Medoo\Medoo;
use Twig\Profiler\Profile;
use Yawasla\Core\Config;
use Yawasla\Http\Router;
use Yawasla\I18n\Translator;
use Yawasla\Trait\Singleton;

final class Debug
{
    use Singleton;

    private const SECRET = '/pass|token|secret|csrf|auth|cookie|session/i';

    private bool $enabled;

    private string $root;

    private Profile $profile;

    /** @var array{class: string, action: string, params: list<string>}|null */
    private ?array $route = null;

    /** @var list<array{origin: string, value: array<string, mixed>}> */
    private array $dumps = [];

    /** @var list<array{level: string, message: string, origin: string}> */
    private array $errors = [];

    /** @var array{class: string, message: string, origin: string, trace: list<string>}|null */
    private ?array $exception = null;

    /** @var array<string, true> */
    private array $missing = [];

    private ?Medoo $medoo = null;

    private function __construct()
    {
        $this->enabled = PHP_SAPI !== 'cli' && Config::getInstance()->get('APP_ENV') === 'dev';
        $this->root = dirname(__DIR__, 2) . '/';
        $this->profile = new Profile();
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    /** @param list<string> $params */
    public function route(string $class, string $action, array $params): void
    {
        $this->route = ['class' => $class, 'action' => $action, 'params' => $params];
    }

    public function dump(mixed $value, string $file, int $line): void
    {
        if ($this->enabled) {
            $this->dumps[] = ['origin' => $this->origin($file, $line), 'value' => Dumper::normalize($value)];
        }
    }

    public function error(int $level, string $message, string $file, int $line): void
    {
        $this->errors[] = ['level' => self::levelName($level), 'message' => $message, 'origin' => $this->origin($file, $line)];
    }

    public function exception(\Throwable $e): void
    {
        $trace = [];
        foreach ($e->getTrace() as $frame) {
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
            $trace[] = isset($frame['file']) ? $call . ' — ' . $this->origin($frame['file'], $frame['line'] ?? 0) : $call;
        }

        $this->exception = ['class' => $e::class, 'message' => $e->getMessage(), 'origin' => $this->origin($e->getFile(), $e->getLine()), 'trace' => $trace];
    }

    public function database(Medoo $medoo): void
    {
        $this->medoo = $medoo;
    }

    public function missingTranslation(string $text): void
    {
        $this->missing[$text] = true;
    }

    /** @return array<string, mixed> */
    public function collect(): array
    {
        $router = Router::getInstance();
        $translator = Translator::getInstance();

        return [
            'route' => ['name' => $router->current(), 'locale' => $router->locale(), 'basePath' => $router->basePath()] + ($this->route ?? []),
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'uri' => (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
                'get' => $this->mask($_GET),
                'post' => $this->mask($_POST),
                'headers' => $this->mask($this->headers()),
                'cookies' => $this->mask($_COOKIE),
            ],
            'templates' => $this->templates($this->profile),
            'sql' => $this->queries(),
            'dumps' => $this->dumps,
            'errors' => $this->errors,
            'exception' => $this->exception,
            'translations' => ['locale' => $translator->locale(), 'dir' => $translator->dir(), 'missing' => array_keys($this->missing)],
            'perf' => [
                'time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000),
                'memory' => round(memory_get_peak_usage() / 1048576, 1),
                'php' => PHP_VERSION,
            ],
        ];
    }

    /** @return array{queries: list<array{sql: string, time: float, count: int}>, time: float, duplicates: int} */
    private function queries(): array
    {
        $queries = [];
        foreach ($this->medoo?->log() ?? [] as [$sql, $time]) {
            $queries[] = ['sql' => $sql, 'time' => round((float) $time * 1000, 2), 'count' => 0];
        }

        $counts = array_count_values(array_column($queries, 'sql'));
        foreach ($queries as $i => $query) {
            $queries[$i]['count'] = $counts[$query['sql']];
        }

        return [
            'queries' => $queries,
            'time' => round(array_sum(array_column($queries, 'time')), 2),
            'duplicates' => count(array_filter($counts, fn (int $count): bool => $count > 1)),
        ];
    }

    private function origin(string $file, int $line): string
    {
        return (str_starts_with($file, $this->root) ? substr($file, strlen($this->root)) : $file) . ':' . $line;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-')] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, string>
     */
    private function mask(array $values): array
    {
        $masked = [];
        foreach ($values as $key => $value) {
            $masked[$key] = preg_match(self::SECRET, (string) $key) ? '••••' : (is_string($value) ? $value : (string) json_encode($value));
        }

        return $masked;
    }

    /** @return list<array{name: string, type: string, depth: int, time: float}> */
    private function templates(Profile $profile, int $depth = 0): array
    {
        $list = [];
        foreach ($profile->getProfiles() as $child) {
            $list[] = ['name' => $child->isTemplate() ? $child->getTemplate() : $child->getName(), 'type' => $child->getType(), 'depth' => $depth, 'time' => round($child->getDuration() * 1000, 1)];
            array_push($list, ...$this->templates($child, $depth + 1));
        }

        return $list;
    }

    private static function levelName(int $level): string
    {
        return match ($level) {
            E_WARNING, E_USER_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            default => 'Error',
        };
    }
}
