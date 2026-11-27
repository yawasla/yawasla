<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Composer\Autoload\ClassLoader;
use JsonException;
use LogicException;
use RuntimeException;
use Yawasla\Debug\Debug;
use Yawasla\I18n\Translator;
use Yawasla\Trait\Singleton;

/**
 * @phpstan-type Module array{name: string, id: string, dir: string, namespace: string, core: bool, version: string, locked: bool, requires: list<string>, admin: list<array{route: string, label: string, icon: string}>, title: array<string, string>, description: array<string, string>, installed: ?string, wanted: bool, active: bool, pending: ?string, reason: string, detail: string}
 */
final class Modules
{
    use Singleton;

    public const NAME = '/^[A-Z][A-Za-z0-9]*$/';

    private const VERSION = '/^\d{1,5}\.\d{1,5}\.\d{1,5}$/';

    private const KEYS = ['version', 'requires', 'locked', 'admin', 'title', 'description'];

    private const ICON = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private string $root;

    /** @var array<string, Module> */
    private array $modules = [];

    private bool $compiled = false;

    private function __construct()
    {
        $this->root = dirname(__DIR__, 2);
        $cache = $this->root . '/data/cache/modules.php';

        if (!Debug::getInstance()->enabled() && is_file($cache)) {
            $data = require $cache;

            if (is_array($data) && ($data['stamp'] ?? null) === $this->stamp()) {
                $this->modules = $data['modules'];

                return;
            }

            Cache::clear();
        }

        $this->compile();
    }

    public function discover(): void
    {
        if (!$this->compiled) {
            $this->refresh();
        }
    }

    public function refresh(): void
    {
        $before = array_keys($this->enabled());
        $this->compile();

        if ($before !== array_keys($this->enabled())) {
            Cache::clear();
            $this->write();
        }
    }

