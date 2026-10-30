<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        $directory = rtrim($directory, '/');

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;

            if (! is_array($data)) {
                throw new \RuntimeException('Config file must return an array: ' . $file);
            }

            self::$items[$key] = $data;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor = &self::$items;

        foreach ($segments as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }
}
