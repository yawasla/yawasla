<?php

declare(strict_types=1);

namespace Yawasla\Core;

use DateTimeImmutable;
use RuntimeException;

final class Trash
{
    public const DAYS = 7;

    private const ENTRY = '/^([A-Z][A-Za-z0-9]*)-(\d{8}-\d{6})(?:-[0-9a-f]{4})?$/';

    public static function put(string $dir, string $name): void
    {
        $trash = self::dir();

        if (!is_dir($trash) && !mkdir($trash, 0700, true)) {
            throw new RuntimeException('Cannot create data/trash');
        }

        $target = $trash . '/' . $name . '-' . date('Ymd-His');

        if (file_exists($target)) {
            $target .= '-' . bin2hex(random_bytes(2));
        }

        if (!rename($dir, $target)) {
            throw new RuntimeException('Cannot move ' . $name . ' to data/trash');
        }
    }

    /** @return list<array{entry: string, name: string, date: DateTimeImmutable, path: string}> */
    public static function list(): array
    {
        $list = [];

        foreach (self::entries() as $path) {
            $date = preg_match(self::ENTRY, basename($path), $m) ? DateTimeImmutable::createFromFormat('!Ymd-His', $m[2]) : false;

            if ($date !== false && !is_link($path) && is_dir($path)) {
                $list[] = ['entry' => basename($path), 'name' => $m[1], 'date' => $date, 'path' => $path];
            }
        }

        usort($list, fn (array $a, array $b): int => [$a['name'], $b['date']] <=> [$b['name'], $a['date']]);

        return $list;
    }

    public static function purge(): void
    {
        $limit = new DateTimeImmutable('-' . self::DAYS . ' days');

        foreach (self::list() as $item) {
            if ($item['date'] < $limit) {
                self::remove($item['path']);
            }
        }
    }

    public static function clear(): void
    {
        foreach (self::entries() as $path) {
            self::remove($path);
        }
    }

    /** @return list<string> */
    private static function entries(): array
    {
        $trash = self::dir();

        if (!is_dir($trash)) {
            return [];
        }

        return array_map(fn (string $entry): string => $trash . '/' . $entry, array_values(array_diff(scandir($trash) ?: [], ['.', '..'])));
    }

    private static function remove(string $path): void
    {
        $root = realpath(self::dir());

        if (is_link($path)) {
            unlink($path);

            return;
        }

        $real = realpath($path);

        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            trigger_error('Not deleted, outside data/trash: ' . basename($path), E_USER_WARNING);

            return;
        }

        if (!self::delete($real)) {
            trigger_error('data/trash/' . basename($path) . ' could not be fully deleted', E_USER_WARNING);
        }
    }

    private static function delete(string $path): bool
    {
        if (is_link($path) || !is_dir($path)) {
            return unlink($path);
        }

        $deleted = true;

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $deleted = self::delete($path . '/' . $entry) && $deleted;
        }

        return rmdir($path) && $deleted;
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 2) . '/data/trash';
    }
}
