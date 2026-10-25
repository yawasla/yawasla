<?php

declare(strict_types=1);

namespace Yawasla\Debug;

final class Dumper
{
    private const MAX_DEPTH = 8;

    private const MAX_ITEMS = 200;

    private const MAX_STRING = 2000;

    /**
     * @param array<int, true> $seen
     * @return array{type: string, value?: string, children?: array<string, array<string, mixed>>}
     */
    public static function normalize(mixed $value, int $depth = 0, array $seen = []): array
    {
        if (is_array($value)) {
            return self::node('array:' . count($value), $value, $depth, $seen);
        }

        if ($value instanceof \UnitEnum) {
            return ['type' => 'enum', 'value' => $value::class . '::' . $value->name];
        }

        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($seen[$id])) {
                return ['type' => $value::class, 'value' => '*récursion*'];
            }
            $seen[$id] = true;

            $properties = [];
            foreach ((array) $value as $key => $property) {
                $key = (string) $key;
                $properties[str_contains($key, "\0") ? substr($key, strrpos($key, "\0") + 1) : $key] = $property;
            }

            return self::node($value::class, $properties, $depth, $seen);
        }

        if (is_string($value)) {
            $short = mb_strlen($value) > self::MAX_STRING ? mb_substr($value, 0, self::MAX_STRING) . '…' : $value;

            return ['type' => 'string:' . mb_strlen($value), 'value' => '"' . $short . '"'];
        }

        return ['type' => get_debug_type($value), 'value' => var_export($value, true)];
    }

    /**
     * @param array<int|string, mixed> $items
     * @param array<int, true> $seen
     * @return array{type: string, value?: string, children?: array<string, array<string, mixed>>}
     */
    private static function node(string $type, array $items, int $depth, array $seen): array
    {
        if ($items !== [] && $depth >= self::MAX_DEPTH) {
            return ['type' => $type, 'value' => '…'];
        }

        $children = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS, true) as $key => $item) {
            $children[(string) $key] = self::normalize($item, $depth + 1, $seen);
        }

        if (count($items) > self::MAX_ITEMS) {
            $children['…'] = ['type' => '', 'value' => (count($items) - self::MAX_ITEMS) . ' de plus'];
        }

        return ['type' => $type, 'children' => $children];
    }
}
