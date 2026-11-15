<?php

declare(strict_types=1);

namespace Yawasla\Core;

final class Maintenance
{
    public static function enabled(): bool
    {
        return is_file(self::file());
    }

    public static function active(): bool
    {
        return self::enabled() || Modules::getInstance()->coreOutdated();
    }

    public static function enable(): void
    {
        file_put_contents(self::file(), date('Y-m-d H:i:s') . PHP_EOL);
    }

    public static function disable(): void
    {
        if (is_file(self::file())) {
            unlink(self::file());
        }
    }

    private static function file(): string
    {
        return dirname(__DIR__, 2) . '/data/maintenance';
    }
}
