<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Yawasla\Trait\Singleton;

final class Request
{
    use Singleton;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<array-key, mixed> */
    private array $query;

    /** @var array<array-key, mixed> */
    private array $post;

    private function __construct()
    {
        $this->server = $_SERVER;
        $this->query = $_GET;
        $this->post = $_POST;
    }

    public function method(): string
    {
        return (string) ($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function path(): string
    {
        return explode('?', (string) ($this->server['REQUEST_URI'] ?? ''), 2)[0] ?: '/';
    }

    public function query(): string
    {
        return (string) ($this->server['QUERY_STRING'] ?? '');
    }

    /** @return array<array-key, mixed> */
    public function queryParams(): array
    {
        return $this->query;
    }

    /** @return array<array-key, mixed> */
    public function post(): array
    {
        return $this->post;
    }

    public function input(string $key): ?string
    {
        $value = $this->post[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return (!empty($https) && strtolower((string) $https) !== 'off')
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? '');
    }

    public function crossSite(): bool
    {
        return ($this->server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site';
    }

    public function basePath(): string
    {
        $script = (string) ($this->server['SCRIPT_NAME'] ?? '');

        return substr($script, 0, (int) strrpos($script, '/'));
    }

    public function publicDir(): string
    {
        return dirname((string) ($this->server['SCRIPT_FILENAME'] ?? ''));
    }
}