    public function autoload(ClassLoader $loader): void
    {
        foreach ($this->enabled() as $module) {
            $loader->addPsr4($module['namespace'], $module['dir'] . '/src');
        }
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string, Module> */
    public function enabled(): array
    {
        return array_filter($this->modules, fn (array $module): bool => $module['active']);
    }

    /** @return Module|null */
    public function get(string $name): ?array
    {
        $module = $this->find($name);

        return $module !== null && $module['active'] ? $module : null;
    }

    /** @return Module|null */
    public function find(string $name): ?array
    {
        return $this->modules[strtolower($name)] ?? null;
    }

    public function coreOutdated(): bool
    {
        foreach ($this->modules as $module) {
            if ($module['core'] && $module['pending'] !== null) {
                return true;
            }
        }

        return false;
    }

    public function updatePending(): bool
    {
        foreach ($this->updateTargets() as $module) {
            if ($module['pending'] === 'update') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, Module> */
    public function updateTargets(): array
    {
        $targets = $this->enabled();

        do {
            $added = false;

            foreach ($this->modules as $id => $module) {
                if (!isset($targets[$id]) && $module['wanted'] && $module['reason'] === 'update'
                    && array_filter($module['requires'], fn (string $required): bool => !isset($targets[strtolower($required)])) === []) {
                    $targets[$id] = $module;
                    $added = true;
                }
            }
        } while ($added);

        return $targets;
    }

    /**
     * @param Module $module
     * @return array{string, array<string, string>}
     */
    public static function reason(array $module): array
    {
        return match ($module['reason']) {
            'locked' => ['Locked', []],
            'disabled' => ['Disabled by the admin', []],
            'not_enabled' => ['Not enabled yet', []],
            'requires' => ['Requires %modules%', ['%modules%' => $module['detail']]],
            'cycle' => ['Circular requirement', []],
            'invalid' => ['Invalid module.json', []],
            'update' => ['Update to finish', []],
            'older' => ['Older than the installed version %version%', ['%version%' => (string) $module['installed']]],
            default => ['', []],
        };
    }

    /** @return array<string, array{version?: string, active?: bool}> */
    public static function state(): array
    {
        $file = dirname(__DIR__, 2) . '/data/modules.json';

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            trigger_error('data/modules.json is not valid JSON: ignored', E_USER_WARNING);

            return [];
        }

        $state = [];

        foreach ($data as $name => $entry) {
            if (!is_string($name) || !preg_match(self::NAME, $name) || !is_array($entry)) {
                continue;
            }

            $clean = [];

            if (is_string($entry['version'] ?? null) && preg_match(self::VERSION, $entry['version'])) {
                $clean['version'] = $entry['version'];
            }

            if (is_bool($entry['active'] ?? null)) {
                $clean['active'] = $entry['active'];
            }

            if ($clean !== []) {
                $state[$name] = $clean;
            }
        }

        return $state;
    }

    /** @param array<string, array{version?: string, active?: bool}> $state */
    public static function saveState(array $state): void
    {
        ksort($state);
        $file = dirname(__DIR__, 2) . '/data/modules.json';
        $tmp = $file . '.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL) === false || !rename($tmp, $file)) {
            if (is_file($tmp)) {
                unlink($tmp);
            }

            throw new RuntimeException('Cannot write data/modules.json');
        }
    }

    private function compile(): void
    {
        $state = self::state();
        $modules = [];

        foreach (['Core', 'Extension'] as $group) {
            foreach (glob($this->root . '/modules/' . $group . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                $core = $group === 'Core';

                if (!preg_match(self::NAME, $name)) {
                    if ($core) {
                        throw new LogicException('Invalid module name: ' . $name);
                    }

                    trigger_error('Extension ignored, invalid name: ' . $name, E_USER_WARNING);
                    continue;
                }

                $id = strtolower($name);

                if (isset($modules[$id])) {
                    if ($core) {
                        throw new LogicException('Duplicate module: ' . $name);
                    }

                    trigger_error('Extension ignored, same name as a core module: ' . $name, E_USER_WARNING);
                    continue;
                }

                [$config, $error] = self::config($dir . '/module.json');

                if ($error !== null && $core) {
                    throw new LogicException($name . '/module.json: ' . $error);
                }

                $locked = $core && ($config['locked'] ?? false);
                $installed = $state[$name]['version'] ?? null;
                $version = $config['version'] ?? '';
                $compare = $installed === null || $version === '' ? 1 : version_compare($version, $installed);

                $modules[$id] = [
                    'name' => $name,
                    'id' => $id,
                    'dir' => $dir,
                    'namespace' => 'Yawasla\\Module\\' . $group . '\\' . $name . '\\',
                    'core' => $core,
                    'version' => $version,
                    'locked' => $locked,
                    'requires' => $config['requires'] ?? [],
                    'admin' => $config['admin'] ?? [],
                    'title' => $config['title'] ?? [],
                    'description' => $config['description'] ?? [],
                    'installed' => $installed,
                    'wanted' => $locked || ($state[$name]['active'] ?? $core),
                    'active' => false,
                    'pending' => $error !== null ? null : ($compare > 0 ? 'update' : ($compare < 0 ? 'older' : null)),
                    'reason' => $error !== null ? 'invalid' : '',
                    'detail' => $error ?? '',
                ];

                if ($error !== null) {
                    trigger_error('Extension ' . $name . ' disabled, invalid module.json: ' . $error, E_USER_WARNING);
                }
            }
        }

        $this->checkCore($modules);
        $cycles = $this->cycles($modules);

        foreach ($modules as $id => $module) {
            if ($module['reason'] === 'invalid') {
                continue;
            }

            if (isset($cycles[$id])) {
                $modules[$id]['reason'] = 'cycle';
                trigger_error('Extension ' . $module['name'] . ' disabled, circular requirement', E_USER_WARNING);
            } elseif (!$module['wanted']) {
                $modules[$id]['reason'] = $module['core'] ? 'disabled' : 'not_enabled';
            } elseif (!$module['core'] && $module['pending'] !== null) {
                $modules[$id]['reason'] = $module['pending'];
                trigger_error('Extension ' . $module['name'] . ' disabled until its ' . ($module['pending'] === 'update' ? 'update is finished' : 'files match the installed version ' . $module['installed']), E_USER_WARNING);
            } else {
                $modules[$id]['active'] = true;
                $modules[$id]['reason'] = $module['pending'] ?? ($module['locked'] ? 'locked' : '');
            }
        }

        do {
            $changed = false;

            foreach ($modules as $id => $module) {
                if (!$module['active']) {
                    continue;
                }

                $missing = array_values(array_filter($module['requires'], fn (string $required): bool => !($modules[strtolower($required)]['active'] ?? false)));

                if ($missing !== []) {
                    $modules[$id]['active'] = false;
                    $modules[$id]['reason'] = 'requires';
                    $modules[$id]['detail'] = implode(', ', $missing);
                    trigger_error('Module ' . $module['name'] . ' disabled, requires ' . implode(', ', $missing), E_USER_WARNING);
                    $changed = true;
                }
            }
        } while ($changed);

        if (array_filter($modules, fn (array $module): bool => $module['core'] && $module['pending'] !== null) !== []) {
            trigger_error('Core version differs from the installed one: visitors see the maintenance page', E_USER_WARNING);
        }

        $this->modules = $modules;
        $this->compiled = true;
        $this->write();
    }

    /** @param array<string, Module> $modules */
    private function checkCore(array $modules): void
    {
        foreach ($modules as $module) {
            if (!$module['core']) {
                continue;
            }

            foreach ($module['requires'] as $required) {
                $target = $modules[strtolower($required)] ?? null;

                if ($target !== null && !$target['core']) {
                    throw new LogicException($module['name'] . ' is a core module and cannot require the extension ' . $required);
                }

                if ($module['locked'] && ($target === null || !$target['locked'])) {
                    throw new LogicException($module['name'] . ' is locked and can only require locked modules: ' . $required);
                }
            }
        }
    }

    /**
     * @param array<string, Module> $modules
     * @return array<string, true>
     */
    private function cycles(array $modules): array
    {
        $cycles = [];

        foreach ($modules as $id => $module) {
            $stack = array_map('strtolower', $module['requires']);
            $seen = [];

            while ($stack !== []) {
                $current = array_pop($stack);

                if ($current === $id) {
                    if ($module['core']) {
                        throw new LogicException('Circular requirement: ' . $module['name']);
                    }

                    $cycles[$id] = true;
                    break;
                }

                if (isset($seen[$current]) || !isset($modules[$current])) {
                    continue;
                }

                $seen[$current] = true;
                array_push($stack, ...array_map('strtolower', $modules[$current]['requires']));
            }
        }

        return $cycles;
    }

    /** @return array{array<string, mixed>, ?string} */
    private static function config(string $file): array
    {
        if (!is_file($file)) {
            return [[], 'missing'];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [[], 'invalid JSON (' . $e->getMessage() . ')'];
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            return [[], 'an object is expected'];
        }

        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                return [[], 'unknown key "' . $key . '"'];
            }
        }

        if (!is_string($data['version'] ?? null) || !preg_match(self::VERSION, $data['version'])) {
            return [[], '"version" must be X.Y.Z'];
        }

        if (array_key_exists('requires', $data) && (!is_array($data['requires']) || !array_is_list($data['requires']) || array_filter($data['requires'], fn (mixed $name): bool => !is_string($name) || !preg_match(self::NAME, $name)) !== [])) {
            return [[], '"requires" must be a list of module names'];
        }

        if (array_key_exists('locked', $data) && !is_bool($data['locked'])) {
            return [[], '"locked" must be a boolean'];
        }

        if (array_key_exists('admin', $data)) {
            if (!is_array($data['admin']) || !array_is_list($data['admin'])) {
                return [[], '"admin" must be a list'];
            }

            foreach ($data['admin'] as $entry) {
                if (!is_array($entry) || count($entry) !== 3 || array_diff(['route', 'label', 'icon'], array_keys($entry)) !== []
                    || !is_string($entry['route']) || !preg_match('/^[a-z][a-z0-9_]*$/', $entry['route'])
                    || !is_string($entry['label']) || $entry['label'] === ''
                    || !is_string($entry['icon']) || !preg_match(self::ICON, $entry['icon'])) {
                    return [[], '"admin" entries need a route, a label and an icon'];
                }
            }
        }

        foreach (['title' => 60, 'description' => 255] as $key => $max) {
            if (array_key_exists($key, $data) && !self::texts($data[$key], $max)) {
                return [[], '"' . $key . '" must be an object of texts by language (en required, ' . $max . ' characters at most)'];
            }
        }

        return [$data, null];
    }

    private static function texts(mixed $texts, int $max): bool
    {
        return is_array($texts) && !array_is_list($texts) && isset($texts['en'])
            && array_diff(array_keys($texts), Translator::LOCALES) === []
            && array_filter($texts, fn (mixed $text): bool => !is_string($text) || $text === '' || mb_strlen($text) > $max) === [];
    }

    private function stamp(): string
    {
        $file = $this->root . '/data/modules.json';

        return is_file($file) ? filemtime($file) . '-' . filesize($file) : 'none';
    }

    private function write(): void
    {
        $file = $this->root . '/data/cache/modules.php';
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        clearstatcache();
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export(['stamp' => $this->stamp(), 'modules' => $this->modules], true) . ';' . PHP_EOL);
        rename($tmp, $file);
    }
}
