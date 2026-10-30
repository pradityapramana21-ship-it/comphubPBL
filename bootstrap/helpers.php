<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;

if (! function_exists('app')) {
    function app(): App
    {
        $app = $GLOBALS['app'] ?? null;

        if (! $app instanceof App) {
            throw new RuntimeException('Application has not been booted.');
        }

        return $app;
    }
}

if (! function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = rtrim((string) Config::get('app.url', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (! function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/' . ltrim($path, '/'));
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app()->csrf()->token();
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (! function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = app()->session()->getFlash('_old', []);

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (! function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return app()->auth()->user();
    }
}

if (! function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return app()->session()->getFlash($key, $default);
    }
}

if (! function_exists('errors')) {
    /** @return array<string, list<string>> */
    function errors(): array
    {
        $errors = app()->session()->getFlash('_errors', []);

        return is_array($errors) ? $errors : [];
    }
}

if (! function_exists('route')) {
    /** @param array<string, string|int> $params */
    function route(string $name, array $params = []): string
    {
        return url(app()->router()->url($name, $params));
    }
}

if (! function_exists('raw')) {
    function raw(string $html): string
    {
        return $html;
    }
}
