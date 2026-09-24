<?php

namespace GFrame\Config;

final class ConfigRepository
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** @param array<string, mixed> $items */
    public static function replace(array $items): void
    {
        self::$items = $items;
    }

    /** @param array<string, mixed> $items */
    public static function merge(array $items): void
    {
        self::$items = self::mergeRecursive(self::$items, $items);
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return self::$items;
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $overrides */
    private static function mergeRecursive(array $base, array $overrides): array
    {
        if (array_is_list($base) || array_is_list($overrides)) {
            return $overrides;
        }

        foreach ($overrides as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
