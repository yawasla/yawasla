<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RuntimeException;
use Yawasla\Core\Modules;

final class Vite
{
    /** @var array<string, array{file: string, css?: list<string>, imports?: list<string>}>|null */
    private ?array $manifest = null;

    public function __construct(private readonly string $root, private readonly string $dir, private readonly string $url)
    {
    }

    public function tags(string $entry, bool $module = true): string
    {
        $entry = $this->key($entry);
        $file = $this->chunk($entry)['file'];

        if (str_ends_with($file, '.css')) {
            return $this->style($file);
        }

        $imports = $this->imports($entry);
        $tags = [];

        foreach ([$entry, ...$imports] as $key) {
            foreach ($this->chunk($key)['css'] ?? [] as $css) {
                $tags[] = $this->style($css);
            }
        }

        foreach ($imports as $key) {
            $tags[] = '<link rel="modulepreload" href="' . $this->url($this->chunk($key)['file']) . '">';
        }

        $tags[] = '<script' . ($module ? ' type="module"' : '') . ' src="' . $this->url($file) . '"></script>';

        return implode("\n", $tags);
    }

    private function key(string $entry): string
    {
        if (!preg_match('#^@(\w+)/(.+)$#', $entry, $m)) {
            throw new RuntimeException('Asset entry must start with @module/: ' . $entry);
        }

        $module = Modules::getInstance()->get($m[1]) ?? throw new RuntimeException('Unknown module: ' . $m[1]);

        return substr($module['dir'], strlen($this->root) + 1) . '/assets/' . $m[2];
    }

    /**
     * @param array<string, true> $seen
     * @return list<string>
     */
    private function imports(string $key, array &$seen = []): array
    {
        foreach ($this->chunk($key)['imports'] ?? [] as $import) {
            if (!isset($seen[$import])) {
                $seen[$import] = true;
                $this->imports($import, $seen);
            }
        }

        return array_keys($seen);
    }

    /** @return array{file: string, css?: list<string>, imports?: list<string>} */
    private function chunk(string $key): array
    {
        if ($this->manifest === null) {
            $json = @file_get_contents($this->dir . '/.vite/manifest.json');

            if ($json === false) {
                throw new RuntimeException('Assets are not built: run npm run build.');
            }

            $this->manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }

        return $this->manifest[$key] ?? throw new RuntimeException('Unknown asset entry: ' . $key);
    }

    private function style(string $file): string
    {
        return '<link rel="stylesheet" href="' . $this->url($file) . '">';
    }

    private function url(string $file): string
    {
        return htmlspecialchars($this->url . '/' . $file, ENT_QUOTES);
    }
}
