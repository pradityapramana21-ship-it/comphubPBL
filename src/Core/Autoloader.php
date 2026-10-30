<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $srcDirectory): void
    {
        $srcDirectory = rtrim($srcDirectory, '/');

        spl_autoload_register(static function (string $class) use ($srcDirectory): void {
            $prefix = 'App\\';

            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $srcDirectory . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
