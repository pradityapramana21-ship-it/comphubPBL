<?php

declare(strict_types=1);

namespace App\Support;

final class FileCache
{
    public function __construct(private readonly string $directory)
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (! is_file($path)) {
            return $default;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return $default;
        }

        $data = json_decode($contents, true);

        if (! is_array($data) || (int) ($data['expires'] ?? 0) <= time()) {
            return $default;
        }

        return $data['value'] ?? $default;
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        $block = json_encode(['expires' => time() + $seconds, 'value' => $value], JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path($key), $block, LOCK_EX);
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $seconds);

        return $value;
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.json';
    }
}
